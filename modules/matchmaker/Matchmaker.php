<?php
require_once __DIR__ . '/Matchmaker_model.php';
require_once __DIR__ . '/../job_posts/Job_post_rules.php';
require_once __DIR__ . '/../cv_match/Cv_matcher.php';

/**
 * Matchmaker, the company's list of a post's applicants (company staff
 * only): ordered by Laya and the legacy score (Application_scoring::order),
 * in tabs (Top 10, All, Shortlist, Bookmarked, Rejected), each card with the
 * score, the requirements met and missed, the cover letter and the CV.
 * Staff shortlist, bookmark and reject from the cards; each is logged in
 * job_application_actions. Applications new since this member last looked
 * are marked.
 *
 * Rejecting only sets the status: no email goes to the candidate yet.
 */
class Matchmaker extends Trongate {

    public const TABS = ['top' => 'Top 10', 'all' => 'All', 'shortlist' => 'Shortlist', 'bookmarked' => 'Bookmarked', 'rejected' => 'Rejected'];

    private const PER_PAGE = 25;

    /** Nothing here: the lists are reached from the dashboard. */
    public function index(): void {
        redirect('company');
    }

    /**
     * matchmaker/post/{id}: the post's applicants. ?tab= one of TABS,
     * ?tag= top|good|medium|poor, ?required=1 for only those who meet every
     * requirement. Shows PER_PAGE cards; scrolling to the end loads the
     * next ones from more() (matchmaker.js), and without JS "Show more"
     * opens this page from ?after= (a Matchmaker_model cursor). Filtering,
     * counting and paging happen in SQL, so a post with thousands of
     * applicants loads one batch of CVs at a time.
     *
     * @return void
     */
    public function post(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $data = $this->cards($post, $member, true);
        $data['member'] = $member;
        $data['counts'] = $this->model->counts((int) $post['id'], (int) $post['version'], $data['since']);
        $data['filtered_out'] = $data['counts'][$data['tab']] - $data['total'];
        $data['new'] = $data['counts']['new'];
        $data['unscored'] = $data['counts']['unscored'];
        $this->view('post', $data);
    }

    /**
     * matchmaker/more/{id}?after=...: the next cards of post()'s list as an
     * HTML fragment (the same query string as the list, plus the cursor).
     *
     * @return void
     */
    public function more(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $this->view('cards', $this->cards($post, $member, false));
    }

    /**
     * The view data for a batch of cards, from the query string: the tab,
     * filters and cursor, and `since` (when the member last looked before
     * this visit, so later batches mark the same cards NEW). Without
     * ?since= this is a fresh visit, and it is recorded.
     */
    private function cards(array $post, array $member, bool $count): array {
        $version = (int) $post['version'];
        $groups = Cv_matcher::criteria(Job_post_rules::to_job($post['rows']));
        $tab = isset(self::TABS[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'top';
        $tag = in_array($_GET['tag'] ?? '', ['top', 'good', 'medium', 'poor'], true) ? $_GET['tag'] : '';
        $required = ($_GET['required'] ?? '') === '1';
        $after = Matchmaker_model::place((string) ($_GET['after'] ?? '')) !== null ? (string) $_GET['after'] : '';
        $since = isset($_GET['since']) && ctype_digit((string) $_GET['since'])
            ? (int) $_GET['since']
            : $this->model->seen((int) $post['id'], (int) $member['id']);

        $found = $this->model->page(
            (int) $post['id'], $tab, $tag,
            $required ? array_column($groups['requirements'], 'id') : null,
            $version, self::PER_PAGE, $after, $count
        );
        $applications = $found['rows'];
        foreach ($applications as &$a) {
            $a['is_new'] = $a['status'] === 'in_review' && (int) $a['submitted_at'] > $since;
            $a['current'] = $a['score_id'] !== null && (int) $a['score_version'] === $version;
        }
        unset($a);

        $query = array_filter(['tab' => $tab, 'tag' => $tag, 'required' => $required ? '1' : '']);
        $next = $found['next'] === null ? null : http_build_query($query + ['after' => $found['next'], 'since' => $since]);
        return [
            'post' => $post,
            'groups' => $groups,
            'applications' => $applications,
            'total' => $found['total'],
            'tab' => $tab,
            'tag' => $tag,
            'required' => $required,
            'after' => $after,
            'since' => $since,
            'query' => http_build_query($query),
            'next' => $next,
        ];
    }

    /**
     * POST matchmaker/submit_action/{post id}/{application id}, action = a
     * toggle (Matchmaker_model::TOGGLES) or rescore. Back to the list.
     *
     * @return void
     */
    public function submit_action(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $application_id = (int) segment(4);
        $after = (string) post('after', true);
        $since = (string) post('since', true);
        $back = 'matchmaker/post/' . (int) $post['id'] . '?' . http_build_query(array_filter([
            'tab' => post('tab', true),
            'tag' => post('tag', true),
            'required' => post('required', true),
            'after' => Matchmaker_model::place($after) !== null ? $after : '',
            'since' => $after !== '' && ctype_digit($since) ? $since : '',
        ])) . '#a' . $application_id;
        $action = (string) post('action', true);

        $application = $this->model->application((int) $post['id'], $application_id);
        if ($this->validation->run() !== true || $application === null) {
            redirect($back);
            return;
        }
        $this->module('applications');
        if ($action === 'rescore') {
            try {
                $this->applications->score($application_id);
                $this->applications->log_action($application_id, 'rescore', (int) $member['id'], $application['status'], 'matchmaker');
                set_flashdata('Re-scored on the post as it is now.');
            } catch (Throwable $e) {
                set_flashdata("Couldn't re-score: " . $e->getMessage());
            }
        } elseif (in_array($action, Matchmaker_model::TOGGLES, true)) {
            if ($this->model->toggle((int) $post['id'], $application_id, $action)) {
                $this->applications->log_action($application_id, $action, (int) $member['id'], $application['status'], 'matchmaker');
                if ($action === 'reject' || $action === 'unreject') {
                    $this->applications->rerank((int) $post['id']);
                }
            }
        }
        redirect($back);
    }

    /**
     * matchmaker/csv/{id}: the post's applicants in list order as a CSV file.
     *
     * @return void
     */
    public function csv(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $applications = $this->model->all((int) $post['id']);
        $filename = preg_replace('/[^A-Za-z0-9æøåÆØÅ_-]+/u', '-', $post['title']) . '-applicants.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . trim($filename, '-') . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel reads it as UTF-8
        fputcsv($out, ['Rank', 'Name', 'Email', 'Phone', 'Current title', 'Score', 'Tag', 'Laya: meets all (%)', 'Laya: fit', 'Status', 'Shortlisted', 'Bookmarked', 'Applied'], ',', '"', '');
        foreach ($applications as $a) {
            fputcsv($out, [
                $a['final_rank'],
                $a['name'],
                $a['email'],
                $a['phone'],
                $a['current_title'],
                $a['combined_score'] === null ? '' : (int) round($a['combined_score'] * 100),
                $a['tag'],
                $a['laya_meets'] === null ? '' : (int) round($a['laya_meets'] * 100),
                $a['laya_choice'],
                $a['status'],
                $a['shortlisted_at'] ? 'yes' : '',
                $a['bookmarked_at'] ? 'yes' : '',
                date('Y-m-d', (int) $a['submitted_at']),
            ], ',', '"', '');
        }
        fclose($out);
    }

    // -----------------------------------------------------------------

    /** The signed-in member (see Company::staff()). */
    private function staff(): array {
        $this->module('company');
        return $this->company->staff();
    }

    /** The member's company's published post, with its rows, or a 404 page. */
    private function post_or_404(array $member, int $id): array {
        $this->module('job_posts');
        $post = $id > 0 ? $this->job_posts->company_post((int) $member['company_id'], $id) : null;
        if ($post === null || $post['status'] === 'draft') {
            http_response_code(404);
            $this->view('not_found', ['view_module' => 'job_posts']);
            die();
        }
        return $post;
    }

}

<?php
require_once __DIR__ . '/Smartmatch_model.php';
require_once __DIR__ . '/../job_posts/Job_post_rules.php';
require_once __DIR__ . '/../cv_match/Cv_matcher.php';

/**
 * SmartMatch, the company's list of a post's applicants (company staff
 * only): ordered by Laya and the legacy score (Application_scoring::order),
 * in tabs (Top 10, All, Shortlist, Bookmarked, Rejected), each card with the
 * score, the requirements met and missed, the cover letter and the CV.
 * Staff shortlist, bookmark and reject from the cards; each is logged in
 * job_application_actions. Applications new since this member last looked
 * are marked.
 *
 * Rejecting only sets the status: no email goes to the candidate yet.
 */
class Smartmatch extends Trongate {

    public const TABS = ['top' => 'Top 10', 'all' => 'All', 'shortlist' => 'Shortlist', 'bookmarked' => 'Bookmarked', 'rejected' => 'Rejected'];

    private const TOP = 10;

    /** Nothing here: the lists are reached from the dashboard. */
    public function index(): void {
        redirect('company');
    }

    /**
     * smartmatch/post/{id}: the post's applicants. ?tab= one of TABS,
     * ?tag= top|good|medium|poor, ?required=1 for only those who meet every
     * requirement.
     *
     * @return void
     */
    public function post(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $last_seen = $this->model->seen((int) $post['id'], (int) $member['id']);
        $applications = $this->model->applications((int) $post['id']);
        $groups = Cv_matcher::criteria(Job_post_rules::to_job($post['rows']));

        $tab = isset(self::TABS[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'top';
        $tag = in_array($_GET['tag'] ?? '', ['top', 'good', 'medium', 'poor'], true) ? $_GET['tag'] : '';
        $required = ($_GET['required'] ?? '') === '1';

        foreach ($applications as &$a) {
            $a['is_new'] = $a['status'] === 'in_review' && (int) $a['submitted_at'] > $last_seen;
            $a['current'] = $a['score_id'] !== null && (int) $a['score_version'] === (int) $post['version'];
            $a['meets_required'] = $a['current'] && self::meets_required($groups, $a['details']);
        }
        unset($a);

        $in_tab = [];
        $counts = array_fill_keys(array_keys(self::TABS), 0);
        foreach ($applications as $a) {
            foreach (array_keys(self::TABS) as $key) {
                if (self::in_tab($key, $a)) {
                    $counts[$key]++;
                    if ($key === $tab) {
                        $in_tab[] = $a;
                    }
                }
            }
        }
        $shown = array_values(array_filter($in_tab, fn(array $a) =>
            ($tag === '' || $a['tag'] === $tag) && (!$required || $a['meets_required'])));

        $this->view('post', [
            'member' => $member,
            'post' => $post,
            'groups' => $groups,
            'applications' => $shown,
            'filtered_out' => count($in_tab) - count($shown),
            'counts' => $counts,
            'tab' => $tab,
            'tag' => $tag,
            'required' => $required,
            'new' => count(array_filter($applications, fn(array $a) => $a['is_new'])),
            'unscored' => count(array_filter($applications, fn(array $a) => $a['status'] === 'in_review' && !$a['current'])),
            'query' => http_build_query(array_filter(['tab' => $tab, 'tag' => $tag, 'required' => $required ? '1' : ''])),
        ]);
    }

    /**
     * POST smartmatch/submit_action/{post id}/{application id}, action = a
     * toggle (Smartmatch_model::TOGGLES) or rescore. Back to the list.
     *
     * @return void
     */
    public function submit_action(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $application_id = (int) segment(4);
        $back = 'smartmatch/post/' . (int) $post['id'] . '?' . http_build_query(['tab' => post('tab', true)]) . '#a' . $application_id;
        $action = (string) post('action', true);

        if ($this->validation->run() !== true || $this->model->application((int) $post['id'], $application_id) === null) {
            redirect($back);
            return;
        }
        $this->module('applications');
        if ($action === 'rescore') {
            try {
                $this->applications->score($application_id);
                $this->applications->log_action($application_id, 'rescore', (int) $member['id']);
                set_flashdata('Re-scored on the post as it is now.');
            } catch (Throwable $e) {
                set_flashdata("Couldn't re-score: " . $e->getMessage());
            }
        } elseif (in_array($action, Smartmatch_model::TOGGLES, true)) {
            if ($this->model->toggle((int) $post['id'], $application_id, $action)) {
                $this->applications->log_action($application_id, $action, (int) $member['id']);
                if ($action === 'reject' || $action === 'unreject') {
                    $this->applications->rerank((int) $post['id']);
                }
            }
        }
        redirect($back);
    }

    /**
     * smartmatch/csv/{id}: the post's applicants in list order as a CSV file.
     *
     * @return void
     */
    public function csv(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $applications = $this->model->applications((int) $post['id']);
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

    /** Whether an application belongs in a tab. */
    public static function in_tab(string $tab, array $a): bool {
        $open = $a['status'] === 'in_review';
        return match ($tab) {
            'top' => $open && $a['final_rank'] !== null && (int) $a['final_rank'] <= self::TOP,
            'all' => $open,
            'shortlist' => $open && $a['shortlisted_at'] !== null,
            'bookmarked' => $a['status'] !== 'rejected' && $a['bookmarked_at'] !== null,
            'rejected' => $a['status'] === 'rejected',
        };
    }

    /** Whether every required criterion was met (details by criterion id). */
    public static function meets_required(array $groups, array $details): bool {
        foreach ($groups['requirements'] as $item) {
            if (!(int) ($details[$item['id']]['passed'] ?? 0)) {
                return false;
            }
        }
        return true;
    }

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

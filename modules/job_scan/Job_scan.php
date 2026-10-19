<?php
require_once __DIR__ . '/Job_scan_model.php';
require_once __DIR__ . '/Job_search_rules.php';

/**
 * Job scan: a signed-in user's saved job searches (what to look for, where,
 * and which CV to match with) and the job posts their scans found, each
 * with what auto-match made of it: a saved CV match (cv_match/{id}) with its
 * score and, from the search's tailor_from_score up, a tailored résumé.
 *
 * search/{id} lists a search's posts newest first, PER_PAGE at a time;
 * scrolling to the end loads the next ones from more() (js/job_scan.js),
 * and without JS "Show more" opens the page from ?after= (a Job_scan_model
 * cursor).
 */
class Job_scan extends Trongate {

    private const PER_PAGE = 25;

    /**
     * The user's searches.
     *
     * @return void
     */
    public function index(): void {
        $user_id = $this->user_id();
        $this->view('index', [
            'searches' => $this->model->searches($user_id),
            'scan_every' => Job_search_rules::SCAN_EVERY,
        ]);
    }

    /**
     * job_scan/create: the form for a new search, with the CV of the user's
     * latest saved match filled in.
     *
     * @return void
     */
    public function create(): void {
        $user_id = $this->user_id();
        $cv = $this->model->latest_cv($user_id);
        $this->form(0, [
            'name' => '',
            'keywords' => '',
            'location' => '',
            'remote_ok' => 1,
            'cv_name' => $cv['cv_name'] ?? '',
            'cv_text' => $cv['cv_text'] ?? '',
            'tailor_from_score' => 60,
            'scan_every_hours' => 24,
            'active' => 1,
            'sites' => [],
        ]);
    }

    /**
     * job_scan/edit/{id}: the form for one of the user's searches.
     *
     * @return void
     */
    public function edit(): void {
        $user_id = $this->user_id();
        $this->form((int) segment(3), $this->search_or_404((int) segment(3), $user_id));
    }

    /**
     * POST job_scan/submit/{id, 0 for a new search}: saves the form and opens
     * the search.
     *
     * @return void
     */
    public function submit(): void {
        $user_id = $this->user_id();
        $id = (int) segment(3);
        if ($id !== 0) {
            $this->search_or_404($id, $user_id);
        }
        $this->validation->set_rules('name', 'name', 'required|max_length[120]');
        $this->validation->set_rules('keywords', 'what to look for', 'required|max_length[500]');
        $this->validation->set_rules('location', 'where', 'max_length[255]');
        $this->validation->set_rules('cv_name', 'CV name', 'max_length[255]');
        $this->validation->set_rules('cv_text', 'CV', 'required|min_length[200]|max_length[60000]');
        $this->validation->set_rules('tailor_from_score', 'score to tailor from', 'required|integer|greater_than[-1]|less_than[101]');
        $this->validation->set_rules('scan_every_hours', 'how often', 'required|callback_known_interval');
        $this->validation->set_rules('sites', 'sites', 'max_length[2000]|callback_site_list');
        if ($this->validation->run() !== true) {
            $this->form($id, $this->posted());
            return;
        }
        $posted = $this->posted();
        $sites = $posted['sites'];
        unset($posted['sites']);
        if ((int) $posted['active'] === 1) {
            $posted['paused_reason'] = '';
        }
        $id = $this->model->save($user_id, $id, $posted, $sites);
        set_flashdata('Your job search is saved.');
        redirect('job_scan/search/' . $id);
    }

    /**
     * POST job_scan/submit_delete/{id}: deletes the search and the posts it
     * found; the saved matches stay in the CV match list.
     *
     * @return void
     */
    public function submit_delete(): void {
        $user_id = $this->user_id();
        $id = (int) segment(3);
        $this->search_or_404($id, $user_id);
        if ($this->validation->run() === true) {
            $this->model->delete_search($id, $user_id);
            set_flashdata('The job search is deleted. Its saved matches are still in your CV match list.');
        }
        redirect('job_scan');
    }

    /**
     * job_scan/search/{id}: the search and the posts it found. ?dismissed=1
     * shows dismissed posts too.
     *
     * @return void
     */
    public function search(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        $data = $this->posts($search);
        $data['scan_every'] = Job_search_rules::SCAN_EVERY;
        $data['statuses'] = Job_scan_model::STATUSES;
        $this->view('search', $data);
    }

    /**
     * job_scan/more/{id}?after=...: the next posts of search()'s list as an
     * HTML fragment.
     *
     * @return void
     */
    public function more(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        $data = $this->posts($search);
        $data['statuses'] = Job_scan_model::STATUSES;
        $this->view('posts', $data);
    }

    /**
     * POST job_scan/submit_dismiss/{post id}, undo=1 to bring it back.
     *
     * @return void
     */
    public function submit_dismiss(): void {
        $user_id = $this->user_id();
        if ($this->validation->run() !== true) {
            redirect('job_scan');
        }
        $search_id = $this->model->dismiss((int) segment(3), $user_id, post('undo') === '1');
        redirect($search_id === null ? 'job_scan' : 'job_scan/search/' . $search_id . (post('undo') === '1' ? '?dismissed=1' : ''));
    }

    /** Validation callback: one of the form's intervals. */
    public function known_interval(string $hours): string|bool {
        block_url('job_scan/known_interval');
        return isset(Job_search_rules::SCAN_EVERY[(int) $hours]) ? true : 'Pick how often to scan.';
    }

    /** Validation callback: the sites box holds only domains, not too many. */
    public function site_list(string $text): string|bool {
        block_url('job_scan/site_list');
        [$sites, $bad] = Job_search_rules::sites($text);
        if ($bad) {
            return 'These aren\'t sites: ' . implode(', ', array_slice($bad, 0, 5)) . '. Write one site per line, like jobnet.dk.';
        }
        return count($sites) <= Job_search_rules::MAX_SITES ? true : 'Pick at most ' . Job_search_rules::MAX_SITES . ' sites.';
    }

    /** Shows the form with $search's values. */
    private function form(int $id, array $search): void {
        $this->view('form', [
            'id' => $id,
            'search' => $search,
            'scan_every' => Job_search_rules::SCAN_EVERY,
        ]);
    }

    /** The posted form as job_searches columns, plus `sites`: the domains. */
    private function posted(): array {
        return [
            'name' => trim(post('name', true)),
            'keywords' => trim(post('keywords', true)),
            'location' => trim(post('location', true)),
            'remote_ok' => post('remote_ok') === '1' ? 1 : 0,
            'cv_name' => trim(post('cv_name', true)),
            'cv_text' => trim(post('cv_text')),
            'tailor_from_score' => max(0, min(100, (int) post('tailor_from_score'))),
            'scan_every_hours' => (int) post('scan_every_hours'),
            'active' => post('active') === '1' ? 1 : 0,
            'sites' => Job_search_rules::sites(post('sites'))[0],
        ];
    }

    /** The view data for a batch of $search's posts, from the query string. */
    private function posts(array $search): array {
        $dismissed = ($_GET['dismissed'] ?? '') === '1';
        $after = Job_scan_model::place((string) ($_GET['after'] ?? '')) !== null ? (string) $_GET['after'] : '';
        $found = $this->model->posts((int) $search['id'], $dismissed, self::PER_PAGE, $after);
        $query = array_filter(['dismissed' => $dismissed ? '1' : '']);
        return [
            'search' => $search,
            'posts' => $found['rows'],
            'dismissed' => $dismissed,
            'after' => $after,
            'next' => $found['next'] === null ? null : http_build_query($query + ['after' => $found['next']]),
        ];
    }

    /** One of the user's searches, or a 404 page. */
    private function search_or_404(int $id, int $user_id): array {
        $search = $id > 0 ? $this->model->find($id, $user_id) : null;
        if ($search === null) {
            http_response_code(404);
            $this->view('not_found');
            die();
        }
        return $search;
    }

    /** The signed-in user's id; anyone else is sent to sign in. */
    private function user_id(): int {
        $id = $this->trongate_tokens->attempt_get_valid_token() !== false ? $this->trongate_tokens->get_user_id() : false;
        if (!$id) {
            redirect('sign-in');
            die();
        }
        return (int) $id;
    }

}

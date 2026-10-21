<?php
require_once __DIR__ . '/Job_scan_model.php';
require_once __DIR__ . '/Job_search_rules.php';
require_once __DIR__ . '/../cv_match/Page_reader.php';
require_once __DIR__ . '/../cv_match/Match_prompts.php';
require_once __DIR__ . '/Found_post_card.php';
require_once __DIR__ . '/Job_search_summary.php';
require_once __DIR__ . '/Job_search_card.php';
require_once __DIR__ . '/../llm/Llm.php';
require_once __DIR__ . '/../queue/Queue.php';

/**
 * Job scan: a signed-in user's saved job searches (what to look for, where,
 * and which CV to match with) and the job posts their scans found, each
 * with what auto-match made of it: a saved CV match (cv_match/{id}) with its
 * score and, from the search's tailor_from_score up, a tailored résumé.
 *
 * Each post found is matched on the queue (_auto_match): the page is
 * fetched, read and matched with the search's CV like a post on /cv_match,
 * saved as a CV match, and from the search's tailor_from_score up its
 * tailored résumé is written right away. All on the search owner's own AI
 * key, as far as its switches allow (Account_model::USES): with it off for
 * evaluating job posts the post is read and matched for free (Free_reader
 * and the taxonomy, like quick_match), and with it off for résumés no
 * résumé is tailored. Administrators without a key use the server's key. A
 * post can also be added by its link (submit_add), or sent by another tool
 * (a ChatGPT Action) to job_scan/api with the search's token.
 *
 * Posts are found by scans (_scan): one call to the model with its web
 * search tool, asking for new job posts that fit the search. A scan is due
 * every scan_every_hours; _scan_due runs every SWEEP_SECONDS on the queue,
 * queues the due ones and queues itself again (schedule() starts it).
 * "Scan now" runs one at once (submit_scan).
 *
 * search/{id} lists a search's posts newest first, PER_PAGE at a time;
 * scrolling to the end loads the next ones from more() (js/job_scan.js),
 * and without JS "Show more" opens the page from ?after= (a Job_scan_model
 * cursor). While the page is in view, Trongate MX polls latest() and the
 * page merges new posts and changed ones into the list, so posts sent to
 * api() show up without a reload.
 */
class Job_scan extends Trongate {

    private const PER_PAGE = 25;

    // Posts job_scan/api takes in one request. There's no hourly limit:
    // unusual volume shows on the job searches admin page instead.
    private const POSTS_PER_REQUEST = 50;

    // How often the queue looks for searches due a scan.
    private const SWEEP_SECONDS = 900;

    // Posts one scan asks the model for, at most.
    private const POSTS_PER_SCAN = 15;

    // "Scan now" waits this long after a scan, so a click can't run up the bill.
    private const SCAN_NOW_AFTER = 3600;

    /**
     * The user's searches.
     *
     * @return void
     */
    public function index(): void {
        $user_id = $this->user_id();
        $this->schedule();
        $this->view('index', [
            'searches' => array_map([Job_search_card::class, 'from'], $this->model->searches($user_id)),
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
        $this->schedule();
        set_flashdata('Your job search is saved.' . ((int) $posted['active'] === 1 ? ' It is scanned within ' . (int) (self::SWEEP_SECONDS / 60) . ' minutes when it is due, or now with Scan now.' : ''));
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
        $data['summary'] = Job_search_summary::from(
            $search,
            $this->model->last_run((int) $search['id']),
            $this->model->tokens_last_30_days((int) $search['id'])
        );
        $data['can_scan_now'] = (int) ($search['last_scanned_at'] ?? 0) <= time() - self::SCAN_NOW_AFTER;
        // A token just made is shown this once.
        $new = $_SESSION['job_scan_new_token'] ?? null;
        unset($_SESSION['job_scan_new_token']);
        $data['new_token'] = ($new['search_id'] ?? 0) === (int) $search['id'] ? $new['token'] : null;
        $data['has_token'] = $search['api_token_hash'] !== null;
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
        $this->view('posts', $this->posts($search));
    }

    /**
     * job_scan/latest/{id}: the first batch of search()'s list (newest
     * posts), as an HTML fragment in a .posts-latest section. The search page
     * polls it and merges it into its list (js/job_scan.js).
     *
     * @return void
     */
    public function latest(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        $this->view('latest', $this->posts($search, true));
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

    /**
     * POST job_scan/submit_add/{search id}, url: adds a job post by its link
     * and queues its match.
     *
     * @return void
     */
    public function submit_add(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        $back = 'job_scan/search/' . (int) $search['id'];
        $this->validation->set_rules('url', 'link', 'required|max_length[2048]|valid_url');
        if ($this->validation->run() !== true) {
            set_flashdata('Paste the link of a job post, starting with https://.');
            redirect($back);
        }
        $id = $this->model->add_post((int) $search['id'], null, trim(post('url')));
        if ($id === null) {
            set_flashdata('This search has that post already.');
            redirect($back);
        }
        $this->queue_match($id);
        redirect($back);
    }

    /**
     * POST job_scan/submit_retry/{post id}: matches a failed post again.
     *
     * @return void
     */
    public function submit_retry(): void {
        $user_id = $this->user_id();
        $post = $this->validation->run() === true ? $this->model->user_post((int) segment(3), $user_id) : null;
        if ($post === null) {
            redirect('job_scan');
        }
        if (in_array($post['status'], ['failed', 'new', 'not_a_job'], true)) {
            $this->model->set_status((int) $post['id'], 'new');
            $this->queue_match((int) $post['id']);
        }
        redirect('job_scan/search/' . (int) $post['job_search_id']);
    }

    /**
     * POST job_scan/submit_token/{search id}: makes the search a new token
     * for job_scan/api, replacing any old one, and shows it once on the
     * search page. Only its hash is kept.
     *
     * @return void
     */
    public function submit_token(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        if ($this->validation->run() === true) {
            $token = 'js_' . bin2hex(random_bytes(24));
            $this->model->set_api_token_hash((int) $search['id'], hash('sha256', $token));
            $_SESSION['job_scan_new_token'] = ['search_id' => (int) $search['id'], 'token' => $token];
        }
        redirect('job_scan/search/' . (int) $search['id']);
    }

    /**
     * POST job_scan/submit_token_delete/{search id}: turns posting from
     * outside off; the old token stops working.
     *
     * @return void
     */
    public function submit_token_delete(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        if ($this->validation->run() === true) {
            $this->model->set_api_token_hash((int) $search['id'], null);
            set_flashdata('Posting from outside is off. The old token no longer works.');
        }
        redirect('job_scan/search/' . (int) $search['id']);
    }

    /**
     * POST job_scan/api with "Authorization: Bearer {search token}" and JSON
     * {posts: [{url, title?, company?, location?}]}: adds the posts to the
     * token's search and queues their match, like submit_add, at most
     * POSTS_PER_REQUEST at a time. For a ChatGPT Action or any other tool;
     * job_scan/openapi describes it. Answers JSON: added, and skipped with
     * why.
     *
     * @return void
     */
    public function api(): void {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->api_error(405, 'Send the posts with POST.');
        }
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $token = preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m) ? $m[1] : '';
        $search = $token !== '' ? $this->model->by_api_token_hash(hash('sha256', $token)) : null;
        if ($search === null) {
            $this->api_error(401, 'Unknown token. Make one on the job search page and send it as "Authorization: Bearer <token>".');
        }
        $body = json_decode((string) file_get_contents('php://input'), true);
        $posts = is_array($body) ? ($body['posts'] ?? null) : null;
        if (!is_array($posts) || !array_is_list($posts)) {
            $this->api_error(400, 'Send JSON like {"posts": [{"url": "https://...", "title": "...", "company": "...", "location": "..."}]}.');
        }
        $room = self::POSTS_PER_REQUEST;
        $added = [];
        $skipped = [];
        foreach ($posts as $post) {
            $url = is_array($post) && is_string($post['url'] ?? null) ? trim($post['url']) : '';
            if (!preg_match('~^https?://~i', $url) || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $skipped[] = ['url' => $url, 'reason' => 'Not an http(s) link.'];
                continue;
            }
            if ($room <= 0) {
                $skipped[] = ['url' => $url, 'reason' => 'Over ' . self::POSTS_PER_REQUEST . ' posts in one request. Send it in the next one.'];
                continue;
            }
            $text = fn(string $key, int $max) => mb_substr(trim(is_string($post[$key] ?? null) ? $post[$key] : ''), 0, $max, 'UTF-8');
            $id = $this->model->add_post((int) $search['id'], null, $url, $text('title', 255), $text('company', 255), $text('location', 255));
            if ($id === null) {
                $skipped[] = ['url' => $url, 'reason' => 'The search has this post already.'];
                continue;
            }
            $room--;
            $added[] = ['url' => $url, 'id' => $id];
        }
        foreach ($added as $post) {
            try {
                $this->queue->_enqueue_unique('_auto_match', [$post['id']]);
            } catch (Throwable $e) {
                error_log("job_scan: queueing the match of post {$post['id']} failed: " . $e->getMessage());
            }
        }
        echo json_encode(['added' => count($added), 'skipped' => $skipped, 'search' => BASE_URL . 'job_scan/search/' . (int) $search['id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET job_scan/openapi: job_scan/api as an OpenAPI 3.1 schema, to import
     * into a ChatGPT Action (authentication: API key, Bearer).
     *
     * @return void
     */
    public function openapi(): void {
        header('Content-Type: application/json');
        $post = [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                'url' => ['type' => 'string', 'description' => "The job post's own page, not a search results page."],
                'title' => ['type' => 'string', 'description' => 'The job title.'],
                'company' => ['type' => 'string'],
                'location' => ['type' => 'string'],
            ],
        ];
        echo json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Recruiter job scan', 'version' => '1.0.0', 'description' => 'Adds job posts to a saved job search, where each one is matched with its CV.'],
            'servers' => [['url' => rtrim(BASE_URL, '/')]],
            'paths' => [
                '/job_scan/api' => [
                    'post' => [
                        'operationId' => 'addJobPosts',
                        'summary' => 'Add job posts to the job search',
                        'description' => 'Send the job posts you found, at most ' . self::POSTS_PER_REQUEST . ' per request. Posts the search has already are skipped.',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'required' => ['posts'],
                                'properties' => ['posts' => ['type' => 'array', 'items' => $post]],
                            ]]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'How many were added, and which were skipped and why.', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'added' => ['type' => 'integer'],
                                    'skipped' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string'], 'reason' => ['type' => 'string']]]],
                                    'search' => ['type' => 'string', 'description' => 'The job search page.'],
                                ],
                            ]]]],
                            '400' => ['description' => 'The body is not {posts: [...]}.'],
                            '401' => ['description' => 'Unknown token.'],
                        ],
                    ],
                ],
            ],
            'components' => ['securitySchemes' => ['token' => ['type' => 'http', 'scheme' => 'bearer']], 'schemas' => (object) []],
            'security' => [['token' => []]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST job_scan/submit_scan/{search id}: scans the search now (on the
     * queue), at most once an hour.
     *
     * @return void
     */
    public function submit_scan(): void {
        $user_id = $this->user_id();
        $search = $this->search_or_404((int) segment(3), $user_id);
        $back = 'job_scan/search/' . (int) $search['id'];
        if ($this->validation->run() !== true) {
            redirect($back);
        }
        $problem = $this->key_problem($user_id);
        if ($problem !== null) {
            set_flashdata($problem);
            redirect($back);
        }
        if ((int) ($search['last_scanned_at'] ?? 0) > time() - self::SCAN_NOW_AFTER) {
            set_flashdata('This search was scanned less than an hour ago. Scan it again a little later.');
            redirect($back);
        }
        $this->model->resume((int) $search['id']);
        try {
            $job = $this->queue->_enqueue_unique('_scan', [(int) $search['id']]);
            set_flashdata($job->handled
                ? 'The scan is done. The posts it found are matched in a moment; reload to see them.'
                : 'The scan has started. Reload in a minute to see what it finds.');
        } catch (Throwable $e) {
            error_log('job_scan: queueing a scan failed: ' . $e->getMessage());
            set_flashdata("The scan couldn't start: " . $e->getMessage());
        }
        redirect($back);
    }

    /**
     * Queues a scan of every search that is due one, lifts the pause of
     * searches whose owner has saved or switched on a key since, and queues itself again for the
     * next slot (a SWEEP_SECONDS window; the slot makes the job unique, so
     * the chain never doubles). Never a URL.
     */
    public function _scan_due(int $slot): void {
        try {
            foreach ($this->model->paused_searches() as $search_id => $user_id) {
                if ($this->key_problem((int) $user_id) === null) {
                    $this->model->resume((int) $search_id);
                }
            }
            foreach ($this->model->due_searches(time()) as $search_id) {
                $this->queue->_enqueue_unique('_scan', [$search_id]);
            }
        } finally {
            $this->schedule($slot + 1);
        }
    }

    /**
     * Scans a search: asks the model, searching the web on the owner's key,
     * for new job posts that fit, adds each one the search doesn't have yet
     * and queues its match (_auto_match). The run is kept in
     * job_search_runs with its token use or its error. Never a URL.
     */
    public function _scan(int $job_search_id): void {
        $search = $this->model->for_scan($job_search_id);
        if ($search === null || !(int) $search['active']) {
            return;
        }
        $user_id = (int) $search['trongate_user_id'];
        $llm = $this->llm_for($user_id, 'job_evaluation');
        if ($llm === null) {
            $this->model->pause($job_search_id, ($this->key_problem($user_id) ?? 'There is no AI key to scan with.') . ' The search starts again by itself once it is.');
            return;
        }
        ignore_user_abort(true);
        $run_id = $this->model->start_run($job_search_id);
        [$system, $user] = $this->scan_prompt($search, $this->model->recent_posts($job_search_id));
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['posts'],
            'properties' => [
                'posts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['url', 'title', 'company', 'location'],
                        'properties' => [
                            'url' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'company' => ['type' => 'string'],
                            'location' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
        try {
            $found = $llm->web_search($system, $user, $schema, $search['sites'], 'DK');
        } catch (Throwable $e) {
            error_log("job_scan: scanning search $job_search_id failed: " . get_class($e) . ': ' . $e->getMessage());
            $this->model->finish_run($run_id, 0, 0, 0, 0, 'The scan failed: ' . $e->getMessage());
            if ($e instanceof Llm_exception && !$e->retryable) {
                // A rejected key or a provider that can't search: say so on the page.
                $this->model->pause($job_search_id, 'The scan failed: ' . $e->getMessage() . ' Fix it and save the search again.');
            }
            return;
        }
        $posts = array_slice(is_array($found['answer']['posts'] ?? null) ? $found['answer']['posts'] : [], 0, self::POSTS_PER_SCAN);
        $new = 0;
        foreach ($posts as $post) {
            $url = trim((string) ($post['url'] ?? ''));
            if (!preg_match('~^https?://~i', $url)) {
                continue;
            }
            $id = $this->model->add_post($job_search_id, $run_id, $url, trim((string) $post['title']), trim((string) $post['company']), trim((string) $post['location']));
            if ($id !== null) {
                $new++;
                $this->queue->_enqueue_unique('_auto_match', [$id]);
            }
        }
        $this->model->finish_run($run_id, count($posts), $new, $found['input_tokens'], $found['output_tokens']);
    }

    /**
     * Matches a found post with its search's CV and saves the match; from
     * the search's tailor_from_score up, tailors the résumé too. The post's
     * status says how it went (matched, not_a_job, failed with why). Queued
     * by queue_match; never a URL.
     */
    public function _auto_match(int $scanned_job_post_id): void {
        $post = $this->model->for_matching($scanned_job_post_id);
        if ($post === null || $post['status'] === 'dismissed') {
            return;
        }
        $id = (int) $post['id'];
        $user_id = (int) $post['trongate_user_id'];
        // Finish and save even when it runs in a request whose visitor leaves.
        ignore_user_abort(true);
        // Evaluation off (or no key): read and match for free, as quick_match does.
        $llm = $this->llm_for($user_id, 'job_evaluation');
        $this->model->set_status($id, 'matching');
        try {
            $page = Page_reader::read($post['url']);
        } catch (Throwable $e) {
            // A link a scan found that doesn't open is most likely gone, or
            // one the model got wrong: not a job post to try again.
            $found_by_scan = $post['job_search_run_id'] !== null;
            $this->model->set_status($id, $found_by_scan ? 'not_a_job' : 'failed', ($found_by_scan ? "The link the scan found doesn't open: " : "The post couldn't be fetched: ") . $e->getMessage());
            return;
        }
        $job_text = trim($page['title'] !== '' ? $page['title'] . "\n\n" . $page['text'] : $page['text']);
        try {
            $existing = $this->model->existing_match($user_id, $post['cv_text'], $job_text);
            if ($existing !== null) {
                $cv_match_id = $existing;
                $job = [];
            } else {
                $known = [];
                if ($llm !== null) {
                    $job = Match_prompts::read_job($llm, $job_text);
                } else {
                    require_once __DIR__ . '/../cv_match/Cv_matcher.php';
                    require_once __DIR__ . '/../cv_match/Free_reader.php';
                    $reader = new Free_reader(Taxonomy::load());
                    $job = $reader->job($job_text);
                }
                if (trim((string) ($job['job_title'] ?? '')) === '' && empty($job['requirements'])) {
                    $this->model->set_status($id, 'not_a_job', $llm !== null
                        ? 'This page has no job post the AI could read. It may need JavaScript, or the post is gone.'
                        : 'No job title or requirements were found on this page. It may need JavaScript, or the post is gone.');
                    return;
                }
                if ($llm !== null) {
                    $profile = Match_prompts::read_cv($llm, $post['cv_text']);
                } else {
                    $profile = $reader->cv($post['cv_text']);
                    $known = $reader->known;
                }
                $this->module('cv_match');
                $scored = $this->cv_match->_ai_match($llm, $job, $job_text, $page['url'], $profile, $post['cv_name'], $post['cv_text'], $user_id, $known);
                if ($scored['saved_id'] === null) {
                    throw new RuntimeException($scored['save_error'] ?? "The match wasn't saved.");
                }
                $cv_match_id = (int) $scored['saved_id'];
            }
        } catch (Throwable $e) {
            error_log("job_scan: matching post $id failed: " . get_class($e) . ': ' . $e->getMessage());
            $this->model->set_status($id, 'failed', "The match failed: " . $e->getMessage());
            return;
        }
        $found = ['title' => (string) ($job['job_title'] ?? '') ?: $page['title'], 'company' => (string) ($job['company'] ?? '')];
        $match = $this->model->match_score($cv_match_id);
        $error = '';
        // Résumé off (or no key): no eager tailoring; it can be tailored from the match.
        $resume_llm = $this->llm_for($user_id, 'resume');
        if ($resume_llm !== null && $match !== null && $match['resume_written_at'] === null && (float) $match['score'] * 100 >= (int) $post['tailor_from_score']) {
            try {
                $this->module('cv_match');
                $this->cv_match->_tailor_resume($cv_match_id, $user_id, $resume_llm);
            } catch (Throwable $e) {
                error_log("job_scan: tailoring the résumé of match $cv_match_id failed: " . get_class($e) . ': ' . $e->getMessage());
                $error = "The tailored résumé failed: " . $e->getMessage() . ' Tailor it from the match.';
            }
        }
        $this->model->set_status($id, 'matched', $error, $cv_match_id, $found);
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

    /**
     * The view data for a batch of $search's posts, from the query string;
     * with $first, the first batch whatever ?after= says.
     */
    private function posts(array $search, bool $first = false): array {
        $dismissed = ($_GET['dismissed'] ?? '') === '1';
        $after = !$first && Job_scan_model::place((string) ($_GET['after'] ?? '')) !== null ? (string) $_GET['after'] : '';
        $found = $this->model->posts((int) $search['id'], $dismissed, self::PER_PAGE, $after);
        $query = array_filter(['dismissed' => $dismissed ? '1' : '']);
        return [
            'search' => $search,
            'posts' => array_map([Found_post_card::class, 'from'], $found['rows']),
            'dismissed' => $dismissed,
            'after' => $after,
            'next' => $found['next'] === null ? null : http_build_query($query + ['after' => $found['next']]),
        ];
    }

    /**
     * Makes sure _scan_due runs at the start of the next SWEEP_SECONDS slot
     * (or of $slot, when that is later). Unique per slot, so calling this
     * often (page views, saves, the sweep itself) queues it once. Needs a
     * worker: a delayed job never runs in a request.
     */
    private function schedule(int $slot = 0): void {
        $now = time();
        $slot = max($slot, intdiv($now, self::SWEEP_SECONDS) + 1);
        try {
            $this->queue->_enqueue_unique_in(max(1, $slot * self::SWEEP_SECONDS - $now), '_scan_due', [$slot]);
        } catch (Throwable $e) {
            error_log('job_scan: scheduling the scans failed: ' . $e->getMessage());
        }
    }

    /**
     * The scan's instructions and question for $search: what to look for,
     * where, and the posts it found lately (to skip).
     *
     * @return array{0: string, 1: string}
     */
    private function scan_prompt(array $search, array $recent): array {
        $per_scan = self::POSTS_PER_SCAN;
        $system = <<<PROMPT
            You find job posts on the web for a job seeker in Denmark. Search the job sites (Jobnet, Jobindex, LinkedIn Jobs, The Hub, Graduateland, Indeed and the like) and company career pages for open positions that fit what they are looking for, and list them.

            - Only open positions that were published or updated in the last 14 days and still take applications. No expired posts, no articles, no search result pages, no company pages without a specific job.
            - url is the address of the job post itself, exactly as you found it. Never make one up or guess one: leave out a post whose address you don't have.
            - title, company and location as the post gives them; location is the town or "Remote".
            - At most {$per_scan} posts, the best fits first. Skip the posts listed as found already, also when you find them under another address.
            - Answer with an empty list when you find nothing new that fits.
            PROMPT;
        $where = trim($search['location']) !== '' ? $search['location'] : 'anywhere in Denmark';
        $remote = (int) $search['remote_ok'] ? "\nRemote positions (from Denmark) fit too." : '';
        $sites = $search['sites'] ? "\nSearch only these sites: " . implode(', ', $search['sites']) . '.' : '';
        $skip = $recent
            ? implode("\n", array_map(fn($p) => '- ' . trim("{$p['title']} {$p['company']}") . " ({$p['url']})", $recent))
            : '- (none yet)';
        $user = <<<TEXT
            Looking for: {$search['keywords']}
            Where: {$where}{$remote}{$sites}

            Found already:
            {$skip}
            TEXT;
        return [$system, $user];
    }

    /** Queues a post's match; when the queue can't take it, matches it here. */
    private function queue_match(int $post_id): void {
        try {
            $job = $this->queue->_enqueue_unique('_auto_match', [$post_id]);
            set_flashdata($job->handled ? 'The post is matched.' : 'The post is added. It is matched in a moment; reload to see it.');
        } catch (Throwable $e) {
            error_log("job_scan: queueing the match of post $post_id failed: " . $e->getMessage());
            $this->_auto_match($post_id);
            set_flashdata('The post is matched.');
        }
    }

    /**
     * A fresh Llm on the user's own key when it is on for $use (a purpose
     * in Account_model::USES), on the server's key for an administrator
     * without one, or null.
     */
    private function llm_for(int $user_id, string $use): ?Llm {
        $this->module('account');
        $key = $this->account->key_for($user_id);
        $on = $key !== null && $key['uses'][$use];
        if (!$on && !$this->model->is_admin($user_id)) {
            return null;
        }
        // A new one each time: the worker runs many users' jobs in one process.
        $llm = new Llm('llm');
        if ($on) {
            $llm->use_key($key['provider'], $key['api_key'], $key['model']);
        }
        return $llm;
    }

    /**
     * Why the user's searches can't be scanned, or null when they can: the
     * scan searches the web on the key's job_evaluation switch.
     */
    private function key_problem(int $user_id): ?string {
        $this->module('account');
        $key = $this->account->key_for($user_id);
        if (($key !== null && $key['uses']['job_evaluation']) || $this->model->is_admin($user_id)) {
            return null;
        }
        if ($key !== null) {
            return "Your API key is switched off for evaluating job posts, which the job scan searches the job sites with. Switch it on on your account page.";
        }
        return 'Add your OpenAI API key on your account page: the job scan searches the job sites on your own key.';
    }

    private function api_error(int $status, string $message): never {
        http_response_code($status);
        echo json_encode(['error' => $message]);
        die();
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

<?php
require_once __DIR__ . '/Job_posts_model.php';
require_once __DIR__ . '/../cv_match/Match_prompts.php';

/**
 * A company's job posts: create one from pasted text or a link, review what
 * was read (title, basics, the requirements list, responsibilities, pitch),
 * publish it, and pause, close, reopen, archive or duplicate it. Company
 * staff only (Company::staff()); the dashboard listing them is the company
 * home page (Company::index).
 *
 * A pasted post is read on the company's own AI key (Match_prompts::read_job)
 * or, without one, with the free reader (taxonomy labels and rules). Either
 * way the company reviews the rows before publishing.
 *
 * show() is the public page of a published post: /jobs/{token}.
 */
class Job_posts extends Trongate {

    // Longest job post text read, in characters (as the CV checker).
    private const MAX_TEXT = 40000;

    // Blank requirement rows the review form offers for adding.
    private const BLANK_ROWS = 2;

    /** Nothing here: the posts are on the company home page. */
    public function index(): void {
        redirect('company');
    }

    // -----------------------------------------------------------------
    // Creating a post
    // -----------------------------------------------------------------

    /**
     * The form: paste the post, or give its link.
     *
     * @return void
     */
    public function create(): void {
        $member = $this->staff();
        $data = [
            'member' => $member,
            'form_location' => BASE_URL . 'job_posts/submit_create',
            'text' => post('text'),
            'url' => post('url', true),
            'has_key' => $this->company_key((int) $member['company_id']) !== null,
        ];
        $this->view('create', $data);
    }

    /**
     * POST {text | url}: reads the post into a draft and opens its review.
     *
     * @return void
     */
    public function submit_create(): void {
        $member = $this->staff();
        $this->validation->set_rules('url', 'link', 'max_length[2048]');
        $this->validation->set_rules('text', 'job post', 'max_length[' . self::MAX_TEXT . ']');
        if ($this->validation->run() !== true) {
            $this->create();
            return;
        }
        $text = trim((string) post('text'));
        $url = trim((string) post('url', true));
        if ($text === '' && $url === '') {
            set_flashdata('Paste the job post, or give its link.');
            redirect('job_posts/create');
            return;
        }
        if ($text === '') {
            try {
                require_once __DIR__ . '/../cv_match/Page_reader.php';
                $page = Page_reader::read($url);
                $text = mb_substr($page['text'], 0, self::MAX_TEXT, 'UTF-8');
            } catch (RuntimeException $e) {
                set_flashdata("That link couldn't be read: {$e->getMessage()} Paste the text instead.");
                redirect('job_posts/create');
                return;
            }
        }

        [$job, $extractor, $warning] = $this->read($text, (int) $member['company_id']);
        $id = $this->model->create(
            (int) $member['company_id'],
            (int) $member['id'],
            $text,
            $extractor,
            Job_post_rules::from_reading($job),
            self::language_of($text)
        );
        set_flashdata($warning ?? 'Check what was read, then publish when it looks right.');
        redirect('job_posts/review/' . $id);
    }

    // -----------------------------------------------------------------
    // Review and edit
    // -----------------------------------------------------------------

    /**
     * The review page: everything candidates are matched on, editable.
     *
     * @return void
     */
    public function review(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $this->show_review($member, $post, $this->model->rows((int) $post['id']), []);
    }

    /**
     * POST: saves the review form. A live post whose requirements change
     * becomes a new version.
     *
     * @return void
     */
    public function submit_review(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        if ($this->validation->run() !== true) {
            redirect('job_posts/review/' . $post['id']);
            return;
        }
        $form = $_POST;
        [$rows, $errors] = Job_post_rules::from_form($form);
        $basics = [];
        foreach (Job_posts_model::BASICS as $field) {
            $basics[$field] = trim((string) ($form[$field] ?? ''));
        }
        if (!isset(Job_post_rules::LANGUAGES[$basics['language']])) {
            $basics['language'] = 'da';
        }
        if (!isset(Job_post_rules::WORK_HOURS[$basics['work_hours']])) {
            $errors[] = 'Pick the working hours from the list.';
        }
        if (!isset(Job_post_rules::WORKPLACE[$basics['workplace_flexibility']])) {
            $errors[] = 'Pick where the work happens from the list.';
        }
        if ($basics['postal_code'] !== '' && !preg_match('/^[0-9]{4}$/', $basics['postal_code'])) {
            $errors[] = 'A postal code is 4 digits.';
        }
        $pitch = trim((string) ($form['pitch'] ?? ''));
        if (mb_strlen($pitch, 'UTF-8') > 10000) {
            $errors[] = 'The pitch is too long: keep it under 10,000 characters.';
        }
        if ($errors) {
            $this->show_review($member, array_merge($post, $basics, ['pitch' => $pitch]), $rows, $errors);
            return;
        }
        $new_version = $this->model->save_review($post, $basics, $pitch, $rows);
        $publish = ($form['then'] ?? '') === 'publish' && $post['status'] === 'draft';
        if ($publish) {
            $this->change_status($this->model->find((int) $member['company_id'], (int) $post['id']), 'publish');
            return;
        }
        set_flashdata($new_version
            ? 'Saved. The requirements changed, so this is a new version of the post.'
            : 'Saved.');
        redirect('job_posts/review/' . $post['id']);
    }

    // -----------------------------------------------------------------
    // Status, duplicate, delete
    // -----------------------------------------------------------------

    /**
     * POST {action}: publish, pause, resume, close, reopen or archive.
     *
     * @return void
     */
    public function submit_status(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        if ($this->validation->run() !== true) {
            redirect('job_posts/review/' . $post['id']);
            return;
        }
        $this->change_status($post, (string) post('action', true));
    }

    /**
     * POST: a copy of the post as a new draft.
     *
     * @return void
     */
    public function submit_duplicate(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        if ($this->validation->run() !== true) {
            redirect('job_posts/review/' . $post['id']);
            return;
        }
        $id = $this->model->duplicate($post, (int) $member['id']);
        set_flashdata('This is a copy, saved as a draft.');
        redirect('job_posts/review/' . $id);
    }

    /**
     * POST: deletes a draft.
     *
     * @return void
     */
    public function submit_delete(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        if ($this->validation->run() !== true) {
            redirect('job_posts/review/' . $post['id']);
            return;
        }
        if ($this->model->delete_draft((int) $member['company_id'], (int) $post['id'])) {
            set_flashdata("The draft \"{$post['title']}\" is deleted.");
            redirect('company?tab=drafts');
            return;
        }
        set_flashdata('Only drafts can be deleted. Close the post instead.');
        redirect('job_posts/review/' . $post['id']);
    }

    // -----------------------------------------------------------------
    // The public page
    // -----------------------------------------------------------------

    /**
     * /jobs/{token}: a published post for candidates. A paused post says it
     * isn't taking applications; a closed one that it's closed.
     *
     * @return void
     */
    public function show(): void {
        $post = $this->model->find_public((string) segment(3));
        if ($post === null) {
            $this->not_found();
            return;
        }
        $this->view('show', ['post' => $post, 'rows' => $this->model->rows((int) $post['id']), 'preview' => false]);
    }

    /**
     * The public page as candidates will see it, for staff, drafts included.
     *
     * @return void
     */
    public function preview(): void {
        $member = $this->staff();
        $post = $this->post_or_404($member, (int) segment(3));
        $post['company_name'] = $member['company_name'];
        $this->view('show', ['post' => $post, 'rows' => $this->model->rows((int) $post['id']), 'preview' => true]);
    }

    // -----------------------------------------------------------------
    // For the company module
    // -----------------------------------------------------------------

    /** The company's posts with their counts, for the dashboard. Never a URL. */
    public function overview(int $company_id, int $member_id): array {
        block_url('job_posts/overview');
        return $this->model->overview($company_id, $member_id);
    }

    // -----------------------------------------------------------------

    private function show_review(array $member, array $post, array $rows, array $errors): void {
        $requirements = array_values(array_filter($rows, fn(array $r) => isset(Job_post_rules::KINDS[$r['kind']])));
        $title = ['raw_text' => '', 'english' => ''];
        foreach ($rows as $row) {
            if ($row['kind'] === 'title') {
                $title = $row;
            }
        }
        for ($i = 0; $i < self::BLANK_ROWS; $i++) {
            $requirements[] = ['kind' => 'skill', 'raw_text' => '', 'english' => '', 'is_required' => 1, 'alt_group' => null, 'min_years' => null, 'min_level' => null];
        }
        $data = [
            'member' => $member,
            'post' => $post,
            'title_row' => $title,
            'requirements' => $requirements,
            'responsibilities' => array_column(array_filter($rows, fn(array $r) => $r['kind'] === 'responsibility_area'), 'raw_text'),
            'actions' => Job_post_rules::actions($post['status']),
            'errors' => $errors,
            'public_url' => $post['public_token'] ? BASE_URL . 'jobs/' . $post['public_token'] : null,
        ];
        $this->view('review', $data);
    }

    private function change_status(array $post, string $action): void {
        $to = Job_post_rules::next_status($post['status'], $action);
        if ($to === null) {
            set_flashdata("A post that is {$post['status']} can't do that.");
            redirect('job_posts/review/' . $post['id']);
            return;
        }
        if ($action === 'publish') {
            $refusal = Job_post_rules::refuse_publish($post['title'], $this->model->rows((int) $post['id']));
            if ($refusal !== null) {
                set_flashdata($refusal);
                redirect('job_posts/review/' . $post['id']);
                return;
            }
        }
        if (!$this->model->set_status($post, $to)) {
            set_flashdata('Someone changed this post at the same time. Here it is as it is now.');
            redirect('job_posts/review/' . $post['id']);
            return;
        }
        set_flashdata(match ($action) {
            'publish' => 'Published. Share the link below to get applications.',
            'pause' => "Paused. The link stays up but says it isn't taking applications.",
            'resume', 'reopen' => 'Taking applications again.',
            'close' => 'Closed. The link now says the job is closed.',
            'archive' => 'Archived.',
        });
        redirect('job_posts/review/' . $post['id']);
    }

    /**
     * Reads a post's text: [job (extract_job shape), extractor, warning or
     * null]. On the company's key when it has one; the free reader
     * otherwise, or when the model fails.
     */
    private function read(string $text, int $company_id): array {
        $key = $this->company_key($company_id);
        if ($key !== null) {
            try {
                $this->module('llm');
                $this->llm->use_key($key['provider'], $key['api_key'], $key['model']);
                return [Match_prompts::read_job($this->llm, $text), $key['provider'], null];
            } catch (Throwable $e) {
                $warning = "The AI couldn't read the post ({$e->getMessage()}), so the free reader did. Check the requirements carefully.";
            }
        }
        require_once __DIR__ . '/../cv_match/Free_reader.php';
        $job = (new Free_reader(Taxonomy::load()))->job($text);
        return [$job, 'free_reader', $warning ?? 'Read with the free reader (no AI key saved): it finds named skills and years only. Add the rest below.'];
    }

    /** The company's AI key settings, or null. */
    private function company_key(int $company_id): ?array {
        $this->module('company');
        return $this->company->key_for($company_id);
    }

    /** The signed-in member (see Company::staff()). */
    private function staff(): array {
        $this->module('company');
        return $this->company->staff();
    }

    /** The member's company's post, or a 404 page. */
    private function post_or_404(array $member, int $id): array {
        $post = $id > 0 ? $this->model->find((int) $member['company_id'], $id) : null;
        if ($post === null) {
            $this->not_found();
            die();
        }
        return $post;
    }

    private function not_found(): void {
        http_response_code(404);
        $this->view('not_found', []);
    }

    /** 'en' for a post written in English, else 'da'. A rough guess the review form corrects. */
    private static function language_of(string $text): string {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        $count = array_count_values($words);
        $en = ($count['the'] ?? 0) + ($count['and'] ?? 0) + ($count['you'] ?? 0) + ($count['with'] ?? 0);
        $da = ($count['og'] ?? 0) + ($count['du'] ?? 0) + ($count['med'] ?? 0) + ($count['at'] ?? 0) + ($count['vi'] ?? 0);
        return $en > $da ? 'en' : 'da';
    }

}

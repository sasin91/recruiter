<?php
require_once __DIR__ . '/Applications_model.php';
require_once __DIR__ . '/../login/Return_path.php';
require_once __DIR__ . '/../cv_match/Match_prompts.php';
require_once __DIR__ . '/../cv_match/Free_reader.php';
require_once __DIR__ . '/../laya/Laya_client.php';

/**
 * Applying for a job post (candidates, user level 3) and scoring the
 * application for the company's list (Matchmaker).
 *
 * From the post's Apply button (/jobs/{token}/apply): sign in or sign up
 * (and come back here), give a CV once (kept as the candidate's résumé for
 * the next application), see how it matches the post, add a cover letter if
 * you like, and send. The application is scored there and then: the taxonomy
 * decides what it can, the company's AI key judges the rest, and Laya reads
 * the verdicts (Application_scoring).
 *
 * /applications lists your applications; one still in review can be
 * withdrawn.
 */
class Applications extends Trongate {

    private const USER_LEVEL = 3;

    // Longest CV and cover letter taken, in characters.
    private const MAX_CV = 40000;
    private const MAX_LETTER = 5000;

    /**
     * The candidate's applications.
     *
     * @return void
     */
    public function index(): void {
        $candidate = $this->candidate();
        if ($candidate === null) {
            redirect('sign-in');
            return;
        }
        $this->view('mine', [
            'candidate' => $candidate,
            'applications' => $this->model->mine((int) $candidate['id']),
        ]);
    }

    /**
     * /jobs/{token}/apply: the step the candidate is at. Not signed in as a
     * candidate: sign in or sign up first. No CV yet (or ?cv=new): the CV
     * form. Otherwise: how the CV matches, the cover letter and Send.
     *
     * @return void
     */
    public function apply(): void {
        $token = (string) segment(3);
        $post = $this->public_post($token);
        $candidate = $this->candidate();
        $data = ['post' => $post, 'candidate' => $candidate, 'step' => '', 'errors' => []];

        if ($candidate === null) {
            Return_path::remember("jobs/$token/apply");
            $data['step'] = 'sign_in';
        } elseif ($post['status'] !== 'active') {
            $data['step'] = 'closed';
        } elseif (($application = $this->model->application_for((int) $post['id'], (int) $candidate['id'])) && $application['status'] !== 'withdrawn') {
            $data['step'] = 'applied';
            $data['application'] = $application;
        } else {
            $resume = $this->model->resume((int) $candidate['id']);
            if ($resume === null || ($_GET['cv'] ?? '') === 'new') {
                $data['step'] = 'cv';
                $data['resume'] = $resume;
            } else {
                $data['step'] = 'send';
                $data['resume'] = $resume;
                $data['preview'] = $this->preview($post, $resume);
                $data['cover_letter'] = post('cover_letter', true);
            }
        }
        $this->view('apply', $data);
    }

    /**
     * POST cv_text (and cv_name): reads the CV, on the candidate's own AI key
     * when they have one, else with the free reader, and saves it as their
     * résumé. Back to the apply page.
     *
     * @return void
     */
    public function submit_cv(): void {
        $token = (string) segment(3);
        $candidate = $this->candidate_or_sign_in($token);
        $this->public_post($token);
        $this->validation->set_rules('cv_text', 'CV', 'required|max_length[' . self::MAX_CV . ']');
        if ($this->validation->run() !== true) {
            $_GET['cv'] = 'new';
            $this->apply();
            return;
        }
        $text = trim((string) post('cv_text'));
        [$read, $extractor, $warning] = $this->read_cv($text, (int) $candidate['trongate_user_id']);
        $this->model->save_resume((int) $candidate['id'], [
            'name' => trim((string) post('cv_name', true)),
            'text' => $text,
            'language' => self::language_of($text),
            'extractor' => $extractor,
        ], Application_scoring::profile_terms($read));
        set_flashdata($warning ?? 'Your CV is saved. Here is how it matches the job.');
        redirect("jobs/$token/apply");
    }

    /**
     * POST cover_letter: sends the application with the saved CV, then
     * scores it. A score that fails doesn't stop the application: the
     * company can re-score it.
     *
     * @return void
     */
    public function submit_apply(): void {
        $token = (string) segment(3);
        $candidate = $this->candidate_or_sign_in($token);
        $post = $this->public_post($token);
        $this->validation->set_rules('cover_letter', 'cover letter', 'max_length[' . self::MAX_LETTER . ']');
        if ($this->validation->run() !== true) {
            $this->apply();
            return;
        }
        if ($post['status'] !== 'active') {
            set_flashdata("This job isn't taking applications.");
            redirect("jobs/$token/apply");
            return;
        }
        $resume = $this->model->resume((int) $candidate['id']);
        if ($resume === null) {
            redirect("jobs/$token/apply");
            return;
        }
        try {
            $id = $this->model->apply($post, $candidate, $resume, trim((string) post('cover_letter')));
        } catch (RuntimeException $e) {
            set_flashdata($e->getMessage());
            redirect("jobs/$token/apply");
            return;
        }
        try {
            $this->score($id);
        } catch (Throwable $e) {
            error_log("Scoring application $id failed: " . $e->getMessage());
        }
        set_flashdata('Sent to ' . $post['company_name'] . '. Good luck!');
        redirect('applications');
    }

    /**
     * POST: withdraws one of your applications still in review.
     *
     * @return void
     */
    public function submit_withdraw(): void {
        $candidate = $this->candidate();
        if ($candidate === null) {
            redirect('sign-in');
            return;
        }
        if ($this->validation->run() === true) {
            $post_id = $this->model->withdraw((int) $candidate['id'], (int) segment(3));
            if ($post_id !== null) {
                $this->model->rerank($post_id);
                set_flashdata('Withdrawn. The company no longer sees it.');
            } else {
                set_flashdata("That application can't be withdrawn.");
            }
        }
        redirect('applications');
    }

    // -----------------------------------------------------------------
    // For the matchmaker module
    // -----------------------------------------------------------------

    /**
     * Scores an application against its post as the post is now, and saves
     * the score (match_scores, match_score_details) and the post's new order.
     * Never a URL.
     */
    public function score(int $application_id): void {
        block_url('applications/score');
        $application = $this->model->for_scoring($application_id);
        if ($application === null) {
            throw new RuntimeException('No such application.');
        }
        $this->module('job_posts');
        $post = $this->job_posts->company_post((int) $application['company_id'], (int) $application['job_post_id']);
        $job = Job_post_rules::to_job($post['rows']);
        $groups = Cv_matcher::criteria($job);
        $taxonomy = Taxonomy::load();
        $profile = Application_scoring::profile_from_terms($application['terms']);
        $matcher = new Cv_matcher($taxonomy, $profile, $this->known($taxonomy, $profile, $job));

        $verdicts = [];
        $undecided = [];
        foreach (Cv_matcher::units($groups) as $unit) {
            $verdict = $matcher->decide($unit);
            if ($verdict) {
                $verdicts[$unit['id']] = $verdict;
            } else {
                $undecided[$unit['id']] = $unit;
            }
        }
        $verdicts += $this->judge($post, $undecided, (string) $application['raw_text']);

        $scores = Application_scoring::scores($groups, $verdicts);
        $combined = Cv_matcher::combine($groups, $verdicts);
        $laya = null;
        if ($client = Laya_client::from_env()) {
            try {
                $laya = $client->decide(Cv_matcher::laya_summary($job, $groups, $combined));
            } catch (Throwable $e) {
                error_log("Laya on application $application_id: " . $e->getMessage());
            }
        }
        $this->model->save_score(
            $application,
            (int) $post['version'],
            $scores,
            $laya,
            Application_scoring::details($groups, $combined, $job, $post['rows'])
        );
    }

    /** Re-ranks a post's list (after a reject or a withdrawal). Never a URL. */
    public function rerank(int $job_post_id): void {
        block_url('applications/rerank');
        $this->model->rerank($job_post_id);
    }

    /**
     * Logs a staff member's action on an application, after it took effect
     * (see Applications_model::action()). Never a URL.
     */
    public function log_action(int $application_id, string $action, int $member_id, string $from_status, string $source, ?string $reason = null, ?string $note = null): void {
        block_url('applications/log_action');
        $this->model->action($application_id, $action, $member_id, null, $from_status, $source, $reason, $note);
    }

    // -----------------------------------------------------------------

    /**
     * The model's verdicts on what the taxonomy couldn't decide, on the
     * company's AI key; without a key, or when the call fails, they count as
     * not found.
     *
     * @param array $undecided unit id => unit
     */
    private function judge(array $post, array $undecided, string $cv_text): array {
        if (!$undecided) {
            return [];
        }
        $why = 'Not named in the CV. Add an AI key in Settings to judge it from the whole CV.';
        $this->module('company');
        $key = $this->company->key_for((int) $post['company_id']);
        if ($key !== null) {
            try {
                $this->module('llm');
                $this->llm->use_key($key['provider'], $key['api_key'], $key['model']);
                $items = array_map(fn(array $u) => ['id' => $u['id'], 'text' => $u['text'], 'kind' => $u['kind']], array_values($undecided));
                $answer = Match_prompts::judge($this->llm, $post['title'], $items, $cv_text);
                $judged = Application_scoring::judged($answer, array_keys($undecided));
                $why = 'The AI gave no verdict on this.';
            } catch (Throwable $e) {
                error_log('Judging an application failed: ' . $e->getMessage());
                $why = "Not named in the CV, and the AI couldn't judge it this time. Re-score to try again.";
            }
        }
        $verdicts = [];
        foreach (array_keys($undecided) as $id) {
            $verdicts[$id] = $judged[$id] ?? Application_scoring::not_found($why);
        }
        return $verdicts;
    }

    /**
     * Phrases whose taxonomy term the free reader knows by label, so the
     * matcher needn't rank the whole taxonomy for each (Free_reader::$known).
     */
    private function known(Taxonomy $taxonomy, array $profile, array $job): array {
        $reader = new Free_reader($taxonomy);
        $phrases = array_merge($profile['skills'], $profile['languages'], $profile['certifications'], $profile['education'], $profile['titles']);
        $reader->cv(implode("\n", $phrases));
        $reader->cv(implode("\n", array_column($job['requirements'], 'value')));
        return array_intersect_key($reader->known, array_flip(array_merge($phrases, array_column($job['requirements'], 'value'))));
    }

    /**
     * How the saved CV matches the post's requirements on the taxonomy
     * alone, for the candidate before sending: the score, tag and each
     * requirement's verdict.
     */
    private function preview(array $post, array $saved): array {
        $job = Job_post_rules::to_job($post['rows']);
        $groups = Cv_matcher::criteria($job);
        $taxonomy = Taxonomy::load();
        $profile = Application_scoring::profile_from_terms($saved['terms']);
        $matcher = new Cv_matcher($taxonomy, $profile, $this->known($taxonomy, $profile, $job));
        $verdicts = [];
        foreach (Cv_matcher::units($groups) as $unit) {
            $verdicts[$unit['id']] = $matcher->decide($unit)
                ?? Application_scoring::not_found("Not named in your CV. Mention it in the cover letter if you have it.");
        }
        $verdicts = Cv_matcher::combine($groups, $verdicts);
        // Scored on the requirements only: the title and responsibilities
        // need the model to judge, so here they would always count as missed.
        $asked = ['requirements' => $groups['requirements'], 'skills' => $groups['skills']];
        return [
            'groups' => $groups,
            'verdicts' => $verdicts,
            'result' => Cv_matcher::score($asked, $verdicts),
        ];
    }

    /** [profile (extract_cv shape), extractor, warning or null]. */
    private function read_cv(string $text, int $user_id): array {
        $this->module('account');
        $key = $this->account->key_for($user_id);
        if ($key !== null) {
            try {
                $this->module('llm');
                $this->llm->use_key($key['provider'], $key['api_key'], $key['model']);
                return [Match_prompts::read_cv($this->llm, $text), $key['provider'], null];
            } catch (Throwable $e) {
                $warning = "The AI couldn't read your CV ({$e->getMessage()}), so the free reader did. It finds named skills, titles and years.";
            }
        }
        return [(new Free_reader(Taxonomy::load()))->cv($text), 'free_reader', $warning ?? null];
    }

    /** The published post, or a 404 page. */
    private function public_post(string $token): array {
        $this->module('job_posts');
        $post = $this->job_posts->public_post($token);
        if ($post === null) {
            http_response_code(404);
            $this->view('not_found', ['view_module' => 'job_posts']);
            die();
        }
        return $post;
    }

    /** The signed-in candidate, or null. */
    private function candidate(): ?array {
        $token = $this->trongate_tokens->attempt_get_valid_token(self::USER_LEVEL);
        if ($token === false) {
            return null;
        }
        return $this->model->candidate_for_user((int) $this->trongate_tokens->get_user_id($token));
    }

    /** As candidate(); anyone else goes to the apply page, which asks them to sign in. */
    private function candidate_or_sign_in(string $token): array {
        $candidate = $this->candidate();
        if ($candidate === null) {
            redirect("jobs/$token/apply");
            die();
        }
        return $candidate;
    }

    /** 'en' for a CV written in English, else 'da' (as Job_posts guesses a post's). */
    private static function language_of(string $text): string {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        $count = array_count_values($words);
        $en = ($count['the'] ?? 0) + ($count['and'] ?? 0) + ($count['with'] ?? 0) + ($count['of'] ?? 0);
        $da = ($count['og'] ?? 0) + ($count['med'] ?? 0) + ($count['af'] ?? 0) + ($count['jeg'] ?? 0);
        return $en > $da ? 'en' : 'da';
    }

}

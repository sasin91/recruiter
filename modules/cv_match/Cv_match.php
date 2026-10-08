<?php
/**
 * CV match: paste or upload a job post and see how a CV matches it.
 *
 * The page (views/cv_match.php + js/app.js) reads the files and shows the
 * result; the work happens here. fetch_job reads a job post from a pasted URL
 * with Page_reader, which keeps the fetch to public web pages and hands back
 * plain text. Three JSON endpoints ask a language model
 * through the llm module (job post extraction, CV extraction, and
 * per-requirement judgement for what the taxonomy can't decide) and two run
 * Cv_matcher: decide() matches with the taxonomy lookup
 * (taxonomy/php/Taxonomy.php) before the model judges the rest, and score()
 * ranks the verdicts with the legacy ranking weights.
 *
 * score() also saves the match (Cv_match_model: cv_matches + cv_match_items),
 * history and saved list and reopen saved matches, write_application
 * drafts a job application for a saved match and tailor_resume rewrites the
 * CV for its post; both are kept with the match.
 *
 * The provider, model and API key come from config/llm.php (see the llm
 * module).
 *
 * The page is public. Visitors who aren't signed in get quick_match: the
 * free reading (Free_reader: taxonomy labels and rules, no model) matched and
 * scored in one call. Everything that spends model credits or fetches pages
 * (and saving, which needs an owner) is for signed-in users only; see
 * make_sure_signed_in(). The model calls run on the user's own API key
 * (saved on the account page); only administrators fall back to the
 * server's key. See llm().
 *
 * Several job posts (up to MAX_JOBS) can be compared with one CV: the page
 * runs each through the same endpoints in turn, so every post is matched and
 * saved exactly like a single one, and lists them by score. Page fetches are
 * capped per session (count_fetch) so a batch of links can't become a crawler,
 * and so are Laya's answers (score), which share one CPU-bound service.
 */
class Cv_match extends Trongate {

    // Longest CV or job post quick_match reads, in characters.
    private const MAX_TEXT = 40000;

    // Longest notes the candidate can add for an application or résumé.
    private const MAX_NOTES = 2000;

    // Most job posts the page compares against one CV in one go.
    private const MAX_JOBS = 10;

    // Most pages fetch_job reads for one session in FETCH_WINDOW seconds:
    // a batch of MAX_JOBS links a few times over, not a crawler.
    private const MAX_FETCHES = 30;
    private const FETCH_WINDOW = 600;

    // Most matches score() asks Laya about for one session in LAYA_WINDOW
    // seconds: a batch of MAX_JOBS posts a few times over. Past it the score
    // comes back without Laya's answer.
    private const MAX_LAYA_CALLS = 30;
    private const LAYA_WINDOW = 600;

    /**
     * The match page.
     *
     * @return void
     */
    public function index(): void {
        $signed_in = $this->signed_in();
        $data = [
            'base_url' => BASE_URL,
            'signed_in' => $signed_in,
            'ai_ready' => ($signed_in || $this->dev_machine()) && $this->llm_settings() !== false,
            'max_jobs' => self::MAX_JOBS,
        ];
        $this->view('cv_match', $data);
    }

    /**
     * POST {job_text, cv_text}: the free match, for anyone. Both texts are
     * read with Free_reader (no model), matched with the taxonomy and scored
     * like the full match; what the taxonomy can't find in the CV counts as
     * missing. Nothing is saved.
     *
     * @return void
     */
    public function quick_match(): void {
        $input = $this->read_input();
        $job_text = trim((string) ($input['job_text'] ?? ''));
        $cv_text = trim((string) ($input['cv_text'] ?? ''));
        $this->respond(function () use ($job_text, $cv_text) {
            if ($job_text === '' || $cv_text === '') {
                http_response_code(422);
                throw new RuntimeException('Paste or upload both the CV and the job post.');
            }
            if (mb_strlen($job_text, 'UTF-8') > self::MAX_TEXT || mb_strlen($cv_text, 'UTF-8') > self::MAX_TEXT) {
                http_response_code(413);
                throw new RuntimeException('That text is too long. Paste only the CV and the job post.');
            }
            require_once __DIR__ . '/Cv_matcher.php';
            require_once __DIR__ . '/Free_reader.php';
            $taxonomy = Taxonomy::load();
            $reader = new Free_reader($taxonomy);
            $job = $reader->job($job_text);
            $profile = $reader->cv($cv_text);
            $matcher = new Cv_matcher($taxonomy, $profile, $reader->known);
            $groups = Cv_matcher::criteria($job);
            $verdicts = [];
            foreach (Cv_matcher::units($groups) as $unit) {
                $verdicts[$unit['id']] = $matcher->decide($unit) ?? [
                    'verdict' => 'missing',
                    'by' => 'taxonomy',
                    'method' => 'not_found',
                    'evidence' => '',
                    'reason' => 'Not named in the CV. The AI match also looks for related experience.',
                ];
            }
            $verdicts = Cv_matcher::combine($groups, $verdicts);
            return [
                'job' => array_diff_key($job, ['text_en' => true]),
                'profile' => array_diff_key($profile, ['text_en' => true]),
                'groups' => $groups,
                'verdicts' => (object) $verdicts,
                'result' => Cv_matcher::score($groups, $verdicts),
                'soft_skills' => Cv_matcher::soft_skills($job),
            ];
        });
    }

    /**
     * POST {job, profile}: the job post's criteria and the verdicts the
     * taxonomy can give on its own; `undecided` is what the model should judge.
     *
     * @return void
     */
    public function decide(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $this->respond(function () use ($input) {
            require_once __DIR__ . '/Cv_matcher.php';
            $matcher = new Cv_matcher(Taxonomy::load(), $input['profile'] ?? []);
            $groups = Cv_matcher::criteria($input['job'] ?? []);
            $verdicts = [];
            $undecided = [];
            foreach (Cv_matcher::units($groups) as $unit) {
                $verdict = $matcher->decide($unit);
                if ($verdict) {
                    $verdicts[$unit['id']] = $verdict;
                } else {
                    $undecided[] = ['id' => $unit['id'], 'text' => $unit['text'], 'kind' => $unit['kind']];
                }
            }
            return ['verdicts' => (object) $verdicts, 'undecided' => $undecided];
        });
    }

    /**
     * POST {job, verdicts, job_text, job_url, cv_name, cv_text}: the legacy
     * ranking over all verdicts, with the criteria, combined verdicts, soft
     * skills, the Laya summary and, when the Laya service is set up, `laya`:
     * its answer on that summary (Laya_client), or `laya_error`. The match is
     * saved when job_text and cv_text are given; `saved_id` is its id, or
     * null with `save_error` when the database couldn't take it (the score
     * still comes back).
     *
     * @return void
     */
    public function score(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $this->respond(function () use ($input) {
            require_once __DIR__ . '/Cv_matcher.php';
            $job = $input['job'] ?? [];
            $groups = Cv_matcher::criteria($job);
            $verdicts = Cv_matcher::combine($groups, $input['verdicts'] ?? []);
            $result = Cv_matcher::score($groups, $verdicts);
            $answer = [
                'groups' => $groups,
                'verdicts' => (object) $verdicts,
                'result' => $result,
                'soft_skills' => Cv_matcher::soft_skills($job),
                'laya_summary' => Cv_matcher::laya_summary($job, $groups, $verdicts),
                'laya' => null,
                'saved_id' => null,
            ];
            // Laya's own reading of the verdicts, when its service is set up
            // (LAYA_URL); the score comes back without it if Laya fails.
            require_once __DIR__ . '/../laya/Laya_client.php';
            $laya = Laya_client::from_env();
            $wait = $laya === null ? null : $this->use_allowance('cv_match_laya_calls', self::MAX_LAYA_CALLS, self::LAYA_WINDOW);
            if ($wait !== null) {
                $answer['laya_error'] = "Laya's limit for now is reached. Its answer comes back in $wait minute" . ($wait === 1 ? '' : 's') . '.';
            } elseif ($laya !== null) {
                try {
                    $answer['laya'] = $laya->decide($answer['laya_summary']);
                } catch (Throwable $e) {
                    $answer['laya_error'] = $e->getMessage();
                }
            }
            if (trim($input['job_text'] ?? '') !== '' && trim($input['cv_text'] ?? '') !== '') {
                try {
                    $answer['saved_id'] = $this->model->save($this->user_id(), [
                        'job' => $job,
                        'job_url' => (string) ($input['job_url'] ?? ''),
                        'job_text' => (string) $input['job_text'],
                        'cv_name' => (string) ($input['cv_name'] ?? ''),
                        'cv_text' => (string) $input['cv_text'],
                    ], $groups, $verdicts, $result);
                } catch (Throwable $e) {
                    $answer['save_error'] = "The match wasn't saved: " . $e->getMessage();
                }
            }
            return $answer;
        });
    }

    /**
     * GET: the saved matches, newest first.
     *
     * @return void
     */
    public function history(): void {
        $this->make_sure_signed_in();
        $this->respond(fn() => ['matches' => $this->model->recent($this->user_id())]);
    }

    /**
     * GET saved/{id}: one saved match with its items and application.
     *
     * @return void
     */
    public function saved(): void {
        $this->make_sure_signed_in();
        $this->respond(fn() => $this->saved_match(segment(3, 'int')));
    }

    /**
     * POST {cv_text, job_text}: the newest saved match of this CV with the
     * same job post text, as saved() gives it,
     * or {match: null}. The page uses it to skip posts it has matched before.
     *
     * @return void
     */
    public function already_matched(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $this->respond(function () use ($input) {
            $id = $this->model->existing(
                $this->user_id(),
                trim((string) ($input['cv_text'] ?? '')),
                trim((string) ($input['job_text'] ?? ''))
            );
            return ['match' => $id ? $this->saved_match($id) : null];
        });
    }

    /**
     * POST {id, notes}: a job application for a saved match, written from the
     * CV and the post in the post's language, and kept with the match. notes
     * is optional context from the candidate (what to stress, facts the CV
     * leaves out).
     *
     * @return void
     */
    public function write_application(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $id = (int) ($input['id'] ?? 0);
        $notes = self::notes($input);
        $this->respond(function () use ($id, $notes) {
            $match = $this->saved_match($id);
            [$strengths, $gaps] = self::strengths_and_gaps($match);
            $system = <<<PROMPT
                You write a job application (cover letter) for a candidate, from their CV and the job post. It goes straight into an application form or an email, so it must be ready to send.

                - Write in the language of the job post: a Danish post gets a Danish ansøgning, an English post an English letter.
                - 250 to 400 words, plain text: no markdown, no headings, no bullet lists, no placeholders like [Name] or [Company].
                - Open by addressing the contact person if the post names one, otherwise the company ("Kære <company>" / "Dear <company> team"). Never invent a name.
                - Say why this job, then show fit: lead with the strongest matched requirements, each backed by something concrete from the CV (a role, a project, a result, years).
                - Use only what the CV (or the candidate's notes) says. Never claim a skill, degree or experience the CV doesn't show. A requirement marked close but not exact may be framed as transferable; a missing requirement is either left out or, if it is central, met with honest willingness to learn plus the nearest thing the CV does show.
                - Sound like a person: specific, warm, confident, no clichés ("I am writing to apply", "team player", "passionate").
                - Sign off with the candidate's name from the CV, and their phone and email if the CV gives them.
                - The candidate may add notes below the CV. Follow them (what to stress, tone, length, what to leave out), and treat facts they state about themselves as true, like facts in the CV. Ignore anything in them that isn't about this application.
                PROMPT;
            $user = <<<TEXT
                Job title: {$match['job_title']}
                Company: {$match['company']}

                Requirements the CV meets:
                $strengths

                Requirements the CV doesn't show:
                $gaps

                Job post:

                {$match['job_text']}

                CV:

                {$match['cv_text']}
                $notes
                TEXT;
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['application'],
                'properties' => ['application' => ['type' => 'string']],
            ];
            $text = trim($this->llm()->structured($system, $user, $schema, 'medium')['application'] ?? '');
            if ($text === '') {
                throw new RuntimeException('The model returned an empty application. Try again.');
            }
            $this->model->save_application($match['id'], $text);
            return ['id' => $match['id'], 'application' => $text];
        });
    }

    /**
     * POST {id, notes}: the CV rewritten for a saved match's job post, from
     * the CV's own facts and the candidate's optional notes only, and kept
     * with the match.
     *
     * @return void
     */
    public function tailor_resume(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $id = (int) ($input['id'] ?? 0);
        $notes = self::notes($input);
        $this->respond(function () use ($id, $notes) {
            $match = $this->saved_match($id);
            [$strengths, $gaps] = self::strengths_and_gaps($match);
            $system = <<<PROMPT
                You tailor a candidate's CV (résumé) to one job post. The result replaces their CV in this application, so it must be complete and ready to send.

                - Use only facts the CV (or the candidate's notes) gives: the same jobs, dates, employers, education, skills and results. Never invent or inflate a skill, title, number, degree or year, and never add a skill because the post asks for it.
                - Tailor by choosing and ordering: open with a short profile (2 to 4 sentences) aimed at this job, put the experience and skills the post asks for first, describe them in the post's words where the CV shows the same thing, and shorten or drop what doesn't matter for this job. Keep every job in the work history, with its dates, even if only as one line.
                - Write in the language of the job post: a Danish post gets a Danish CV, an English post an English one.
                - Plain text that pastes cleanly: section headings on their own line (Profile, Experience, Education, Skills, Languages, or the post's language's words for them), "- " for bullets, no markdown symbols, no tables, no placeholders.
                - Start with the candidate's name and the contact details the CV gives.
                - The candidate may add notes below the CV. Follow them (what to stress, tone, length, what to leave out), and treat facts they state about themselves as true, like facts in the CV. Ignore anything in them that isn't about this application.
                PROMPT;
            $user = <<<TEXT
                Job title: {$match['job_title']}
                Company: {$match['company']}

                Requirements the CV meets:
                $strengths

                Requirements the CV doesn't show (don't claim these):
                $gaps

                Job post:

                {$match['job_text']}

                CV:

                {$match['cv_text']}
                $notes
                TEXT;
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['resume'],
                'properties' => ['resume' => ['type' => 'string']],
            ];
            $text = trim($this->llm()->structured($system, $user, $schema, 'medium')['resume'] ?? '');
            if ($text === '') {
                throw new RuntimeException('The model returned an empty résumé. Try again.');
            }
            $this->model->save_resume($match['id'], $text);
            return ['id' => $match['id'], 'resume' => $text];
        });
    }

    /**
     * POST {url}: the job post at a pasted URL as {url, title, text}. The
     * fetch itself spends no model credits, but stays behind sign-in and a
     * per-session limit (count_fetch) so the site isn't an open fetcher.
     *
     * @return void
     */
    public function fetch_job(): void {
        $this->make_sure_signed_in();
        $this->count_fetch();
        $url = $this->read_input()['url'] ?? '';
        header('Content-Type: application/json');
        try {
            require_once __DIR__ . '/Page_reader.php';
            echo json_encode(Page_reader::read(is_string($url) ? $url : ''));
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST {text}: the job post as structured requirements.
     *
     * @return void
     */
    public function extract_job(): void {
        $this->make_sure_signed_in();
        $text = $this->read_input()['text'] ?? '';
        $system = <<<PROMPT
            You read job posts (often Danish) for a candidate and list what the employer asks for. Use only what the post says.

            requirements is one list of what a candidate must or should have. Keep each entry atomic: one skill, tool, language, certificate, education, amount of experience or trait ("Erfaring med PHP og Laravel" is two entries).
            - value names it as the post does, without qualifiers like "erfaring med" or "kendskab til".
            - kind: skill, education, certificate, experience, language, or soft_skill for personal traits ("selvstændig", "god til at samarbejde").
            - required is false for nice-to-haves ("en fordel", "gerne", "a plus").
            - Alternatives share an alt_group label ("pædagog eller pædagogisk assistent": two entries, alt_group "a"; "uddannet kok eller 5 års erfaring": an education and an experience entry, alt_group "b"); alt_group is empty otherwise.
            - min_years: the years of experience asked for ("et par år" = 2, "flere års" = 3, none stated = 0).
            - min_level: the level asked for, in English, when the post states one ("flydende" = "fluent", "modersmål" = "native", "kandidat" = "master's degree", "senior"); empty otherwise.

            responsibilities: at most 6, a few words each.
            text_en: the whole post as plain English text, translated if needed; keep every fact, drop layout.
            job_title_en and english: the job title and each value in English (the same text when it already is English).
            PROMPT;
        $this->respond(fn() => $this->llm()->structured($system, "Job post:\n\n$text", self::job_schema(), 'low'));
    }

    /**
     * POST {text}: the CV as a structured profile.
     *
     * @return void
     */
    public function extract_cv(): void {
        $this->make_sure_signed_in();
        $text = $this->read_input()['text'] ?? '';
        $system = <<<PROMPT
            You read a CV and list what it shows the candidate can do. Use only what the CV says or directly shows: a bullet like "Upgraded Symfony from 6 to 7" shows Symfony and PHP.

            Keep each skill atomic (one technology, tool, method or language per item) and name it as commonly written ("Laravel", "Kubernetes", "CI/CD").
            titles: job titles held.
            experience_years: total years of professional work, from the dates.
            Include the languages the CV is written in or names.
            text_en: the whole CV as plain English text, translated if it is in another language, otherwise as given; keep every fact, drop layout.
            PROMPT;
        $this->respond(fn() => $this->llm()->structured($system, "CV:\n\n$text", self::cv_schema(), 'low'));
    }

    /**
     * POST {cv_text, job_title, items: [{id, text, kind}]}: the model's
     * verdict on each requirement the taxonomy couldn't decide.
     *
     * @return void
     */
    public function judge(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $items = implode("\n", array_map(
            fn($item) => "- [{$item['id']}] ({$item['kind']}) {$item['text']}",
            $input['items'] ?? []
        ));
        $job_title = $input['job_title'] ?? '';
        $cv_text = $input['cv_text'] ?? '';
        $system = <<<PROMPT
            You judge whether a candidate meets job requirements, from their CV alone. For each requirement say:
            - "met" when the CV shows it (directly, or through something that clearly includes it: Laravel shows PHP),
            - "partial" when the CV shows something close or transferable but not the thing itself (Vue for React, Symfony for Laravel),
            - "missing" when the CV shows nothing relevant.

            Be strict: a guess is "missing". evidence quotes the CV briefly, empty when missing. reason is one short sentence in English.
            PROMPT;
        $user = <<<TEXT
            Job title: $job_title

            Requirements:
            $items

            CV:

            $cv_text
            TEXT;
        $this->respond(fn() => $this->llm()->structured($system, $user, self::judge_schema(), 'medium'));
    }

    /**
     * The candidate's own notes for an application or résumé, as a block to
     * end the prompt with, or '' when there are none.
     */
    private static function notes(array $input): string {
        $notes = trim(mb_substr((string) ($input['notes'] ?? ''), 0, self::MAX_NOTES));
        return $notes === '' ? '' : "\nNotes from the candidate:\n\n$notes";
    }

    /**
     * A saved match's verdicts as two bullet lists for a prompt: what the CV
     * meets (with its evidence) and what it doesn't show.
     *
     * @return array{0: string, 1: string}
     */
    private static function strengths_and_gaps(array $match): array {
        $strengths = [];
        $gaps = [];
        foreach ($match['items'] as $item) {
            if ($item['criterion'] === 'title') {
                continue;
            }
            $line = "- {$item['text']}" . ($item['evidence'] !== '' ? " (CV: {$item['evidence']})" : '');
            if ($item['verdict'] === 'missing') {
                $gaps[] = $line;
            } else {
                $strengths[] = $line . ($item['verdict'] === 'partial' ? ' [close, not exact]' : '');
            }
        }
        return [
            $strengths ? implode("\n", $strengths) : '- (none found)',
            $gaps ? implode("\n", $gaps) : '- (none)',
        ];
    }

    /** A saved match of the current user, or an exception the page shows. */
    private function saved_match(int $id): array {
        $match = $id > 0 ? $this->model->find($id, $this->user_id()) : null;
        if (!$match) {
            http_response_code(404);
            throw new RuntimeException('That saved match was not found.');
        }
        $match['id'] = (int) $match['id'];
        return $match;
    }

    /** The logged-in user's id; null when nobody is (dev). */
    private function user_id(): ?int {
        $id = $this->trongate_tokens->get_user_id();
        return $id ? (int) $id : null;
    }

    /**
     * The llm module on the user's own key. Administrators without a key of
     * their own, and a development copy answering its own machine, use the
     * server's key; anyone else without a key gets a 402 the page answers
     * with a link to the account page.
     */
    private function llm(): Llm {
        $settings = $this->llm_settings();
        if ($settings === false) {
            http_response_code(402);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Add your OpenAI or Anthropic API key on your account page to use the AI match.', 'need_key' => true]);
            die();
        }
        $this->module('llm');
        if ($settings !== null) {
            $this->llm->use_key($settings['provider'], $settings['api_key'], $settings['model']);
        }
        return $this->llm;
    }

    /**
     * The user's key settings, null for the server's settings, or false when
     * there is no key to use.
     */
    private function llm_settings(): array|false|null {
        $user_id = $this->user_id();
        if ($user_id) {
            $this->module('account');
            $settings = $this->account->key_for($user_id);
            if ($settings) {
                return $settings;
            }
        }
        $is_admin = $this->trongate_tokens->attempt_get_valid_token(1) !== false;
        return $is_admin || (!$user_id && $this->dev_machine()) ? null : false;
    }

    private function read_input(): array {
        // A JSON content type can't be sent cross-site without a CORS
        // preflight, which this site never answers: no CSRF on these.
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            http_response_code(415);
            die('Expected a JSON body');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            die('Expected a JSON body');
        }
        return $input;
    }

    private function respond(callable $answer): void {
        header('Content-Type: application/json');
        try {
            echo json_encode($answer());
        } catch (Llm_exception $e) {
            http_response_code(502);
            echo json_encode(['error' => $e->getMessage(), 'retryable' => $e->retryable]);
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(502);
            }
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /** Whether a user of any level is signed in. */
    private function signed_in(): bool {
        return $this->trongate_tokens->attempt_get_valid_token() !== false;
    }

    /**
     * For signed-in users only, since these requests spend model credits,
     * fetch pages or save: anyone else gets a 401 the page answers with a
     * sign-in prompt. A development copy answers its own machine without
     * sign-in; ENV alone doesn't open it, so a server left on 'dev' stays shut.
     */
    private function make_sure_signed_in(): void {
        if ($this->signed_in() || $this->dev_machine()) {
            return;
        }
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Sign in to use the AI match.', 'sign_in' => true]);
        die();
    }

    /** A development copy answering its own machine. */
    private function dev_machine(): bool {
        return strtolower(ENV) === 'dev' && self::from_localhost();
    }

    /**
     * Counts a page fetch for this session, or answers 429 when the session
     * has used its MAX_FETCHES in the last FETCH_WINDOW seconds.
     */
    private function count_fetch(): void {
        $wait = $this->use_allowance('cv_match_fetches', self::MAX_FETCHES, self::FETCH_WINDOW);
        if ($wait !== null) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => "That's a lot of links in a short time. Try again in $wait minute" . ($wait === 1 ? '' : 's') . ', or paste the post text.']);
            die();
        }
    }

    /**
     * Counts one use of a per-session allowance: at most $max uses in the
     * last $window seconds, kept in $_SESSION[$key]. Null when this use fits
     * (and is counted), or the minutes until the next one does.
     */
    private function use_allowance(string $key, int $max, int $window): ?int {
        $now = time();
        $recent = array_values(array_filter(
            (array) ($_SESSION[$key] ?? []),
            fn($at) => is_int($at) && $at > $now - $window
        ));
        if (count($recent) >= $max) {
            return max(1, (int) ceil(($recent[0] + $window - $now) / 60));
        }
        $recent[] = $now;
        $_SESSION[$key] = $recent;
        return null;
    }

    private static function from_localhost(): bool {
        $address = $_SERVER['REMOTE_ADDR'] ?? '';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '';
        return in_array($address, ['127.0.0.1', '::1'], true) && $forwarded === '';
    }

    private static function job_schema(): array {
        // One requirements list, as agreed for job_post_terms: kind, value,
        // required, alt_group (alternatives share a label), min_years and
        // min_level. Soft skills are entries of kind soft_skill.
        $requirement = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value', 'english', 'kind', 'required', 'alt_group', 'min_years', 'min_level'],
            'properties' => [
                'value' => ['type' => 'string'],
                'english' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => ['skill', 'education', 'certificate', 'experience', 'language', 'soft_skill']],
                'required' => ['type' => 'boolean'],
                'alt_group' => ['type' => 'string'],
                'min_years' => ['type' => 'integer'],
                'min_level' => ['type' => 'string'],
            ],
        ];
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['text_en', 'job_title', 'job_title_en', 'company', 'requirements', 'responsibilities'],
            'properties' => [
                'text_en' => ['type' => 'string'],
                'job_title' => ['type' => 'string'],
                'job_title_en' => ['type' => 'string'],
                'company' => ['type' => 'string'],
                'requirements' => ['type' => 'array', 'items' => $requirement],
                'responsibilities' => $strings,
            ],
        ];
    }

    private static function cv_schema(): array {
        $list = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'titles', 'skills', 'languages', 'certifications', 'education', 'experience_years', 'responsibilities', 'text_en'],
            'properties' => [
                'name' => ['type' => 'string'],
                'text_en' => ['type' => 'string'],
                'titles' => $list,
                'skills' => $list,
                'languages' => $list,
                'certifications' => $list,
                'education' => $list,
                'experience_years' => ['type' => 'integer'],
                'responsibilities' => $list,
            ],
        ];
    }

    private static function judge_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['verdicts'],
            'properties' => [
                'verdicts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'verdict', 'evidence', 'reason'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'verdict' => ['type' => 'string', 'enum' => ['met', 'partial', 'missing']],
                            'evidence' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

}

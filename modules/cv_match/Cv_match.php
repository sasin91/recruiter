<?php
require_once __DIR__ . '/Match_prompts.php';
require_once __DIR__ . '/Apply_instructions.php';
require_once __DIR__ . '/Saved_match_row.php';
require_once __DIR__ . '/Score_cursor.php';
require_once __DIR__ . '/Match_result.php';
require_once __DIR__ . '/Match_documents.php';
require_once __DIR__ . '/Batch_row.php';
require_once __DIR__ . '/Resume_comparison.php';
require_once __DIR__ . '/../account/Account_model.php';
require_once __DIR__ . '/../login/Return_path.php';
/**
 * CV match: paste or upload a job post and see how a CV matches it.
 *
 * The page (views/cv_match.php + js/app.js) reads the files and puts the
 * result in place; the work, and the printing of the result
 * (views/result.php, Match_result), happens here. fetch_job reads a job post from a pasted URL
 * with Page_reader, which keeps the fetch to public web pages and hands back
 * plain text. extract_job and extract_cv read the post and the CV with a
 * language model through the llm module, and match() runs the match on the
 * server (_ai_match, which the job scan runs too): Cv_matcher decides what
 * it can with the taxonomy lookup (taxonomy/php/Taxonomy.php), the model
 * judges the rest, and the verdicts are ranked with the legacy ranking
 * weights.
 *
 * match() also saves the match (Cv_match_model: cv_matches + cv_match_items),
 * history and saved list and reopen saved matches, write_application
 * drafts a job application for a saved match and tailor_resume rewrites the
 * CV for its post; both are kept with the match, and pdf exports either as
 * a printable PDF (Pdf_writer). joblog fills in Jobnet's joblog entry for a
 * saved match (Joblog) for the candidate to copy over, with the application,
 * CV and job post as PDFs. application_delivery says how the application
 * is delivered (Application_delivery): the email to send, or the link to the
 * employer's form, with the application and the PDFs.
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
 * server's key. See llm(). The user can switch the key off for tailoring
 * résumés, writing applications or evaluating job posts
 * (Account_model::USES); with evaluation off, extract_job and extract_cv
 * read with Free_reader, so the match is still saved and can have its
 * résumé and application written.
 *
 * Several job posts (up to MAX_JOBS) can be compared with one CV: the page
 * runs each through the same endpoints in turn, so every post is matched and
 * saved exactly like a single one, and lists them by score. Page fetches are
 * capped per session (count_fetch) so a batch of links can't become a crawler.
 *
 * quick_match, score and saved matches also carry apply_instructions
 * (Apply_instructions, read by rule from the post text): how the post asks to
 * be applied to when it wants an email, a subject line, a keyword or answers
 * to questions, often a filter for one-click applications. The page shows it
 * above the requirements and write_application follows it.
 */
class Cv_match extends Trongate {

    // Longest CV or job post quick_match reads, in characters.
    private const MAX_TEXT = 40000;

    // Longest notes the candidate can add for an application or résumé.
    private const MAX_NOTES = 2000;

    // Most job posts the page compares against one CV in one go.
    private const MAX_JOBS = 10;

    // Saved matches the page lists at a time; scrolling to the end of the
    // list loads the next ones (history_more).
    private const PER_PAGE = 25;

    // Most pages fetch_job reads for one session in FETCH_WINDOW seconds:
    // a batch of MAX_JOBS links a few times over, not a crawler.
    private const MAX_FETCHES = 30;
    private const FETCH_WINDOW = 600;

    /**
     * The match page.
     *
     * @return void
     */
    public function index(): void {
        $this->match_page(null);
    }

    /**
     * A saved match's own address, /cv_match/{id}: the match page with that
     * match open, so it can be linked to. Only its owner can open it (saved()
     * checks); signed out, the page asks to sign in and comes back here after.
     *
     * @return void
     */
    public function show(): void {
        $id = (int) segment(3, 'int');
        if (!$this->signed_in()) {
            Return_path::remember("cv_match/$id");
        }
        $this->match_page($id > 0 ? $id : null);
    }

    /** The match page, opening saved match $open_id when given. */
    private function match_page(?int $open_id): void {
        $signed_in = $this->signed_in();
        $data = [
            'base_url' => BASE_URL,
            'signed_in' => $signed_in,
            'ai_ready' => $this->ai_ready(),
            'ai_uses' => $this->ai_uses(),
            'max_jobs' => self::MAX_JOBS,
            'open_id' => $open_id,
        ];
        $data += $signed_in ? $this->saved_matches() : ['matches' => [], 'next' => null, 'after' => ''];
        $this->view('cv_match', $data);
    }

    /**
     * POST {job_text, cv_text}: the free match, for anyone. Both texts are
     * read with Free_reader (no model), matched with the taxonomy and scored
     * like the full match; what the taxonomy can't find in the CV counts as
     * missing. Nothing is saved. When the Laya service is set up, `laya` is
     * its answer on these verdicts, rate limited per visitor (free_laya).
     *
     * The answer is the result as the page shows it (respond_result), with
     * what was found in the CV. job_url, optional, is where the page read the
     * post from.
     *
     * @return void
     */
    public function quick_match(): void {
        $input = $this->read_input();
        $job_url = (string) ($input['job_url'] ?? '');
        $job_text = trim((string) ($input['job_text'] ?? ''));
        $cv_text = trim((string) ($input['cv_text'] ?? ''));
        $this->respond_result($job_url, function () use ($job_text, $cv_text) {
            $match = $this->free_match($job_text, $cv_text);
            $result = Match_result::from($match['job'], $match, $this->ai_ready(), null);
            return $result + ['cv_found' => Match_result::cv_found($match['profile'])];
        });
    }

    /** quick_match's answer. */
    private function free_match(string $job_text, string $cv_text): array {
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
        if (!array_filter($groups)) {
            http_response_code(422);
            throw new RuntimeException('The free match found no skills it knows in this post. The AI match reads requirements written as sentences too.');
        }
        return [
            'job' => array_diff_key($job, ['text_en' => true]),
            'profile' => array_diff_key($profile, ['text_en' => true]),
            'groups' => $groups,
            'verdicts' => (object) $verdicts,
            'result' => Cv_matcher::score($groups, $verdicts),
            'soft_skills' => Cv_matcher::soft_skills($job),
            'apply_instructions' => Apply_instructions::read($job_text),
        ] + $this->free_laya(Cv_matcher::laya_summary($job, $groups, $verdicts));
    }

    /**
     * POST {job, profile, job_text, job_url, cv_name, cv_text}: the match of
     * a read job post (extract_job) with a read CV (extract_cv), run by
     * _ai_match. The answer is the legacy ranking over all verdicts, with the
     * criteria, combined verdicts, soft skills, the Laya summary and, when
     * the Laya service is set up, `laya`: its answer on that summary
     * (Laya_client), or `laya_error`. The match is saved when job_text and
     * cv_text are given; `saved_id` is its id, or null with `save_error` when
     * the database couldn't take it (the score still comes back).
     *
     * Asked for text/html, the answer is the result as the page shows it
     * (respond_result) with the application and résumé panel, carrying
     * the saved id; otherwise JSON, which the page reads when it matches a
     * tailored résumé only to compare its score (resume_comparison).
     *
     * @return void
     */
    public function match(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $job = is_array($input['job'] ?? null) ? $input['job'] : [];
        $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];
        $cv_text = (string) ($input['cv_text'] ?? '');
        $answer = fn() => $this->_ai_match(
            $this->llm_settings('job_evaluation') === false ? null : $this->llm('job_evaluation'),
            $job,
            (string) ($input['job_text'] ?? ''),
            (string) ($input['job_url'] ?? ''),
            $profile,
            (string) ($input['cv_name'] ?? ''),
            $cv_text,
            $this->user_id()
        );
        if (!$this->wants_html()) {
            $this->respond($answer);
            return;
        }
        $this->respond_result((string) ($input['job_url'] ?? ''), function () use ($answer, $job, $profile, $cv_text) {
            $match = $answer();
            $documents = Match_documents::from($match['saved_id'], (string) ($match['save_error'] ?? ''), [], $this->ai_uses(), $match['apply_instructions']);
            return Match_result::from($job, $match, true, $documents, self::laya_input($job, $profile, $cv_text, $match['laya_summary']));
        });
    }

    /** What Laya's English checkpoint reads, shown under the AI match: both texts in English and the verdicts. */
    private static function laya_input(array $job, array $profile, string $cv_text, string $summary): string {
        return (string) json_encode(
            ['job' => $job['text_en'] ?? '', 'cv' => ($profile['text_en'] ?? '') ?: $cv_text, 'summary' => $summary],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /** _ai_match's answer: $verdicts scored, and saved as $user_id's match. */
    private function score_and_save(array $job, array $verdicts, string $job_text, string $job_url, string $cv_name, string $cv_text, ?int $user_id): array {
        require_once __DIR__ . '/Cv_matcher.php';
        $groups = Cv_matcher::criteria($job);
        $verdicts = Cv_matcher::combine($groups, $verdicts);
        $result = Cv_matcher::score($groups, $verdicts);
        $answer = [
            'groups' => $groups,
            'verdicts' => (object) $verdicts,
            'result' => $result,
            'soft_skills' => Cv_matcher::soft_skills($job),
            'apply_instructions' => Apply_instructions::read($job_text),
            'laya_summary' => Cv_matcher::laya_summary($job, $groups, $verdicts),
            'laya' => null,
            'saved_id' => null,
        ];
        // Laya's own reading of the verdicts, when its service is set up
        // (LAYA_URL); the score comes back without it if Laya fails.
        require_once __DIR__ . '/../laya/Laya_client.php';
        $laya = Laya_client::from_env();
        if ($laya !== null) {
            try {
                $answer['laya'] = $laya->decide($answer['laya_summary']);
            } catch (Throwable $e) {
                self::log_failure('Laya', $e);
                $answer['laya_error'] = $e->getMessage();
            }
        }
        if (trim($job_text) !== '' && trim($cv_text) !== '') {
            try {
                $answer['saved_id'] = $this->model->save($user_id, [
                    'job' => $job,
                    'job_url' => $job_url,
                    'job_text' => $job_text,
                    'cv_name' => $cv_name,
                    'cv_text' => $cv_text,
                ], $groups, $verdicts, $result);
            } catch (Throwable $e) {
                self::log_failure('saving a match', $e);
                $answer['save_error'] = "The match wasn't saved: " . $e->getMessage();
            }
        }
        return $answer;
    }

    /**
     * One job post through the match, for the page (match()) and the job
     * scan (job_scan/_auto_match) alike: the requirements of $job decided by
     * the taxonomy against $profile, the rest judged by the model on $llm
     * (or, without one, left missing as the free match leaves them), then
     * scored and saved for $user_id. $job and $profile are read by
     * Match_prompts or by Free_reader. Never a URL.
     */
    public function _ai_match(?Llm $llm, array $job, string $job_text, string $job_url, array $profile, string $cv_name, string $cv_text, ?int $user_id, array $known = []): array {
        require_once __DIR__ . '/Cv_matcher.php';
        $matcher = new Cv_matcher(Taxonomy::load(), $profile, $known);
        $verdicts = [];
        $undecided = [];
        foreach (Cv_matcher::units(Cv_matcher::criteria($job)) as $unit) {
            $verdict = $matcher->decide($unit);
            if ($verdict) {
                $verdicts[$unit['id']] = $verdict;
            } else {
                $undecided[] = ['id' => $unit['id'], 'text' => $unit['text'], 'kind' => $unit['kind']];
            }
        }
        if ($undecided && $llm !== null) {
            $judged = Match_prompts::judge($llm, (string) ($job['job_title'] ?? ''), $undecided, (string) (($profile['text_en'] ?? '') ?: $cv_text), trim((string) ($profile['location'] ?? '')));
            foreach ($judged['verdicts'] ?? [] as $v) {
                $verdicts[$v['id']] = $v + ['by' => 'llm', 'method' => 'judgement'];
            }
        } else {
            foreach ($undecided as $unit) {
                $verdicts[$unit['id']] = $unit['kind'] === 'location' ? self::unweighed_location() : [
                    'verdict' => 'missing',
                    'by' => 'taxonomy',
                    'method' => 'not_found',
                    'evidence' => '',
                    'reason' => 'Not named in the CV. With an AI key on for evaluating job posts, the AI also looks for related experience.',
                ];
            }
        }
        return $this->score_and_save($job, $verdicts, $job_text, $job_url, $cv_name !== '' ? $cv_name : (string) ($profile['name'] ?? ''), $cv_text, $user_id);
    }

    /**
     * A location requirement nobody weighed (no AI key on for evaluating job
     * posts): partial, so it's shown without capping the score.
     */
    private static function unweighed_location(): array {
        return [
            'verdict' => 'partial',
            'by' => 'taxonomy',
            'method' => 'not_found',
            'evidence' => '',
            'reason' => 'Check this yourself: with an AI key on for evaluating job posts, the AI weighs it against where you live.',
        ];
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
     * GET: the first batch of the page's list of saved matches (best
     * first), an HTML fragment (views/history_latest.php). The page polls it
     * with Trongate MX, so matches saved elsewhere (a job search, its API)
     * show up on their own.
     *
     * @return void
     */
    public function history_list(): void {
        $this->make_sure_signed_in();
        $this->view('history_latest', $this->saved_matches(true));
    }

    /**
     * GET history_more?after=...: the next saved matches of the page's list,
     * as rows (views/history.php) ending with the link to the ones after.
     *
     * @return void
     */
    public function history_more(): void {
        $this->make_sure_signed_in();
        $this->view('history', $this->saved_matches());
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
     * GET result/{id}: a saved match as the page shows it, an HTML fragment
     * (respond_result) with its application and résumé panel. A match
     * that can't be read answers JSON {error} like the other endpoints.
     *
     * @return void
     */
    public function result(): void {
        $this->make_sure_signed_in();
        $this->respond_result('', function () {
            $match = $this->saved_match(segment(3, 'int'));
            $documents = Match_documents::from($match['id'], '', Match_documents::written_of($match), $this->ai_uses(), $match['apply_instructions']);
            return Match_result::from_saved($match, true, $documents);
        });
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
     * leaves out). The page gets its box back (respond_written).
     *
     * @return void
     */
    public function write_application(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $id = (int) ($input['id'] ?? 0);
        $notes = self::notes($input);
        // Finish and save even if the phone drops the connection meanwhile;
        // the page picks the result up from the saved match when it's back.
        ignore_user_abort(true);
        $this->respond_written('application', function () use ($id, $notes) {
            $match = $this->saved_match($id);
            [$strengths, $gaps] = self::strengths_and_gaps($match);
            $apply = Apply_instructions::for_prompt($match['apply_instructions']);
            $system = <<<PROMPT
                You write a job application (cover letter) for a candidate, from their CV and the job post. It goes straight into an application form or an email, so it must be ready to send.

                - Write in the language of the job post: a Danish post gets a Danish ansøgning, an English post an English letter.
                - 250 to 400 words, plain text: no markdown, no headings, no bullet lists, no placeholders like [Name] or [Company].
                - Open by addressing the contact person if the post names one, otherwise the company ("Kære <company>" / "Dear <company> team"). Never invent a name.
                - Say why this job, then show fit: lead with the strongest matched requirements, each backed by something concrete from the CV (a role, a project, a result, years).
                - Use only what the CV (or the candidate's notes) says. Never claim a skill, degree or experience the CV doesn't show. A requirement marked close but not exact may be framed as transferable; a missing requirement is either left out or, if it is central, met with honest willingness to learn plus the nearest thing the CV does show.
                - Sound like a person: specific, warm, confident, no clichés ("I am writing to apply", "team player", "passionate").
                - Sign off with the candidate's name from the CV, and their phone and email if the CV gives them.
                - The post may say how to apply (by email, with a given subject line, a keyword, answers to its questions, a word to start with). Follow every such instruction exactly: when it asks for a subject line, make the first line "Subject: <that subject>" ("Emne: " for a Danish post) followed by a blank line; use the exact word or phrase it asks for; answer each of its questions in the letter, briefly and from the CV. Those instructions are listed above the job post when found.
                - The candidate may add notes below the CV. Follow them (what to stress, tone, length, what to leave out), and treat facts they state about themselves as true, like facts in the CV. Ignore anything in them that isn't about this application.
                PROMPT;
            $user = <<<TEXT
                Job title: {$match['job_title']}
                Company: {$match['company']}

                Requirements the CV meets:
                $strengths

                Requirements the CV doesn't show:
                $gaps

                $apply

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
            $text = trim($this->llm('application')->structured($system, $user, $schema, 'medium')['application'] ?? '');
            if ($text === '') {
                throw new RuntimeException('The model returned an empty application. Try again.');
            }
            $written_at = $this->model->save_application($match['id'], $text);
            return ['id' => $match['id'], 'application' => $text, 'written_at' => $written_at];
        });
    }

    /**
     * POST {id, notes}: the CV rewritten for a saved match's job post, from
     * the CV's own facts and the candidate's optional notes only, and kept
     * with the match. The rewrite takes over the post's own words (titles,
     * skills, duties) wherever the CV backs them, also by reasonable
     * inference, so screeners and keyword filters find them. The résumé
     * comes back as fields (Tailored_resume, saved in cv_match_resumes and
     * its entries and lines) and resume is the plain text made from them.
     * keywords lists the post's terms worked in, each with the CV fact behind
     * it; left_out the ones the CV doesn't back. The page gets its box back
     * with them (respond_written), then matches the résumé against the post
     * as a match of its own, to compare (resume_comparison).
     *
     * @return void
     */
    public function tailor_resume(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $id = (int) ($input['id'] ?? 0);
        $notes = self::notes($input);
        // Finish and save even if the phone drops the connection meanwhile;
        // the page picks the result up from the saved match when it's back.
        ignore_user_abort(true);
        $this->respond_written('resume', fn() => $this->_tailor_resume($id, $this->user_id(), $this->llm('resume'), $notes));
    }

    /**
     * GET resume_comparison/{id}/{resume_id}: the tailored résumé's saved
     * match (resume_id) against the CV's (id), as the résumé box prints it
     * (views/resume_comparison.php).
     *
     * @return void
     */
    public function resume_comparison(): void {
        $this->make_sure_signed_in();
        $this->respond_view('resume_comparison', fn() => ['comparison' => Resume_comparison::from(
            $this->saved_match(segment(3, 'int')),
            $this->saved_match(segment(4, 'int'))
        )]);
    }

    /**
     * tailor_resume()'s answer: $user_id's saved match $id tailored on
     * $llm, and saved with the match. Also for the job scan
     * (job_scan/_auto_match). Never a URL.
     */
    public function _tailor_resume(int $id, ?int $user_id, Llm $llm, string $notes = ''): array {
        require_once __DIR__ . '/Tailored_resume.php';
        $match = $this->saved_match_of($id, $user_id);
        [$strengths, $gaps] = self::strengths_and_gaps($match);
        $apply = Apply_instructions::for_prompt($match['apply_instructions']);
        $system = <<<PROMPT
            You tailor a candidate's CV (résumé) to one job post. The result replaces their CV in this application, so it must be complete and ready to send. Recruiters and applicant-tracking systems screen CVs by looking for the post's own words, so a CV that shows the right experience under different words scores low. Your main job is to close that gap honestly.

            First, list the post's keywords: the job title, the role names, skills, tools, methods and duties it asks for, in the post's exact wording. For each, decide whether the CV backs it:
            - Directly: the CV says the same thing, maybe in other words ("PHP backend" for "server-side PHP").
            - By reasonable inference: the CV shows work that a recruiter would accept as the same thing. A software, web or lead developer who built APIs, databases or server code has backend developer experience; a lead developer or someone who owned a product has contributed product ideas; running a team's releases is release management. Name the CV fact you infer it from.
            - Not at all: nothing in the CV comes close. Leave these out.

            Then write the CV:
            - Use every backed keyword in the post's exact wording, where it fits naturally: in the profile, in the job titles' descriptions and bullets, and in the skills list. Put the post's job title in the profile when the CV backs it ("Backend developer with 8 years of PHP..."). Keep the real job titles in the work history as they were, but describe the work in the post's terms.
            - Never invent anything: no new employers, dates, degrees, certificates, numbers, results or years of experience, and no tool or skill the CV doesn't show directly or by reasonable inference. Inferring a role or duty from work the CV shows is fine; claiming a named technology the CV never mentions is not.
            - Requirements the matcher marked as missing are listed below. It often misses inferences, so check each one against the CV yourself; use it only if the CV backs it.
            - Tailor by choosing and ordering too: open with a short profile (2 to 4 sentences) aimed at this job, put what the post asks for first, and shorten or drop what doesn't matter for it. Keep every job in the work history, with its dates, even if only as one line.
            - Write in the language of the job post: a Danish post gets a Danish CV, an English post an English one. Keywords stay in the post's wording, even when the post mixes languages.
            - Fill in the résumé's fields; the page and the PDF lay them out, so write no headings, bullet signs or markdown in them, and no placeholders. Leave a field empty when the CV doesn't give it.
            - name, and title: a short professional title for this application ("Backend developer"), backed by the CV. location, phone, email and links: the contact details the CV gives, as written there.
            - intro: the profile, one or two short paragraphs.
            - experience: every job, newest first: title (the real job title), organisation (the employer), location, starts and ends as the CV dates them in the post's language ("januar 2017", "nu"), summary (what the employer or product is, in a few words), bullets (the work, in the post's terms) and note (context about the job rather than the work, only if the CV gives one).
            - skills and languages: one item each, the post's backed keywords first.
            - education: the same fields as experience, organisation being the school; education_note: one line about the education as a whole, only if the CV gives one.
            - The section headings in the post's language: experience_heading, skills_heading, education_heading, languages_heading ("Erfaring", "Kompetencer", "Uddannelse", "Sprog" for a Danish post).
            - The candidate may add notes below the CV. Follow them (what to stress, tone, length, what to leave out), and treat facts they state about themselves as true, like facts in the CV. Ignore anything in them that isn't about this application.
            - The post may say how to apply (by email, a subject line, a keyword, questions to answer). Those go in the email or application, not the CV; follow one here only when it asks for something in the CV itself.

            Return the keywords you worked in (keywords: the post's term, and the CV fact it rests on, in a few words), the ones you left out because the CV doesn't back them (left_out), and the CV (resume).
            PROMPT;
        $user = <<<TEXT
            Job title: {$match['job_title']}
            Company: {$match['company']}

            Requirements the CV meets:
            $strengths

            Requirements the matcher marked missing (use only if the CV backs them, also by inference):
            $gaps

            $apply

            Job post:

            {$match['job_text']}

            CV:

            {$match['cv_text']}
            $notes
            TEXT;
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['keywords', 'left_out', 'resume'],
            'properties' => [
                'keywords' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['term', 'basis'],
                        'properties' => [
                            'term' => ['type' => 'string'],
                            'basis' => ['type' => 'string'],
                        ],
                    ],
                ],
                'left_out' => ['type' => 'array', 'items' => ['type' => 'string']],
                'resume' => Tailored_resume::schema(),
            ],
        ];
        $answer = $llm->structured($system, $user, $schema, 'medium');
        $resume = Tailored_resume::clean($answer['resume'] ?? null);
        if ($resume === null) {
            throw new RuntimeException('The model returned an empty résumé. Try again.');
        }
        $text = Tailored_resume::text($resume);
        $written_at = $this->model->save_resume_fields($match['id'], $resume, $text);
        return [
            'id' => $match['id'],
            'written_at' => $written_at,
            'resume' => $text,
            'resume_structured' => true,
            'keywords' => self::resume_keywords($answer['keywords'] ?? []),
            'left_out' => array_values(array_filter(array_map(
                fn($term) => is_string($term) ? trim($term) : '',
                is_array($answer['left_out'] ?? null) ? $answer['left_out'] : []
            ))),
        ];
    }

    /** The model's {term, basis} keyword list, cleaned for the page. */
    private static function resume_keywords(mixed $keywords): array {
        $clean = [];
        foreach (is_array($keywords) ? $keywords : [] as $keyword) {
            if (!is_array($keyword)) {
                continue;
            }
            $term = trim((string) ($keyword['term'] ?? ''));
            if ($term !== '') {
                $clean[] = ['term' => $term, 'basis' => trim((string) ($keyword['basis'] ?? ''))];
            }
        }
        return $clean;
    }

    /**
     * POST {id, kind, text}: a saved match's job application (kind
     * application), tailored résumé (kind resume), the CV it was matched with
     * (kind cv) or its job post (kind job) as a PDF download (Pdf_writer). A
     * résumé with fields (Tailored_resume) is laid out from them, so edits
     * to its text aren't in the PDF (the page says so first). Otherwise text is what the page shows, edits included; without it the
     * saved text is used; the CV and the job post are always the saved text.
     * Errors come back as JSON like the other endpoints.
     *
     * @return void
     */
    public function pdf(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $kind = (string) ($input['kind'] ?? '');
        $id = (int) ($input['id'] ?? 0);
        $text = trim((string) ($input['text'] ?? ''));
        try {
            require_once __DIR__ . '/Pdf_writer.php';
            if (!in_array($kind, Pdf_writer::KINDS, true)) {
                http_response_code(422);
                throw new RuntimeException('Choose the application, the résumé, the CV or the job post.');
            }
            $match = $this->saved_match($id);
            if ($text === '' || in_array($kind, ['cv', 'job'], true)) {
                $text = trim((string) $match[['application' => 'application_text', 'resume' => 'resume_text', 'cv' => 'cv_text', 'job' => 'job_text'][$kind]]);
            }
            if ($kind === 'job' && $text !== '' && (string) $match['job_url'] !== '') {
                $text = "{$match['job_url']}\n\n$text";
            }
            if ($text === '') {
                http_response_code(422);
                throw new RuntimeException('There is no text to export yet.');
            }
            if (mb_strlen($text, 'UTF-8') > self::MAX_TEXT) {
                http_response_code(413);
                throw new RuntimeException('That text is too long for a PDF.');
            }
            $title = $kind === 'job'
                ? implode(' · ', array_filter([(string) $match['job_title'], (string) $match['company']])) ?: 'Job post'
                : ['application' => 'Job application', 'resume' => 'Résumé', 'cv' => 'CV'][$kind]
                    . ((string) $match['company'] !== '' ? ": {$match['company']}" : '');
            // A résumé written with fields is laid out from them; the text
            // is only for one written before they existed.
            $fields = $kind === 'resume' ? $this->model->resume_fields($match['id']) : null;
            $pdf = $fields !== null
                ? Pdf_writer::resume_pdf($fields, $title)
                : Pdf_writer::pdf($kind, $text, $title);
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(500);
                self::log_failure('pdf', $e);
            }
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            return;
        }
        $name = Pdf_writer::file_name($kind, (string) $match['job_title'], (string) $match['company']);
        $ascii = preg_replace('/[^\x20-\x7e]|"/', '_', $name);
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($name));
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
    }

    /**
     * GET joblog/{id}: a saved match as an entry for Jobnet's joblog
     * (Joblog): its fields in Jobnet's order, filled in from the match, and
     * which of the three PDFs Jobnet keeps can be downloaded.
     *
     * @return void
     */
    public function joblog(): void {
        $this->make_sure_signed_in();
        $this->respond(function () {
            require_once __DIR__ . '/Joblog.php';
            return Joblog::read($this->saved_match(segment(3, 'int')));
        });
    }

    /**
     * GET joblog_panel/{id}: the same entry as joblog/{id} as the page prints
     * it under the match (views/joblog_panel.php, Joblog_panel).
     *
     * @return void
     */
    public function joblog_panel(): void {
        $this->make_sure_signed_in();
        require_once __DIR__ . '/Joblog_panel.php';
        $this->respond_view('joblog_panel', function () {
            $match = $this->saved_match(segment(3, 'int'));
            return ['panel' => Joblog_panel::from((int) $match['id'], Joblog::read($match))];
        });
    }

    /**
     * GET application_delivery/{id}: the Apply dialog's content for a saved
     * match, an HTML fragment (views/application_delivery.php): how the
     * application is delivered (Application_delivery), by email (recipient,
     * subject, body and mailto link) or on the employer's form (the job
     * post's link), with the PDFs. A match that can't be read answers
     * JSON {error} like the other endpoints.
     *
     * @return void
     */
    public function application_delivery(): void {
        $this->make_sure_signed_in();
        require_once __DIR__ . '/Application_delivery.php';
        try {
            $delivery = Application_delivery::from($this->saved_match(segment(3, 'int')));
        } catch (Throwable $e) {
            $this->respond(fn() => throw $e);
            return;
        }
        $this->view('application_delivery', ['delivery' => $delivery]);
    }

    /**
     * POST {id, status, logged}: how far the candidate is with the job in
     * Jobnet's words (Joblog::SEARCH_STATUSES), and whether they have
     * entered it in their Jobnet joblog. Answers the "In your Jobnet joblog"
     * section as the page prints it (views/joblog_logged.php).
     *
     * @return void
     */
    public function joblog_status(): void {
        $this->make_sure_signed_in();
        $input = $this->read_input();
        $save = function () use ($input) {
            require_once __DIR__ . '/Joblog.php';
            $match = $this->saved_match((int) ($input['id'] ?? 0));
            $status = $input['status'] ?? null;
            if (!is_string($status) || !isset(Joblog::SEARCH_STATUSES[$status])) {
                http_response_code(400);
                throw new RuntimeException('Pick one of Jobnet\'s statuses: ' . implode(', ', Joblog::SEARCH_STATUSES) . '.');
            }
            $row = $this->model->save_integration($match['id'], 'jobnet_joblog', $status, ($input['logged'] ?? false) === true);
            return $row['logged_at'] === null ? null : (int) $row['logged_at'];
        };
        require_once __DIR__ . '/Joblog_panel.php';
        $this->respond_view('joblog_logged', fn() => ['logged' => Joblog_panel::logged($save())]);
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
     * POST {text}: the job post as structured requirements. Read by
     * Free_reader when the user's key is off for evaluating job posts.
     *
     * @return void
     */
    public function extract_job(): void {
        $this->make_sure_signed_in();
        $text = (string) ($this->read_input()['text'] ?? '');
        $this->respond(fn() => $this->llm_settings('job_evaluation') === false
            ? $this->free_reader()->job($text)
            : Match_prompts::read_job($this->llm('job_evaluation'), $text));
    }

    /**
     * POST {text}: the CV as a structured profile. Read by Free_reader when
     * the user's key is off for evaluating job posts.
     *
     * @return void
     */
    public function extract_cv(): void {
        $this->make_sure_signed_in();
        $text = (string) ($this->read_input()['text'] ?? '');
        $this->respond(fn() => $this->llm_settings('job_evaluation') === false
            ? $this->free_reader()->cv($text)
            : Match_prompts::read_cv($this->llm('job_evaluation'), $text));
    }

    private function free_reader(): Free_reader {
        require_once __DIR__ . '/Cv_matcher.php';
        require_once __DIR__ . '/Free_reader.php';
        return new Free_reader(Taxonomy::load());
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
        return $this->saved_match_of($id, $this->user_id());
    }

    /** A saved match of $user_id, or an exception the page shows. */
    private function saved_match_of(int $id, ?int $user_id): array {
        $match = $id > 0 ? $this->model->find($id, $user_id) : null;
        if (!$match) {
            http_response_code(404);
            throw new RuntimeException('That saved match was not found.');
        }
        $match['id'] = (int) $match['id'];
        $match['apply_instructions'] = Apply_instructions::read((string) $match['job_text']);
        return $match;
    }

    /** The logged-in user's id; null when nobody is (dev). */
    private function user_id(): ?int {
        $id = $this->trongate_tokens->get_user_id();
        return $id ? (int) $id : null;
    }

    /**
     * The llm module on the user's own key, for $use (Account_model::USES).
     * Administrators without a key of their own (or with it off for $use),
     * and a development copy answering its own machine, use the server's
     * key; anyone else without a key gets a 402 the page answers with a link
     * to the account page.
     */
    private function llm(string $use): Llm {
        $settings = $this->llm_settings($use);
        if ($settings === false) {
            http_response_code(402);
            header('Content-Type: application/json');
            echo json_encode(['error' => $this->missing_key_message($use), 'need_key' => true]);
            die();
        }
        $this->module('llm');
        if ($settings !== null) {
            $this->llm->use_key($settings['provider'], $settings['api_key'], $settings['model']);
        }
        return $this->llm;
    }

    /**
     * Why there's no key to use. A key that is saved but can't be decrypted
     * (the server's LLM_KEY_SECRET changed or is missing) says so, rather
     * than asking for a key the account page shows as saved.
     */
    private function missing_key_message(string $use): string {
        $user_id = $this->user_id();
        $saved = $user_id ? $this->account->saved($user_id) : null;
        if ($saved !== null && $this->account->key_for($user_id) !== null && !$saved['uses'][$use]) {
            $label = mb_strtolower(Account_model::USES[$use]);
            return "Your API key is switched off for $label. Switch it on on your account page.";
        }
        if ($saved !== null) {
            error_log("cv_match: the saved API key of user $user_id can't be decrypted (LLM_KEY_SECRET missing or changed)");
            return "Your saved API key can't be read on this server anymore. Save it again on your account page.";
        }
        return 'Add your OpenAI or Anthropic API key on your account page to use the AI match.';
    }

    /**
     * The user's key settings for $use, null for the server's settings, or
     * false when there is no key to use for it.
     */
    private function llm_settings(string $use): array|false|null {
        $user_id = $this->user_id();
        if ($user_id) {
            $this->module('account');
            $settings = $this->account->key_for($user_id);
            if ($settings && $settings['uses'][$use]) {
                return $settings;
            }
        }
        $is_admin = $this->trongate_tokens->attempt_get_valid_token(1) !== false;
        return $is_admin || (!$user_id && $this->dev_machine()) ? null : false;
    }

    /** Whether the page runs the AI match: a key to use for something (dev: the server's). */
    private function ai_ready(): bool {
        return ($this->signed_in() || $this->dev_machine()) && in_array(true, $this->ai_uses(), true);
    }

    /** use => whether there's a key to use for it (llm_settings), for the page. */
    private function ai_uses(): array {
        $uses = [];
        foreach (array_keys(Account_model::USES) as $use) {
            $uses[$use] = $this->llm_settings($use) !== false;
        }
        return $uses;
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

    /** Whether the page asked for HTML (the result as it shows it) rather than JSON. */
    private function wants_html(): bool {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html');
    }

    /**
     * The view data for a batch of the user's saved matches, best first,
     * from ?after=; with $first, the first batch whatever ?after= says.
     */
    private function saved_matches(bool $first = false): array {
        $after = !$first && Score_cursor::place((string) ($_GET['after'] ?? '')) !== null ? (string) $_GET['after'] : '';
        $found = $this->model->best_first($this->user_id(), self::PER_PAGE, $after);
        return [
            'matches' => array_map([Saved_match_row::class, 'from'], $found['rows']),
            'after' => $after,
            'next' => $found['next'] === null ? null : http_build_query(['after' => $found['next']]),
        ];
    }

    /**
     * Prints the Match_result $result() gives: as the open match
     * (views/result.php), or with ?view=row as a row of the ranked jobs
     * (views/batch_row.php, Batch_row), whose post was read from $job_url
     * (a saved match knows its own).
     */
    private function respond_result(string $job_url, callable $result): void {
        if (($_GET['view'] ?? '') !== 'row') {
            $this->respond_view('result', fn() => ['result' => $result()]);
            return;
        }
        $this->respond_view('batch_row', function () use ($job_url, $result) {
            $made = $result();
            return ['result' => $made, 'row' => Batch_row::from($made, $job_url !== '' ? $job_url : $made['job_url'])];
        });
    }

    /**
     * Answers what $write() wrote for a saved match ({id, ...}) with the
     * $kind's document box as the page prints it (views/document_box.php),
     * read back from the saved match, with the résumé's keywords.
     */
    private function respond_written(string $kind, callable $write): void {
        $this->respond_view('document_box', function () use ($kind, $write) {
            $written = $write();
            $match = $this->saved_match((int) $written['id']);
            $box = Document_box::from($kind, (int) $match['id'], Match_documents::written_of($match)[$kind], $this->ai_uses()[$kind], $match['apply_instructions']);
            return ['box' => Document_box::with_keywords($box, $written['keywords'] ?? [], $written['left_out'] ?? [])];
        });
    }

    /**
     * Prints $view with the data $data() gives; when that throws, the
     * answer is JSON {error} with a status, as respond() gives it.
     */
    private function respond_view(string $view, callable $data): void {
        try {
            $data = $data();
        } catch (Throwable $e) {
            $this->respond(fn() => throw $e);
            return;
        }
        $this->view($view, $data);
    }

    private function respond(callable $answer): void {
        header('Content-Type: application/json');
        try {
            echo json_encode($answer());
        } catch (Llm_exception $e) {
            self::log_failure((string) segment(2), $e);
            http_response_code(502);
            echo json_encode(['error' => $e->getMessage(), 'retryable' => $e->retryable]);
        } catch (Throwable $e) {
            // A status set before the throw (400s) is the user's input, not
            // a failure worth logging.
            if (http_response_code() < 400) {
                http_response_code(502);
                self::log_failure((string) segment(2), $e);
            }
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Writes a failure the user was told about to the server log as well,
     * so it can be found after they've left the page.
     */
    private static function log_failure(string $where, Throwable $e): void {
        error_log("cv_match ($where): " . get_class($e) . ': ' . $e->getMessage());
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

    /**
     * Laya's answer for the free match: ['laya' => answer], or
     * ['laya_error' => why] when it failed or this visitor used its calls
     * (Laya_limit), or nothing when there is no Laya service. Free to run
     * (Laya is our own model), but one pod, so each address gets
     * Laya_limit::MAX calls per Laya_limit::WINDOW seconds.
     */
    private function free_laya(string $summary): array {
        require_once __DIR__ . '/../laya/Laya_client.php';
        require_once __DIR__ . '/../laya/Laya_limit.php';
        $laya = Laya_client::from_env();
        if ($laya === null) {
            return [];
        }
        $wait = (new Laya_limit())->take(Laya_limit::client($_SERVER));
        if ($wait > 0) {
            $minutes = (int) ceil($wait / 60);
            return ['laya_error' => "Laya has answered a lot of matches from you in a short time. It answers again in $minutes minute" . ($minutes === 1 ? '' : 's') . '.'];
        }
        try {
            return ['laya' => $laya->decide($summary)];
        } catch (Throwable $e) {
            self::log_failure('Laya', $e);
            return ['laya_error' => $e->getMessage()];
        }
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
        $now = time();
        $recent = array_values(array_filter(
            (array) ($_SESSION['cv_match_fetches'] ?? []),
            fn($at) => is_int($at) && $at > $now - self::FETCH_WINDOW
        ));
        if (count($recent) >= self::MAX_FETCHES) {
            $wait = (int) ceil(($recent[0] + self::FETCH_WINDOW - $now) / 60);
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => "That's a lot of links in a short time. Try again in $wait minute" . ($wait === 1 ? '' : 's') . ', or paste the post text.']);
            die();
        }
        $recent[] = $now;
        $_SESSION['cv_match_fetches'] = $recent;
    }

    private static function from_localhost(): bool {
        $address = $_SERVER['REMOTE_ADDR'] ?? '';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '';
        return in_array($address, ['127.0.0.1', '::1'], true) && $forwarded === '';
    }

}

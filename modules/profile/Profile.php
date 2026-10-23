<?php
require_once __DIR__ . '/Profile_model.php';
require_once __DIR__ . '/Profile_fields.php';
require_once __DIR__ . '/Profile_page.php';

/**
 * The signed-in candidate's profile (/profile): the one source their job
 * applications and tailored résumés are written from (_text_for_user, used
 * by cv_match). A CV fills it in (submit_fill, one model call on their own
 * key); after that they edit it here, adding what the CV leaves out, like
 * projects of their own. Administrators get a candidates row on their first
 * save, as with profile links (account/_candidate_id).
 */
class Profile extends Trongate {

    /** An entry form posted with errors, printed again in its place (Profile_page::entry_form). */
    private ?array $posted_entry = null;

    /**
     * The profile page.
     *
     * @return void
     */
    public function index(): void {
        $candidate_id = $this->candidate_id();
        $data = Profile_page::from(
            $candidate_id !== null ? $this->model->candidate($candidate_id) : [],
            $candidate_id !== null ? $this->model->header($candidate_id) : null,
            $candidate_id !== null ? $this->model->entries($candidate_id) : [],
            $candidate_id !== null ? $this->model->lines($candidate_id) : [],
            $this->posted_entry,
            $this->model->latest_matched_cv($this->user_id())
        );
        $this->view('profile', ['page' => $data]);
    }

    /**
     * POST {cv_text, cv_name, replace}: the CV read into the profile by the
     * model, replacing the whole profile; replace must be ticked when there
     * is one already.
     *
     * @return void
     */
    public function submit_fill(): void {
        $candidate_id = $this->candidate_id(true);
        $this->validation->set_rules('cv_text', 'CV', 'required|min_length[100]|max_length[60000]');
        if ($this->validation->run() !== true) {
            $this->index();
            return;
        }
        if ($this->model->header($candidate_id) !== null && post('replace') !== '1') {
            set_flashdata('Tick "Replace my profile" to fill it in again from this CV.');
            redirect('profile');
        }
        $llm = $this->llm();
        if ($llm === null) {
            set_flashdata('Add your OpenAI or Anthropic API key on your account page first; reading the CV runs on it.');
            redirect('profile');
        }
        try {
            $profile = Profile_fields::from_cv($this->read_cv($llm, post('cv_text')));
            if ($profile === null) {
                throw new RuntimeException('No jobs, projects or education were found in that CV. Check that the whole CV was pasted and try again.');
            }
            $this->model->replace_from_cv($candidate_id, $profile, trim(post('cv_name', true)));
            set_flashdata('Your profile is filled in from the CV. Check it below, and add what the CV leaves out.');
        } catch (Throwable $e) {
            error_log('profile/submit_fill: ' . $e->getMessage());
            set_flashdata($e instanceof RuntimeException ? $e->getMessage() : 'The CV could not be read into your profile. Try again.');
        }
        redirect('profile');
    }

    /**
     * POST {name, phone, title, location, about}: the profile's header.
     *
     * @return void
     */
    public function submit_header(): void {
        $candidate_id = $this->candidate_id(true);
        if ($this->validation->run() === true) {
            $this->model->save_header($candidate_id, Profile_fields::header_from_form($_POST));
            set_flashdata('Saved.');
        }
        redirect('profile#about-you');
    }

    /**
     * POST {section, title, organisation, location, url, started, ended,
     * summary, bullets, note} to profile/submit_entry/{id}, or without an
     * id for a new entry. With errors, the page shows the form again.
     *
     * @return void
     */
    public function submit_entry(): void {
        $candidate_id = $this->candidate_id(true);
        if ($this->validation->run() !== true) {
            redirect('profile');
        }
        $entry_id = segment(3, 'int') ?: null;
        $read = Profile_fields::entry_from_form($_POST);
        if ($read['errors']) {
            $this->posted_entry = Profile_page::entry_form(['id' => $entry_id] + $read['entry'], $read['errors']);
            $this->index();
            return;
        }
        $saved_id = $this->model->save_entry($candidate_id, $entry_id, $read['entry']);
        set_flashdata($saved_id !== null ? 'Saved.' : 'That entry was not found.');
        redirect('profile#' . $read['entry']['section']);
    }

    /**
     * POST to profile/submit_delete_entry/{id}: deletes the entry.
     *
     * @return void
     */
    public function submit_delete_entry(): void {
        $candidate_id = $this->candidate_id(true);
        if ($this->validation->run() === true) {
            $deleted = $this->model->delete_entry($candidate_id, segment(3, 'int'));
            set_flashdata($deleted ? 'Deleted.' : 'That entry was not found.');
        }
        redirect('profile');
    }

    /**
     * POST {skill, language, certificate}: the lists, one item per line.
     *
     * @return void
     */
    public function submit_lists(): void {
        $candidate_id = $this->candidate_id(true);
        if ($this->validation->run() === true) {
            $lists = [];
            foreach (array_keys(Profile_fields::LISTS) as $kind) {
                $lists[$kind] = Profile_fields::list_from_form((string) post($kind));
            }
            $this->model->save_lists($candidate_id, $lists);
            set_flashdata('Saved.');
        }
        redirect('profile#lists');
    }

    /**
     * The profile of the user $user_id as plain text (Profile_fields::text),
     * or null when they have none. For cv_match, never a URL.
     */
    public function _text_for_user(int $user_id): ?string {
        $this->module('account');
        $candidate_id = $this->account->_candidate_id($user_id);
        $header = $candidate_id !== null ? $this->model->header($candidate_id) : null;
        if ($header === null) {
            return null;
        }
        return Profile_fields::text(
            $this->model->candidate($candidate_id),
            $header,
            $this->model->entries($candidate_id),
            $this->model->lines($candidate_id),
            $this->account->profile_links($user_id)
        );
    }

    /** What the model reads $cv into (Profile_fields::schema). */
    private function read_cv(Llm $llm, string $cv): array {
        $system = <<<PROMPT
            You read a candidate's CV into their profile, which their job applications and résumés will be written from. Copy what the CV says; never add, improve or guess anything.

            - name and phone: the candidate's, as the CV gives them.
            - title: their professional title as the CV gives it; location: where they live, as written ("Slagelse, 4200").
            - about: the CV's own profile or summary paragraphs, word for word; empty when it has none.
            - entries: every job (section experience), every project or product of the candidate's own, side projects and open source included (section project), and every education (section education), in the CV's order.
              - title: the job title, the project's name or the education; organisation: the employer or school, empty for an own project; location as written; url: a project's link if the CV gives one.
              - started and ended as "YYYY-MM" (a year alone as "YYYY"); ended empty when it is ongoing ("now", "present", "nu").
              - summary: the line about what the employer or project is ("Job & candidate matchmaking platform"); bullets: the CV's bullets for it, word for word; note: context about it, like why a role ended.
            - skills, languages and certificates: as the CV lists them, one item each.
            Keep the CV's language.
            PROMPT;
        return $llm->structured($system, "CV:\n\n$cv", Profile_fields::schema(), 'low');
    }

    /** The llm module on the user's own key; the server's for an administrator without one; null otherwise. */
    private function llm(): ?Llm {
        $this->module('account');
        $settings = $this->account->key_for($this->user_id());
        $this->module('llm');
        if ($settings !== null) {
            $this->llm->use_key($settings['provider'], $settings['api_key'], $settings['model']);
            return $this->llm;
        }
        return $this->is_admin() ? $this->llm : null;
    }

    /**
     * The signed-in candidate's id; null for an administrator who isn't one
     * yet, unless $make (they become one). Anyone else goes to their account page.
     */
    private function candidate_id(bool $make = false): ?int {
        $this->module('account');
        $candidate_id = $this->account->_candidate_id($this->user_id(), $make);
        if ($candidate_id === null && !$this->is_admin()) {
            set_flashdata('The profile is for candidates.');
            redirect('account');
        }
        return $candidate_id;
    }

    private function is_admin(): bool {
        return $this->trongate_tokens->attempt_get_valid_token(1) !== false;
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

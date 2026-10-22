<?php
require_once __DIR__ . '/Account_model.php';
require_once __DIR__ . '/Profile_links.php';
/**
 * The signed-in user's account page: their own OpenAI or Anthropic API key,
 * which the AI features of the CV match run on (Account_model, Key_vault).
 * The key is stored encrypted and never shown again; only its last 4
 * characters are. Its uses (Account_model::USES: tailoring résumés, writing
 * applications, evaluating job posts) can each be switched off.
 * A candidate also keeps their profile links here (Profile_links,
 * candidate_profile_links), offered to copy when they apply.
 */
class Account extends Trongate {

    /** What the profile links form posted, printed again when it had errors. */
    private ?array $posted_links = null;
    private ?array $link_errors = null;

    /**
     * The key form. Not signed in: to the sign-in page (which never sends
     * signed-out visitors back here, so the two can't loop).
     *
     * @return void
     */
    public function index(): void {
        $user_id = $this->user_id();
        $saved = $this->model->saved($user_id);
        $vault_ready = Account_model::vault() !== null;
        $candidate_id = $this->model->candidate_id($user_id);
        $data = [
            'saved' => $saved,
            // Saved, but not decryptable with the server's current secret.
            'unreadable' => $saved !== null && $vault_ready && $this->model->key_for($user_id) === null,
            'providers' => Account_model::PROVIDERS,
            'uses' => Account_model::USES,
            'vault_ready' => $vault_ready,
            'is_admin' => $this->trongate_tokens->attempt_get_valid_token(1) !== false,
            'provider' => post('provider', true),
            'model' => post('model', true),
            'is_candidate' => $candidate_id !== null,
            'links' => $this->posted_links ?? Profile_links::form_rows($candidate_id !== null ? $this->model->profile_links($candidate_id) : []),
            'link_errors' => $this->link_errors ?? [],
        ];
        $this->view('account', $data);
    }

    /**
     * POST {provider, model, api_key}: saves the key, replacing any saved one.
     *
     * @return void
     */
    public function submit_key(): void {
        $user_id = $this->user_id();
        $this->validation->set_rules('provider', 'provider', 'required|callback_known_provider');
        $this->validation->set_rules('model', 'model', 'max_length[64]|callback_model_name');
        $this->validation->set_rules('api_key', 'API key', 'required|min_length[20]|max_length[300]|callback_key_shape');
        if ($this->validation->run() !== true) {
            $this->index();
            return;
        }
        try {
            $this->model->save($user_id, post('provider', true), trim(post('model', true)), trim(post('api_key')));
            set_flashdata('Your key is saved. The AI match now runs on it.');
        } catch (RuntimeException $e) {
            set_flashdata($e->getMessage());
        }
        redirect('account');
    }

    /**
     * POST {use_for_resume, use_for_application, use_for_job_evaluation}:
     * switches the saved key's uses; a box left unticked is off.
     *
     * @return void
     */
    public function submit_uses(): void {
        $user_id = $this->user_id();
        if ($this->validation->run() === true) {
            $on = array_filter(array_keys(Account_model::USES), fn(string $use) => post("use_for_$use") === '1');
            $this->model->save_uses($user_id, array_values($on));
            set_flashdata('Saved where your key is used.');
        }
        redirect('account');
    }

    /**
     * POST: deletes the saved key.
     *
     * @return void
     */
    public function submit_delete(): void {
        $user_id = $this->user_id();
        if ($this->validation->run() === true) {
            $this->model->delete_key($user_id);
            set_flashdata('Your key is deleted.');
        }
        redirect('account');
    }

    /**
     * POST {label[], url[]}: replaces the candidate's profile links. A row
     * without a URL is dropped; with errors nothing is saved and the form
     * shows them. Only candidates have profile links.
     *
     * @return void
     */
    public function submit_links(): void {
        $candidate_id = $this->model->candidate_id($this->user_id());
        if ($candidate_id === null || $this->validation->run() !== true) {
            redirect('account');
        }
        $labels = (array) post('label');
        $urls = (array) post('url');
        $read = Profile_links::read($labels, $urls);
        if ($read['errors']) {
            $this->posted_links = Profile_links::posted_rows($labels, $urls);
            $this->link_errors = $read['errors'];
            $this->index();
            return;
        }
        try {
            $this->model->save_profile_links($candidate_id, $read['links']);
        } catch (Throwable $e) {
            error_log('account/submit_links: ' . $e->getMessage());
            $this->posted_links = Profile_links::posted_rows($labels, $urls);
            $this->link_errors = [Profile_links::NOT_SAVED];
            $this->index();
            return;
        }
        set_flashdata('Your profile links are saved.');
        redirect('account#profile-links');
    }

    /**
     * The profile links ([['label', 'url'], ...]) of the candidate signed in
     * as $user_id, for the Apply dialog (cv_match); none for anyone who
     * isn't a candidate. Never a URL.
     */
    public function profile_links(int $user_id): array {
        block_url('account/profile_links');
        $candidate_id = $this->model->candidate_id($user_id);
        return $candidate_id !== null ? $this->model->profile_links($candidate_id) : [];
    }

    /**
     * The user's own key for the llm module ({ provider, model, api_key }),
     * or null. For other modules (cv_match), never a URL.
     */
    public function key_for(int $user_id): ?array {
        block_url('account/key_for');
        return $this->model->key_for($user_id);
    }

    /**
     * The saved key's details without the key (Account_model::saved()), or
     * null. For other modules (cv_match), never a URL.
     */
    public function saved(int $user_id): ?array {
        block_url('account/saved');
        return $this->model->saved($user_id);
    }

    /** Validation callback. */
    public function known_provider(string $provider): string|bool {
        block_url('account/known_provider');
        return isset(Account_model::PROVIDERS[$provider]) ? true : 'Pick OpenAI or Anthropic.';
    }

    /** Validation callback: empty, or a plain model id ("gpt-5.4-mini"). */
    public function model_name(string $model): string|bool {
        block_url('account/model_name');
        return preg_match('/^[A-Za-z0-9._:\/-]*$/', trim($model)) ? true : 'A model name is letters, digits, dots and dashes, like gpt-5.4-mini.';
    }

    /** Validation callback: looks like a key of the chosen provider. */
    public function key_shape(string $key): string|bool {
        block_url('account/key_shape');
        $key = trim($key);
        if (preg_match('/\s/', $key)) {
            return 'The {label} has spaces in it. Paste only the key.';
        }
        $prefix = post('provider', true) === 'anthropic' ? 'sk-ant-' : 'sk-';
        return str_starts_with($key, $prefix) ? true : "That doesn't look like a key of this provider; it should start with $prefix.";
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

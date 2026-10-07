<?php
require_once __DIR__ . '/Account_model.php';
/**
 * The signed-in user's account page: their own OpenAI or Anthropic API key,
 * which the AI features of the CV match run on (Account_model, Key_vault).
 * The key is stored encrypted and never shown again; only its last 4
 * characters are.
 */
class Account extends Trongate {

    /**
     * The key form. Not signed in: to the sign-in page (which never sends
     * signed-out visitors back here, so the two can't loop).
     *
     * @return void
     */
    public function index(): void {
        $user_id = $this->user_id();
        $data = [
            'saved' => $this->model->saved($user_id),
            'providers' => Account_model::PROVIDERS,
            'vault_ready' => Account_model::vault() !== null,
            'is_admin' => $this->trongate_tokens->attempt_get_valid_token(1) !== false,
            'provider' => post('provider', true),
            'model' => post('model', true),
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
     * The user's own key for the llm module ({ provider, model, api_key }),
     * or null. For other modules (cv_match), never a URL.
     */
    public function key_for(int $user_id): ?array {
        block_url('account/key_for');
        return $this->model->key_for($user_id);
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

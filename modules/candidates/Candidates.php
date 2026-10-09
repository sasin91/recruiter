<?php
require_once __DIR__ . '/../login/Return_path.php';
/**
 * Candidates (user level 3): sign-up for the public site. A candidate is a
 * trongate_users row plus its candidates row; signing in goes through the
 * login module (config/login.php, level 3, /sign-in).
 */
class Candidates extends Trongate {

    /**
     * The sign-up form. Someone already signed in goes straight to the CV match.
     *
     * @return void
     */
    public function register(): void {
        if ($this->trongate_tokens->attempt_get_valid_token() !== false) {
            redirect('cv_match');
            return;
        }
        $data = [
            'form_location' => BASE_URL . 'candidates/submit_register',
            'name' => post('name', true),
            'email' => post('email', true),
        ];
        $this->view('register', $data);
    }

    /**
     * POST: creates the candidate, signs them in and sends them back to the
     * job they were applying for, or else to the account page, where they add
     * the API key the AI match runs on.
     *
     * @return void
     */
    public function submit_register(): void {
        $this->validation->set_rules('name', 'name', 'required|max_length[255]');
        $this->validation->set_rules('email', 'email', 'required|valid_email|max_length[255]|callback_email_free');
        $this->validation->set_rules('password', 'password', 'required|min_length[8]|max_length[72]');
        $this->validation->set_rules('password_repeat', 'repeated password', 'required|matches[password]');

        if ($this->validation->run() !== true) {
            $this->register();
            return;
        }

        $this->module('login');
        $user_id = $this->model->create(
            post('name', true),
            strtolower(post('email', true)),
            $this->login->hash_password(post('password'))
        );

        // Signed in like login/submit_login does it, for this browser session.
        $this->module('trongate_tokens');
        $_SESSION['trongatetoken'] = $this->trongate_tokens->generate_token(['user_id' => $user_id]);
        redirect(Return_path::take('account'));
    }

    /**
     * Validation callback: no candidate has this email yet.
     *
     * @param string $email
     * @return string|bool
     */
    public function email_free(string $email): string|bool {
        block_url('candidates/email_free');
        return $this->model->email_taken(strtolower(trim($email)))
            ? 'There is already an account with that email. Sign in instead.'
            : true;
    }

}

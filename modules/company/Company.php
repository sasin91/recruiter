<?php
require_once __DIR__ . '/Company_model.php';
/**
 * The company side's accounts: a company signs up (company-sign-up), its
 * owners invite colleagues (company/members) and keep the company's settings
 * and AI key (company/settings). Staff sign in at company-sign-in (user
 * level 2).
 *
 * Invites are links the owner copies and sends: the site doesn't send email
 * yet. An invited colleague opens company/join/{token} and chooses a password.
 */
class Company extends Trongate {

    private const USER_LEVEL = 2;

    /**
     * The company's home page. Not signed in as staff: to the staff sign-in.
     *
     * @return void
     */
    public function index(): void {
        $member = $this->member();
        $data = [
            'member' => $member,
            'members' => $this->model->members((int) $member['company_id']),
            'has_key' => $this->model->saved_key((int) $member['company_id']) !== null,
        ];
        $this->view('home', $data);
    }

    // -----------------------------------------------------------------
    // Sign-up
    // -----------------------------------------------------------------

    /**
     * The sign-up form. Someone already signed in as staff goes home.
     *
     * @return void
     */
    public function register(): void {
        if ($this->trongate_tokens->attempt_get_valid_token(self::USER_LEVEL) !== false) {
            redirect('company');
            return;
        }
        $data = [
            'form_location' => BASE_URL . 'company/submit_register',
            'company_name' => post('company_name', true),
            'cvr' => post('cvr', true),
            'name' => post('name', true),
            'email' => post('email', true),
        ];
        $this->view('register', $data);
    }

    /**
     * POST: creates the company with you as its owner and signs you in.
     *
     * @return void
     */
    public function submit_register(): void {
        $this->validation->set_rules('company_name', 'company name', 'required|max_length[255]');
        $this->validation->set_rules('cvr', 'CVR number', 'required|max_length[16]|callback_cvr_free');
        $this->validation->set_rules('name', 'name', 'required|max_length[255]');
        $this->validation->set_rules('email', 'work email', 'required|valid_email|max_length[255]|callback_email_free');
        $this->validation->set_rules('password', 'password', 'required|min_length[8]|max_length[72]');
        $this->validation->set_rules('password_repeat', 'repeated password', 'required|matches[password]');

        if ($this->validation->run() !== true) {
            $this->register();
            return;
        }

        $this->module('login');
        $user_id = $this->model->create(
            trim(post('company_name', true)),
            Company_rules::cvr(post('cvr', true)),
            trim(post('name', true)),
            strtolower(trim(post('email', true))),
            $this->login->hash_password(post('password'))
        );
        $this->sign_in($user_id);
        set_flashdata('Your company is set up.');
        redirect('company');
    }

    // -----------------------------------------------------------------
    // Members
    // -----------------------------------------------------------------

    /**
     * The roster, and for owners the invite form and member changes.
     *
     * @return void
     */
    public function members(): void {
        $member = $this->member();
        $data = [
            'member' => $member,
            'members' => $this->model->members((int) $member['company_id']),
            'roles' => Company_rules::ROLES,
            'name' => post('name', true),
            'email' => post('email', true),
            'role' => post('role', true) ?: 'recruiter',
            'invite_link' => $_SESSION['company_invite_link'] ?? null,
        ];
        unset($_SESSION['company_invite_link']);
        $this->view('members', $data);
    }

    /**
     * POST {name, email, role}: invites a colleague. The page then shows the
     * link to send them.
     *
     * @return void
     */
    public function submit_invite(): void {
        $member = $this->owner();
        $this->validation->set_rules('name', 'name', 'required|max_length[255]');
        $this->validation->set_rules('email', 'work email', 'required|valid_email|max_length[255]|callback_email_free');
        $this->validation->set_rules('role', 'role', 'required|callback_known_role');
        if ($this->validation->run() !== true) {
            $this->members();
            return;
        }
        $email = strtolower(trim(post('email', true)));
        $token = $this->model->invite((int) $member['company_id'], trim(post('name', true)), $email, post('role', true));
        $_SESSION['company_invite_link'] = ['email' => $email, 'url' => BASE_URL . 'company/join/' . $token];
        redirect('company/members');
    }

    /**
     * POST {member_id, change}: deactivate, activate, make_owner,
     * make_recruiter, or revoke (an invite nobody has accepted).
     *
     * @return void
     */
    public function submit_member_change(): void {
        $actor = $this->owner();
        if ($this->validation->run() !== true) {
            redirect('company/members');
            return;
        }
        $company_id = (int) $actor['company_id'];
        $target = $this->model->member($company_id, (int) post('member_id'));
        $change = post('change', true);
        if ($target === null) {
            set_flashdata("That member isn't in your company.");
        } elseif ($change === 'revoke') {
            if ($target['invite_token'] === null) {
                set_flashdata("{$target['name']} has already joined; deactivate them instead.");
            } else {
                $this->model->revoke_invite($company_id, (int) $target['id']);
                set_flashdata("The invite for {$target['email']} is withdrawn.");
            }
        } else {
            $refusal = Company_rules::refuse_change($actor, $target, $change, $this->model->active_owners($company_id));
            if ($refusal !== null) {
                set_flashdata($refusal);
            } else {
                $this->model->change_member($company_id, (int) $target['id'], $change);
                set_flashdata("{$target['name']} is updated.");
            }
        }
        redirect('company/members');
    }

    /**
     * An invited colleague chooses a password. Unknown or used links get a
     * plain "no longer valid" page.
     *
     * @return void
     */
    public function join(): void {
        $token = segment(3);
        $invitation = $this->model->invitation($token);
        $data = [
            'invitation' => $invitation,
            'form_location' => BASE_URL . 'company/submit_join/' . $token,
        ];
        $this->view('join', $data);
    }

    /**
     * POST {password, password_repeat}: accepts the invite and signs in.
     *
     * @return void
     */
    public function submit_join(): void {
        $token = segment(3);
        $invitation = $this->model->invitation($token);
        if ($invitation === null) {
            redirect('company/join/' . $token);
            return;
        }
        $this->validation->set_rules('password', 'password', 'required|min_length[8]|max_length[72]');
        $this->validation->set_rules('password_repeat', 'repeated password', 'required|matches[password]');
        if ($this->validation->run() !== true) {
            $this->join();
            return;
        }
        $this->module('login');
        $this->model->accept_invite((int) $invitation['id'], $this->login->hash_password(post('password')));
        $this->sign_in((int) $invitation['trongate_user_id']);
        set_flashdata("Welcome to {$invitation['company_name']}.");
        redirect('company');
    }

    // -----------------------------------------------------------------
    // Settings and the company's AI key (owners)
    // -----------------------------------------------------------------

    /**
     * Company name, the contact email candidates see, and the AI key.
     *
     * @return void
     */
    public function settings(): void {
        $member = $this->owner();
        $company_id = (int) $member['company_id'];
        $data = [
            'member' => $member,
            'company_name' => post('company_name', true) ?: $member['company_name'],
            'contact_email' => post('contact_email', true) ?: ($member['contact_email'] ?? ''),
            'saved' => $this->model->saved_key($company_id),
            'providers' => Account_model::PROVIDERS,
            'vault_ready' => Account_model::vault() !== null,
            'provider' => post('provider', true),
            'model' => post('model', true),
        ];
        $this->view('settings', $data);
    }

    /**
     * POST {company_name, contact_email}.
     *
     * @return void
     */
    public function submit_settings(): void {
        $member = $this->owner();
        $this->validation->set_rules('company_name', 'company name', 'required|max_length[255]');
        $this->validation->set_rules('contact_email', 'contact email', 'required|valid_email|max_length[255]');
        if ($this->validation->run() !== true) {
            $this->settings();
            return;
        }
        $this->model->update_settings((int) $member['company_id'], trim(post('company_name', true)), strtolower(trim(post('contact_email', true))));
        set_flashdata('Saved.');
        redirect('company/settings');
    }

    /**
     * POST {provider, model, api_key}: saves the company's key, replacing any
     * saved one. Checked like a personal key (see the account module).
     *
     * @return void
     */
    public function submit_key(): void {
        $member = $this->owner();
        $this->module('account');
        $this->validation->set_rules('provider', 'provider', 'required|callback_known_provider');
        $this->validation->set_rules('model', 'model', 'max_length[64]|callback_model_name');
        $this->validation->set_rules('api_key', 'API key', 'required|min_length[20]|max_length[300]|callback_key_shape');
        if ($this->validation->run() !== true) {
            $this->settings();
            return;
        }
        try {
            $this->model->save_key((int) $member['company_id'], post('provider', true), trim(post('model', true)), trim(post('api_key')));
            set_flashdata("The company's key is saved.");
        } catch (RuntimeException $e) {
            set_flashdata($e->getMessage());
        }
        redirect('company/settings');
    }

    /**
     * POST: deletes the company's key.
     *
     * @return void
     */
    public function submit_delete_key(): void {
        $member = $this->owner();
        if ($this->validation->run() === true) {
            $this->model->delete_key((int) $member['company_id']);
            set_flashdata("The company's key is deleted.");
        }
        redirect('company/settings');
    }

    /**
     * The company's own key for the llm module ({ provider, model, api_key }),
     * or null. For other modules, never a URL.
     */
    public function key_for(int $company_id): ?array {
        block_url('company/key_for');
        return $this->model->key_for($company_id);
    }

    // -----------------------------------------------------------------
    // Validation callbacks
    // -----------------------------------------------------------------

    /** Validation callback: a valid CVR number no company has yet. */
    public function cvr_free(string $cvr): string|bool {
        block_url('company/cvr_free');
        $digits = Company_rules::cvr($cvr);
        if ($digits === null) {
            return 'That isn\'t a valid CVR number: it has 8 digits, like 12345674.';
        }
        return $this->model->cvr_taken($digits)
            ? 'A company with that CVR number already has an account. Ask its owner to invite you.'
            : true;
    }

    /** Validation callback: no company member has this email yet. */
    public function email_free(string $email): string|bool {
        block_url('company/email_free');
        return $this->model->email_taken(strtolower(trim($email)))
            ? 'Someone already uses that email at a company here. Sign in instead.'
            : true;
    }

    /** Validation callback. */
    public function known_role(string $role): string|bool {
        block_url('company/known_role');
        return isset(Company_rules::ROLES[$role]) ? true : 'Pick owner or recruiter.';
    }

    /** Validation callbacks shared with the account module's key form. */
    public function known_provider(string $provider): string|bool {
        block_url('company/known_provider');
        return $this->account->known_provider($provider);
    }

    public function model_name(string $model): string|bool {
        block_url('company/model_name');
        return $this->account->model_name($model);
    }

    public function key_shape(string $key): string|bool {
        block_url('company/key_shape');
        return $this->account->key_shape($key);
    }

    // -----------------------------------------------------------------

    /**
     * The signed-in, active member of an active company. Anyone else is sent
     * to the staff sign-in.
     */
    private function member(): array {
        $token = $this->trongate_tokens->attempt_get_valid_token(self::USER_LEVEL);
        $member = $token !== false ? $this->model->member_for_user((int) $this->trongate_tokens->get_user_id($token)) : null;
        if ($member === null || (int) $member['active'] !== 1 || (int) $member['company_active'] !== 1) {
            redirect('company-sign-in');
            die();
        }
        return $member;
    }

    /** As member(), for owners only; recruiters go back to the company page. */
    private function owner(): array {
        $member = $this->member();
        if ($member['role'] !== 'owner') {
            set_flashdata('Only an owner can do that.');
            redirect('company');
            die();
        }
        return $member;
    }

    /** Signed in like login/submit_login does it, for this browser session. */
    private function sign_in(int $user_id): void {
        $this->module('trongate_tokens');
        $_SESSION['trongatetoken'] = $this->trongate_tokens->generate_token(['user_id' => $user_id]);
    }

}

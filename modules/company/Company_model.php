<?php
require_once __DIR__ . '/Company_rules.php';
require_once __DIR__ . '/../account/Account_model.php';

/**
 * Companies, their staff (company_members, user level 2) and the company's
 * own AI key (company_llm_keys, encrypted with the account module's Key_vault).
 */
class Company_model extends Model {

    private const USER_LEVEL = 2;

    /**
     * Creates the company and its first member, the owner, and returns the
     * owner's trongate_users id.
     */
    public function create(string $company_name, string $cvr, string $name, string $email, string $password_hash): int {
        $now = time();
        $company_id = $this->db->insert([
            'name' => $company_name,
            'cvr_number' => $cvr,
            'contact_email' => $email,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'companies');
        $user_id = $this->new_user();
        $this->db->insert([
            'company_id' => $company_id,
            'trongate_user_id' => $user_id,
            'name' => $name,
            'email' => $email,
            'password' => $password_hash,
            'role' => 'owner',
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'company_members');
        return $user_id;
    }

    /**
     * The member signed in as $user_id, with their company's name and state
     * (company_name, company_active, verified_at, contact_email, cvr_number),
     * or null.
     */
    public function member_for_user(int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT m.id, m.company_id, m.trongate_user_id, m.name, m.email, m.role, m.active,
                    c.name AS company_name, c.active AS company_active, c.verified_at, c.contact_email, c.cvr_number
             FROM company_members m JOIN companies c ON c.id = m.company_id
             WHERE m.trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** The company's members, owners first; invited ones have invite_pending = 1. */
    public function members(int $company_id): array {
        return $this->db->query_bind(
            "SELECT id, name, email, role, active, last_login, invited_at,
                    (invite_token IS NOT NULL) AS invite_pending, invite_token
             FROM company_members WHERE company_id = :company_id
             ORDER BY active DESC, role = 'owner' DESC, name",
            ['company_id' => $company_id],
            'array'
        );
    }

    /** One member of the company, or null. */
    public function member(int $company_id, int $member_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT id, company_id, trongate_user_id, name, email, role, active, invite_token
             FROM company_members WHERE id = :id AND company_id = :company_id',
            ['id' => $member_id, 'company_id' => $company_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    public function active_owners(int $company_id): int {
        $rows = $this->db->query_bind(
            "SELECT COUNT(*) AS n FROM company_members WHERE company_id = :company_id AND role = 'owner' AND active = 1",
            ['company_id' => $company_id],
            'array'
        );
        return (int) ($rows[0]['n'] ?? 0);
    }

    /**
     * Adds an invited member (no password yet) and returns the invite token
     * for the link they open to choose one.
     */
    public function invite(int $company_id, string $name, string $email, string $role): string {
        $now = time();
        $token = Company_rules::invite_token();
        $this->db->insert([
            'company_id' => $company_id,
            'trongate_user_id' => $this->new_user(),
            'name' => $name,
            'email' => $email,
            'password' => null,
            'role' => $role,
            'active' => 1,
            'invite_token' => $token,
            'invited_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'company_members');
        return $token;
    }

    /** The invited member and company name for an invite token, or null. */
    public function invitation(string $token): ?array {
        if (!Company_rules::is_invite_token($token)) {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT m.id, m.trongate_user_id, m.name, m.email, c.name AS company_name
             FROM company_members m JOIN companies c ON c.id = m.company_id
             WHERE m.invite_token = :token AND m.active = 1 AND c.active = 1',
            ['token' => $token],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** Sets the invited member's password and uses up the invite. */
    public function accept_invite(int $member_id, string $password_hash): void {
        $this->db->query_bind(
            'UPDATE company_members SET password = :password, invite_token = NULL, updated_at = :now
             WHERE id = :id AND invite_token IS NOT NULL',
            ['password' => $password_hash, 'now' => time(), 'id' => $member_id]
        );
    }

    /** Takes back an invite nobody has accepted: the member row and its user go. */
    public function revoke_invite(int $company_id, int $member_id): void {
        $member = $this->member($company_id, $member_id);
        if ($member === null || $member['invite_token'] === null) {
            return;
        }
        $this->db->query_bind(
            'DELETE FROM company_members WHERE id = :id AND invite_token IS NOT NULL',
            ['id' => $member_id]
        );
        $this->db->query_bind('DELETE FROM trongate_users WHERE id = :id', ['id' => (int) $member['trongate_user_id']]);
    }

    /**
     * Applies an allowed change (see Company_rules::refuse_change) to a member.
     * Deactivating also signs them out everywhere.
     */
    public function change_member(int $company_id, int $member_id, string $change): void {
        $set = match ($change) {
            'deactivate' => 'active = 0',
            'activate' => 'active = 1',
            'make_owner' => "role = 'owner'",
            'make_recruiter' => "role = 'recruiter'",
        };
        $this->db->query_bind(
            "UPDATE company_members SET $set, updated_at = :now WHERE id = :id AND company_id = :company_id",
            ['now' => time(), 'id' => $member_id, 'company_id' => $company_id]
        );
        if ($change === 'deactivate') {
            $member = $this->member($company_id, $member_id);
            $this->db->query_bind('DELETE FROM trongate_tokens WHERE user_id = :user_id', ['user_id' => (int) $member['trongate_user_id']]);
        }
    }

    public function update_settings(int $company_id, string $name, string $contact_email): void {
        $this->db->query_bind(
            'UPDATE companies SET name = :name, contact_email = :email, updated_at = :now WHERE id = :id',
            ['name' => $name, 'email' => $contact_email, 'now' => time(), 'id' => $company_id]
        );
    }

    public function email_taken(string $email): bool {
        return $this->db->get_one_where('email', $email, 'company_members') !== false;
    }

    public function cvr_taken(string $cvr): bool {
        return $this->db->get_one_where('cvr_number', $cvr, 'companies') !== false;
    }

    // -----------------------------------------------------------------
    // Administrators: verifying companies
    // -----------------------------------------------------------------

    /** Every company with its owner's contact, unverified first, newest first. */
    public function all_companies(): array {
        return $this->db->query_bind(
            "SELECT c.id, c.name, c.cvr_number, c.contact_email, c.active, c.verified_at, c.created_at,
                    (SELECT COUNT(*) FROM company_members m WHERE m.company_id = c.id AND m.active = 1) AS members
             FROM companies c
             ORDER BY c.verified_at IS NULL DESC, c.created_at DESC",
            [],
            'array'
        );
    }

    public function set_verified(int $company_id, ?int $admin_user_id): void {
        $this->db->query_bind(
            'UPDATE companies SET verified_at = :verified_at, verified_by = :by, updated_at = :now WHERE id = :id',
            [
                'verified_at' => $admin_user_id === null ? null : time(),
                'by' => $admin_user_id,
                'now' => time(),
                'id' => $company_id,
            ]
        );
    }

    // -----------------------------------------------------------------
    // The company's AI key
    // -----------------------------------------------------------------

    /** The saved key's details without the key, or null. */
    public function saved_key(int $company_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT provider, model, key_hint, updated_at FROM company_llm_keys WHERE company_id = :company_id',
            ['company_id' => $company_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** { provider, model, api_key } for the llm module, or null without a usable key. */
    public function key_for(int $company_id): ?array {
        $vault = Account_model::vault();
        if (!$vault) {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT provider, model, api_key_encrypted FROM company_llm_keys WHERE company_id = :company_id',
            ['company_id' => $company_id],
            'array'
        );
        $row = $rows[0] ?? null;
        $api_key = $row ? $vault->decrypt($row['api_key_encrypted'], $company_id, 'company') : null;
        if ($api_key === null) {
            return null;
        }
        return ['provider' => $row['provider'], 'model' => $row['model'], 'api_key' => $api_key];
    }

    public function save_key(int $company_id, string $provider, string $model, string $api_key): void {
        $vault = Account_model::vault() ?? throw new RuntimeException('Saving keys is not set up on this server yet.');
        $now = time();
        $this->db->query_bind(
            'INSERT INTO company_llm_keys (company_id, provider, model, api_key_encrypted, key_hint, created_at, updated_at)
             VALUES (:company_id, :provider, :model, :encrypted, :hint, :now, :now2)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model),
                 api_key_encrypted = VALUES(api_key_encrypted), key_hint = VALUES(key_hint), updated_at = VALUES(updated_at)',
            [
                'company_id' => $company_id,
                'provider' => $provider,
                'model' => $model,
                'encrypted' => $vault->encrypt($api_key, $company_id, 'company'),
                'hint' => substr($api_key, -4),
                'now' => $now,
                'now2' => $now,
            ]
        );
    }

    public function delete_key(int $company_id): void {
        $this->db->query_bind('DELETE FROM company_llm_keys WHERE company_id = :company_id', ['company_id' => $company_id]);
    }

    /** A trongate_users row for a new company member. */
    private function new_user(): int {
        return $this->db->insert([
            'code' => make_rand_str(32),
            'user_level_id' => self::USER_LEVEL,
        ], 'trongate_users');
    }

}

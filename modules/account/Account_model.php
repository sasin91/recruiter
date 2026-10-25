<?php
require_once __DIR__ . '/Key_vault.php';
require_once __DIR__ . '/../llm/Llm.php';

/**
 * Users' own language model API keys (user_llm_keys), encrypted with
 * Key_vault, and what each key is used for (user_llm_keys.uses). The plain
 * key only leaves here through key_for(), for the llm module.
 */
class Account_model extends Model {

    public const PROVIDERS = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)'];

    // What the key can be used for, each switched on or off by the user:
    // purpose => what the account page calls it. user_llm_keys.uses is a SET
    // of the purposes switched on; a new key has them all.
    public const USES = [
        'resume' => 'Tailoring résumés',
        'application' => 'Writing job applications',
        'job_evaluation' => 'Evaluating job posts (the AI match)',
    ];

    /** The saved key's details without the key, plus its uses, or null. */
    public function saved(int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT provider, model, key_hint, uses, updated_at FROM user_llm_keys WHERE trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        return isset($rows[0]) ? ['uses' => self::uses((string) $rows[0]['uses'])] + $rows[0] : null;
    }

    /**
     * { provider, model, api_key, uses } for the llm module, or null without
     * a usable key. uses: purpose => whether the user has it on.
     */
    public function key_for(int $user_id): ?array {
        $vault = self::vault();
        if (!$vault) {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT provider, model, api_key_encrypted, uses FROM user_llm_keys WHERE trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        $row = $rows[0] ?? null;
        $api_key = $row ? $vault->decrypt($row['api_key_encrypted'], $user_id) : null;
        if ($api_key === null) {
            return null;
        }
        return ['provider' => $row['provider'], 'model' => $row['model'], 'api_key' => $api_key, 'uses' => self::uses((string) $row['uses'])];
    }

    /** purpose => on, for every purpose in USES, from a uses SET value ("resume,job_evaluation"). */
    public static function uses(string $set): array {
        $on = explode(',', $set);
        $uses = [];
        foreach (array_keys(self::USES) as $purpose) {
            $uses[$purpose] = in_array($purpose, $on, true);
        }
        return $uses;
    }

    /** Switches the saved key's uses: the purposes in $on are on, the rest off. */
    public function save_uses(int $user_id, array $on): void {
        $this->db->query_bind(
            'UPDATE user_llm_keys SET uses = :uses, updated_at = :now WHERE trongate_user_id = :user_id',
            [
                'user_id' => $user_id,
                'uses' => implode(',', array_intersect(array_keys(self::USES), $on)),
                'now' => time(),
            ]
        );
    }

    public function save(int $user_id, string $provider, string $model, string $api_key): void {
        $vault = self::vault() ?? throw new RuntimeException('Saving keys is not set up on this server yet.');
        $now = time();
        $this->db->query_bind(
            'INSERT INTO user_llm_keys (trongate_user_id, provider, model, api_key_encrypted, key_hint, created_at, updated_at)
             VALUES (:user_id, :provider, :model, :encrypted, :hint, :now, :now2)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model),
                 api_key_encrypted = VALUES(api_key_encrypted), key_hint = VALUES(key_hint), updated_at = VALUES(updated_at)',
            [
                'user_id' => $user_id,
                'provider' => $provider,
                'model' => $model,
                'encrypted' => $vault->encrypt($api_key, $user_id),
                'hint' => substr($api_key, -4),
                'now' => $now,
                'now2' => $now,
            ]
        );
    }

    public function delete_key(int $user_id): void {
        $this->db->query_bind('DELETE FROM user_llm_keys WHERE trongate_user_id = :user_id', ['user_id' => $user_id]);
    }

    /** The candidates row id of the user, or null when they aren't a candidate (an administrator). */
    public function candidate_id(int $user_id): ?int {
        $rows = $this->db->query_bind('SELECT id FROM candidates WHERE trongate_user_id = :user_id', ['user_id' => $user_id], 'array');
        return isset($rows[0]) ? (int) $rows[0]['id'] : null;
    }

    /**
     * Makes the administrator signed in as $user_id a candidate (no
     * password: they keep signing in as an administrator), so they can keep
     * profile links; returns the new candidates row id. Named and addressed
     * as their administrator account.
     */
    public function candidate_for_administrator(int $user_id): int {
        $rows = $this->db->query_bind(
            'SELECT username, email FROM trongate_administrators WHERE trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        $admin = $rows[0] ?? throw new RuntimeException("User $user_id is not an administrator.");
        $name = trim((string) $admin['username']) ?: 'Administrator';
        $now = time();
        return $this->db->insert([
            'trongate_user_id' => $user_id,
            'name' => $name,
            'email' => strtolower(trim((string) $admin['email'])) ?: $name,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'candidates');
    }

    /** The candidate's profile links in their order: [['label', 'url'], ...] (Profile_links). */
    public function profile_links(int $candidate_id): array {
        return $this->db->query_bind(
            'SELECT label, url FROM candidate_profile_links WHERE candidate_id = :candidate_id ORDER BY position, id',
            ['candidate_id' => $candidate_id],
            'array'
        );
    }

    /**
     * Replaces the candidate's profile links with $links, in that order, all
     * or nothing: a failed insert (a label the table's collation counts as a
     * duplicate) keeps the links saved before.
     */
    public function save_profile_links(int $candidate_id, array $links): void {
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query_bind('DELETE FROM candidate_profile_links WHERE candidate_id = :candidate_id', ['candidate_id' => $candidate_id]);
            $now = time();
            foreach (array_values($links) as $position => $link) {
                $this->db->query_bind(
                    'INSERT INTO candidate_profile_links (candidate_id, label, url, position, created_at, updated_at)
                     VALUES (:candidate_id, :label, :url, :position, :now, :now2)',
                    ['candidate_id' => $candidate_id, 'label' => $link['label'], 'url' => $link['url'], 'position' => $position, 'now' => $now, 'now2' => $now]
                );
            }
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    // Everything kept about a candidate, table => the query for their rows
    // (:user_id is their trongate_users id), for "Download my data" (GDPR
    // art. 15 and 20). EXPORT_HIDDEN columns are left out: secrets, not data.
    private const MINE = 'SELECT id FROM candidates WHERE trongate_user_id = :user_id';
    private const MY_MATCHES = 'SELECT id FROM cv_matches WHERE trongate_user_id = :user_id';
    private const MY_SEARCHES = 'SELECT id FROM job_searches WHERE trongate_user_id = :user_id';
    private const MY_APPLICATIONS = 'SELECT id FROM job_applications WHERE candidate_id IN (' . self::MINE . ')';
    public const EXPORT = [
        'candidates' => 'SELECT * FROM candidates WHERE trongate_user_id = :user_id',
        'candidate_profiles' => 'SELECT * FROM candidate_profiles WHERE candidate_id IN (' . self::MINE . ')',
        'candidate_profile_entries' => 'SELECT * FROM candidate_profile_entries WHERE candidate_id IN (' . self::MINE . ') ORDER BY section, sort_order, id',
        'candidate_profile_lines' => 'SELECT * FROM candidate_profile_lines WHERE candidate_id IN (' . self::MINE . ') ORDER BY kind, sort_order, id',
        'candidate_profile_links' => 'SELECT * FROM candidate_profile_links WHERE candidate_id IN (' . self::MINE . ') ORDER BY position, id',
        'candidate_resumes' => 'SELECT * FROM candidate_resumes WHERE candidate_id IN (' . self::MINE . ')',
        'job_applications' => 'SELECT * FROM job_applications WHERE candidate_id IN (' . self::MINE . ')',
        'job_application_actions' => 'SELECT * FROM job_application_actions WHERE job_application_id IN (' . self::MY_APPLICATIONS . ')',
        'match_scores' => 'SELECT * FROM match_scores WHERE job_application_id IN (' . self::MY_APPLICATIONS . ')',
        'cv_matches' => 'SELECT * FROM cv_matches WHERE trongate_user_id = :user_id ORDER BY id',
        'cv_match_items' => 'SELECT * FROM cv_match_items WHERE cv_match_id IN (' . self::MY_MATCHES . ')',
        'cv_match_resumes' => 'SELECT * FROM cv_match_resumes WHERE cv_match_id IN (' . self::MY_MATCHES . ')',
        'cv_match_resume_entries' => 'SELECT * FROM cv_match_resume_entries WHERE cv_match_id IN (' . self::MY_MATCHES . ')',
        'cv_match_resume_lines' => 'SELECT * FROM cv_match_resume_lines WHERE cv_match_id IN (' . self::MY_MATCHES . ')',
        'cv_match_integrations' => 'SELECT * FROM cv_match_integrations WHERE cv_match_id IN (' . self::MY_MATCHES . ')',
        'cv_match_rejections' => 'SELECT * FROM cv_match_rejections WHERE cv_match_id IN (' . self::MY_MATCHES . ')',
        'job_searches' => 'SELECT * FROM job_searches WHERE trongate_user_id = :user_id ORDER BY id',
        'job_search_sites' => 'SELECT * FROM job_search_sites WHERE job_search_id IN (' . self::MY_SEARCHES . ')',
        'job_search_runs' => 'SELECT * FROM job_search_runs WHERE job_search_id IN (' . self::MY_SEARCHES . ')',
        'scanned_job_posts' => 'SELECT * FROM scanned_job_posts WHERE job_search_id IN (' . self::MY_SEARCHES . ')',
        'user_llm_keys' => 'SELECT * FROM user_llm_keys WHERE trongate_user_id = :user_id',
    ];
    public const EXPORT_HIDDEN = ['password', 'api_key_encrypted', 'api_token_hash'];

    /** table => the user's rows (EXPORT), without EXPORT_HIDDEN columns. */
    public function data_of(int $user_id): array {
        $data = [];
        foreach (self::EXPORT as $table => $sql) {
            $rows = $this->db->query_bind($sql, ['user_id' => $user_id], 'array');
            $data[$table] = self::without_secrets($rows);
        }
        return $data;
    }

    /** $rows without the EXPORT_HIDDEN columns. */
    public static function without_secrets(array $rows): array {
        $hidden = array_flip(self::EXPORT_HIDDEN);
        return array_map(fn(array $row) => array_diff_key($row, $hidden), $rows);
    }

    /** The candidate's email and password hash, or null when the user isn't a candidate. */
    public function candidate_sign_in(int $user_id): ?array {
        $rows = $this->db->query_bind('SELECT email, password FROM candidates WHERE trongate_user_id = :user_id', ['user_id' => $user_id], 'array');
        return $rows[0] ?? null;
    }

    /**
     * Deletes the candidate signed in as $user_id and everything kept about
     * them, all or nothing (GDPR art. 17). The candidates row takes their
     * profile, links, résumés and applications with it (ON DELETE CASCADE);
     * the trongate_users row their matches, searches and API key. Sign-in
     * tokens, reset links and failed sign-ins have no foreign key, so they
     * go by hand.
     */
    public function delete_account(int $user_id, string $email): void {
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query_bind('DELETE FROM candidates WHERE trongate_user_id = :user_id', ['user_id' => $user_id]);
            $this->db->query_bind("DELETE FROM password_resets WHERE target_table = 'candidates' AND identifier = :email", ['email' => $email]);
            $this->db->query_bind("DELETE FROM login_attempts WHERE target_table = 'candidates' AND identifier = :email", ['email' => $email]);
            $this->db->query_bind('DELETE FROM trongate_tokens WHERE user_id = :user_id', ['user_id' => $user_id]);
            $this->db->query_bind('DELETE FROM trongate_users WHERE id = :user_id', ['user_id' => $user_id]);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /** The vault, or null when the server has no LLM_KEY_SECRET (keys can't be saved or used). */
    public static function vault(): ?Key_vault {
        $secret = Llm::env('LLM_KEY_SECRET');
        return $secret !== null && strlen($secret) >= 32 ? new Key_vault($secret) : null;
    }

}

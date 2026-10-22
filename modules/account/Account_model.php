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

    /** The vault, or null when the server has no LLM_KEY_SECRET (keys can't be saved or used). */
    public static function vault(): ?Key_vault {
        $secret = Llm::env('LLM_KEY_SECRET');
        return $secret !== null && strlen($secret) >= 32 ? new Key_vault($secret) : null;
    }

}

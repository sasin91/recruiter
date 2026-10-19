<?php
require_once __DIR__ . '/Key_vault.php';
require_once __DIR__ . '/../llm/Llm.php';

/**
 * Candidates' own language model API keys (candidate_llm_keys), encrypted
 * with Key_vault, and what each key is used for (candidate_llm_key_uses).
 * Callers pass the signed-in user's id; the key belongs to their candidates
 * row, so a user who isn't a candidate has none. The plain key only leaves
 * here through key_for(), for the llm module.
 */
class Account_model extends Model {

    public const PROVIDERS = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)'];

    // What the key can be used for, each switched on or off by the candidate:
    // purpose => what the account page calls it. A purpose without a
    // candidate_llm_key_uses row is on.
    public const USES = [
        'resume' => 'Tailoring résumés',
        'application' => 'Writing job applications',
        'job_evaluation' => 'Evaluating job posts (the AI match)',
    ];

    /** The user's candidates.id, or null when they aren't a candidate. */
    public function candidate_id(int $user_id): ?int {
        $rows = $this->db->query_bind(
            'SELECT id FROM candidates WHERE trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        return isset($rows[0]) ? (int) $rows[0]['id'] : null;
    }

    /** The saved key's details without the key, plus its uses, or null. */
    public function saved(int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT k.candidate_id, k.provider, k.model, k.key_hint, k.updated_at
             FROM candidate_llm_keys k JOIN candidates c ON c.id = k.candidate_id
             WHERE c.trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        return isset($rows[0]) ? $rows[0] + ['uses' => $this->uses((int) $rows[0]['candidate_id'])] : null;
    }

    /**
     * { provider, model, api_key, uses } for the llm module, or null without
     * a usable key. uses: purpose => whether the candidate has it on.
     */
    public function key_for(int $user_id): ?array {
        $vault = self::vault();
        if (!$vault) {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT k.candidate_id, k.provider, k.model, k.api_key_encrypted
             FROM candidate_llm_keys k JOIN candidates c ON c.id = k.candidate_id
             WHERE c.trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        $row = $rows[0] ?? null;
        $api_key = $row ? $vault->decrypt($row['api_key_encrypted'], (int) $row['candidate_id'], 'candidate') : null;
        if ($api_key === null) {
            return null;
        }
        return [
            'provider' => $row['provider'],
            'model' => $row['model'],
            'api_key' => $api_key,
            'uses' => $this->uses((int) $row['candidate_id']),
        ];
    }

    /** purpose => on, for every purpose in USES. */
    public function uses(int $candidate_id): array {
        $rows = $this->db->query_bind(
            'SELECT purpose, enabled FROM candidate_llm_key_uses WHERE candidate_id = :candidate_id',
            ['candidate_id' => $candidate_id],
            'array'
        );
        return self::uses_from_rows($rows ?: []);
    }

    /** purpose => on, from candidate_llm_key_uses rows; a missing purpose is on. */
    public static function uses_from_rows(array $rows): array {
        $enabled = array_column($rows, 'enabled', 'purpose');
        $uses = [];
        foreach (array_keys(self::USES) as $purpose) {
            $uses[$purpose] = (bool) ($enabled[$purpose] ?? true);
        }
        return $uses;
    }

    /**
     * Switches the candidate's uses: each purpose in USES is on when it's in
     * $on, off otherwise.
     */
    public function save_uses(int $candidate_id, array $on): void {
        $now = time();
        foreach (array_keys(self::USES) as $purpose) {
            $this->db->query_bind(
                'INSERT INTO candidate_llm_key_uses (candidate_id, purpose, enabled, created_at, updated_at)
                 VALUES (:candidate_id, :purpose, :enabled, :now, :now2)
                 ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = VALUES(updated_at)',
                [
                    'candidate_id' => $candidate_id,
                    'purpose' => $purpose,
                    'enabled' => in_array($purpose, $on, true) ? 1 : 0,
                    'now' => $now,
                    'now2' => $now,
                ]
            );
        }
    }

    public function save(int $candidate_id, string $provider, string $model, string $api_key): void {
        $vault = self::vault() ?? throw new RuntimeException('Saving keys is not set up on this server yet.');
        $now = time();
        $this->db->query_bind(
            'INSERT INTO candidate_llm_keys (candidate_id, provider, model, api_key_encrypted, key_hint, created_at, updated_at)
             VALUES (:candidate_id, :provider, :model, :encrypted, :hint, :now, :now2)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model),
                 api_key_encrypted = VALUES(api_key_encrypted), key_hint = VALUES(key_hint), updated_at = VALUES(updated_at)',
            [
                'candidate_id' => $candidate_id,
                'provider' => $provider,
                'model' => $model,
                'encrypted' => $vault->encrypt($api_key, $candidate_id, 'candidate'),
                'hint' => substr($api_key, -4),
                'now' => $now,
                'now2' => $now,
            ]
        );
    }

    public function delete_key(int $candidate_id): void {
        $this->db->query_bind('DELETE FROM candidate_llm_keys WHERE candidate_id = :candidate_id', ['candidate_id' => $candidate_id]);
    }

    /** The vault, or null when the server has no LLM_KEY_SECRET (keys can't be saved or used). */
    public static function vault(): ?Key_vault {
        $secret = Llm::env('LLM_KEY_SECRET');
        return $secret !== null && strlen($secret) >= 32 ? new Key_vault($secret) : null;
    }

}

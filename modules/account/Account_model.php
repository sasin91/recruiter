<?php
require_once __DIR__ . '/Key_vault.php';
require_once __DIR__ . '/../llm/Llm.php';

/**
 * Users' own language model API keys (user_llm_keys), encrypted with
 * Key_vault. The plain key only leaves here through key_for(), for the llm
 * module.
 */
class Account_model extends Model {

    public const PROVIDERS = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)'];

    /** The saved key's details without the key, or null. */
    public function saved(int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT provider, model, key_hint, updated_at FROM user_llm_keys WHERE trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** { provider, model, api_key } for the llm module, or null without a usable key. */
    public function key_for(int $user_id): ?array {
        $vault = self::vault();
        if (!$vault) {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT provider, model, api_key_encrypted FROM user_llm_keys WHERE trongate_user_id = :user_id',
            ['user_id' => $user_id],
            'array'
        );
        $row = $rows[0] ?? null;
        $api_key = $row ? $vault->decrypt($row['api_key_encrypted'], $user_id) : null;
        if ($api_key === null) {
            return null;
        }
        return ['provider' => $row['provider'], 'model' => $row['model'], 'api_key' => $api_key];
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

    /** The vault, or null when the server has no LLM_KEY_SECRET (keys can't be saved or used). */
    public static function vault(): ?Key_vault {
        $secret = Llm::env('LLM_KEY_SECRET');
        return $secret !== null && strlen($secret) >= 32 ? new Key_vault($secret) : null;
    }

}

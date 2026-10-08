<?php
/**
 * Encrypts users' API keys at rest (user_llm_keys.api_key_encrypted) with
 * libsodium's XChaCha20-Poly1305, under a key derived from the server secret
 * LLM_KEY_SECRET. Each ciphertext is bound to its owner's user id, so a row
 * copied to another user doesn't decrypt. Plain PHP, so the tests in tests/ can run it.
 */
class Key_vault {

    private string $key;

    /** @param string $secret the server secret; at least 32 characters */
    public function __construct(string $secret) {
        if (strlen($secret) < 32) {
            throw new RuntimeException('LLM_KEY_SECRET must be at least 32 characters.');
        }
        $this->key = sodium_crypto_generichash("user_llm_keys:$secret", '', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    }

    /** base64 of nonce + ciphertext. */
    public function encrypt(string $plain, int $user_id): string {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, self::bound_to($user_id), $nonce, $this->key);
        return base64_encode($nonce . $cipher);
    }

    /** The plain key, or null when the ciphertext, owner or secret doesn't match. */
    public function decrypt(string $stored, int $user_id): ?string {
        $raw = base64_decode($stored, true);
        $size = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) <= $size) {
            return null;
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $size), self::bound_to($user_id), substr($raw, 0, $size), $this->key);
        return $plain === false ? null : $plain;
    }

    private static function bound_to(int $user_id): string {
        return "trongate_user:$user_id";
    }

}

<?php
/**
 * How often one visitor may ask Laya from the free match: at most MAX calls
 * per WINDOW seconds per client address. Laya is one CPU pod answering one
 * call at a time (about a second each), so a burst from one address would
 * queue everyone else behind it.
 *
 * The counts live in small files under the system temp directory, one per
 * hashed address, so nothing is stored in the database and the limit holds
 * across sessions (a visitor who drops the cookie is still the same address).
 * They are per app pod, which is enough while the app runs one replica.
 */
class Laya_limit {

    public const MAX = 20;
    public const WINDOW = 600;

    private string $dir;

    public function __construct(?string $dir = null, private int $max = self::MAX, private int $window = self::WINDOW) {
        $this->dir = $dir ?? sys_get_temp_dir() . '/laya-limit';
    }

    /**
     * Counts a call for $client and returns 0, or returns the seconds until
     * the next call is allowed (nothing counted) when $client used its calls.
     */
    public function take(string $client, ?int $now = null): int {
        $now ??= time();
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0700, true);
        }
        $file = $this->dir . '/' . hash('sha256', $client);
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return 0; // No place to count: let the call through rather than break the match
        }
        try {
            flock($handle, LOCK_EX);
            $calls = array_values(array_filter(
                (array) json_decode((string) stream_get_contents($handle), true),
                fn($at) => is_int($at) && $at > $now - $this->window
            ));
            if (count($calls) >= $this->max) {
                return max(1, $calls[0] + $this->window - $now);
            }
            $calls[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($calls));
            return 0;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * The visitor's address: the last X-Forwarded-For entry, which the
     * platform's proxy appends (earlier entries are whatever the client
     * sent), else REMOTE_ADDR.
     */
    public static function client(array $server): string {
        $forwarded = array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')));
        $last = end($forwarded);
        return $last !== '' && $last !== false ? $last : (string) ($server['REMOTE_ADDR'] ?? '');
    }
}

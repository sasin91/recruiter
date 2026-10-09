<?php
/**
 * Where a candidate goes after signing in or signing up, when it isn't the
 * level's usual page: the apply page they came from. Only paths that look
 * like one of ours are kept, so the session value can't send anyone off-site.
 */
class Return_path {

    private const KEY = 'after_sign_in';

    // The pages a candidate may be sent back to.
    private const ALLOWED = '#^jobs/[A-Za-z0-9]{12}/apply$#';

    public static function is_allowed(string $path): bool {
        return (bool) preg_match(self::ALLOWED, $path);
    }

    /** Remembers $path for after sign-in; anything not allowed is ignored. */
    public static function remember(string $path): void {
        if (self::is_allowed($path)) {
            $_SESSION[self::KEY] = $path;
        }
    }

    /** The remembered path (forgotten once taken), or $default. */
    public static function take(string $default): string {
        $path = (string) ($_SESSION[self::KEY] ?? '');
        unset($_SESSION[self::KEY]);
        return self::is_allowed($path) ? $path : $default;
    }

}

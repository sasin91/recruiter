<?php
/**
 * What a visitor sees when a request fails with an uncaught exception: a
 * page (or, for fetch() calls, JSON) saying what went wrong in words, never
 * a bare 500. The exception still goes to error_log. A database without
 * this version's tables or columns (db/schema.sql not applied after a
 * deploy) gets its own message, since only running bin/import-db.php
 * fixes it.
 */
class Failure {

    public static function handle(Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $message = self::message($e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (self::wants_json()) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => $message]);
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $text = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $home = defined('BASE_URL') ? htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') : '/';
        echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong</title>
            <style>body{font:16px/1.5 system-ui,sans-serif;max-width:36rem;margin:15vh auto;padding:0 16px;color:#1f2933}h1{font-size:1.4rem}</style></head>
            <body><h1>Something went wrong</h1><p>{$text}</p><p><a href="{$home}">Back to the front page</a></p></body>
            </html>
            HTML;
    }

    /** The words for the visitor: why it failed, without internals. */
    public static function message(Throwable $e): string {
        if (self::is_schema_missing($e)) {
            return "The site was just updated and its database hasn't caught up yet, so this page can't load. Please try again in a little while.";
        }
        return "This page couldn't load because of an error on our side. It has been logged. Please try again.";
    }

    /** A missing table (42S02) or column (42S22): db/schema.sql wasn't applied. */
    public static function is_schema_missing(Throwable $e): bool {
        for (; $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof PDOException && in_array((string) $e->getCode(), ['42S02', '42S22'], true)) {
                return true;
            }
        }
        return false;
    }

    private static function wants_json(): bool {
        $mode = strtolower($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');
        return in_array($mode, ['cors', 'same-origin'], true)
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
            || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}

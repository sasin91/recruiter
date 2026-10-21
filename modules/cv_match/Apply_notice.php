<?php
require_once __DIR__ . '/Apply_instructions.php';
require_once __DIR__ . '/Application_delivery.php';

/**
 * What the post asks of applicants beyond a click (Apply_instructions), as
 * the result prints it above the requirements (views/apply_notice.php): send
 * it by email, with a subject line, a keyword or answers to questions,
 * quoting the post. Often a filter for people who only press Easy Apply.
 */
class Apply_notice {

    public const HEADING = 'This post asks for applications by email';
    public const TRAP_HEADING = 'Read this before you apply: the post asks for more than a click';
    public const TRAP_NOTE = 'Posts like this often skip applications that don\'t follow it, such as Easy Apply.';
    public const WRITER_NOTE = 'Write application below follows it for you.';

    /**
     * ['trap' => true, 'heading' => ..., 'note' => ..., 'email' => ...,
     * 'mailto' => ..., 'subject' => ..., 'keyword' => ..., 'asks' => "It
     * asks for ...", 'lines' => [quoted lines]], or null when the post asks
     * for nothing special. $writer: the page can write the application.
     */
    public static function from(?array $apply, bool $writer): ?array {
        if (!$apply) {
            return null;
        }
        $trap = (bool) $apply['trap'];
        return [
            'trap' => $trap,
            'heading' => $trap ? self::TRAP_HEADING : self::HEADING,
            'note' => $trap ? self::note($writer) : '',
            'email' => (string) ($apply['email'] ?? ''),
            'mailto' => self::mailto($apply),
            'subject' => (string) ($apply['subject'] ?? ''),
            'keyword' => self::keyword($apply),
            'asks' => self::asks($apply),
            'lines' => $apply['lines'],
        ];
    }

    private static function note(bool $writer): string {
        return $writer ? self::TRAP_NOTE . ' ' . self::WRITER_NOTE : self::TRAP_NOTE;
    }

    private static function mailto(array $apply): string {
        return ($apply['email'] ?? '') !== '' ? Application_delivery::mailto($apply['email'], (string) ($apply['subject'] ?? '')) : '';
    }

    /** The word to include, unless the subject line already carries it. */
    private static function keyword(array $apply): string {
        return ($apply['subject'] ?? '') === '' ? (string) ($apply['keyword'] ?? '') : '';
    }

    /** "It asks for a specific subject line, answers to questions." or ''. */
    private static function asks(array $apply): string {
        $words = array_map(fn(string $ask) => Apply_instructions::ASKS[$ask], $apply['asks']);
        return $words ? 'It asks for ' . implode(', ', $words) . '.' : '';
    }
}

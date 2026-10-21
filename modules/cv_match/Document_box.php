<?php
require_once __DIR__ . '/Application_delivery.php';

/**
 * The box for one document of a saved match, the job application or the
 * tailored résumé, as the result prints it (views/documents.php): its
 * title, the Write button, the text already written (editable, with Copy and
 * Download PDF), where the post wants the application sent, and a note when
 * the user's key is switched off for writing it. js/app.js runs the buttons.
 */
class Document_box {

    public const KINDS = [
        'application' => ['title' => 'Job application', 'noun' => 'application', 'write' => 'Write application'],
        'resume' => ['title' => 'Tailored résumé', 'noun' => 'résumé', 'write' => 'Tailor résumé'],
    ];
    public const WRITE_AGAIN = 'Write it again';

    /**
     * ['kind' => 'application', 'id' => 7, 'title' => ..., 'write' => ...,
     * 'off' => false, 'off_note' => ..., 'text' => ..., 'written_at' => '1760000000' or '',
     * 'structured' => false, 'send_to' => ['email', 'mailto', 'subject'] or null].
     *
     * $written: ['text' => ..., 'written_at' => ..., 'structured' => bool];
     * $on: whether the user's key may write it; $apply: Apply_instructions::read.
     */
    public static function from(string $kind, int $id, array $written, bool $on, ?array $apply): array {
        $text = (string) ($written['text'] ?? '');
        return [
            'kind' => $kind,
            'id' => $id,
            'title' => self::KINDS[$kind]['title'],
            'write' => $text !== '' ? self::WRITE_AGAIN : self::KINDS[$kind]['write'],
            'off' => !$on,
            'off_note' => $on ? '' : self::off_note($kind),
            'text' => $text,
            'written_at' => (string) ($written['written_at'] ?? ''),
            'structured' => (bool) ($written['structured'] ?? false),
            'send_to' => $kind === 'application' ? self::send_to($apply) : null,
        ];
    }

    private static function off_note(string $kind): string {
        return 'Your API key is switched off for writing the ' . self::KINDS[$kind]['noun'] . '.';
    }

    /** The address the post wants the application sent to, with its subject line. */
    private static function send_to(?array $apply): ?array {
        $email = (string) ($apply['email'] ?? '');
        if ($email === '') {
            return null;
        }
        $subject = (string) ($apply['subject'] ?? '');
        return ['email' => $email, 'mailto' => Application_delivery::mailto($email, $subject), 'subject' => $subject];
    }
}

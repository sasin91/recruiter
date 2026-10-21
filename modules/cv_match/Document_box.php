<?php
require_once __DIR__ . '/Application_delivery.php';

/**
 * The box for one document of a saved match, the job application or the
 * tailored résumé, as the result prints it (views/documents.php): its
 * title, the Write button, the text already written (editable, with Copy and
 * Download PDF), where the post wants the application sent, and a note when
 * the user's key is switched off for writing it. js/app.js runs the buttons.
 * Just after the résumé is written, with_keywords() adds the post's keywords
 * it took over and the ones it left out (tailor_resume).
 */
class Document_box {

    public const KINDS = [
        'application' => ['title' => 'Job application', 'noun' => 'application', 'write' => 'Write application'],
        'resume' => ['title' => 'Tailored résumé', 'noun' => 'résumé', 'write' => 'Tailor résumé'],
    ];
    public const WRITE_AGAIN = 'Write it again';
    public const KEYWORDS_USED = 'Uses %d of the post\'s keywords: %s';
    public const LEFT_OUT = 'Left out, since your CV doesn\'t show it: %s';

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
            'keywords' => null,
            'left_out' => '',
        ];
    }

    /**
     * $box with the keywords the résumé took over, as ['summary' => "Uses 2
     * of the post's keywords: PHP, Go", 'items' => [['term', 'basis'], ...]]
     * or null, and the ones it left out as a line or ''. $keywords: [['term',
     * 'basis'], ...]; $left_out: terms.
     */
    public static function with_keywords(array $box, array $keywords, array $left_out): array {
        $box['keywords'] = $keywords ? ['summary' => self::keywords_summary($keywords), 'items' => $keywords] : null;
        $box['left_out'] = $left_out ? sprintf(self::LEFT_OUT, implode(', ', $left_out)) : '';
        return $box;
    }

    private static function keywords_summary(array $keywords): string {
        return sprintf(self::KEYWORDS_USED, count($keywords), implode(', ', array_column($keywords, 'term')));
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

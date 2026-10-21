<?php
require_once __DIR__ . '/Apply_instructions.php';

/**
 * How the application for a saved match is delivered, the way the post
 * asks for it, ready to print in the Apply dialog
 * (views/application_delivery.php):
 * - by email, when the post names an address to send applications to
 *   (Apply_instructions): the recipient, the subject (the one the post asks
 *   for, else the job title) and the body (the written application) to
 *   copy, and a mailto link with the recipient and subject;
 * - otherwise on the employer's side (an application form, LinkedIn Easy
 *   Apply): the link to the job post and the application to paste in.
 * Either way the application and résumé PDFs to attach or upload (a mailto
 * link can't carry files), and what else the post asks for.
 *
 * from() fills it by rule from the saved match (no model call).
 */
class Application_delivery {

    public const HEADINGS = ['email' => 'Apply by email', 'form' => 'Apply on the employer\'s site'];
    public const FILES_HEADINGS = ['email' => 'Files to attach', 'form' => 'Files to upload'];
    public const FORM_INTRO = 'The post doesn\'t ask for an email, so apply where it says: its application form or Easy Apply. Paste the application into the cover letter field and upload the PDFs.';
    public const NO_LINK = 'This match has no link to the post: open it where you found it.';
    public const TO_HINT = 'The address the post asks applications to be sent to.';
    public const NOT_WRITTEN = 'Write the application first (Write application).';

    /**
     * ['id' => 7, 'by' => 'email', 'heading' => ..., 'intro' => ...,
     * 'fields' => [['id', 'label', 'value', 'hint', 'multiline'], ...],
     * 'asks' => "The post also asks for ...", 'files_heading' => ...,
     * 'files' => [['kind', 'label', 'ready', 'missing'], ...],
     * 'missing' => ["Application: Write the application first ..."],
     * 'open' => ['href', 'label', 'target'] or null].
     */
    public static function from(array $match): array {
        $by = self::by($match);
        return [
            'id' => (int) $match['id'],
            'by' => $by,
            'heading' => self::HEADINGS[$by],
            'intro' => self::intro($match, $by),
            'fields' => $by === 'email' ? self::email_fields($match) : [self::body_field($match, 'Application')],
            'asks' => self::asks($match),
            'files_heading' => self::FILES_HEADINGS[$by],
            'files' => self::files($match),
            'missing' => self::missing(self::files($match)),
            'open' => $by === 'email' ? self::mailto_link($match) : self::job_link($match),
        ];
    }

    /** 'email' when the post names an address to send applications to, else 'form'. */
    public static function by(array $match): string {
        return self::to($match) !== '' ? 'email' : 'form';
    }

    /** The subject line the post asks for, else the job title. */
    public static function subject(array $match): string {
        $subject = trim((string) ($match['apply_instructions']['subject'] ?? ''));
        return $subject !== '' ? $subject : trim((string) ($match['job_title'] ?? ''));
    }

    /** mailto:{to}?subject={subject}. */
    public static function mailto(string $to, string $subject): string {
        $link = 'mailto:' . str_replace('%40', '@', rawurlencode($to));
        return $subject !== '' ? $link . '?subject=' . rawurlencode($subject) : $link;
    }

    private static function to(array $match): string {
        return trim((string) ($match['apply_instructions']['email'] ?? ''));
    }

    private static function intro(array $match, string $by): string {
        if ($by === 'email') {
            return '';
        }
        return self::job_url($match) !== '' ? self::FORM_INTRO : self::FORM_INTRO . ' ' . self::NO_LINK;
    }

    private static function email_fields(array $match): array {
        return [
            self::field('delivery-to', 'To', self::to($match), self::TO_HINT),
            self::field('delivery-subject', 'Subject', self::subject($match)),
            self::body_field($match, 'Body'),
        ];
    }

    private static function body_field(array $match, string $label): array {
        $body = self::application($match);
        return self::field('delivery-body', $label, $body, $body === '' ? self::NOT_WRITTEN : '', true);
    }

    private static function field(string $id, string $label, string $value, string $hint = '', bool $multiline = false): array {
        return ['id' => $id, 'label' => $label, 'value' => $value, 'hint' => $hint, 'multiline' => $multiline];
    }

    /** "The post also asks for answers to questions." or '': what it asks besides the subject. */
    private static function asks(array $match): string {
        $asks = array_diff((array) ($match['apply_instructions']['asks'] ?? []), ['subject']);
        $words = array_values(array_intersect_key(Apply_instructions::ASKS, array_flip($asks)));
        return $words ? 'The post also asks for ' . implode(', ', $words) . '.' : '';
    }

    private static function files(array $match): array {
        $tailored = trim((string) ($match['resume_text'] ?? '')) !== '';
        return [
            ['kind' => 'application', 'label' => 'Application', 'ready' => self::application($match) !== '', 'missing' => self::NOT_WRITTEN],
            ['kind' => $tailored ? 'resume' : 'cv', 'label' => $tailored ? 'Tailored résumé' : 'CV',
                'ready' => trim((string) ($match['cv_text'] ?? '')) !== '', 'missing' => 'There is no CV saved.'],
        ];
    }

    /** "Label: why", for each file that can't be downloaded yet. */
    private static function missing(array $files): array {
        return array_map(fn($file) => "{$file['label']}: {$file['missing']}", array_values(array_filter($files, fn($file) => !$file['ready'])));
    }

    private static function mailto_link(array $match): array {
        return ['href' => self::mailto(self::to($match), self::subject($match)), 'label' => 'Open in your email app', 'target' => '_self'];
    }

    private static function job_link(array $match): ?array {
        $url = self::job_url($match);
        return $url !== '' ? ['href' => $url, 'label' => 'Open the job post', 'target' => '_blank'] : null;
    }

    private static function job_url(array $match): string {
        return trim((string) ($match['job_url'] ?? ''));
    }

    private static function application(array $match): string {
        return trim((string) ($match['application_text'] ?? ''));
    }

}

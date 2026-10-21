<?php
require_once __DIR__ . '/Apply_instructions.php';
require_once __DIR__ . '/Joblog.php';

/**
 * A saved match as an email to the employer, for applying by email: who it
 * goes to, the subject, the body (the written application) and a mailto
 * link with the recipient and subject, each for the candidate to copy, plus
 * the PDFs to attach. A mailto link can't carry files, so they are
 * downloaded next to it.
 *
 * read() fills it by rule from the saved match (no model call):
 * - to: the address the post wants applications sent to
 *   (Apply_instructions), else the contact person's email (Joblog);
 * - subject: the subject line the post asks for, else the job title;
 * - asks: what else the post asks for, in plain words, as a reminder.
 */
class Apply_email {

    /**
     * The email for a saved match (as Cv_match::saved_match gives it).
     *
     * @return array{to: string, to_note: string, subject: string, body: string, mailto: string, asks: list<string>, files: list<array>}
     */
    public static function read(array $match): array {
        $apply = $match['apply_instructions'] ?? null;
        $to = self::recipient($match, $apply);
        $subject = self::subject($match, $apply);
        $written = trim((string) ($match['application_text'] ?? ''));
        $resume = trim((string) ($match['resume_text'] ?? '')) !== '';
        return [
            'to' => $to,
            'to_note' => self::recipient_note($to, $apply),
            'subject' => $subject,
            'body' => $written,
            'mailto' => self::mailto($to, $subject),
            'asks' => self::asks($apply),
            'files' => [
                ['kind' => 'application', 'label' => 'Application', 'ready' => $written !== '',
                    'missing' => 'Write the application first (Write application).'],
                ['kind' => $resume ? 'resume' : 'cv', 'label' => $resume ? 'Tailored résumé' : 'CV',
                    'ready' => trim((string) ($match['cv_text'] ?? '')) !== '', 'missing' => 'There is no CV saved.'],
            ],
        ];
    }

    /** Where the application goes: the post's apply address, else its contact person's. */
    public static function recipient(array $match, ?array $apply): string {
        $email = trim((string) ($apply['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($match['job_text'] ?? ''));
        return Joblog::contact($text, (string) ($match['company'] ?? ''))['email'];
    }

    /** The subject line the post asks for, else the job title. */
    public static function subject(array $match, ?array $apply): string {
        $subject = trim((string) ($apply['subject'] ?? ''));
        return $subject !== '' ? $subject : trim((string) ($match['job_title'] ?? ''));
    }

    /** mailto:{to}?subject={subject}, or '' without a recipient. */
    public static function mailto(string $to, string $subject): string {
        if ($to === '') {
            return '';
        }
        $link = 'mailto:' . str_replace('%40', '@', rawurlencode($to));
        return $subject !== '' ? $link . '?subject=' . rawurlencode($subject) : $link;
    }

    private static function recipient_note(string $to, ?array $apply): string {
        if ($to === '') {
            return 'The post names no email address: look for one in the post.';
        }
        return trim((string) ($apply['email'] ?? '')) !== ''
            ? 'The address the post asks applications to be sent to.'
            : 'The contact person in the post: the post doesn\'t say where to send applications.';
    }

    /** @return list<string> What the post asks for besides the subject, in plain words. */
    private static function asks(?array $apply): array {
        $asks = array_diff((array) ($apply['asks'] ?? []), ['subject']);
        return array_values(array_map(fn($ask) => Apply_instructions::ASKS[$ask], array_intersect($asks, array_keys(Apply_instructions::ASKS))));
    }

}

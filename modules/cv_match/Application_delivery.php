<?php
require_once __DIR__ . '/Apply_instructions.php';

/**
 * How the application for a saved match is delivered, the way the post
 * asks for it, with what the candidate needs at hand:
 * - by email, when the post names an address to send applications to
 *   (Apply_instructions): the recipient, the subject (the one the post asks
 *   for, else the job title), the body (the written application) and a
 *   mailto link with the recipient and subject;
 * - otherwise on the employer's side (an application form, LinkedIn Easy
 *   Apply): the link to the job post and the application to paste in.
 * Either way the application and résumé PDFs to attach or upload (a mailto
 * link can't carry files), and what else the post asks for.
 *
 * read() fills it by rule from the saved match (no model call).
 */
class Application_delivery {

    /**
     * How the application for a saved match is delivered (as Cv_match::saved_match gives it).
     *
     * @return array{by: string, to: string, subject: string, body: string, mailto: string, job_url: string, asks: list<string>, files: list<array>}
     */
    public static function read(array $match): array {
        $apply = $match['apply_instructions'] ?? null;
        $to = trim((string) ($apply['email'] ?? ''));
        $subject = self::subject($match, $apply);
        $written = trim((string) ($match['application_text'] ?? ''));
        $resume = trim((string) ($match['resume_text'] ?? '')) !== '';
        return [
            'by' => $to !== '' ? 'email' : 'form',
            'to' => $to,
            'subject' => $subject,
            'body' => $written,
            'mailto' => self::mailto($to, $subject),
            'job_url' => trim((string) ($match['job_url'] ?? '')),
            'asks' => self::asks($apply),
            'files' => [
                ['kind' => 'application', 'label' => 'Application', 'ready' => $written !== '',
                    'missing' => 'Write the application first (Write application).'],
                ['kind' => $resume ? 'resume' : 'cv', 'label' => $resume ? 'Tailored résumé' : 'CV',
                    'ready' => trim((string) ($match['cv_text'] ?? '')) !== '', 'missing' => 'There is no CV saved.'],
            ],
        ];
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

    /** @return list<string> What the post asks for besides the subject, in plain words. */
    private static function asks(?array $apply): array {
        $asks = array_diff((array) ($apply['asks'] ?? []), ['subject']);
        return array_values(array_map(fn($ask) => Apply_instructions::ASKS[$ask], array_intersect($asks, array_keys(Apply_instructions::ASKS))));
    }

}

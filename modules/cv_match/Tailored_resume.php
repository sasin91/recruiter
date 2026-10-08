<?php
/**
 * The tailored résumé as fields, shaped like sasin91.xyz's content/cv.toml:
 * a header (name, title, contact, links), intro paragraphs, experience and
 * education entries (title, organisation, location, dates, summary, bullets,
 * note), skills and languages, and the section headings in the post's
 * language.
 *
 * tailor_resume asks the model for schema() and passes its answer through
 * clean(); text() writes the plain-text version the page shows and copies,
 * and Pdf_writer::resume_pdf() lays the fields out. Cv_match_model stores them
 * in cv_match_resumes, cv_match_resume_entries and cv_match_resume_lines.
 */
class Tailored_resume {

    public const SECTIONS = ['experience', 'education'];

    // The headings the model names in the post's language, with the English
    // ones as the fallback.
    public const HEADINGS = [
        'experience_heading' => 'Experience',
        'skills_heading' => 'Skills',
        'education_heading' => 'Education',
        'languages_heading' => 'Languages',
    ];

    /** The JSON schema the model fills in. */
    public static function schema(): array {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];
        $entry = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'organisation', 'location', 'starts', 'ends', 'summary', 'bullets', 'note'],
            'properties' => [
                'title' => $string,
                'organisation' => $string,
                'location' => $string,
                'starts' => $string,
                'ends' => $string,
                'summary' => $string,
                'bullets' => $strings,
                'note' => $string,
            ],
        ];
        $properties = [
            'name' => $string,
            'title' => $string,
            'location' => $string,
            'phone' => $string,
            'email' => $string,
            'links' => $strings,
            'intro' => $strings,
            'experience' => ['type' => 'array', 'items' => $entry],
            'skills' => $strings,
            'languages' => $strings,
            'education_note' => $string,
            'education' => ['type' => 'array', 'items' => $entry],
        ];
        foreach (array_keys(self::HEADINGS) as $heading) {
            $properties[$heading] = $string;
        }
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * The model's answer trimmed and cut to the columns' lengths, with
     * nothing but strings and lists of strings; null when it has no name or
     * no entries at all.
     */
    public static function clean(mixed $answer): ?array {
        if (!is_array($answer)) {
            return null;
        }
        $resume = [
            'name' => self::string($answer['name'] ?? '', 255),
            'title' => self::string($answer['title'] ?? '', 255),
            'location' => self::string($answer['location'] ?? '', 255),
            'phone' => self::string($answer['phone'] ?? '', 64),
            'email' => self::string($answer['email'] ?? '', 255),
            'links' => self::strings($answer['links'] ?? [], 300),
            'intro' => self::strings($answer['intro'] ?? [], 2000),
            'skills' => self::strings($answer['skills'] ?? [], 1000),
            'languages' => self::strings($answer['languages'] ?? [], 1000),
            'education_note' => self::string($answer['education_note'] ?? '', 500),
        ];
        foreach (self::HEADINGS as $heading => $english) {
            $resume[$heading] = self::string($answer[$heading] ?? '', 64) ?: $english;
        }
        foreach (self::SECTIONS as $section) {
            $resume[$section] = [];
            foreach (is_array($answer[$section] ?? null) ? $answer[$section] : [] as $entry) {
                if (!is_array($entry) || self::string($entry['title'] ?? '', 255) === '') {
                    continue;
                }
                $resume[$section][] = [
                    'title' => self::string($entry['title'], 255),
                    'organisation' => self::string($entry['organisation'] ?? '', 255),
                    'location' => self::string($entry['location'] ?? '', 255),
                    'starts' => self::string($entry['starts'] ?? '', 32),
                    'ends' => self::string($entry['ends'] ?? '', 32),
                    'summary' => self::string($entry['summary'] ?? '', 500),
                    'bullets' => self::strings($entry['bullets'] ?? [], 1000),
                    'note' => self::string($entry['note'] ?? '', 1000),
                ];
            }
        }
        if ($resume['name'] === '' || (!$resume['experience'] && !$resume['education'])) {
            return null;
        }
        return $resume;
    }

    /**
     * The résumé as plain text that pastes cleanly: the header lines, the
     * intro, then each section under its heading, entries as
     * "Title · Organisation", their date and place line, and "- " bullets.
     */
    public static function text(array $resume): string {
        $header = array_filter([$resume['name'], $resume['title'], self::contact($resume), implode(' · ', $resume['links'])]);
        $blocks = [implode("\n", $header)];
        if ($resume['intro']) {
            $blocks[] = implode("\n\n", $resume['intro']);
        }
        if ($resume['experience']) {
            $blocks[] = $resume['experience_heading'] . "\n\n" . implode("\n\n", array_map([self::class, 'entry_text'], $resume['experience']));
        }
        if ($resume['skills']) {
            $blocks[] = $resume['skills_heading'] . "\n" . self::bullets($resume['skills']);
        }
        if ($resume['languages']) {
            $blocks[] = $resume['languages_heading'] . "\n" . self::bullets($resume['languages']);
        }
        if ($resume['education'] || $resume['education_note'] !== '') {
            $parts = array_filter([$resume['education_note'], ...array_map([self::class, 'entry_text'], $resume['education'])]);
            $blocks[] = $resume['education_heading'] . "\n\n" . implode("\n\n", $parts);
        }
        return implode("\n\n", $blocks);
    }

    /** "Slagelse, 4200 · +45 50106917 · name@example.com". */
    public static function contact(array $resume): string {
        return implode(' · ', array_filter([$resume['location'], $resume['phone'], $resume['email']], fn($part) => $part !== ''));
    }

    /** "Web Developer · JUICE ApS". */
    public static function entry_heading(array $entry): string {
        return implode(' · ', array_filter([$entry['title'], $entry['organisation']], fn($part) => $part !== ''));
    }

    /** "September 2024 – February 2026 — Copenhagen". */
    public static function entry_meta(array $entry): string {
        $dates = implode(' – ', array_filter([$entry['starts'], $entry['ends']], fn($part) => $part !== ''));
        return implode(' — ', array_filter([$dates, $entry['location']], fn($part) => $part !== ''));
    }

    private static function entry_text(array $entry): string {
        $lines = array_filter([self::entry_heading($entry), self::entry_meta($entry), $entry['summary']], fn($line) => $line !== '');
        $text = implode("\n", $lines);
        if ($entry['bullets']) {
            $text .= "\n" . self::bullets($entry['bullets']);
        }
        if ($entry['note'] !== '') {
            $text .= "\n\n" . $entry['note'];
        }
        return $text;
    }

    private static function bullets(array $items): string {
        return implode("\n", array_map(fn($item) => "- $item", $items));
    }

    private static function string(mixed $value, int $length): string {
        return is_string($value) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $length, 'UTF-8') : '';
    }

    private static function strings(mixed $values, int $length): array {
        $clean = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $value = self::string($value, $length);
            if ($value !== '') {
                $clean[] = $value;
            }
        }
        return $clean;
    }
}

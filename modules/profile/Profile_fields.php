<?php
/**
 * The candidate profile's fields (candidate_profiles, candidate_profile_entries,
 * candidate_profile_lines) on their way in and out:
 *
 * - schema() and from_cv(): what the model reads a CV into, cleaned to the
 *   columns, with its "2024-09" months turned into dates.
 * - entry_from_form(): one entry as the profile page's form posted it.
 * - text(): the whole profile as plain text, what applications and tailored
 *   résumés are written from (Profile::_text_for_user).
 */
class Profile_fields {

    public const SECTIONS = ['experience' => 'Experience', 'project' => 'Projects', 'education' => 'Education'];
    public const LISTS = ['skill' => 'Skills', 'language' => 'Languages', 'certificate' => 'Certificates'];

    /** The JSON schema the model fills in from a CV. */
    public static function schema(): array {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];
        $entry = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['section', 'title', 'organisation', 'location', 'url', 'started', 'ended', 'summary', 'bullets', 'note'],
            'properties' => [
                'section' => ['type' => 'string', 'enum' => array_keys(self::SECTIONS)],
                'title' => $string,
                'organisation' => $string,
                'location' => $string,
                'url' => $string,
                'started' => $string,
                'ended' => $string,
                'summary' => $string,
                'bullets' => $strings,
                'note' => $string,
            ],
        ];
        $properties = [
            'name' => $string,
            'phone' => $string,
            'title' => $string,
            'location' => $string,
            'about' => $strings,
            'entries' => ['type' => 'array', 'items' => $entry],
        ];
        foreach (array_keys(self::LISTS) as $kind) {
            $properties[$kind . 's'] = $strings;
        }
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * The model's answer as the profile's rows: ['title', 'location', 'about',
     * 'entries' => [[section, title, ..., started_on, ended_on, bullets], ...],
     * 'lines' => [kind => [text, ...]]]; null when it found no entries at all.
     */
    public static function from_cv(mixed $answer): ?array {
        if (!is_array($answer)) {
            return null;
        }
        $entries = [];
        foreach (is_array($answer['entries'] ?? null) ? $answer['entries'] : [] as $entry) {
            if (!is_array($entry) || !isset(self::SECTIONS[$entry['section'] ?? '']) || self::string($entry['title'] ?? '', 255) === '') {
                continue;
            }
            $entries[] = self::entry($entry, self::month_date($entry['started'] ?? ''), self::month_date($entry['ended'] ?? ''), self::strings($entry['bullets'] ?? [], 1000));
        }
        if (!$entries) {
            return null;
        }
        $lines = [];
        foreach (array_keys(self::LISTS) as $kind) {
            $lines[$kind] = self::strings($answer[$kind . 's'] ?? [], 1000);
        }
        return [
            'name' => self::string($answer['name'] ?? '', 255),
            'phone' => self::string($answer['phone'] ?? '', 32),
            'title' => self::string($answer['title'] ?? '', 255),
            'location' => self::string($answer['location'] ?? '', 255),
            'about' => self::about(self::strings($answer['about'] ?? [], 2000)),
            'entries' => $entries,
            'lines' => $lines,
        ];
    }

    /**
     * One entry as the page's form posted it ($post: section, title,
     * organisation, location, url, started and ended as "2024-09", summary,
     * bullets one per line, note): ['entry' => [...], 'errors' => [...]].
     */
    public static function entry_from_form(array $post): array {
        $errors = [];
        $section = (string) ($post['section'] ?? '');
        if (!isset(self::SECTIONS[$section])) {
            $errors[] = 'Pick experience, project or education.';
        }
        if (self::string($post['title'] ?? '', 255) === '') {
            $errors[] = 'Give it a title, like the job title or the project\'s name.';
        }
        $started = self::month_date($post['started'] ?? '');
        $ended = self::month_date($post['ended'] ?? '');
        if ($started !== null && $ended !== null && $ended < $started) {
            $errors[] = 'It ends before it starts.';
        }
        $url = self::string($post['url'] ?? '', 512);
        if ($url !== '' && !preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'The link isn\'t a web address (https://...).';
        }
        $bullets = self::strings(preg_split('/\R/u', (string) ($post['bullets'] ?? '')) ?: [], 1000);
        return [
            'entry' => self::entry(['url' => $url] + $post, $started, $ended, $bullets),
            'errors' => $errors,
        ];
    }

    /** The header as its form posted it: name, phone, title, location, and about with its paragraphs kept. */
    public static function header_from_form(array $post): array {
        $paragraphs = self::strings(preg_split('/\R\s*\R/u', (string) ($post['about'] ?? '')) ?: [], 2000);
        return [
            'name' => self::string($post['name'] ?? '', 255),
            'phone' => self::string($post['phone'] ?? '', 32),
            'title' => self::string($post['title'] ?? '', 255),
            'location' => self::string($post['location'] ?? '', 255),
            'about' => self::about($paragraphs),
        ];
    }

    /** A list (skills, languages, certificates) as its textarea posted it: one item per line. */
    public static function list_from_form(string $text): array {
        return self::strings(preg_split('/\R/u', $text) ?: [], 1000);
    }

    /**
     * The profile as plain text: name, title, contact and links; about; then
     * the entries by section, each "Title · Organisation", its months and
     * place, summary, "- " bullets and note; then the lists.
     * $candidate: name, email, phone; $profile: title, location, about;
     * $entries: rows with bullets; $lines: kind => [text, ...]; $links:
     * [['label', 'url'], ...].
     */
    public static function text(array $candidate, array $profile, array $entries, array $lines, array $links): string {
        $contact = implode(' · ', array_filter([$profile['location'] ?? '', $candidate['phone'] ?? '', $candidate['email'] ?? '']));
        $link_line = implode(' · ', array_map(fn(array $link) => "{$link['label']}: {$link['url']}", $links));
        $blocks = [implode("\n", array_filter([$candidate['name'] ?? '', $profile['title'] ?? '', $contact, $link_line]))];
        if (trim((string) ($profile['about'] ?? '')) !== '') {
            $blocks[] = trim($profile['about']);
        }
        foreach (self::SECTIONS as $section => $heading) {
            $texts = array_map([self::class, 'entry_text'], array_filter($entries, fn(array $entry) => $entry['section'] === $section));
            if ($texts) {
                $blocks[] = $heading . "\n\n" . implode("\n\n", $texts);
            }
        }
        foreach (self::LISTS as $kind => $heading) {
            if (!empty($lines[$kind])) {
                $blocks[] = $heading . "\n" . implode("\n", array_map(fn($item) => "- $item", $lines[$kind]));
            }
        }
        return implode("\n\n", $blocks);
    }

    /** "September 2024 – February 2026", "January 2023 – now", or ''. */
    public static function months(?string $started_on, ?string $ended_on): string {
        if ($started_on === null && $ended_on === null) {
            return '';
        }
        $month = fn(?string $date) => $date !== null ? date('F Y', strtotime($date)) : '';
        return $month($started_on) . ' – ' . ($ended_on !== null ? $month($ended_on) : 'now');
    }

    /** "2024-09-01" from "2024-09", "2024-09-15" or "2024"; null for anything else. */
    public static function month_date(mixed $value): ?string {
        if (!is_string($value) || !preg_match('/^(\d{4})(?:-(\d{1,2}))?/', trim($value), $m)) {
            return null;
        }
        $month = (int) ($m[2] ?? 1);
        return $month >= 1 && $month <= 12 ? sprintf('%04d-%02d-01', (int) $m[1], $month) : null;
    }

    /** The paragraphs as candidate_profiles.about takes them: cut to the TEXT column's 65,535 bytes, never inside a character. */
    private static function about(array $paragraphs): string {
        return mb_strcut(implode("\n\n", $paragraphs), 0, 65535, 'UTF-8');
    }

    private static function entry(array $fields, ?string $started_on, ?string $ended_on, array $bullets): array {
        return [
            'section' => (string) ($fields['section'] ?? ''),
            'title' => self::string($fields['title'] ?? '', 255),
            'organisation' => self::string($fields['organisation'] ?? '', 255),
            'location' => self::string($fields['location'] ?? '', 255),
            'url' => self::string($fields['url'] ?? '', 512),
            'started_on' => $started_on,
            'ended_on' => $ended_on,
            'summary' => self::string($fields['summary'] ?? '', 500),
            'note' => self::string($fields['note'] ?? '', 1000),
            'bullets' => $bullets,
        ];
    }

    private static function entry_text(array $entry): string {
        $heading = implode(' · ', array_filter([$entry['title'], $entry['organisation']]));
        $meta = implode(' — ', array_filter([self::months($entry['started_on'], $entry['ended_on']), $entry['location'], $entry['url']]));
        $text = implode("\n", array_filter([$heading, $meta, $entry['summary']]));
        if ($entry['bullets']) {
            $text .= "\n" . implode("\n", array_map(fn($bullet) => "- $bullet", $entry['bullets']));
        }
        if ($entry['note'] !== '') {
            $text .= "\n" . $entry['note'];
        }
        return $text;
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

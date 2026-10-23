<?php
require_once __DIR__ . '/Profile_fields.php';

/**
 * The profile page as views/profile.php prints it: the header form (with
 * the candidate's name and phone from candidates), one
 * form per entry by section (and an empty one to add an entry), and the
 * lists' form. $posted_entry is an entry form posted with errors, printed
 * again in its place.
 */
class Profile_page {

    /**
     * ['has_profile', 'filled_note', 'header' => [title, location, about],
     *  'sections' => [['section', 'heading', 'add_label', 'entries' => [form, ...], 'new' => form], ...],
     *  'lists' => [['kind', 'label', 'text'], ...]]; a form is entry_form()'s.
     */
    public static function from(array $candidate, ?array $header, array $entries, array $lines, ?array $posted_entry = null): array {
        $sections = [];
        foreach (Profile_fields::SECTIONS as $section => $heading) {
            $forms = [];
            foreach ($entries as $entry) {
                if ($entry['section'] === $section) {
                    $forms[] = self::posted_in_place($posted_entry, $entry['id']) ?? self::entry_form($entry);
                }
            }
            $new = self::entry_form(['section' => $section]);
            if ($posted_entry !== null && $posted_entry['id'] === null && $posted_entry['section'] === $section) {
                $new = $posted_entry;
            }
            $sections[] = [
                'section' => $section,
                'heading' => $heading,
                'add_label' => self::ADD_LABELS[$section],
                'entries' => $forms,
                'new' => $new,
            ];
        }
        $lists = [];
        foreach (Profile_fields::LISTS as $kind => $label) {
            $lists[] = ['kind' => $kind, 'label' => $label, 'text' => implode("\n", $lines[$kind] ?? [])];
        }
        return [
            'has_profile' => $header !== null,
            'filled_note' => self::filled_note($header),
            'header' => [
                'name' => (string) ($candidate['name'] ?? ''),
                'phone' => (string) ($candidate['phone'] ?? ''),
                'title' => (string) ($header['title'] ?? ''),
                'location' => (string) ($header['location'] ?? ''),
                'about' => (string) ($header['about'] ?? ''),
            ],
            'sections' => $sections,
            'lists' => $lists,
        ];
    }

    private const ADD_LABELS = ['experience' => 'Add a job', 'project' => 'Add a project', 'education' => 'Add an education'];

    /**
     * One entry's form: its fields as the inputs take them (months as
     * "2024-09", bullets one per line), the line its <summary> shows, and
     * errors (none for a saved entry).
     */
    public static function entry_form(array $entry, array $errors = []): array {
        $month = fn(?string $date) => $date !== null && $date !== '' ? substr($date, 0, 7) : '';
        $form = [
            'id' => isset($entry['id']) ? (int) $entry['id'] : null,
            'section' => (string) $entry['section'],
            'title' => (string) ($entry['title'] ?? ''),
            'organisation' => (string) ($entry['organisation'] ?? ''),
            'location' => (string) ($entry['location'] ?? ''),
            'url' => (string) ($entry['url'] ?? ''),
            'started' => $month($entry['started_on'] ?? null),
            'ended' => $month($entry['ended_on'] ?? null),
            'summary' => (string) ($entry['summary'] ?? ''),
            'bullets' => implode("\n", $entry['bullets'] ?? []),
            'note' => (string) ($entry['note'] ?? ''),
            'errors' => $errors,
        ];
        $form['line'] = implode(', ', array_filter([
            implode(' · ', array_filter([$form['title'], $form['organisation']])),
            Profile_fields::months($entry['started_on'] ?? null, $entry['ended_on'] ?? null),
        ]));
        $form['open'] = $errors !== [];
        // The prefix of its inputs' ids, unique on the page.
        $form['key'] = $form['id'] !== null ? 'entry-' . $form['id'] : 'new-' . $form['section'];
        return $form;
    }

    private static function posted_in_place(?array $posted_entry, int $id): ?array {
        return $posted_entry !== null && $posted_entry['id'] === $id ? $posted_entry : null;
    }

    /** "Filled from cv.pdf on 23 Oct 2026." or ''. */
    private static function filled_note(?array $header): string {
        if (empty($header['filled_from_cv_at'])) {
            return '';
        }
        $name = trim((string) $header['filled_from_cv_name']);
        return 'Filled from ' . ($name !== '' ? $name : 'a CV') . ' on ' . date('j M Y', (int) $header['filled_from_cv_at']) . '.';
    }
}

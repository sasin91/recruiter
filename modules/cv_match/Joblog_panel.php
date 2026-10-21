<?php
require_once __DIR__ . '/Joblog.php';

/**
 * A saved match's Jobnet joblog entry (Joblog::read) as the panel under its
 * application and résumé prints it (views/joblog_panel.php): Jobnet's
 * sections with each field to copy, a choice with its options and the one to
 * pick, the three PDFs, the "Fill it in with an AI" prompt and whether the
 * candidate has entered it in their joblog (views/joblog_logged.php).
 *
 * Each field carries what the AI prompt says for it (`prompt` on a choice's
 * options); js/app.js reads typed values from the inputs, edits included.
 */
class Joblog_panel {

    // The candidate picks how far they are here, saved with the match (joblog_status).
    public const CHOOSE = 'searchStatus';
    public const CHOOSE_HINT = 'Pick it here as you get along: it\'s saved with the match.';
    public const CHECKBOX_OPTIONS = ['yes' => 'Ja', 'no' => 'Nej'];
    public const CHARACTERS_LEFT = 'Antal tegn tilbage:';
    public const LOGGED_LABEL = 'I\'ve entered this job in my joblog on Jobnet';
    public const LOGGED_ON = 'Entered on';

    /**
     * ['id' => 7, 'sections' => [['heading', 'fields' => [field, ...]], ...],
     * 'files' => [['kind', 'label', 'ready', 'title'], ...], 'missing' =>
     * ["Jobansøgning: ...", ...], 'logged' => logged()].
     */
    public static function from(int $id, array $entry): array {
        return [
            'id' => $id,
            'sections' => array_map(fn(array $section) => [
                'heading' => $section['heading'],
                'fields' => array_map([self::class, 'field'], $section['fields']),
            ], $entry['sections']),
            'files' => array_map([self::class, 'file'], $entry['files']),
            'missing' => self::missing($entry['files']),
            'logged' => self::logged($entry['logged_at']),
        ];
    }

    /** ['checked' => bool, 'at' => ISO time or '']: whether, and when, it is in the candidate's joblog. */
    public static function logged(?int $logged_at): array {
        return ['checked' => $logged_at !== null, 'at' => $logged_at === null ? '' : gmdate('Y-m-d\\TH:i:s\\Z', $logged_at)];
    }

    /**
     * ['name', 'label', 'required', 'hint', 'choice' => bool, 'choose' => bool,
     * 'options' => [['value', 'text', 'chosen', 'prompt'], ...], 'value',
     * 'multiline', 'numeric', 'left' => characters left or null].
     */
    private static function field(array $field): array {
        $choice = in_array($field['type'], ['radio', 'checkbox'], true);
        $choose = $field['name'] === self::CHOOSE;
        $multiline = $field['type'] === 'textarea';
        $value = $choice ? '' : (string) $field['value'];
        return [
            'name' => $field['name'],
            'label' => $field['label'],
            'required' => $field['required'],
            'hint' => $choose ? self::CHOOSE_HINT : (string) ($field['hint'] ?? ''),
            'choice' => $choice,
            'choose' => $choose,
            'options' => $choice ? self::options($field) : [],
            'value' => $value,
            'multiline' => $multiline,
            'numeric' => $field['type'] === 'number',
            'left' => $multiline ? Joblog::MAX_NOTES - mb_strlen($value) : null,
        ];
    }

    private static function options(array $field): array {
        if ($field['type'] === 'checkbox') {
            return [
                self::option('yes', self::CHECKBOX_OPTIONS['yes'], $field['value'] === true, 'tick it'),
                self::option('no', self::CHECKBOX_OPTIONS['no'], $field['value'] !== true, 'leave it unticked'),
            ];
        }
        $options = [];
        foreach ($field['options'] as $value => $text) {
            $options[] = self::option((string) $value, $text, $value === $field['value'], "choose \"$text\"");
        }
        return $options;
    }

    private static function option(string $value, string $text, bool $chosen, string $prompt): array {
        return ['value' => $value, 'text' => $text, 'chosen' => $chosen, 'prompt' => $prompt];
    }

    /** A PDF button: its title says what it is, or why it can't be made yet. */
    private static function file(array $file): array {
        return ['kind' => $file['kind'], 'label' => "{$file['label']} (PDF)", 'ready' => $file['ready'],
            'title' => $file['ready'] ? (string) ($file['note'] ?? '') : $file['missing']];
    }

    /** "Jobansøgning: Skriv ansøgningen først (Write application)." for each PDF that can't be made yet. */
    private static function missing(array $files): array {
        return array_values(array_map(fn(array $file) => "{$file['label']}: {$file['missing']}", array_filter($files, fn(array $file) => !$file['ready'])));
    }
}

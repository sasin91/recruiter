<?php
/**
 * A candidate's profile links (candidate_profile_links): LinkedIn, GitHub, a
 * portfolio, each a label and a URL, for applications that ask for them.
 * Edited on the account page, offered to copy in the Apply dialog.
 *
 * read() checks what the account page's form posted; form_rows() is what
 * the form prints.
 */
class Profile_links {

    /** The labels the form offers before anything is saved. */
    public const SUGGESTED_LABELS = ['LinkedIn', 'GitHub', 'Portfolio'];
    public const MAX = 10;
    public const NOT_SAVED = 'Your links could not be saved, so the ones saved before are kept. Check that no two names are the same apart from accents or case, and try again.';

    /**
     * The form's rows: the saved links, then the suggested labels not used
     * yet with an empty URL, then one empty row, at most MAX.
     * [['label' => 'LinkedIn', 'url' => 'https://...'], ...]
     */
    public static function form_rows(array $saved): array {
        $rows = array_map(fn(array $link) => ['label' => (string) $link['label'], 'url' => (string) $link['url']], $saved);
        $used = array_map('mb_strtolower', array_column($rows, 'label'));
        foreach (self::SUGGESTED_LABELS as $label) {
            if (!in_array(mb_strtolower($label), $used, true)) {
                $rows[] = ['label' => $label, 'url' => ''];
            }
        }
        $rows[] = ['label' => '', 'url' => ''];
        return array_slice($rows, 0, self::MAX);
    }

    /** The form's rows as posted ($labels[i] with $urls[i]), to print again. */
    public static function posted_rows(array $labels, array $urls): array {
        return array_map(fn($label, $url) => ['label' => (string) $label, 'url' => (string) $url], $labels, $urls);
    }

    /**
     * The links the form posted ($labels[i] with $urls[i]), in order:
     * ['links' => [['label', 'url'], ...], 'errors' => ["...", ...]].
     * A row without a URL is left out; a URL without a scheme gets https://.
     */
    public static function read(array $labels, array $urls): array {
        $links = [];
        $errors = [];
        foreach (array_slice(array_values($urls), 0, self::MAX) as $i => $url) {
            $url = trim((string) $url);
            $label = trim((string) (array_values($labels)[$i] ?? ''));
            if ($url === '') {
                continue;
            }
            $url = self::with_scheme($url);
            $error = self::problem($label, $url, array_column($links, 'label'));
            if ($error !== '') {
                $errors[] = $error;
                continue;
            }
            $links[] = ['label' => $label, 'url' => $url];
        }
        return ['links' => $links, 'errors' => $errors];
    }

    private static function with_scheme(string $url): string {
        return preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) ? $url : 'https://' . $url;
    }

    /** What is wrong with a link, or ''. */
    private static function problem(string $label, string $url, array $labels_before): string {
        if ($label === '') {
            return "Give $url a name, like LinkedIn.";
        }
        if (mb_strlen($label) > 64) {
            return "The name \"$label\" is too long (64 characters at most).";
        }
        if (in_array(mb_strtolower($label), array_map('mb_strtolower', $labels_before), true)) {
            return "There are two links named $label.";
        }
        if (strlen($url) > 512 || filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('~^https?://~i', $url)) {
            return "The $label link isn't a web address (https://...).";
        }
        return '';
    }

}

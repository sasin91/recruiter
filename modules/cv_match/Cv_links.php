<?php
/**
 * The profile links a CV names (LinkedIn, GitHub, a website), read by rule
 * from its text, for the Apply dialog when the candidate hasn't saved any
 * on the account page (or isn't a candidate, like an administrator).
 *
 * Any http(s) address counts; without a scheme only linkedin.com/in/...,
 * github.com/... and gitlab.com/... do, as "Node.js/React" would otherwise
 * read as one.
 */
class Cv_links {

    public const LABELS = ['linkedin.com' => 'LinkedIn', 'github.com' => 'GitHub', 'gitlab.com' => 'GitLab'];
    public const WEBSITE = 'Website';

    private const ADDRESS = '~(?<![@\w.])(?:https?://|(?:www\.)?(?:linkedin\.com/in|github\.com|gitlab\.com)/)[^\s<>"\'·]+~i';

    /** [['label' => 'LinkedIn', 'url' => 'https://...'], ...], in the CV's order, each address once. */
    public static function read(string $cv_text): array {
        preg_match_all(self::ADDRESS, $cv_text, $found);
        $links = [];
        foreach ($found[0] as $address) {
            $url = self::with_scheme(self::without_trailing_punctuation($address));
            $host = strtolower(preg_replace('~^www\.~', '', (string) parse_url($url, PHP_URL_HOST)));
            if ($host === '' || isset($links[$url])) {
                continue;
            }
            $links[$url] = ['label' => self::LABELS[$host] ?? self::WEBSITE, 'url' => $url];
        }
        return array_values($links);
    }

    /**
     * The address without the prose punctuation after it ("see x.com/a, or
     * (x.com/b)."), keeping what belongs to it (";view=full", "(1)").
     */
    private static function without_trailing_punctuation(string $address): string {
        while ($address !== '') {
            $last = substr($address, -1);
            $unbalanced = $last === ')' && substr_count($address, '(') < substr_count($address, ')');
            if (!$unbalanced && !str_contains('.,;:!?\'"', $last)) {
                return $address;
            }
            $address = substr($address, 0, -1);
        }
        return $address;
    }

    private static function with_scheme(string $address): string {
        return preg_match('~^https?://~i', $address) ? $address : 'https://' . $address;
    }

}

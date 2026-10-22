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

    private const ADDRESS = '~(?<![@\w.])(?:https?://[^\s<>"\'()·,;]+|(?:www\.)?(?:linkedin\.com/in|github\.com|gitlab\.com)/[^\s<>"\'()·,;]+)~i';

    /** [['label' => 'LinkedIn', 'url' => 'https://...'], ...], in the CV's order, each address once. */
    public static function read(string $cv_text): array {
        preg_match_all(self::ADDRESS, $cv_text, $found);
        $links = [];
        foreach ($found[0] as $address) {
            $url = self::with_scheme(rtrim($address, '.:!?'));
            $host = strtolower(preg_replace('~^www\.~', '', (string) parse_url($url, PHP_URL_HOST)));
            if ($host === '' || isset($links[$url])) {
                continue;
            }
            $links[$url] = ['label' => self::LABELS[$host] ?? self::WEBSITE, 'url' => $url];
        }
        return array_values($links);
    }

    private static function with_scheme(string $address): string {
        return preg_match('~^https?://~i', $address) ? $address : 'https://' . $address;
    }

}

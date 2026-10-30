<?php
/**
 * Reading the job search form: the sites box as domains, and the limits
 * the form and the scan keep to.
 */
final class Job_search_rules {

    public const MAX_SITES = 20;

    /** How often a search can be scanned, in hours, as the form offers it. */
    public const SCAN_EVERY = [self::ONLY_BY_HAND => 'Only when I scan it', 12 => 'Twice a day', 24 => 'Every day', 72 => 'Every 3 days', 168 => 'Every week'];

    /** The interval of a search that is never due: it is scanned by "Scan now" or scan.php --search. */
    public const ONLY_BY_HAND = 0;

    /** "Every day", or "36 h" for an interval the form doesn't offer. */
    public static function frequency(int $hours): string {
        return self::SCAN_EVERY[$hours] ?? $hours . ' h';
    }

    /**
     * The sites box (one per line, or separated by commas or spaces) as
     * lowercase domains without "www.", in order and without repeats.
     * Pasted links are cut down to their domain ("https://www.jobnet.dk/find-job"
     * is "jobnet.dk"). Anything that isn't a domain is returned in $bad.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function sites(string $text): array {
        $sites = [];
        $bad = [];
        foreach (preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            $host = strtolower($entry);
            if (str_contains($host, '://')) {
                $host = (string) parse_url($host, PHP_URL_HOST);
            }
            $host = preg_replace('/^www\./', '', explode('/', $host)[0]);
            if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host) && strlen($host) <= 255) {
                $sites[$host] = $host;
            } else {
                $bad[] = $entry;
            }
        }
        return [array_values($sites), $bad];
    }

    /**
     * A pasted shortlist as posts: a JSON array of {url, title, company,
     * location} or of links, or {"posts": [...]} as job_scan/api takes it, in
     * a Markdown code fence or not. Anything else, broken JSON too, is read
     * as text with links in it. Whether a link is any good is add_posts' call.
     *
     * @return list<array{url: string}>
     */
    public static function shortlist(string $text): array {
        $json = trim(preg_replace('/^\s*```[a-z]*\s*|\s*```\s*$/i', '', $text));
        $list = json_decode($json, true);
        $list = is_array($list) && isset($list['posts']) ? $list['posts'] : $list;
        if (is_array($list) && array_is_list($list)) {
            $posts = [];
            foreach ($list as $post) {
                $post = is_string($post) ? ['url' => $post] : $post;
                if (is_array($post) && is_string($post['url'] ?? null)) {
                    $posts[] = $post;
                }
            }
            return $posts;
        }
        preg_match_all('~https?://[^\s"\'<>\])]+~i', $text, $links);
        return array_map(fn(string $url) => ['url' => rtrim($url, '.,;')], array_values(array_unique($links[0])));
    }

    /**
     * A url as the same post is always written: lowercase scheme and host,
     * no "www.", no fragment, no tracking parameters (utm_*, fbclid, gclid,
     * refId and the like) and no trailing slash.
     */
    public static function normal_url(string $url): string {
        $parts = parse_url(trim($url));
        if ($parts === false || empty($parts['host'])) {
            return trim($url);
        }
        $host = preg_replace('/^www\./', '', strtolower($parts['host']));
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn($key) => !preg_match('/^(utm_|fbclid$|gclid$|mc_|ref$|refid$|trackingid$|trk)/i', (string) $key), ARRAY_FILTER_USE_KEY);
        ksort($query);
        return strtolower($parts['scheme'] ?? 'https') . '://' . $host
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . rtrim($parts['path'] ?? '', '/')
            . ($query ? '?' . http_build_query($query) : '');
    }

}

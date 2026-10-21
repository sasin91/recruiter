<?php
/**
 * Where each browser gets the Jobnet joblog extension
 * (extensions/jobnet-joblog): its listing in that browser's store. Empty
 * until the listing is published; a browser without a link gets no banner
 * and the page shows how to load the folder instead.
 */
class Store_links {

    public const CHROME = '';
    public const EDGE = '';
    public const FIREFOX = '';

    // The only hosts a link may point at, by browser.
    private const HOSTS = [
        'chrome' => 'chromewebstore.google.com',
        'edge' => 'microsoftedge.microsoft.com',
        'firefox' => 'addons.mozilla.org',
    ];

    // What each link installs into, as the extension page names it.
    public const NAMES = [
        'chrome' => 'Chrome, Brave, Opera, Vivaldi',
        'edge' => 'Microsoft Edge',
        'firefox' => 'Firefox',
    ];

    /** The published links by browser ('chrome', 'edge', 'firefox'). */
    public static function all(): array {
        return self::listed(['chrome' => self::CHROME, 'edge' => self::EDGE, 'firefox' => self::FIREFOX]);
    }

    /**
     * The links from $links that are set and point at their own browser's
     * store over https: a link pasted in the wrong place is left out rather
     * than sending people to the wrong store.
     */
    public static function listed(array $links): array {
        $listed = [];
        foreach (self::HOSTS as $browser => $host) {
            $link = trim((string) ($links[$browser] ?? ''));
            $parts = parse_url($link);
            if ($link !== '' && ($parts['scheme'] ?? '') === 'https' && strtolower($parts['host'] ?? '') === $host) {
                $listed[$browser] = $link;
            }
        }
        return $listed;
    }

    /**
     * The extension page's "Get it" list: one {name, url} per published
     * link, and whether some browser has none yet (the page then says how
     * to load the folder).
     */
    public static function for_page(array $links): array {
        $list = [];
        foreach ($links as $browser => $link) {
            $list[] = ['name' => self::NAMES[$browser], 'url' => $link];
        }
        return ['stores' => $list, 'some_unlisted' => count($links) < count(self::HOSTS)];
    }

}

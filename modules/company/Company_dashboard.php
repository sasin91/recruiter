<?php
require_once __DIR__ . '/Company_post_row.php';

/**
 * The company dashboard (views/home.php): the greeting, key numbers, the
 * tabs of job posts with the open tab's rows, and the members line.
 */
class Company_dashboard {

    // The tabs and the post statuses each shows.
    private const TABS = ['active' => ['active', 'paused'], 'drafts' => ['draft'], 'closed' => ['closed', 'archived']];
    private const TAB_NAMES = ['active' => 'Live', 'drafts' => 'Drafts', 'closed' => 'Closed'];

    /**
     * $posts as Job_posts::overview, $asked_tab from ?tab= (null without one).
     * ['greeting', 'is_owner', 'tab', 'tabs' => [['key', 'name', 'count', 'current']], 'posts', 'totals', 'members_line'].
     */
    public static function from(array $member, array $members, array $posts, ?string $asked_tab): array {
        $counts = array_map(fn(array $statuses) => count(self::with_status($posts, $statuses)), self::TABS);
        $tab = self::open_tab($asked_tab, $counts);
        return [
            'greeting' => 'Hi ' . explode(' ', (string) $member['name'])[0],
            'is_owner' => $member['role'] === 'owner',
            'tab' => $tab,
            'tabs' => self::tabs($tab, $counts),
            'posts' => array_map([Company_post_row::class, 'from'], self::with_status($posts, self::TABS[$tab])),
            'totals' => self::totals($posts),
            'members_line' => self::members_line($members),
        ];
    }

    /** The asked tab; without one, Drafts when there is nothing live but drafts, else Live. */
    private static function open_tab(?string $asked_tab, array $counts): string {
        if ($asked_tab !== null) {
            return isset(self::TABS[$asked_tab]) ? $asked_tab : 'active';
        }
        return $counts['active'] === 0 && $counts['drafts'] > 0 ? 'drafts' : 'active';
    }

    private static function tabs(string $open, array $counts): array {
        return array_map(
            fn(string $key) => ['key' => $key, 'name' => self::TAB_NAMES[$key], 'count' => $counts[$key], 'current' => $key === $open],
            array_keys(self::TABS)
        );
    }

    /** Live posts (and on live and paused ones: applications, shortlisted), and everything new. */
    private static function totals(array $posts): array {
        $live = self::with_status($posts, self::TABS['active']);
        return [
            'live' => count(self::with_status($live, ['active'])),
            'applications' => array_sum(array_column($live, 'applications')),
            'new' => array_sum(array_column($posts, 'new')),
            'shortlisted' => array_sum(array_column($live, 'shortlisted')),
        ];
    }

    /** "3 active, 1 invited." */
    private static function members_line(array $members): string {
        $active = count(array_filter($members, fn(array $m) => (int) $m['active'] === 1 && !$m['invite_pending']));
        $invited = count(array_filter($members, fn(array $m) => (bool) $m['invite_pending']));
        return $active . ' active' . ($invited ? ", $invited invited" : '') . '.';
    }

    private static function with_status(array $posts, array $statuses): array {
        return array_values(array_filter($posts, fn(array $p) => in_array($p['status'], $statuses, true)));
    }
}

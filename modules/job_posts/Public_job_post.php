<?php
require_once __DIR__ . '/Job_post_rules.php';

/**
 * A job post as its public page shows it (views/show.php): the facts line,
 * the responsibilities, and the requirements as tags in three lists.
 * Required ones come first, alternatives (an OR-group) share one tag, then
 * nice-to-haves and personal qualities.
 */
class Public_job_post {

    private const LIST_TITLES = ['required' => 'What you bring', 'nice' => 'Nice to have', 'soft' => 'Who you are'];

    /** ['facts', 'notice', 'takes_applications', 'responsibilities', 'lists' => [['title', 'required', 'tags']]]. */
    public static function from(array $post, array $rows): array {
        return [
            'facts' => self::facts($post),
            'notice' => self::notice((string) $post['status']),
            'takes_applications' => $post['status'] === 'active',
            'responsibilities' => self::responsibilities($rows),
            'lists' => self::lists($rows),
        ];
    }

    /** ["Full time", "Hybrid", "Postal code 8000"], leaving out what the post doesn't state. */
    private static function facts(array $post): array {
        return array_values(array_filter([
            Job_post_rules::WORK_HOURS[$post['work_hours'] ?? ''] ?? '',
            Job_post_rules::WORKPLACE[$post['workplace_flexibility'] ?? ''] ?? '',
            $post['postal_code'] ? 'Postal code ' . $post['postal_code'] : '',
        ], fn(string $fact) => $fact !== '' && $fact !== 'Not stated'));
    }

    /** Why a candidate can't apply right now, or '' when they can. */
    private static function notice(string $status): string {
        return match ($status) {
            'closed', 'archived' => 'This job is closed and no longer takes applications.',
            'paused' => "This job isn't taking applications right now.",
            default => '',
        };
    }

    private static function responsibilities(array $rows): array {
        return array_column(array_filter($rows, fn(array $r) => $r['kind'] === 'responsibility_area'), 'raw_text');
    }

    /** The lists that have tags, in LIST_TITLES order. */
    private static function lists(array $rows): array {
        $lists = [];
        foreach (self::LIST_TITLES as $list => $title) {
            $tags = self::tags(array_filter($rows, fn(array $r) => self::list_of($r) === $list), $list !== 'soft');
            if ($tags) {
                $lists[] = ['title' => $title, 'required' => $list === 'required', 'tags' => $tags];
            }
        }
        return $lists;
    }

    /** Which list a row goes in, or null for a row that isn't a requirement. */
    private static function list_of(array $row): ?string {
        if (!isset(Job_post_rules::KINDS[$row['kind']])) {
            return null;
        }
        if ($row['kind'] === 'soft_skill') {
            return 'soft';
        }
        return $row['is_required'] ? 'required' : 'nice';
    }

    /** One list's rows as tags, each a list of alternatives; with $grouped, an OR-group is one tag. */
    private static function tags(array $rows, bool $grouped): array {
        $tags = [];
        foreach ($rows as $i => $row) {
            $key = $grouped && $row['alt_group'] !== null ? 'group' . $row['alt_group'] : 'row' . $i;
            $tags[$key][] = self::alternative($row);
        }
        return array_values($tags);
    }

    /** ['text', 'years' => "5+ yrs" or '', 'level' => "(fluent)" or '']. */
    private static function alternative(array $row): array {
        return [
            'text' => (string) $row['raw_text'],
            'years' => self::years($row),
            'level' => $row['min_level'] ? '(' . $row['min_level'] . ')' : '',
        ];
    }

    /** "5+ yrs", unless the text already says how many years. */
    private static function years(array $row): string {
        return $row['min_years'] && !preg_match('/\d/', (string) $row['raw_text']) ? (int) $row['min_years'] . '+ yrs' : '';
    }
}

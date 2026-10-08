<?php
require_once __DIR__ . '/../applications/Application_scoring.php';
/**
 * A post's applications as SmartMatch lists them: each with its candidate,
 * its newest score and that score's details, and what the company did with
 * it (shortlisted, bookmarked, rejected).
 */
class Smartmatch_model extends Model {

    // What a toggle sets: action => [SQL SET, the status it needs].
    private const ACTIONS = [
        'shortlist' => ['shortlisted_at = :now', "status = 'in_review' AND shortlisted_at IS NULL"],
        'unshortlist' => ['shortlisted_at = NULL', 'shortlisted_at IS NOT NULL'],
        'bookmark' => ['bookmarked_at = :now', 'bookmarked_at IS NULL'],
        'unbookmark' => ['bookmarked_at = NULL', 'bookmarked_at IS NOT NULL'],
        'reject' => ["status = 'rejected', rejected_at = :now, shortlisted_at = NULL", "status = 'in_review'"],
        'unreject' => ["status = 'in_review', rejected_at = NULL", "status = 'rejected'"],
    ];

    public const TOGGLES = ['shortlist', 'unshortlist', 'bookmark', 'unbookmark', 'reject', 'unreject'];

    // The SQL condition for each SmartMatch tab (a = job_applications, s = the newest score).
    private const TAB_SQL = [
        'top' => "a.status = 'in_review' AND s.final_rank IS NOT NULL AND s.final_rank <= 10",
        'all' => "a.status = 'in_review'",
        'shortlist' => "a.status = 'in_review' AND a.shortlisted_at IS NOT NULL",
        'bookmarked' => "a.status <> 'rejected' AND a.bookmarked_at IS NOT NULL",
        'rejected' => "a.status = 'rejected'",
    ];

    /**
     * How many of the post's received applications are in each tab, how
     * many arrived after $last_seen (`new`) and how many in review have no
     * score on $version yet (`unscored`). One query, whatever the count.
     */
    public function counts(int $job_post_id, int $version, int $last_seen): array {
        $sums = [];
        foreach (self::TAB_SQL as $tab => $condition) {
            $sums[] = "COALESCE(SUM($condition), 0) AS `$tab`";
        }
        $sums[] = "COALESCE(SUM(a.status = 'in_review' AND a.submitted_at > :seen), 0) AS `new`";
        $sums[] = "COALESCE(SUM(a.status = 'in_review' AND (s.id IS NULL OR s.job_post_version <> :version)), 0) AS `unscored`";
        $rows = $this->db->query_bind(
            'SELECT ' . implode(', ', $sums) . self::FROM,
            $this->base_params($job_post_id) + ['seen' => $last_seen, 'version' => $version],
            'array'
        );
        return array_map('intval', $rows[0]);
    }

    /**
     * The next cards of a tab, in list order: each application with its
     * candidate, the newest score (score_* columns, null when it has none
     * yet) and `details` (that score's match_score_details rows by
     * criterion). $tag narrows to a score tag; $required (the criterion ids
     * every one of which must be met, see Cv_matcher::criteria) to those
     * whose score on $version meets them all, or null for no such filter.
     * $after is the cursor of the last card already shown ('' for the
     * first cards); a cursor is the card's place in the list order, so
     * paging costs the same however far down the list it is.
     * Returns ['rows' => ..., 'next' => the cursor for the cards after
     * these, or null at the end, 'total' => matches in all (only when
     * $count)].
     */
    public function page(int $job_post_id, string $tab, string $tag, ?array $required, int $version, int $limit, string $after = '', bool $count = false): array {
        [$where, $params] = $this->filters($tab, $tag, $required, $version);
        $params += $this->base_params($job_post_id);
        $total = $count ? (int) $this->db->query_bind('SELECT COUNT(*) AS n' . self::FROM . $where, $params, 'array')[0]['n'] : null;
        if (($place = self::place($after)) !== null) {
            $where .= ' AND (' . self::SORT . ') > (:k1, :k2, CAST(:k3 AS DECIMAL(6,4)), :k4, :k5)';
            $params += $place;
        }
        $rows = $this->db->query_bind(
            'SELECT ' . self::COLUMNS . self::FROM . $where . ' ORDER BY ' . self::SORT . sprintf(' LIMIT %d', $limit + 1),
            $params,
            'array'
        );
        $next = null;
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $last = end($rows);
            $next = implode('_', [
                $last['final_rank'] === null ? 1 : 0,
                (int) ($last['final_rank'] ?? 0),
                $last['combined_score'] === null ? '1' : self::negate((string) $last['combined_score']),
                (int) $last['submitted_at'],
                (int) $last['id'],
            ]);
        }
        if ($rows) {
            $texts = [];
            foreach ($this->db->query_bind(
                'SELECT id, raw_text, cover_letter FROM job_applications WHERE id IN (' . self::ids($rows, 'id') . ')',
                [],
                'array'
            ) as $text) {
                $texts[(int) $text['id']] = $text;
            }
            $details = $this->details(self::ids($rows, 'score_id'));
            foreach ($rows as &$row) {
                $row['raw_text'] = $texts[(int) $row['id']]['raw_text'] ?? '';
                $row['cover_letter'] = $texts[(int) $row['id']]['cover_letter'] ?? null;
                $row['details'] = $details[(int) $row['score_id']] ?? [];
            }
            unset($row);
        }
        return ['rows' => $rows, 'next' => $next, 'total' => $total];
    }

    /** A cursor's sort values as :k1..:k5, or null when it is empty or not a cursor. */
    public static function place(string $cursor): ?array {
        if (!preg_match('/^([01])_(\d{1,5})_(-?\d(?:\.\d{1,4})?)_(\d{1,10})_(\d{1,10})$/', $cursor, $m)) {
            return null;
        }
        return ['k1' => (int) $m[1], 'k2' => (int) $m[2], 'k3' => $m[3], 'k4' => (int) $m[4], 'k5' => (int) $m[5]];
    }

    /** "0.7885" -> "-0.7885", without going through a float. */
    private static function negate(string $decimal): string {
        return str_starts_with($decimal, '-') ? substr($decimal, 1) : ($decimal === '0' || (float) $decimal == 0.0 ? '0' : '-' . $decimal);
    }

    /** All the post's received applications in list order, without CV text or details (for the CSV). */
    public function all(int $job_post_id): array {
        return $this->db->query_bind(
            'SELECT ' . self::COLUMNS . self::FROM . ' ORDER BY ' . self::SORT,
            $this->base_params($job_post_id),
            'array'
        );
    }

    private const COLUMNS = "a.id, a.status, a.current_title, a.submitted_at,
                    a.shortlisted_at, a.bookmarked_at, a.rejected_at,
                    c.name, c.email, c.phone,
                    s.id AS score_id, s.job_post_version AS score_version, s.deterministic_score, s.llm_score,
                    s.combined_score, s.tag, s.laya_requirements_probability AS laya_meets,
                    s.laya_fit_expected AS laya_fit, s.laya_choice, s.needs_human, s.final_rank, s.computed_at";

    // The post's received applications, each joined to its newest score.
    private const FROM = "
             FROM job_applications a
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN match_scores s ON s.job_application_id = a.id AND s.ranking_version = :ranking
                AND s.job_post_version = (SELECT MAX(s2.job_post_version) FROM match_scores s2
                                          WHERE s2.job_application_id = a.id AND s2.ranking_version = :ranking2)
             WHERE a.job_post_id = :post AND a.status IN ('in_review', 'rejected', 'hired')";

    // The list order as one ascending row value, so a cursor can say
    // "after this card": ranked first by rank, then by score (highest
    // first), then oldest application first.
    private const SORT = '(s.final_rank IS NULL), COALESCE(s.final_rank, 0), -COALESCE(s.combined_score, -1), a.submitted_at, a.id';

    private function base_params(int $job_post_id): array {
        return [
            'ranking' => Application_scoring::RANKING_VERSION,
            'ranking2' => Application_scoring::RANKING_VERSION,
            'post' => $job_post_id,
        ];
    }

    /** The AND conditions (after FROM's WHERE) and their params for a tab and its filters. */
    private function filters(string $tab, string $tag, ?array $required, int $version): array {
        $sql = ' AND ' . self::TAB_SQL[$tab];
        $params = [];
        if ($tag !== '') {
            $sql .= ' AND s.tag = :tag';
            $params['tag'] = $tag;
        }
        if ($required !== null) {
            $sql .= ' AND s.job_post_version = :version';
            $params['version'] = $version;
            $required = array_values(array_unique($required));
            if ($required) {
                $names = [];
                foreach ($required as $i => $criterion) {
                    $names[] = ":c$i";
                    $params["c$i"] = (string) $criterion;
                }
                $sql .= ' AND (SELECT COUNT(*) FROM match_score_details d WHERE d.match_score_id = s.id AND d.passed = 1
                               AND d.criterion IN (' . implode(', ', $names) . ')) = ' . count($required);
            }
        }
        return [$sql, $params];
    }

    /** match_score_details rows by score id, then by criterion. */
    private function details(string $score_ids): array {
        $details = [];
        if ($score_ids === '') {
            return $details;
        }
        foreach ($this->db->query_bind(
            "SELECT match_score_id, stage, criterion, rule, weight, `rank`, passed, reason
             FROM match_score_details WHERE match_score_id IN ($score_ids) ORDER BY id",
            [],
            'array'
        ) as $detail) {
            $details[(int) $detail['match_score_id']][$detail['criterion']] = $detail;
        }
        return $details;
    }

    /** A comma-separated list of the rows' non-null integer ids in a column. */
    private static function ids(array $rows, string $column): string {
        return implode(',', array_map('intval', array_filter(array_column($rows, $column), fn($id) => $id !== null)));
    }

    /** One of the post's received applications, or null. */
    public function application(int $job_post_id, int $application_id): ?array {
        $rows = $this->db->query_bind(
            "SELECT id, status FROM job_applications
             WHERE id = :id AND job_post_id = :post AND status IN ('in_review', 'rejected', 'hired')",
            ['id' => $application_id, 'post' => $job_post_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /**
     * Shortlists, bookmarks or rejects an application, or undoes it.
     * Returns whether anything changed (false when it was already so).
     */
    public function toggle(int $job_post_id, int $application_id, string $action): bool {
        [$set, $condition] = self::ACTIONS[$action];
        $now = time();
        $params = ['id' => $application_id, 'post' => $job_post_id, 'updated' => $now];
        if (str_contains($set, ':now')) {
            $params['now'] = $now;
        }
        $this->db->query_bind(
            "UPDATE job_applications SET $set, updated_at = :updated
             WHERE id = :id AND job_post_id = :post AND $condition",
            $params
        );
        return $this->db->query_bind('SELECT ROW_COUNT() AS n', [], 'array')[0]['n'] > 0;
    }

    /** When the member last opened this post's list (0 if never), and now marks it opened. */
    public function seen(int $job_post_id, int $member_id): int {
        $rows = $this->db->query_bind(
            'SELECT last_viewed_at FROM job_post_member_views WHERE job_post_id = :post AND company_member_id = :member',
            ['post' => $job_post_id, 'member' => $member_id],
            'array'
        );
        $now = time();
        $this->db->query_bind(
            'INSERT INTO job_post_member_views (job_post_id, company_member_id, last_viewed_at) VALUES (:post, :member, :now)
             ON DUPLICATE KEY UPDATE last_viewed_at = VALUES(last_viewed_at)',
            ['post' => $job_post_id, 'member' => $member_id, 'now' => $now]
        );
        return (int) ($rows[0]['last_viewed_at'] ?? 0);
    }

}

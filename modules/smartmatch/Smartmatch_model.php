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
     * One page of a tab, in list order: each application with its
     * candidate, the newest score (score_* columns, null when it has none
     * yet) and `details` (that score's match_score_details rows by
     * criterion). $tag narrows to a score tag; $required (the criterion ids
     * every one of which must be met, see Cv_matcher::criteria) to those
     * whose score on $version meets them all, or null for no such filter.
     * Returns ['rows' => ..., 'total' => matches across all pages].
     */
    public function page(int $job_post_id, string $tab, string $tag, ?array $required, int $version, int $limit, int $offset): array {
        [$where, $params] = $this->filters($tab, $tag, $required, $version);
        $params += $this->base_params($job_post_id);
        $total = (int) $this->db->query_bind('SELECT COUNT(*) AS n' . self::FROM . $where, $params, 'array')[0]['n'];
        $rows = $this->db->query_bind(
            'SELECT ' . self::COLUMNS . self::FROM . $where . self::ORDER . sprintf(' LIMIT %d OFFSET %d', $limit, $offset),
            $params,
            'array'
        );
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
        return ['rows' => $rows, 'total' => $total];
    }

    /** All the post's received applications in list order, without CV text or details (for the CSV). */
    public function all(int $job_post_id): array {
        return $this->db->query_bind(
            'SELECT ' . self::COLUMNS . self::FROM . self::ORDER,
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

    private const ORDER = ' ORDER BY s.final_rank IS NULL, s.final_rank, s.combined_score DESC, a.submitted_at, a.id';

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

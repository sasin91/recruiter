<?php
require_once __DIR__ . '/../applications/Application_scoring.php';
/**
 * A post's applications as SmartMatch lists them: each with its candidate,
 * its newest score and that score's details, and what the company did with
 * it (shortlisted, bookmarked, rejected).
 */
class Smartmatch_model extends Model {

    // What a toggle sets: action => [SQL SET, the status it needs, event].
    private const ACTIONS = [
        'shortlist' => ['shortlisted_at = :now', "status = 'in_review' AND shortlisted_at IS NULL"],
        'unshortlist' => ['shortlisted_at = NULL', 'shortlisted_at IS NOT NULL'],
        'bookmark' => ['bookmarked_at = :now', 'bookmarked_at IS NULL'],
        'unbookmark' => ['bookmarked_at = NULL', 'bookmarked_at IS NOT NULL'],
        'reject' => ["status = 'rejected', rejected_at = :now, shortlisted_at = NULL", "status = 'in_review'"],
        'unreject' => ["status = 'in_review', rejected_at = NULL", "status = 'rejected'"],
    ];

    public const TOGGLES = ['shortlist', 'unshortlist', 'bookmark', 'unbookmark', 'reject', 'unreject'];

    /**
     * The post's received applications (in review, rejected or hired), with
     * the candidate, the newest score (score_* columns, null when it has
     * none yet) and `details` (that score's match_score_details rows by
     * criterion).
     */
    public function applications(int $job_post_id): array {
        $rows = $this->db->query_bind(
            "SELECT a.id, a.status, a.current_title, a.raw_text, a.cover_letter, a.submitted_at,
                    a.shortlisted_at, a.bookmarked_at, a.rejected_at,
                    c.name, c.email, c.phone,
                    s.id AS score_id, s.job_post_version AS score_version, s.deterministic_score, s.llm_score,
                    s.combined_score, s.tag, s.laya_requirements_probability AS laya_meets,
                    s.laya_fit_expected AS laya_fit, s.laya_choice, s.needs_human, s.final_rank, s.computed_at
             FROM job_applications a
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN match_scores s ON s.job_application_id = a.id AND s.ranking_version = :ranking
                AND s.job_post_version = (SELECT MAX(s2.job_post_version) FROM match_scores s2
                                          WHERE s2.job_application_id = a.id AND s2.ranking_version = :ranking2)
             WHERE a.job_post_id = :post AND a.status IN ('in_review', 'rejected', 'hired')
             ORDER BY s.final_rank IS NULL, s.final_rank, s.combined_score DESC, a.submitted_at",
            [
                'ranking' => Application_scoring::RANKING_VERSION,
                'ranking2' => Application_scoring::RANKING_VERSION,
                'post' => $job_post_id,
            ],
            'array'
        );
        $score_ids = array_filter(array_map(fn(array $r) => (int) $r['score_id'], $rows));
        $details = [];
        if ($score_ids) {
            $list = implode(',', $score_ids);
            foreach ($this->db->query_bind(
                "SELECT match_score_id, stage, criterion, rule, weight, `rank`, passed, reason
                 FROM match_score_details WHERE match_score_id IN ($list) ORDER BY id",
                [],
                'array'
            ) as $detail) {
                $details[(int) $detail['match_score_id']][$detail['criterion']] = $detail;
            }
        }
        foreach ($rows as &$row) {
            $row['details'] = $details[(int) $row['score_id']] ?? [];
        }
        unset($row);
        return $rows;
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

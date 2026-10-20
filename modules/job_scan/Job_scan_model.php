<?php
/**
 * A user's job searches (job_searches + job_search_sites) and the posts
 * their scans found (scanned_job_posts). Every read and write takes the
 * owner's user id, so nobody reaches another user's searches.
 */
class Job_scan_model extends Model {

    public const STATUSES = [
        'new' => 'Waiting to be matched',
        'matching' => 'Matching',
        'matched' => 'Matched',
        'not_a_job' => 'Not a job post',
        'failed' => 'Failed',
        'dismissed' => 'Dismissed',
    ];

    /** The user's searches, newest first, each with its post counts. */
    public function searches(int $user_id): array {
        return $this->db->query_bind(
            'SELECT s.id, s.name, s.keywords, s.location, s.active, s.paused_reason, s.last_scanned_at,
                    s.scan_every_hours, s.tailor_from_score,
                    COUNT(p.id) AS posts, SUM(p.status = \'matched\') AS matched
             FROM job_searches s
             LEFT JOIN scanned_job_posts p ON p.job_search_id = s.id AND p.status <> \'dismissed\'
             WHERE s.trongate_user_id = :user_id
             GROUP BY s.id
             ORDER BY s.created_at DESC, s.id DESC',
            ['user_id' => $user_id],
            'array'
        );
    }

    /** One of the user's searches with its sites (`sites`: domains), or null. */
    public function find(int $id, int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT * FROM job_searches WHERE id = :id AND trongate_user_id = :user_id',
            ['id' => $id, 'user_id' => $user_id],
            'array'
        );
        if (!$rows) {
            return null;
        }
        $search = $rows[0];
        $sites = $this->db->query_bind(
            'SELECT domain FROM job_search_sites WHERE job_search_id = :id ORDER BY domain',
            ['id' => $id],
            'array'
        );
        $search['sites'] = array_column($sites, 'domain');
        return $search;
    }

    /**
     * Saves a search (new when $id is 0) and replaces its sites. $fields are
     * job_searches columns; $sites are domains. Returns the search's id.
     */
    public function save(int $user_id, int $id, array $fields, array $sites): int {
        $now = time();
        $fields['updated_at'] = $now;
        if ($id === 0) {
            $fields['trongate_user_id'] = $user_id;
            $fields['created_at'] = $now;
            $id = $this->db->insert($fields, 'job_searches');
        } else {
            $this->db->update($id, $fields, 'job_searches');
        }
        $this->db->query_bind('DELETE FROM job_search_sites WHERE job_search_id = :id', ['id' => $id]);
        if ($sites) {
            $this->db->insert_batch(array_map(fn($domain) => ['job_search_id' => $id, 'domain' => $domain], $sites), 'job_search_sites');
        }
        return $id;
    }

    /** Deletes one of the user's searches, with its sites, scans and found posts. */
    public function delete_search(int $id, int $user_id): void {
        $this->db->query_bind(
            'DELETE FROM job_searches WHERE id = :id AND trongate_user_id = :user_id',
            ['id' => $id, 'user_id' => $user_id]
        );
    }

    /**
     * A batch of a search's found posts, newest first, without dismissed
     * ones unless $dismissed. $after is the cursor of the last post shown
     * ('' for the first batch). Each post has the saved match's score and
     * tag, and whether its tailored résumé is written. Returns ['rows',
     * 'next' => the cursor for the posts after these, or null].
     */
    public function posts(int $search_id, bool $dismissed, int $limit, string $after = ''): array {
        $where = 'p.job_search_id = :id' . ($dismissed ? '' : ' AND p.status <> \'dismissed\'');
        $params = ['id' => $search_id];
        if (($place = self::place($after)) !== null) {
            $where .= ' AND (p.found_at, p.id) < (:found_at, :post_id)';
            $params += $place;
        }
        $rows = $this->db->query_bind(
            "SELECT p.id, p.url, p.title, p.company, p.location, p.status, p.error, p.found_at, p.matched_at,
                    p.cv_match_id, m.score, m.tag, m.resume_written_at
             FROM scanned_job_posts p
             LEFT JOIN cv_matches m ON m.id = p.cv_match_id
             WHERE $where
             ORDER BY p.found_at DESC, p.id DESC
             LIMIT " . ($limit + 1),
            $params,
            'array'
        );
        $next = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $last = end($rows);
            $next = $last['found_at'] . '_' . $last['id'];
        }
        return ['rows' => $rows, 'next' => $next];
    }

    /** A cursor ("{found_at}_{id}") as query params, or null when it isn't one. */
    public static function place(string $cursor): ?array {
        if (!preg_match('/^(\d{1,10})_(\d{1,10})$/', $cursor, $m)) {
            return null;
        }
        return ['found_at' => (int) $m[1], 'post_id' => (int) $m[2]];
    }

    /** Dismisses (or, with $undo, brings back) a post of one of the user's searches; its search id, or null. */
    public function dismiss(int $post_id, int $user_id, bool $undo = false): ?int {
        $rows = $this->db->query_bind(
            'SELECT p.job_search_id, p.status, p.cv_match_id FROM scanned_job_posts p
             JOIN job_searches s ON s.id = p.job_search_id
             WHERE p.id = :id AND s.trongate_user_id = :user_id',
            ['id' => $post_id, 'user_id' => $user_id],
            'array'
        );
        if (!$rows) {
            return null;
        }
        // Brought back, a post shows as matched when it has a match, and
        // otherwise waits to be matched again.
        $status = $undo ? ($rows[0]['cv_match_id'] !== null ? 'matched' : 'new') : 'dismissed';
        $this->db->update($post_id, ['status' => $status], 'scanned_job_posts');
        return (int) $rows[0]['job_search_id'];
    }

    /** The CV of the user's latest saved match, to start a new search with: ['cv_name', 'cv_text'], or null. */
    public function latest_cv(int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT cv_name, cv_text FROM cv_matches WHERE trongate_user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT 1',
            ['user_id' => $user_id],
            'array'
        );
        return $rows[0] ?? null;
    }

}

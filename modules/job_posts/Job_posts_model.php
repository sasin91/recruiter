<?php
require_once __DIR__ . '/Job_post_rules.php';

/**
 * A company's job posts (job_posts) and their requirements list
 * (job_post_terms, see Job_post_rules for the rows). Every query that takes
 * a post id also takes the company id, so one company never reaches
 * another's posts.
 */
class Job_posts_model extends Model {

    // The basics the review form edits, besides the rows and the pitch.
    public const BASICS = ['language', 'postal_code', 'work_hours', 'workplace_flexibility'];

    // Applications a company sees: the rest are drafts, cards and invites.
    public const RECEIVED = "('in_review', 'rejected', 'hired')";

    /**
     * Saves a new draft post with its rows and returns its id.
     *
     * @param string $extractor what read the text: the provider's name, or 'free_reader'
     */
    public function create(int $company_id, int $member_id, string $raw_text, string $extractor, array $rows, string $language = 'da'): int {
        $now = time();
        $title = '';
        foreach ($rows as $row) {
            if ($row['kind'] === 'title') {
                $title = $row['raw_text'];
            }
        }
        $this->db->query('START TRANSACTION');
        try {
            $id = $this->db->insert([
                'company_id' => $company_id,
                'created_by' => $member_id,
                'title' => $title !== '' ? $title : 'Untitled job post',
                'language' => $language,
                'status' => 'draft',
                'version' => 1,
                'raw_text' => $raw_text,
                'extractor' => substr($extractor, 0, 32),
                'created_at' => $now,
                'updated_at' => $now,
            ], 'job_posts');
            $this->insert_rows($id, $rows);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
        return $id;
    }

    /** One of the company's posts, or null. */
    public function find(int $company_id, int $id): ?array {
        $rows = $this->db->query_bind(
            'SELECT * FROM job_posts WHERE id = :id AND company_id = :company_id',
            ['id' => $id, 'company_id' => $company_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** A post by its public link, with its company's name, or null. Drafts have no link. */
    public function find_public(string $token): ?array {
        if (!Job_post_rules::is_public_token($token)) {
            return null;
        }
        $rows = $this->db->query_bind(
            "SELECT p.*, c.name AS company_name FROM job_posts p JOIN companies c ON c.id = p.company_id
             WHERE p.public_token = :token AND p.status <> 'draft' AND c.active = 1",
            ['token' => $token],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** The post's rows in their order (see Job_post_rules), each with its id. */
    public function rows(int $job_post_id): array {
        $rows = $this->db->query_bind(
            'SELECT id, kind, raw_text, english, is_required, alt_group, min_years, min_level
             FROM job_post_terms WHERE job_post_id = :id ORDER BY sort_order, id',
            ['id' => $job_post_id],
            'array'
        );
        foreach ($rows as &$row) {
            $row['is_required'] = (int) $row['is_required'];
            $row['alt_group'] = $row['alt_group'] === null ? null : (int) $row['alt_group'];
            $row['min_years'] = $row['min_years'] === null ? null : (int) $row['min_years'];
        }
        unset($row);
        return $rows;
    }

    /**
     * Saves the review form: basics, pitch and rows. A live post whose
     * requirements change gets a new version (applications are scored per
     * version). Returns whether the version went up.
     */
    public function save_review(array $post, array $basics, string $pitch, array $rows): bool {
        $title = $post['title'];
        foreach ($rows as $row) {
            if ($row['kind'] === 'title') {
                $title = $row['raw_text'];
            }
        }
        $new_version = $post['status'] !== 'draft' && !Job_post_rules::same_requirements($this->rows((int) $post['id']), $rows);
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query_bind(
                'UPDATE job_posts SET title = :title, language = :language, postal_code = :postal_code,
                    work_hours = :work_hours, workplace_flexibility = :workplace, pitch = :pitch,
                    version = version + :bump, updated_at = :now
                 WHERE id = :id AND company_id = :company_id',
                [
                    'title' => $title,
                    'language' => $basics['language'],
                    'postal_code' => $basics['postal_code'] !== '' ? $basics['postal_code'] : null,
                    'work_hours' => $basics['work_hours'] !== '' ? $basics['work_hours'] : null,
                    'workplace' => $basics['workplace_flexibility'] !== '' ? $basics['workplace_flexibility'] : null,
                    'pitch' => $pitch !== '' ? $pitch : null,
                    'bump' => $new_version ? 1 : 0,
                    'now' => time(),
                    'id' => (int) $post['id'],
                    'company_id' => (int) $post['company_id'],
                ]
            );
            $this->db->query_bind('DELETE FROM job_post_terms WHERE job_post_id = :id', ['id' => (int) $post['id']]);
            $this->insert_rows((int) $post['id'], $rows);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
        return $new_version;
    }

    /**
     * Moves the post to $status, stamping when (and giving it its public link
     * on first publish). Conditional on the status it was read with, so two
     * members clicking at once change it once.
     */
    public function set_status(array $post, string $status): bool {
        $now = time();
        $stamp = match ($status) {
            'active' => $post['published_at'] ? 'paused_at = NULL, closed_at = NULL' : 'published_at = :now2',
            'paused' => 'paused_at = :now2',
            'closed' => 'closed_at = :now2',
            'archived' => 'archived_at = :now2',
        };
        $params = [
            'status' => $status,
            'now' => $now,
            'id' => (int) $post['id'],
            'company_id' => (int) $post['company_id'],
            'from' => $post['status'],
        ];
        if (str_contains($stamp, ':now2')) {
            $params['now2'] = $now;
        }
        $token = '';
        if ($post['public_token'] === null) {
            $token = ', public_token = :token';
            $params['token'] = Job_post_rules::public_token();
        }
        $this->db->query_bind(
            "UPDATE job_posts SET status = :status, $stamp$token, updated_at = :now
             WHERE id = :id AND company_id = :company_id AND status = :from",
            $params
        );
        $after = $this->find((int) $post['company_id'], (int) $post['id']);
        return $after !== null && $after['status'] === $status;
    }

    /** Deletes a draft (only drafts: a published post has a link and maybe applicants). */
    public function delete_draft(int $company_id, int $id): bool {
        $this->db->query_bind(
            "DELETE FROM job_posts WHERE id = :id AND company_id = :company_id AND status = 'draft'",
            ['id' => $id, 'company_id' => $company_id]
        );
        return $this->find($company_id, $id) === null;
    }

    /** A copy of the post as a new draft, by $member_id; returns its id. */
    public function duplicate(array $post, int $member_id): int {
        $rows = $this->rows((int) $post['id']);
        foreach ($rows as &$row) {
            if ($row['kind'] === 'title') {
                $row['raw_text'] = mb_substr('Copy of ' . $row['raw_text'], 0, 255, 'UTF-8');
            }
        }
        unset($row);
        $id = $this->create((int) $post['company_id'], $member_id, $post['raw_text'], $post['extractor'], $rows, $post['language']);
        $this->db->query_bind(
            'UPDATE job_posts SET postal_code = :postal_code, work_hours = :work_hours,
                workplace_flexibility = :workplace, pitch = :pitch WHERE id = :id',
            [
                'postal_code' => $post['postal_code'],
                'work_hours' => $post['work_hours'],
                'workplace' => $post['workplace_flexibility'],
                'pitch' => $post['pitch'],
                'id' => $id,
            ]
        );
        return $id;
    }

    /**
     * The company's posts for the dashboard, newest first, each with
     * `applications` (received), `new` (received since $member_id last
     * opened the post's applicants) and `shortlisted`.
     */
    public function overview(int $company_id, int $member_id): array {
        return $this->db->query_bind(
            'SELECT p.id, p.title, p.status, p.public_token, p.published_at, p.closed_at, p.created_at, p.updated_at,
                    COUNT(a.id) AS applications,
                    COALESCE(SUM(a.status = \'in_review\' AND a.submitted_at > COALESCE(v.last_viewed_at, 0)), 0) AS new,
                    COALESCE(SUM(a.status = \'in_review\' AND a.shortlisted_at IS NOT NULL), 0) AS shortlisted
             FROM job_posts p
             LEFT JOIN job_applications a ON a.job_post_id = p.id AND a.status IN ' . self::RECEIVED . '
             LEFT JOIN job_post_member_views v ON v.job_post_id = p.id AND v.company_member_id = :member_id
             WHERE p.company_id = :company_id
             GROUP BY p.id
             ORDER BY p.status = \'draft\', COALESCE(p.published_at, p.created_at) DESC',
            ['company_id' => $company_id, 'member_id' => $member_id],
            'array'
        );
    }

    private function insert_rows(int $job_post_id, array $rows): void {
        $records = [];
        foreach (array_values($rows) as $order => $row) {
            $records[] = [
                'job_post_id' => $job_post_id,
                'kind' => $row['kind'],
                'raw_text' => $row['raw_text'],
                'english' => $row['english'],
                'normalised' => Job_post_rules::normalise($row['raw_text']),
                'is_required' => $row['is_required'],
                'alt_group' => $row['alt_group'],
                'min_years' => $row['min_years'],
                'min_level' => $row['min_level'],
                'sort_order' => $order,
            ];
        }
        if ($records) {
            $this->db->insert_batch($records, 'job_post_terms');
        }
    }

}

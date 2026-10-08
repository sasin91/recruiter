<?php
require_once __DIR__ . '/Application_scoring.php';
require_once __DIR__ . '/../job_posts/Job_post_rules.php';
/**
 * Candidates' résumés and applications, and the scores SmartMatch lists
 * them by. An application keeps a copy of the CV it was sent with (raw_text
 * and job_application_terms), so a later CV doesn't change what the company
 * received.
 */
class Applications_model extends Model {

    /** The candidates row of a signed-in candidate's trongate_users id, or null. */
    public function candidate_for_user(int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT * FROM candidates WHERE trongate_user_id = :user_id AND active = 1',
            ['user_id' => $user_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /** The candidate's résumé with its `terms`, or null before their first CV. */
    public function resume(int $candidate_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT * FROM candidate_resumes WHERE candidate_id = :id',
            ['id' => $candidate_id],
            'array'
        );
        if (!$rows) {
            return null;
        }
        $resume = $rows[0];
        $resume['terms'] = $this->db->query_bind(
            'SELECT kind, raw_text, years, level FROM candidate_resume_terms
             WHERE candidate_resume_id = :id ORDER BY sort_order, id',
            ['id' => (int) $resume['id']],
            'array'
        );
        return $resume;
    }

    /**
     * Saves a newly read CV as the candidate's résumé: a new version, with
     * its terms (Application_scoring::profile_terms) replacing the last.
     */
    public function save_resume(int $candidate_id, array $cv, array $terms): void {
        $now = time();
        $title = '';
        foreach ($terms as $term) {
            if ($term['kind'] === 'title') {
                $title = $term['raw_text'];
                break;
            }
        }
        $years = 0;
        foreach ($terms as $term) {
            if ($term['kind'] === 'experience') {
                $years = (int) $term['years'];
            }
        }
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query_bind(
                'INSERT INTO candidate_resumes
                    (candidate_id, version, cv_name, cv_text, current_title, experience_years, language, extractor, confirmed_at, created_at, updated_at)
                 VALUES (:candidate_id, 1, :cv_name, :cv_text, :title, :years, :language, :extractor, :now, :now2, :now3)
                 ON DUPLICATE KEY UPDATE version = version + 1, cv_name = VALUES(cv_name), cv_text = VALUES(cv_text),
                    current_title = VALUES(current_title), experience_years = VALUES(experience_years),
                    language = VALUES(language), extractor = VALUES(extractor),
                    confirmed_at = VALUES(confirmed_at), updated_at = VALUES(updated_at)',
                [
                    'candidate_id' => $candidate_id,
                    'cv_name' => mb_substr($cv['name'], 0, 255, 'UTF-8'),
                    'cv_text' => $cv['text'],
                    'title' => $title !== '' ? $title : null,
                    'years' => $years,
                    'language' => $cv['language'],
                    'extractor' => $cv['extractor'],
                    'now' => $now,
                    'now2' => $now,
                    'now3' => $now,
                ]
            );
            $resume_id = (int) $this->db->query_bind(
                'SELECT id FROM candidate_resumes WHERE candidate_id = :id',
                ['id' => $candidate_id],
                'array'
            )[0]['id'];
            $this->db->query_bind('DELETE FROM candidate_resume_terms WHERE candidate_resume_id = :id', ['id' => $resume_id]);
            $this->insert_terms('candidate_resume_terms', 'candidate_resume_id', $resume_id, $terms);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /** The candidate's application to a post, or null. */
    public function application_for(int $job_post_id, int $candidate_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT * FROM job_applications WHERE job_post_id = :post AND candidate_id = :candidate',
            ['post' => $job_post_id, 'candidate' => $candidate_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /**
     * Sends the application: the résumé copied onto it, status
     * in_review, an `apply` event. Applying again after withdrawing reuses
     * the withdrawn row. Returns the application's id.
     */
    public function apply(array $post, array $candidate, array $resume, string $cover_letter): int {
        $now = time();
        $record = [
            'current_title' => $resume['current_title'],
            'postal_code' => $candidate['postal_code'],
            'language' => $resume['language'],
            'source' => 'apply',
            'raw_text' => $resume['cv_text'],
            'cover_letter' => $cover_letter !== '' ? $cover_letter : null,
            'extractor' => $resume['extractor'],
            'candidate_resume_version' => (int) $resume['version'],
            'status' => 'in_review',
            'submitted_at' => $now,
            'shortlisted_at' => null,
            'bookmarked_at' => null,
            'rejected_at' => null,
            'reject_reason' => null,
            'withdrawn_at' => null,
            'updated_at' => $now,
        ];
        $this->db->query('START TRANSACTION');
        try {
            $existing = $this->application_for((int) $post['id'], (int) $candidate['id']);
            if ($existing !== null) {
                if ($existing['status'] !== 'withdrawn') {
                    throw new RuntimeException('You have already applied for this job.');
                }
                $id = (int) $existing['id'];
                $this->db->update($id, $record, 'job_applications');
                $this->db->query_bind('DELETE FROM job_application_terms WHERE job_application_id = :id', ['id' => $id]);
                $this->db->query_bind('DELETE FROM match_scores WHERE job_application_id = :id', ['id' => $id]);
            } else {
                $id = (int) $this->db->insert($record + [
                    'job_post_id' => (int) $post['id'],
                    'candidate_id' => (int) $candidate['id'],
                    'created_at' => $now,
                ], 'job_applications');
            }
            $this->insert_terms('job_application_terms', 'job_application_id', $id, $resume['terms']);
            $this->action($id, 'apply', null, (int) $candidate['id']);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
        return $id;
    }

    /** The candidate's applications, newest first, with their post and company. */
    public function mine(int $candidate_id): array {
        return $this->db->query_bind(
            "SELECT a.id, a.status, a.submitted_at, a.withdrawn_at, a.rejected_at,
                    p.title, p.public_token, p.status AS post_status, c.name AS company_name
             FROM job_applications a
             JOIN job_posts p ON p.id = a.job_post_id
             JOIN companies c ON c.id = p.company_id
             WHERE a.candidate_id = :id AND a.status <> 'draft'
             ORDER BY a.submitted_at DESC",
            ['id' => $candidate_id],
            'array'
        );
    }

    /**
     * Withdraws an application still in review. Returns its job_post_id, or
     * null when it isn't the candidate's or can't be withdrawn.
     */
    public function withdraw(int $candidate_id, int $application_id): ?int {
        $rows = $this->db->query_bind(
            "SELECT job_post_id FROM job_applications WHERE id = :id AND candidate_id = :candidate AND status = 'in_review'",
            ['id' => $application_id, 'candidate' => $candidate_id],
            'array'
        );
        if (!$rows) {
            return null;
        }
        $now = time();
        $this->db->query_bind(
            "UPDATE job_applications SET status = 'withdrawn', withdrawn_at = :now, updated_at = :now2
             WHERE id = :id AND status = 'in_review'",
            ['now' => $now, 'now2' => $now, 'id' => $application_id]
        );
        $this->action($application_id, 'withdraw', null, $candidate_id);
        return (int) $rows[0]['job_post_id'];
    }

    /** What scoring an application needs: the application with its CV `terms` and its `post`. */
    public function for_scoring(int $application_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT a.*, p.company_id, p.version AS post_version, p.title AS post_title
             FROM job_applications a JOIN job_posts p ON p.id = a.job_post_id
             WHERE a.id = :id',
            ['id' => $application_id],
            'array'
        );
        if (!$rows) {
            return null;
        }
        $application = $rows[0];
        $application['terms'] = $this->db->query_bind(
            'SELECT kind, raw_text, years, level FROM job_application_terms
             WHERE job_application_id = :id ORDER BY sort_order, id',
            ['id' => $application_id],
            'array'
        );
        return $application;
    }

    /**
     * Saves a score and its details, replacing one for the same post version,
     * then re-ranks the post's list.
     *
     * @param array $scores Application_scoring::scores()
     * @param ?array $laya Laya_client::decide()'s answer, or null
     */
    public function save_score(array $application, int $post_version, array $scores, ?array $laya, array $details): void {
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query_bind(
                'DELETE FROM match_scores WHERE job_application_id = :id AND job_post_version = :version AND ranking_version = :ranking',
                ['id' => (int) $application['id'], 'version' => $post_version, 'ranking' => Application_scoring::RANKING_VERSION]
            );
            $score_id = (int) $this->db->insert([
                'job_post_id' => (int) $application['job_post_id'],
                'job_application_id' => (int) $application['id'],
                'job_post_version' => $post_version,
                'ranking_version' => Application_scoring::RANKING_VERSION,
                'deterministic_score' => round($scores['deterministic'], 4),
                'llm_score' => $scores['llm'] === null ? null : round($scores['llm'], 4),
                'combined_score' => round($scores['combined'], 4),
                'tag' => $scores['tag'],
                'laya_requirements_probability' => $laya['meets'] ?? null,
                'laya_fit_expected' => $laya['fit'] ?? null,
                'laya_choice' => $laya['fit_label'] ?? null,
                'needs_human' => Application_scoring::needs_human($laya['meets'] ?? null) ? 1 : 0,
                'computed_at' => time(),
            ], 'match_scores');
            foreach ($details as &$detail) {
                $detail['match_score_id'] = $score_id;
            }
            unset($detail);
            if ($details) {
                $this->db->insert_batch($details, 'match_score_details');
            }
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
        $this->rerank((int) $application['job_post_id']);
    }

    /**
     * Numbers the post's applications in review by Application_scoring::order,
     * on each one's newest score; rejected and withdrawn ones lose their place.
     */
    public function rerank(int $job_post_id): void {
        $scores = $this->db->query_bind(
            "SELECT s.id, s.job_application_id, s.laya_requirements_probability AS meets, s.laya_fit_expected AS fit,
                    s.combined_score AS combined, a.submitted_at, a.status
             FROM match_scores s
             JOIN job_applications a ON a.id = s.job_application_id
             WHERE s.job_post_id = :post AND s.ranking_version = :ranking
               AND s.job_post_version = (SELECT MAX(s2.job_post_version) FROM match_scores s2
                                         WHERE s2.job_application_id = s.job_application_id AND s2.ranking_version = s.ranking_version)",
            ['post' => $job_post_id, 'ranking' => Application_scoring::RANKING_VERSION],
            'array'
        );
        $ranked = array_values(array_filter($scores, fn(array $s) => $s['status'] === 'in_review'));
        $ranked = array_map(fn(array $s) => [
            'id' => (int) $s['id'],
            'meets' => $s['meets'] === null ? null : (float) $s['meets'],
            'fit' => $s['fit'] === null ? null : (float) $s['fit'],
            'combined' => (float) $s['combined'],
            'submitted_at' => (int) $s['submitted_at'],
        ], $ranked);
        // One UPDATE per 1,000 ranks rather than one per application, so a
        // reject on a post with thousands of applicants stays quick.
        $this->db->query('START TRANSACTION');
        try {
            $this->db->query_bind(
                'UPDATE match_scores SET final_rank = NULL WHERE job_post_id = :post AND final_rank IS NOT NULL',
                ['post' => $job_post_id]
            );
            foreach (array_chunk(Application_scoring::order($ranked), 1000, true) as $chunk) {
                $cases = '';
                foreach ($chunk as $place => $score_id) {
                    $cases .= sprintf(' WHEN %d THEN %d', $score_id, $place + 1);
                }
                $this->db->query(
                    "UPDATE match_scores SET final_rank = CASE id$cases END WHERE id IN (" . implode(',', array_map('intval', $chunk)) . ')'
                );
            }
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /** Logs what a staff member or the candidate did to an application. */
    public function action(int $application_id, string $action, ?int $member_id, ?int $candidate_id, ?string $note = null): void {
        $this->db->insert([
            'job_application_id' => $application_id,
            'company_member_id' => $member_id,
            'candidate_id' => $candidate_id,
            'action' => $action,
            'note' => $note !== null ? mb_substr($note, 0, 500, 'UTF-8') : null,
            'created_at' => time(),
        ], 'job_application_actions');
    }

    private function insert_terms(string $table, string $owner_column, int $owner_id, array $terms): void {
        $records = [];
        foreach (array_values($terms) as $order => $term) {
            $records[] = [
                $owner_column => $owner_id,
                'kind' => $term['kind'],
                'raw_text' => $term['raw_text'],
                'normalised' => mb_substr(Job_post_rules::normalise($term['raw_text']), 0, 191, 'UTF-8'),
                'years' => $term['years'] ?? null,
                'level' => $term['level'] ?? null,
                'sort_order' => $order,
            ];
        }
        if ($records) {
            $this->db->insert_batch($records, $table);
        }
    }

}

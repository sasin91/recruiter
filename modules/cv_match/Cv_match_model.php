<?php
/**
 * Saved CV matches (cv_matches + cv_match_items): every scored match, its
 * per-item verdicts and the job application and tailored résumé written for it. Rows belong to
 * whoever was logged in; in dev, where nobody need be, the owner is NULL.
 */
class Cv_match_model extends Model {

    /**
     * Saves a scored match and returns its id.
     *
     * @param array $match {job, job_url, job_text, cv_name, cv_text}
     * @param array $groups Cv_matcher::criteria() of the job
     * @param array $verdicts Cv_matcher::combine() verdicts by item id
     * @param array $result Cv_matcher::score()
     */
    public function save(?int $user_id, array $match, array $groups, array $verdicts, array $result): int {
        $job = $match['job'];
        $id = $this->db->insert([
            'trongate_user_id' => $user_id,
            'job_title' => self::cut($job['job_title'] ?? '', 255),
            'company' => self::cut($job['company'] ?? '', 255),
            'job_url' => ($match['job_url'] ?? '') !== '' ? self::cut($match['job_url'], 2048) : null,
            'job_text' => $match['job_text'],
            'cv_name' => self::cut($match['cv_name'] ?? '', 255),
            'cv_text' => $match['cv_text'],
            'score' => round($result['index'], 4),
            'points' => $result['rank'],
            'max_points' => $result['total'],
            'tag' => $result['tag'],
            'created_at' => time(),
        ], 'cv_matches');

        $items = [];
        foreach ($groups as $criterion => $list) {
            foreach ($list as $item) {
                $v = $verdicts[$item['id']] ?? ['verdict' => 'missing', 'by' => 'llm', 'reason' => 'No verdict returned.'];
                $items[] = [
                    'cv_match_id' => $id,
                    'criterion' => $criterion,
                    'text' => self::cut($item['text'], 500),
                    'verdict' => in_array($v['verdict'] ?? '', ['met', 'partial', 'missing'], true) ? $v['verdict'] : 'missing',
                    'decided_by' => self::cut(($v['by'] ?? 'llm') === 'llm' ? 'llm' : ($v['method'] ?? $v['by']), 16),
                    'reason' => self::cut($v['reason'] ?? '', 500),
                    'evidence' => self::cut($v['evidence'] ?? '', 500),
                    'sort_order' => count($items),
                ];
            }
        }
        if ($items) {
            $this->db->insert_batch($items, 'cv_match_items');
        }
        return $id;
    }

    /** The user's latest matches, newest first, without the long texts. */
    public function recent(?int $user_id, int $limit = 50): array {
        $sql = 'SELECT id, job_title, company, job_url, score, points, max_points, tag,
                       application_text IS NOT NULL AS has_application,
                       resume_text IS NOT NULL AS has_resume, created_at
                FROM cv_matches WHERE trongate_user_id <=> :user_id
                ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit);
        return $this->db->query_bind($sql, ['user_id' => $user_id], 'array');
    }

    /**
     * The id of the user's newest match of this CV with the same job post
     * (the same link or the same text), or null.
     */
    public function existing(?int $user_id, string $cv_text, string $job_url, string $job_text): ?int {
        if ($cv_text === '' || ($job_url === '' && $job_text === '')) {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT id FROM cv_matches
             WHERE trongate_user_id <=> :user_id AND cv_text = :cv_text
               AND ((:has_url = 1 AND job_url = :job_url) OR (:has_text = 1 AND job_text = :job_text))
             ORDER BY created_at DESC, id DESC LIMIT 1',
            [
                'user_id' => $user_id,
                'cv_text' => $cv_text,
                'has_url' => $job_url !== '' ? 1 : 0,
                'job_url' => $job_url,
                'has_text' => $job_text !== '' ? 1 : 0,
                'job_text' => $job_text,
            ],
            'array'
        );
        return $rows ? (int) $rows[0]['id'] : null;
    }

    /** One of the user's matches with its items, or null. */
    public function find(int $id, ?int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT * FROM cv_matches WHERE id = :id AND trongate_user_id <=> :user_id',
            ['id' => $id, 'user_id' => $user_id],
            'array'
        );
        if (!$rows) {
            return null;
        }
        $match = $rows[0];
        $match['items'] = $this->db->query_bind(
            'SELECT criterion, text, verdict, decided_by, reason, evidence
             FROM cv_match_items WHERE cv_match_id = :id ORDER BY sort_order',
            ['id' => $id],
            'array'
        );
        return $match;
    }

    public function save_application(int $id, string $text): void {
        $this->db->update($id, ['application_text' => $text, 'application_written_at' => time()], 'cv_matches');
    }

    public function save_resume(int $id, string $text): void {
        $this->db->update($id, ['resume_text' => $text, 'resume_written_at' => time()], 'cv_matches');
    }

    private static function cut(string $text, int $length): string {
        return mb_substr($text, 0, $length);
    }

}

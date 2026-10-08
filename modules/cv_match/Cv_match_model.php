<?php
require_once __DIR__ . '/Tailored_resume.php';

/**
 * Saved CV matches (cv_matches + cv_match_items): every scored match, its
 * per-item verdicts and the job application and tailored résumé written for it
 * (the résumé also as fields: cv_match_resumes and its entries and lines). Rows belong to
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
        $sql = 'SELECT id, job_title, company, job_url, cv_name, score, points, max_points, tag,
                       application_text IS NOT NULL AS has_application,
                       resume_text IS NOT NULL AS has_resume, created_at
                FROM cv_matches WHERE trongate_user_id <=> :user_id
                ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit);
        return $this->db->query_bind($sql, ['user_id' => $user_id], 'array');
    }

    /**
     * The id of the user's newest match of this CV with the same job post
     * text, or null. By text, not link: a page can change, and a post read
     * wrongly before is matched again once it reads right.
     */
    public function existing(?int $user_id, string $cv_text, string $job_text): ?int {
        if ($cv_text === '' || $job_text === '') {
            return null;
        }
        $rows = $this->db->query_bind(
            'SELECT id FROM cv_matches
             WHERE trongate_user_id <=> :user_id AND cv_text = :cv_text AND job_text = :job_text
             ORDER BY created_at DESC, id DESC LIMIT 1',
            ['user_id' => $user_id, 'cv_text' => $cv_text, 'job_text' => $job_text],
            'array'
        );
        return $rows ? (int) $rows[0]['id'] : null;
    }

    /** One of the user's matches with its items, or null. */
    public function find(int $id, ?int $user_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT cv_matches.*,
                    EXISTS (SELECT 1 FROM cv_match_resumes r WHERE r.cv_match_id = cv_matches.id) AS resume_structured
             FROM cv_matches WHERE id = :id AND trongate_user_id <=> :user_id',
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

    /**
     * Saves a tailored résumé's fields (Tailored_resume::clean()) and its
     * plain text, replacing what was written before, in one transaction.
     */
    public function save_resume_fields(int $id, array $resume, string $text): void {
        $this->db->query('START TRANSACTION');
        try {
            foreach (['cv_match_resume_lines', 'cv_match_resume_entries', 'cv_match_resumes'] as $table) {
                $this->db->query_bind("DELETE FROM `$table` WHERE cv_match_id = :id", ['id' => $id]);
            }
            $header = ['cv_match_id' => $id];
            foreach (['name', 'title', 'location', 'phone', 'email', 'education_note', ...array_keys(Tailored_resume::HEADINGS)] as $field) {
                $header[$field] = $resume[$field];
            }
            $header['links'] = self::cut(implode(' · ', $resume['links']), 1000);
            $header['intro'] = implode("\n\n", $resume['intro']);
            $this->db->insert($header, 'cv_match_resumes');

            $lines = [];
            foreach (Tailored_resume::SECTIONS as $section) {
                foreach ($resume[$section] as $order => $entry) {
                    $entry_id = $this->db->insert([
                        'cv_match_id' => $id,
                        'section' => $section,
                        'title' => $entry['title'],
                        'organisation' => $entry['organisation'],
                        'location' => $entry['location'],
                        'starts' => $entry['starts'],
                        'ends' => $entry['ends'],
                        'summary' => $entry['summary'],
                        'note' => $entry['note'],
                        'sort_order' => $order,
                    ], 'cv_match_resume_entries');
                    foreach ($entry['bullets'] as $n => $bullet) {
                        $lines[] = ['cv_match_id' => $id, 'entry_id' => $entry_id, 'kind' => 'bullet', 'text' => $bullet, 'sort_order' => $n];
                    }
                }
            }
            foreach (['skill' => 'skills', 'language' => 'languages'] as $kind => $field) {
                foreach ($resume[$field] as $n => $line) {
                    $lines[] = ['cv_match_id' => $id, 'entry_id' => null, 'kind' => $kind, 'text' => $line, 'sort_order' => $n];
                }
            }
            if ($lines) {
                $this->db->insert_batch($lines, 'cv_match_resume_lines');
            }
            $this->save_resume($id, $text);
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * A match's tailored résumé as Tailored_resume fields, or null when it
     * has none (not written, or written before résumés had fields).
     */
    public function resume_fields(int $id): ?array {
        $rows = $this->db->query_bind('SELECT * FROM cv_match_resumes WHERE cv_match_id = :id', ['id' => $id], 'array');
        if (!$rows) {
            return null;
        }
        $resume = $rows[0];
        $resume['links'] = $resume['links'] === '' ? [] : explode(' · ', $resume['links']);
        $resume['intro'] = $resume['intro'] === '' ? [] : explode("\n\n", $resume['intro']);
        $resume['experience'] = $resume['education'] = $resume['skills'] = $resume['languages'] = [];

        $entries = [];
        foreach ($this->db->query_bind(
            'SELECT * FROM cv_match_resume_entries WHERE cv_match_id = :id ORDER BY sort_order, id',
            ['id' => $id],
            'array'
        ) as $entry) {
            $entry['bullets'] = [];
            $entries[(int) $entry['id']] = $entry;
        }
        foreach ($this->db->query_bind(
            'SELECT entry_id, kind, text FROM cv_match_resume_lines WHERE cv_match_id = :id ORDER BY sort_order, id',
            ['id' => $id],
            'array'
        ) as $line) {
            if ($line['kind'] === 'bullet' && isset($entries[(int) $line['entry_id']])) {
                $entries[(int) $line['entry_id']]['bullets'][] = $line['text'];
            } elseif ($line['kind'] === 'skill') {
                $resume['skills'][] = $line['text'];
            } elseif ($line['kind'] === 'language') {
                $resume['languages'][] = $line['text'];
            }
        }
        foreach ($entries as $entry) {
            if (in_array($entry['section'], Tailored_resume::SECTIONS, true)) {
                $resume[$entry['section']][] = $entry;
            }
        }
        return $resume;
    }

    private static function cut(string $text, int $length): string {
        return mb_substr($text, 0, $length);
    }

}

<?php
require_once __DIR__ . '/Tailored_resume.php';
require_once __DIR__ . '/Score_cursor.php';
require_once __DIR__ . '/Joblog.php';

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

    /** The user's latest matches, newest first, without the long texts (the Jobnet extension's list). */
    public function recent(?int $user_id, int $limit = 50): array {
        $sql = 'SELECT m.id, m.job_title, m.company, m.job_url, m.cv_name, m.score, m.points, m.max_points, m.tag,
                       m.application_text IS NOT NULL AS has_application,
                       m.resume_text IS NOT NULL AS has_resume, m.created_at,
                       (SELECT i.text FROM cv_match_items i WHERE i.cv_match_id = m.id AND i.criterion = \'location\' AND i.verdict = \'missing\' ORDER BY i.sort_order LIMIT 1) AS location_missed,
                       j.status AS jobnet_status, j.logged_at AS jobnet_logged_at
                FROM cv_matches m
                LEFT JOIN cv_match_integrations j ON j.cv_match_id = m.id AND j.integration = \'jobnet_joblog\'
                WHERE m.trongate_user_id <=> :user_id
                ORDER BY m.created_at DESC, m.id DESC LIMIT ' . max(1, $limit);
        return $this->db->query_bind($sql, ['user_id' => $user_id], 'array');
    }

    /**
     * A batch of the user's matches for the page's list, best first
     * (Score_cursor: highest score, then newest), without the long texts.
     * $after is the cursor of the last match shown ('' for the first
     * batch). Returns ['rows', 'next' => the cursor for the matches after
     * these, or null]. $list keeps only some of them (list_condition()).
     */
    public function best_first(?int $user_id, int $limit, string $after = '', string $list = ''): array {
        $where = 'm.trongate_user_id <=> :user_id';
        $params = ['user_id' => $user_id];
        if (($condition = self::list_condition($list)) !== null) {
            $where .= " AND $condition";
        }
        if (($place = Score_cursor::place($after)) !== null) {
            $where .= ' AND (m.score, m.created_at, m.id) < (:score, :at, :id)';
            $params += $place;
        }
        $rows = $this->db->query_bind(
            "SELECT m.id, m.job_title, m.company, m.job_url, m.cv_name, m.score, m.points, m.max_points, m.tag,
                    m.application_text IS NOT NULL AS has_application,
                    m.resume_text IS NOT NULL AS has_resume, m.created_at,
                    (SELECT i.text FROM cv_match_items i WHERE i.cv_match_id = m.id AND i.criterion = 'location' AND i.verdict = 'missing' ORDER BY i.sort_order LIMIT 1) AS location_missed,
                    j.status AS jobnet_status, j.logged_at AS jobnet_logged_at
             FROM cv_matches m
             LEFT JOIN cv_match_integrations j ON j.cv_match_id = m.id AND j.integration = 'jobnet_joblog'
             WHERE $where
             ORDER BY m.score DESC, m.created_at DESC, m.id DESC
             LIMIT " . ($limit + 1),
            $params,
            'array'
        );
        $next = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $last = end($rows);
            $next = Score_cursor::after($last['score'], $last['created_at'], $last['id']);
        }
        return ['rows' => $rows, 'next' => $next];
    }

    /**
     * The condition for a list of matches by its name: 'shortlist' for the
     * top and good ones (Cv_matcher::match_tag) not applied for yet,
     * 'not_joblogged' for the ones applied for (Joblog::APPLIED_STATUSES)
     * but not yet entered in Jobnet's joblog, 'joblogged' for the ones
     * entered there; null (all of them) for any other.
     */
    private static function list_condition(string $list): ?string {
        $applied = "('" . implode("', '", Joblog::APPLIED_STATUSES) . "')";
        return match ($list) {
            'shortlist' => "m.tag IN ('top', 'good') AND (j.status IS NULL OR j.status NOT IN $applied) AND j.logged_at IS NULL",
            'not_joblogged' => "j.status IN $applied AND j.logged_at IS NULL",
            'joblogged' => 'j.logged_at IS NOT NULL',
            default => null,
        };
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
        $match['jobnet_joblog'] = $this->integration($id, 'jobnet_joblog');
        return $match;
    }

    /** A match's row in cv_match_integrations for one outside service, or null. */
    public function integration(int $id, string $integration): ?array {
        $rows = $this->db->query_bind(
            'SELECT status, external_id, logged_at, updated_at FROM cv_match_integrations
             WHERE cv_match_id = :id AND integration = :integration',
            ['id' => $id, 'integration' => $integration],
            'array'
        );
        return $rows[0] ?? null;
    }

    /**
     * Saves a match's status in an outside service. $logged says whether the
     * candidate has entered it there; the first time keeps its date.
     */
    public function save_integration(int $id, string $integration, string $status, bool $logged): array {
        $now = time();
        $this->db->query_bind(
            'INSERT INTO cv_match_integrations (cv_match_id, integration, status, logged_at, created_at, updated_at)
             VALUES (:id, :integration, :status, :logged_at, :now, :now2)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at),
                 logged_at = IF(:logged = 1, COALESCE(logged_at, VALUES(logged_at)), NULL)',
            ['id' => $id, 'integration' => $integration, 'status' => $status,
             'logged_at' => $logged ? $now : null, 'now' => $now, 'now2' => $now, 'logged' => $logged ? 1 : 0]
        );
        return $this->integration($id, $integration);
    }

    /** Saves a match's application; returns when it was written. */
    public function save_application(int $id, string $text): int {
        $written_at = time();
        $this->db->update($id, ['application_text' => $text, 'application_written_at' => $written_at], 'cv_matches');
        return $written_at;
    }

    /** Saves a match's résumé text; returns when it was written. */
    public function save_resume(int $id, string $text): int {
        $written_at = time();
        $this->db->update($id, ['resume_text' => $text, 'resume_written_at' => $written_at], 'cv_matches');
        return $written_at;
    }

    /**
     * Saves a tailored résumé's fields (Tailored_resume::clean()) and its
     * plain text, replacing what was written before, in one transaction.
     */
    public function save_resume_fields(int $id, array $resume, string $text): int {
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
            $written_at = $this->save_resume($id, $text);
            $this->db->query('COMMIT');
            return $written_at;
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

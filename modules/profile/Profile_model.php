<?php
require_once __DIR__ . '/Profile_fields.php';

/**
 * A candidate's profile in candidate_profiles (the header),
 * candidate_profile_entries and candidate_profile_lines. Every method takes
 * the candidate's id and only reads or changes that candidate's rows.
 */
class Profile_model extends Model {

    /** name, email, phone of the candidate (candidates). */
    public function candidate(int $candidate_id): array {
        $rows = $this->db->query_bind('SELECT name, email, phone FROM candidates WHERE id = :id', ['id' => $candidate_id], 'array');
        return $rows[0] ?? ['name' => '', 'email' => '', 'phone' => ''];
    }

    /** The header (title, location, about, filled_from_cv_name, filled_from_cv_at), or null without a profile. */
    public function header(int $candidate_id): ?array {
        $rows = $this->db->query_bind(
            'SELECT title, location, about, filled_from_cv_name, filled_from_cv_at FROM candidate_profiles WHERE candidate_id = :candidate_id',
            ['candidate_id' => $candidate_id],
            'array'
        );
        return $rows[0] ?? null;
    }

    /**
     * The entries, each with its bullets, by section, newest first: ongoing
     * ones on top, then by when they ended and started; the CV's order breaks ties.
     */
    public function entries(int $candidate_id): array {
        $entries = $this->db->query_bind(
            'SELECT id, section, title, organisation, location, url, started_on, ended_on, summary, note
             FROM candidate_profile_entries WHERE candidate_id = :candidate_id
             ORDER BY FIELD(section, \'experience\', \'project\', \'education\'), ended_on IS NULL DESC, ended_on DESC, started_on DESC, sort_order, id',
            ['candidate_id' => $candidate_id],
            'array'
        );
        $bullets = [];
        $rows = $this->db->query_bind(
            'SELECT entry_id, text FROM candidate_profile_lines WHERE candidate_id = :candidate_id AND kind = \'bullet\' ORDER BY sort_order, id',
            ['candidate_id' => $candidate_id],
            'array'
        );
        foreach ($rows as $row) {
            $bullets[(int) $row['entry_id']][] = $row['text'];
        }
        foreach ($entries as &$entry) {
            $entry['id'] = (int) $entry['id'];
            $entry['bullets'] = $bullets[$entry['id']] ?? [];
        }
        return $entries;
    }

    /** kind => [text, ...] for the lists (Profile_fields::LISTS). */
    public function lines(int $candidate_id): array {
        $lines = array_fill_keys(array_keys(Profile_fields::LISTS), []);
        $rows = $this->db->query_bind(
            'SELECT kind, text FROM candidate_profile_lines WHERE candidate_id = :candidate_id AND entry_id IS NULL ORDER BY sort_order, id',
            ['candidate_id' => $candidate_id],
            'array'
        );
        foreach ($rows as $row) {
            if (isset($lines[$row['kind']])) {
                $lines[$row['kind']][] = $row['text'];
            }
        }
        return $lines;
    }

    /**
     * Replaces the whole profile with what was read from a CV
     * (Profile_fields::from_cv), all or nothing.
     */
    public function replace_from_cv(int $candidate_id, array $profile, string $cv_name): void {
        $this->in_transaction(function () use ($candidate_id, $profile, $cv_name) {
            $this->db->query_bind('DELETE FROM candidate_profile_entries WHERE candidate_id = :candidate_id', ['candidate_id' => $candidate_id]);
            $this->db->query_bind('DELETE FROM candidate_profile_lines WHERE candidate_id = :candidate_id', ['candidate_id' => $candidate_id]);
            $this->save_header_row($candidate_id, $profile);
            $this->save_name_and_phone($candidate_id, $profile);
            $this->db->query_bind(
                'UPDATE candidate_profiles SET filled_from_cv_name = :cv_name, filled_from_cv_at = :now WHERE candidate_id = :candidate_id',
                ['cv_name' => mb_substr($cv_name, 0, 255, 'UTF-8'), 'now' => time(), 'candidate_id' => $candidate_id]
            );
            foreach ($profile['entries'] as $order => $entry) {
                $this->insert_entry($candidate_id, $entry, $order);
            }
            foreach ($profile['lines'] as $kind => $items) {
                $this->insert_lines($candidate_id, null, $kind, $items);
            }
        });
    }

    /**
     * Saves the header (title, location, about), making the profile if it
     * has none, and the candidate's name and phone (candidates) when given.
     */
    public function save_header(int $candidate_id, array $header): void {
        $this->in_transaction(function () use ($candidate_id, $header) {
            $this->save_header_row($candidate_id, $header);
            $this->save_name_and_phone($candidate_id, $header, true);
        });
    }

    /** Saves an entry and its bullets: a new one without $entry_id. Returns its id, or null when it isn't the candidate's. */
    public function save_entry(int $candidate_id, ?int $entry_id, array $entry): ?int {
        return $this->in_transaction(function () use ($candidate_id, $entry_id, $entry) {
            $this->ensure_header($candidate_id);
            if ($entry_id === null) {
                return $this->insert_entry($candidate_id, $entry, 0);
            }
            if (!$this->owns_entry($candidate_id, $entry_id)) {
                return null;
            }
            $this->db->query_bind(
                'UPDATE candidate_profile_entries SET section = :section, title = :title, organisation = :organisation,
                 location = :location, url = :url, started_on = :started_on, ended_on = :ended_on, summary = :summary,
                 note = :note, updated_at = :now WHERE id = :id',
                $this->entry_params($entry) + ['now' => time(), 'id' => $entry_id]
            );
            $this->db->query_bind('DELETE FROM candidate_profile_lines WHERE entry_id = :entry_id', ['entry_id' => $entry_id]);
            $this->insert_lines($candidate_id, $entry_id, 'bullet', $entry['bullets']);
            $this->touch($candidate_id);
            return $entry_id;
        });
    }

    /** Deletes an entry (its bullets go with it). False when it isn't the candidate's. */
    public function delete_entry(int $candidate_id, int $entry_id): bool {
        if (!$this->owns_entry($candidate_id, $entry_id)) {
            return false;
        }
        $this->db->query_bind('DELETE FROM candidate_profile_entries WHERE id = :id', ['id' => $entry_id]);
        $this->touch($candidate_id);
        return true;
    }

    /** Replaces the lists: kind => [text, ...] for each of Profile_fields::LISTS. */
    public function save_lists(int $candidate_id, array $lists): void {
        $this->in_transaction(function () use ($candidate_id, $lists) {
            $this->ensure_header($candidate_id);
            $this->db->query_bind('DELETE FROM candidate_profile_lines WHERE candidate_id = :candidate_id AND entry_id IS NULL', ['candidate_id' => $candidate_id]);
            foreach (array_keys(Profile_fields::LISTS) as $kind) {
                $this->insert_lines($candidate_id, null, $kind, $lists[$kind] ?? []);
            }
            $this->touch($candidate_id);
        });
    }

    private function save_header_row(int $candidate_id, array $header): void {
        $now = time();
        $this->db->query_bind(
            'INSERT INTO candidate_profiles (candidate_id, title, location, about, created_at, updated_at)
             VALUES (:candidate_id, :title, :location, :about, :now, :now2)
             ON DUPLICATE KEY UPDATE title = VALUES(title), location = VALUES(location), about = VALUES(about), updated_at = VALUES(updated_at)',
            ['candidate_id' => $candidate_id, 'title' => $header['title'], 'location' => $header['location'], 'about' => $header['about'], 'now' => $now, 'now2' => $now]
        );
    }

    /**
     * The candidate's name and phone, each only when $fields has it (a CV
     * without a phone keeps the saved one); with $clear_phone (the page's
     * form) an empty phone removes the saved one.
     */
    private function save_name_and_phone(int $candidate_id, array $fields, bool $clear_phone = false): void {
        if ($clear_phone && ($fields['phone'] ?? '') === '') {
            $this->db->query_bind('UPDATE candidates SET phone = NULL, updated_at = :now WHERE id = :id', ['now' => time(), 'id' => $candidate_id]);
        }
        foreach (['name', 'phone'] as $column) {
            if (($fields[$column] ?? '') !== '') {
                $this->db->query_bind(
                    "UPDATE candidates SET $column = :value, updated_at = :now WHERE id = :id",
                    ['value' => $fields[$column], 'now' => time(), 'id' => $candidate_id]
                );
            }
        }
    }

    private function ensure_header(int $candidate_id): void {
        if ($this->header($candidate_id) === null) {
            $this->save_header_row($candidate_id, ['title' => '', 'location' => '', 'about' => '']);
        }
    }

    private function touch(int $candidate_id): void {
        $this->db->query_bind('UPDATE candidate_profiles SET updated_at = :now WHERE candidate_id = :candidate_id', ['now' => time(), 'candidate_id' => $candidate_id]);
    }

    private function owns_entry(int $candidate_id, int $entry_id): bool {
        $rows = $this->db->query_bind(
            'SELECT id FROM candidate_profile_entries WHERE id = :id AND candidate_id = :candidate_id',
            ['id' => $entry_id, 'candidate_id' => $candidate_id],
            'array'
        );
        return isset($rows[0]);
    }

    private function insert_entry(int $candidate_id, array $entry, int $sort_order): int {
        $now = time();
        $entry_id = $this->db->insert([
            'candidate_id' => $candidate_id,
            'sort_order' => $sort_order,
            'created_at' => $now,
            'updated_at' => $now,
        ] + $this->entry_params($entry), 'candidate_profile_entries');
        $this->insert_lines($candidate_id, $entry_id, 'bullet', $entry['bullets']);
        return $entry_id;
    }

    private function entry_params(array $entry): array {
        return [
            'section' => $entry['section'],
            'title' => $entry['title'],
            'organisation' => $entry['organisation'],
            'location' => $entry['location'],
            'url' => $entry['url'],
            'started_on' => $entry['started_on'],
            'ended_on' => $entry['ended_on'],
            'summary' => $entry['summary'],
            'note' => $entry['note'],
        ];
    }

    private function insert_lines(int $candidate_id, ?int $entry_id, string $kind, array $items): void {
        foreach (array_values($items) as $order => $text) {
            $this->db->insert([
                'candidate_id' => $candidate_id,
                'entry_id' => $entry_id,
                'kind' => $kind,
                'text' => $text,
                'sort_order' => $order,
            ], 'candidate_profile_lines');
        }
    }

    private function in_transaction(callable $work): mixed {
        $this->db->query('START TRANSACTION');
        try {
            $result = $work();
            $this->db->query('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }
}

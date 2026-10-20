<?php
/**
 * Job_searches_model - Handles data operations for job search records.
 *
 * Demonstrates proper data conversion patterns and separation
 * of concerns between database operations and presentation logic.
 */
class Job_searches_model extends Model {
    
    private string $table_name = 'job_searches';

    /**
     * Return the list of searchable column names (used by the controller for validation).
     *
     * @return array<string>
     */
    public function get_searchable_columns(): array {
        return ['name', 'keywords', 'location'];
    }


    /**
     * Retrieve a single record from the database by ID.
     *
     * @param int $update_id Record ID.
     * @param bool $prepare_for_display Whether to pass the record through prepare_record_for_display().
     * @return array|bool Associative array on success or false on failure.
     */
    public function get_data_from_db(int $update_id, bool $prepare_for_display = false): array|bool {
        $record_obj = $this->db->get_where($update_id, $this->table_name);

        if ($record_obj === false) {
            return false;
        }

        if ($prepare_for_display === true) {
            $record_obj = $this->prepare_record_for_display($record_obj);
        }

        return (array) $record_obj;
    }



    /**
     * Fetch paginated job searches records.
     *
     * @param int $limit Records per page
     * @param int $offset Records to skip
     * @return array<object>
     */
    public function fetch_records(int $limit, int $offset): array {
        $sql = 'SELECT * FROM '.$this->table_name.' ORDER BY id desc LIMIT '.$limit.' OFFSET '.$offset;
        return $this->db->query($sql, 'object');
    }
    
    /**
     * Count all job searches records in the database table.
     *
     * @return int Total number of job searches records.
     */
    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * Search job searches records across searchable columns.
     *
     * @param string $query The search query string.
     * @param string $column Specific column to search, or empty string to search all.
     * @param int $limit Maximum records to return.
     * @param int $offset Records to skip.
     * @return array<object>
     */
    public function search_records(string $query, string $column, int $limit, int $offset): array {
        $searchable_columns = $this->get_searchable_columns();

        // Validate column against whitelist before interpolating into SQL.
        if ($column !== '' && !in_array($column, $searchable_columns, true)) {
            $column = '';
        }

        if ($column !== '') {
            $sql = 'SELECT * FROM '.$this->table_name.' WHERE '.$column.' LIKE :query ORDER BY id desc LIMIT '.$limit.' OFFSET '.$offset;
        } else {
            $conditions = [];
            foreach ($searchable_columns as $col) {
                $conditions[] = $col.' LIKE :query';
            }
            $sql = 'SELECT * FROM '.$this->table_name.' WHERE ('.implode(' OR ', $conditions).') ORDER BY id desc LIMIT '.$limit.' OFFSET '.$offset;
        }

        return $this->db->query_bind($sql, ['query' => '%'.$query.'%'], 'object');
    }

    /**
     * Count search results for job searches records.
     *
     * @param string $query The search query string.
     * @param string $column Specific column to search, or empty string to search all.
     * @return int Number of matching records.
     */
    public function count_search_results(string $query, string $column): int {
        $searchable_columns = $this->get_searchable_columns();

        // Validate column against whitelist before interpolating into SQL.
        if ($column !== '' && !in_array($column, $searchable_columns, true)) {
            $column = '';
        }

        if ($column !== '') {
            $sql = 'SELECT COUNT(*) as total FROM '.$this->table_name.' WHERE '.$column.' LIKE :query';
        } else {
            $conditions = [];
            foreach ($searchable_columns as $col) {
                $conditions[] = $col.' LIKE :query';
            }
            $sql = 'SELECT COUNT(*) as total FROM '.$this->table_name.' WHERE ('.implode(' OR ', $conditions).')';
        }

        $result = $this->db->query_bind($sql, ['query' => '%'.$query.'%'], 'object');
        return (int) ($result[0]->total ?? 0);
    }

    /**
     * Prepare multiple job searches records for display in list views.
     *
     * @param array $rows Array of job search record objects from database
     * @return array Array of objects with formatted display fields
     */
    public function prepare_records_for_display(array $rows): array {
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[] = $this->prepare_record_for_display($row);
        }
        return $prepared;
    }

    /**
     * Prepare raw job search database data for display in views.
     *
     * @param object $record_obj Raw data from database
     * @return object Enhanced data with formatted fields
     */
    public function prepare_record_for_display(object $record_obj): object {
        $record_obj->trongate_user_id = $record_obj->trongate_user_id === null ? '' : (string) $record_obj->trongate_user_id;
        $record_obj->name = trim((string) $record_obj->name);
        $record_obj->keywords = trim((string) $record_obj->keywords);
        $record_obj->location = trim((string) $record_obj->location);
        $record_obj->remote_ok = (int) $record_obj->remote_ok === 1 ? 'Yes' : 'No';
        $record_obj->cv_name = trim((string) $record_obj->cv_name);
        $record_obj->tailor_from_score = $record_obj->tailor_from_score === null ? '' : (string) $record_obj->tailor_from_score;
        $record_obj->scan_every_hours = $record_obj->scan_every_hours === null ? '' : (string) $record_obj->scan_every_hours;
        $record_obj->active = (int) $record_obj->active === 1 ? 'Yes' : 'No';
        $record_obj->paused_reason = trim((string) $record_obj->paused_reason);
        $record_obj->last_scanned_at = $record_obj->last_scanned_at === null ? '' : date('Y-m-d H:i', (int) $record_obj->last_scanned_at);
        $record_obj->created_at = date('Y-m-d H:i', (int) $record_obj->created_at);
        $record_obj->updated_at = date('Y-m-d H:i', (int) $record_obj->updated_at);
        return $record_obj;
    }

    // More than this in an hour, or ODD_PER_DAY in a day, is shown as unusual.
    public const ODD_PER_HOUR = 20;
    public const ODD_PER_DAY = 100;

    /**
     * Users whose job posts added by link (by hand or job_scan/api) or CV
     * matches look unusual: more than ODD_PER_HOUR links in the last hour,
     * or more than ODD_PER_DAY links or matches in the last day. Matches are
     * counted too because they outlive a deleted search's posts.
     *
     * @return array<int, array{user_id: int, email: string, links_hour: int, links_day: int, matches_day: int}>
     */
    public function unusual_activity(int $now): array {
        $rows = $this->db->query_bind(
            'SELECT u.id AS user_id, COALESCE(c.email, \'\') AS email,
                (SELECT COUNT(*) FROM scanned_job_posts p JOIN job_searches s ON s.id = p.job_search_id
                 WHERE s.trongate_user_id = u.id AND p.job_search_run_id IS NULL AND p.found_at >= :hour) AS links_hour,
                (SELECT COUNT(*) FROM scanned_job_posts p JOIN job_searches s ON s.id = p.job_search_id
                 WHERE s.trongate_user_id = u.id AND p.job_search_run_id IS NULL AND p.found_at >= :day1) AS links_day,
                (SELECT COUNT(*) FROM cv_matches m WHERE m.trongate_user_id = u.id AND m.created_at >= :day2) AS matches_day
             FROM trongate_users u LEFT JOIN candidates c ON c.trongate_user_id = u.id
             HAVING links_hour > :odd_hour OR links_day > :odd_day1 OR matches_day > :odd_day2
             ORDER BY matches_day + links_day DESC',
            ['hour' => $now - 3600, 'day1' => $now - 86400, 'day2' => $now - 86400, 'odd_hour' => self::ODD_PER_HOUR, 'odd_day1' => self::ODD_PER_DAY, 'odd_day2' => self::ODD_PER_DAY],
            'array'
        );
        return array_map(fn(array $r) => array_map(fn($v) => is_numeric($v) ? (int) $v : $v, $r), $rows);
    }

}

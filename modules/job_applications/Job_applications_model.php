<?php
/**
 * Job_applications_model - Handles data operations for job application records.
 *
 * Demonstrates proper data conversion patterns and separation
 * of concerns between database operations and presentation logic.
 */
class Job_applications_model extends Model {
    
    private string $table_name = 'job_applications';

    /**
     * Return the list of searchable column names (used by the controller for validation).
     *
     * @return array<string>
     */
    public function get_searchable_columns(): array {
        return ['current_title'];
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
     * Retrieve a record for editing or delete confirmation (no display transformations).
     *
     * @param int $update_id Record ID.
     * @return array|bool Associative array on success or false on failure.
     */
    public function get_data_for_edit(int $update_id): array|bool {
        return $this->get_data_from_db($update_id, false);
    }

    /**
     * Find a record by ID and return it as an object.
     *
     * @param int $update_id Record ID.
     * @return object|bool Record object on success or false on failure.
     */
    public function find_by_id(int $update_id): object|bool {
        return $this->db->get_where($update_id, $this->table_name);
    }

    /**
     * Fetch paginated job applications records.
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
     * Count all job applications records in the database table.
     *
     * @return int Total number of job applications records.
     */
    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * Search job applications records across searchable columns.
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
     * Count search results for job applications records.
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
     * Prepare multiple job applications records for display in list views.
     *
     * @param array $rows Array of job application record objects from database
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
     * Prepare raw job application database data for display in views.
     *
     * @param object $record_obj Raw data from database
     * @return object Enhanced data with formatted fields
     */
    public function prepare_record_for_display(object $record_obj): object {
        $record_obj->current_title = trim((string) $record_obj->current_title);
        $record_obj->level = trim((string) $record_obj->level);
        $record_obj->workplace_flexibility = trim((string) $record_obj->workplace_flexibility);
        $record_obj->work_hours = trim((string) $record_obj->work_hours);
        $record_obj->postal_code = trim((string) $record_obj->postal_code);
        $record_obj->language = trim((string) $record_obj->language);
        $record_obj->source = trim((string) $record_obj->source);
        $record_obj->raw_text = trim((string) $record_obj->raw_text);
        $record_obj->cover_letter = trim((string) $record_obj->cover_letter);
        $record_obj->extractor = trim((string) $record_obj->extractor);
        $record_obj->status = trim((string) $record_obj->status);
        $record_obj->reject_reason = trim((string) $record_obj->reject_reason);
        $record_obj->submitted_at = $record_obj->submitted_at === null ? '' : date('j M Y H:i', (int) $record_obj->submitted_at);
        $record_obj->shortlisted_at = $record_obj->shortlisted_at === null ? '' : date('j M Y H:i', (int) $record_obj->shortlisted_at);
        $record_obj->bookmarked_at = $record_obj->bookmarked_at === null ? '' : date('j M Y H:i', (int) $record_obj->bookmarked_at);
        $record_obj->rejected_at = $record_obj->rejected_at === null ? '' : date('j M Y H:i', (int) $record_obj->rejected_at);
        $record_obj->withdrawn_at = $record_obj->withdrawn_at === null ? '' : date('j M Y H:i', (int) $record_obj->withdrawn_at);
        $record_obj->created_at = $record_obj->created_at === null ? '' : date('j M Y H:i', (int) $record_obj->created_at);
        $record_obj->updated_at = $record_obj->updated_at === null ? '' : date('j M Y H:i', (int) $record_obj->updated_at);
        return $record_obj;
    }



    /**
     * Delete a job application record.
     *
     * @param int $update_id The ID of the record to delete
     * @return void
     */
    public function delete_record(int $update_id): void {
        $this->db->delete($update_id, $this->table_name);
    }
}

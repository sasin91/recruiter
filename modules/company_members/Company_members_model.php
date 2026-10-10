<?php
/**
 * Company_members_model - Handles data operations for company member records.
 *
 * Demonstrates proper data conversion patterns and separation
 * of concerns between database operations and presentation logic.
 */
class Company_members_model extends Model {
    
    private string $table_name = 'company_members';

    /**
     * Return the list of searchable column names (used by the controller for validation).
     *
     * @return array<string>
     */
    public function get_searchable_columns(): array {
        return ['name', 'email'];
    }

    /**
     * Get company member form data from POST and prepare it for database or view display.
     *
     * @return array Form data with proper types
     */
    public function get_data_from_post(): array {
        return [
            'name' => post('name', true),
            'email' => strtolower(trim(post('email', true))),
            'role' => post('role', true),
            'active' => (int) (bool) post('active', true),
            'updated_at' => time()
        ];
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
     * Fetch paginated company members records.
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
     * Count all company members records in the database table.
     *
     * @return int Total number of company members records.
     */
    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * Search company members records across searchable columns.
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
     * Count search results for company members records.
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
     * Prepare multiple company members records for display in list views.
     *
     * @param array $rows Array of company member record objects from database
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
     * Prepare raw company member database data for display in views.
     *
     * @param object $record_obj Raw data from database
     * @return object Enhanced data with formatted fields
     */
    public function prepare_record_for_display(object $record_obj): object {
        $record_obj->name = trim((string) $record_obj->name);
        $record_obj->role = trim((string) $record_obj->role);
        $record_obj->active = ($record_obj->active == 1) ? 'yes' : 'no';
        $record_obj->invited_at = $record_obj->invited_at === null ? '' : date('j M Y H:i', (int) $record_obj->invited_at);
        $record_obj->last_login = $record_obj->last_login === null ? '' : date('j M Y H:i', (int) $record_obj->last_login);
        $record_obj->created_at = $record_obj->created_at === null ? '' : date('j M Y H:i', (int) $record_obj->created_at);
        $record_obj->updated_at = $record_obj->updated_at === null ? '' : date('j M Y H:i', (int) $record_obj->updated_at);
        return $record_obj;
    }


    /**
     * Update an existing company member record.
     *
     * @param int $update_id The ID of the record to update
     * @param array $data The data to update
     * @return void
     */
    public function update_record(int $update_id, array $data): void {
        $this->db->update($update_id, $data, $this->table_name);
    }

    /**
     * Whether a member other than $except_id uses this email.
     *
     * @param string $email
     * @param int $except_id The member being edited
     * @return bool
     */
    public function email_taken(string $email, int $except_id): bool {
        $rows = $this->db->query_bind(
            'SELECT id FROM company_members WHERE email = :email AND id <> :id',
            ['email' => $email, 'id' => $except_id],
            'array'
        );
        return $rows !== [];
    }

    /**
     * How many other active owners the member's company has.
     *
     * @param array $member A company_members row
     * @return int
     */
    public function count_other_active_owners(array $member): int {
        $rows = $this->db->query_bind(
            "SELECT COUNT(*) AS total FROM company_members WHERE company_id = :company_id AND role = 'owner' AND active = 1 AND id <> :id",
            ['company_id' => (int) $member['company_id'], 'id' => (int) $member['id']],
            'array'
        );
        return (int) $rows[0]['total'];
    }
}

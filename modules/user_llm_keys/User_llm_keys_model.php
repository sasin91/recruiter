<?php
/**
 * User_llm_keys_model - Handles data operations for user ai key records.
 *
 * Demonstrates proper data conversion patterns and separation
 * of concerns between database operations and presentation logic.
 */
class User_llm_keys_model extends Model {
    
    private string $table_name = 'user_llm_keys';

    /**
     * Return the list of searchable column names (used by the controller for validation).
     *
     * @return array<string>
     */
    public function get_searchable_columns(): array {
        return [];
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
     * Fetch paginated user ai keys records.
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
     * Count all user ai keys records in the database table.
     *
     * @return int Total number of user ai keys records.
     */
    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * Prepare multiple user ai keys records for display in list views.
     *
     * @param array $rows Array of user ai key record objects from database
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
     * Prepare raw user ai key database data for display in views.
     *
     * @param object $record_obj Raw data from database
     * @return object Enhanced data with formatted fields
     */
    public function prepare_record_for_display(object $record_obj): object {
        $record_obj->provider = trim((string) $record_obj->provider);
        $record_obj->model = trim((string) $record_obj->model);
        $record_obj->key_hint = trim((string) $record_obj->key_hint);
        $record_obj->created_at = $record_obj->created_at === null ? '' : date('j M Y H:i', (int) $record_obj->created_at);
        $record_obj->updated_at = $record_obj->updated_at === null ? '' : date('j M Y H:i', (int) $record_obj->updated_at);
        return $record_obj;
    }



    /**
     * Delete a user ai key record.
     *
     * @param int $update_id The ID of the record to delete
     * @return void
     */
    public function delete_record(int $update_id): void {
        $this->db->delete($update_id, $this->table_name);
    }
}

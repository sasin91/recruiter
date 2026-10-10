<?php
/**
 * Job_post_member_views_model - Handles data operations for job post view records.
 *
 * Demonstrates proper data conversion patterns and separation
 * of concerns between database operations and presentation logic.
 */
class Job_post_member_views_model extends Model {
    
    private string $table_name = 'job_post_member_views';

    /**
     * Return the list of searchable column names (used by the controller for validation).
     *
     * @return array<string>
     */
    public function get_searchable_columns(): array {
        return [];
    }





    /**
     * Fetch paginated job post views records.
     *
     * @param int $limit Records per page
     * @param int $offset Records to skip
     * @return array<object>
     */
    public function fetch_records(int $limit, int $offset): array {
        $sql = 'SELECT * FROM '.$this->table_name.' ORDER BY job_post_id desc LIMIT '.$limit.' OFFSET '.$offset;
        return $this->db->query($sql, 'object');
    }
    
    /**
     * Count all job post views records in the database table.
     *
     * @return int Total number of job post views records.
     */
    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * Prepare multiple job post views records for display in list views.
     *
     * @param array $rows Array of job post view record objects from database
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
     * Prepare raw job post view database data for display in views.
     *
     * @param object $record_obj Raw data from database
     * @return object Enhanced data with formatted fields
     */
    public function prepare_record_for_display(object $record_obj): object {
        $record_obj->last_viewed_at = $record_obj->last_viewed_at === null ? '' : date('j M Y H:i', (int) $record_obj->last_viewed_at);
        return $record_obj;
    }



}

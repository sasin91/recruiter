<?php
/**
 * Candidate_llm_key_uses Controller
 *
 * Manages candidate ai key use records with full CRUD operations.
 */
class Candidate_llm_key_uses extends Trongate {

    private int $default_limit = 20;
    private array $per_page_options = [10, 20, 50, 100];
    
    /**
     * Default entry point - redirects to manage page
     *
     * @return void
     */
    public function index(): void {
        redirect('candidate_llm_key_uses/manage');
    }

    /**
     * Display paginated list of candidate ai key uses.
     *
     * Shows records in a table with pagination controls. Includes
     * dropdown for selecting number of records per page.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();

        $total_rows = $this->model->count_all(); // Required for pagination.
        
        $limit = $this->get_limit();
        $offset = $this->get_offset();

        $rows = $this->model->fetch_records($limit, $offset);
        $rows = $this->model->prepare_records_for_display($rows);

        $data = [
            'rows' => $rows,
            'pagination_data' => $this->get_pagination_data($total_rows, $limit),
            'view_module' => 'candidate_llm_key_uses',
            'view_file' => 'manage',
            'per_page_options' => $this->per_page_options,
            'selected_per_page' => $this->get_selected_per_page()
        ];

        $this->templates->admin($data);
    }



    /**
     * Display detailed view of a single candidate ai key use.
     *
     * Shows all details with edit/delete options.
     * Automatically handles missing records with 404 page.
     *
     * @return void
     */
    public function show(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');

        if ($update_id === 0) {
            redirect('candidate_llm_key_uses/manage');
        }

        // Fetch record and prepare for display.
        $data = $this->model->get_data_from_db($update_id, true);

        if ($data === false) {
            $this->not_found();
            return;
        }

        // Add additional view data
        $data['update_id'] = $update_id;
        $data['headline'] = 'Candidate AI Key Use Details';
        $data['back_url'] = $this->get_back_url();
        $data['view_module'] = 'candidate_llm_key_uses';
        $data['view_file'] = 'show';
        $this->templates->admin($data);
    }

    /**
     * Display confirmation page before deleting a candidate ai key use.
     *
     * Shows confirmation dialog with details to prevent accidental deletion.
     *
     * @return void
     */
    public function delete_conf(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');

        if ($update_id === 0) {
            $this->not_found();
            return;
        }

        $data = $this->model->get_data_for_edit($update_id);

        if ($data === false) {
            $this->not_found();
            return;
        }

        $data['update_id'] = $update_id;
        $data['headline'] = 'Delete Candidate AI Key Use Record';
        $data['cancel_url'] = 'candidate_llm_key_uses/show/'.$update_id;
        $data['form_location'] = 'candidate_llm_key_uses/submit_delete/'.$update_id;
        $data['view_module'] = 'candidate_llm_key_uses';
        $data['view_file'] = 'delete_conf';
        $this->templates->admin($data);
    }

    /**
     * Handle candidate ai key use deletion after confirmation.
     *
     * Verifies confirmation and deletes record from database.
     * Includes safety checks to prevent unauthorized deletion.
     *
     * @return void
     */
    public function submit_delete(): void {
        $this->trongate_security->make_sure_allowed();

        $submit = post('submit', true);

        if ($submit === 'Yes - Delete Now') {
            $update_id = segment(3, 'int');

            if ($update_id === 0) {
                redirect('candidate_llm_key_uses/manage');
                return;
            }

            $record = $this->model->find_by_id($update_id);

            if ($record === false) {
                redirect('candidate_llm_key_uses/manage');
                return;
            }

            $this->model->delete_record($update_id);

            set_flashdata('The record was successfully deleted');
            redirect('candidate_llm_key_uses/manage');
        } else {
            redirect('candidate_llm_key_uses/manage');
        }
    }

    /**
     * Set number of records per page for pagination.
     *
     * Stores user preference in session for consistent pagination across requests.
     *
     * @return void
     */
    public function set_per_page(): void {
        $this->trongate_security->make_sure_allowed();

        $selected_index = segment(3, 'int');

        if (!isset($this->per_page_options[$selected_index])) {
            $selected_index = 1;
        }

        $_SESSION['selected_per_page'] = $selected_index;
        redirect('candidate_llm_key_uses/manage');
    }

    /**
     * Generate pagination configuration data.
     *
     * @param int    $total_rows    Total number of records
     * @param int    $limit         Number of records per page
     * @return array Pagination configuration for template
     */
    private function get_pagination_data(int $total_rows, int $limit): array {
        return [
            'total_rows' => $total_rows,
            'limit' => $limit,
            'pagination_root' => 'candidate_llm_key_uses/manage',
            'record_name_plural' => 'candidate ai key uses',
            'include_showing_statement' => true
        ];
    }

    /**
     * Determine appropriate back URL for navigation.
     *
     * Uses previous URL if it was the manage page, otherwise defaults to manage.
     *
     * @return string URL for back button
     */
    private function get_back_url(): string {
        $previous_url = previous_url();
        if ($previous_url !== '' && strpos($previous_url, BASE_URL . 'candidate_llm_key_uses/manage') === 0) {
            return $previous_url;
        }
        return BASE_URL . 'candidate_llm_key_uses/manage';
    }

    /**
     * Display 404-style not found page for missing candidate ai key uses.
     *
     * Shows user-friendly error message with navigation back.
     *
     * @return void
     */
    private function not_found(): void {
        $data = [
            'headline' => 'Candidate AI Key Use Not Found',
            'message' => 'The candidate ai key use you\'re looking for doesn\'t exist or has been deleted.',
            'back_url' => $this->get_back_url(),
            'back_label' => 'Go Back',
            'view_module' => 'candidate_llm_key_uses',
            'view_file' => 'not_found'
        ];
        $this->templates->admin($data);
    }

    /**
     * Get selected per-page index from session.
     *
     * @return int Index of selected per-page option
     */
    private function get_selected_per_page(): int {
        return $_SESSION['selected_per_page'] ?? 1;
    }

    /**
     * Get current pagination limit from session.
     *
     * @return int Number of records to display per page
     */
    private function get_limit(): int {
        if (isset($_SESSION['selected_per_page'])) {
            return $this->per_page_options[$_SESSION['selected_per_page']];
        }
        return $this->default_limit;
    }

    /**
     * Calculate pagination offset based on page number.
     *
     * @return int Database offset for current page
     */
    private function get_offset(): int {
        $page_num = segment(3, 'int');
        return ($page_num > 1) ? ($page_num - 1) * $this->get_limit() : 0;
    }



}

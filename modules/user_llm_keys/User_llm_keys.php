<?php
/**
 * User_llm_keys Controller
 *
 * Manages user ai key records with full CRUD operations.
 */
class User_llm_keys extends Trongate {

    private int $default_limit = 20;
    private array $per_page_options = [10, 20, 50, 100];
    
    /**
     * Default entry point - redirects to manage page
     *
     * @return void
     */
    public function index(): void {
        redirect('user_llm_keys/manage');
    }

    /**
     * Display paginated list of user ai keys.
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
            'view_module' => 'user_llm_keys',
            'view_file' => 'manage',
            'per_page_options' => $this->per_page_options,
            'selected_per_page' => $this->get_selected_per_page()
        ];

        $this->templates->admin($data);
    }



    /**
     * Display detailed view of a single user ai key.
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
            redirect('user_llm_keys/manage');
        }

        // Fetch record and prepare for display.
        $data = $this->model->get_data_from_db($update_id, true);

        if ($data === false) {
            $this->not_found();
            return;
        }

        // Add additional view data
        $data['update_id'] = $update_id;
        $data['headline'] = 'User AI Key Details';
        $data['back_url'] = $this->get_back_url();
        $data['view_module'] = 'user_llm_keys';
        $data['view_file'] = 'show';
        $this->templates->admin($data);
    }

    /**
     * Display confirmation page before deleting a user ai key.
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
        $data['headline'] = 'Delete User AI Key Record';
        $data['cancel_url'] = 'user_llm_keys/show/'.$update_id;
        $data['form_location'] = 'user_llm_keys/submit_delete/'.$update_id;
        $data['view_module'] = 'user_llm_keys';
        $data['view_file'] = 'delete_conf';
        $this->templates->admin($data);
    }

    /**
     * Handle user ai key deletion after confirmation.
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
                redirect('user_llm_keys/manage');
                return;
            }

            $record = $this->model->find_by_id($update_id);

            if ($record === false) {
                redirect('user_llm_keys/manage');
                return;
            }

            $this->model->delete_record($update_id);

            set_flashdata('The record was successfully deleted');
            redirect('user_llm_keys/manage');
        } else {
            redirect('user_llm_keys/manage');
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
        redirect('user_llm_keys/manage');
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
            'pagination_root' => 'user_llm_keys/manage',
            'record_name_plural' => 'user ai keys',
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
        if ($previous_url !== '' && strpos($previous_url, BASE_URL . 'user_llm_keys/manage') === 0) {
            return $previous_url;
        }
        return BASE_URL . 'user_llm_keys/manage';
    }

    /**
     * Display 404-style not found page for missing user ai keys.
     *
     * Shows user-friendly error message with navigation back.
     *
     * @return void
     */
    private function not_found(): void {
        $data = [
            'headline' => 'User AI Key Not Found',
            'message' => 'The user ai key you\'re looking for doesn\'t exist or has been deleted.',
            'back_url' => $this->get_back_url(),
            'back_label' => 'Go Back',
            'view_module' => 'user_llm_keys',
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

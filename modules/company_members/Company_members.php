<?php
require_once __DIR__ . '/../company/Company_rules.php';
/**
 * Company_members Controller
 *
 * Manages company member records with full CRUD operations.
 */
class Company_members extends Trongate {

    private int $default_limit = 20;
    private array $per_page_options = [10, 20, 50, 100];
    
    /**
     * Default entry point - redirects to manage page
     *
     * @return void
     */
    public function index(): void {
        redirect('company_members/manage');
    }

    /**
     * Display paginated list of company members.
     *
     * Shows records in a table with pagination controls. Includes
     * dropdown for selecting number of records per page.
     *
     * @return void
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();

        $search_query = $_GET['search_query'] ?? '';
        $search_column = $_GET['search_column'] ?? '';

        // Validate column against known searchable columns.
        $allowed_columns = $this->model->get_searchable_columns();
        if ($search_column !== '' && !in_array($search_column, $allowed_columns, true)) {
            $search_column = '';
        }

        $search_active = ($search_query !== '');

        if ($search_active) {
            $total_rows = $this->model->count_search_results($search_query, $search_column);
        } else {
            $total_rows = $this->model->count_all();
        }
        
        $limit = $this->get_limit();
        $offset = $this->get_offset();

        if ($search_active) {
            $rows = $this->model->search_records($search_query, $search_column, $limit, $offset);
        } else {
            $rows = $this->model->fetch_records($limit, $offset);
        }
        $rows = $this->model->prepare_records_for_display($rows);

        $data = [
            'rows' => $rows,
            'pagination_data' => $this->get_pagination_data($total_rows, $limit, $search_query, $search_column),
            'view_module' => 'company_members',
            'view_file' => 'manage',
            'per_page_options' => $this->per_page_options,
            'selected_per_page' => $this->get_selected_per_page()
        ];

        $data['search_query'] = $search_query;
        $data['search_column'] = $search_column;
        $data['search_active'] = $search_active;
        $this->templates->admin($data);
    }

    /**
     * Display form for editing a company member.
     *
     * Shows form with appropriate headline and action URL.
     * Automatically repopulates form with submitted data on validation errors.
     *
     * @return void
     */
    public function create(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');

        // Rows are made by sign-up; here they are only edited.
        if ($update_id === 0) {
            redirect('company_members/manage');
        }

        if (REQUEST_TYPE === 'GET') {
            $data = $this->model->get_data_from_db($update_id);
            if ($data === false) {
                $this->not_found();
                return;
            }
        } else {
            $data = $this->model->get_data_from_post();
        }

        // Add view-specific data
        $data['headline'] = 'Update Company Member Record';
        $data['cancel_url'] = 'company_members/show/'.$update_id;
        $data['form_location'] = 'company_members/submit/'.$update_id;
        $data['view_module'] = 'company_members';
        $data['role_options'] = Company_rules::ROLES;
        $data['view_file'] = 'create';
        $this->templates->admin($data);
    }

    /**
     * Handle form submission for updating company members.
     *
     * Validates input, converts checkbox data, and saves to database.
     * Includes automatic CSRF validation and proper checkbox conversion.
     *
     * @return void
     */
    public function submit(): void {
        $this->trongate_security->make_sure_allowed();

        $submit = post('submit', true);

        if ($submit === 'Submit') {
            $this->validation->set_rules('name', 'name', 'required|max_length[255]');
            $this->validation->set_rules('email', 'email', 'valid_email|required|max_length[255]|callback_email_check');
            $this->validation->set_rules('role', 'role', 'required|callback_role_check');

            if ($this->validation->run()) {
                $update_id = segment(3, 'int');
                $data = $this->model->get_data_from_post();

                $this->model->update_record($update_id, $data);
                $flash_msg = 'Company Member updated successfully';
                $finish_url = 'company_members/show/'.$update_id;

                set_flashdata($flash_msg);
                redirect($finish_url);
            } else {
                $this->create();
            }
        } else {
            redirect('company_members/manage');
        }
    }

    /**
     * Display detailed view of a single company member.
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
            redirect('company_members/manage');
        }

        // Fetch record and prepare for display.
        $data = $this->model->get_data_from_db($update_id, true);

        if ($data === false) {
            $this->not_found();
            return;
        }

        // Add additional view data
        $data['update_id'] = $update_id;
        $data['headline'] = 'Company Member Details';
        $data['back_url'] = $this->get_back_url();
        $data['view_module'] = 'company_members';
        $data['view_file'] = 'show';
        $this->templates->admin($data);
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
        redirect('company_members/manage');
    }

    /**
     * Generate pagination configuration data.
     *
     * @param int    $total_rows    Total number of records
     * @param int    $limit         Number of records per page
     * @param string $search_query  The search query string
     * @param string $search_column The column being searched
     * @return array Pagination configuration for template
     */
    private function get_pagination_data(int $total_rows, int $limit, string $search_query = '', string $search_column = ''): array {
        $pagination_query = '';
        if ($search_query !== '' && $search_column !== '') {
            $pagination_query = 'search_query=' . urlencode($search_query) . '&search_column=' . urlencode($search_column);
        }

        return [
            'total_rows' => $total_rows,
            'limit' => $limit,
            'pagination_root' => 'company_members/manage',
            'pagination_query' => $pagination_query,
            'record_name_plural' => 'company members',
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
        if ($previous_url !== '' && strpos($previous_url, BASE_URL . 'company_members/manage') === 0) {
            return $previous_url;
        }
        return BASE_URL . 'company_members/manage';
    }

    /**
     * Display 404-style not found page for missing company members.
     *
     * Shows user-friendly error message with navigation back.
     *
     * @return void
     */
    private function not_found(): void {
        $data = [
            'headline' => 'Company Member Not Found',
            'message' => 'The company member you\'re looking for doesn\'t exist or has been deleted.',
            'back_url' => $this->get_back_url(),
            'back_label' => 'Go Back',
            'view_module' => 'company_members',
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

    /**
     * Handle search form submission for filtering company members.
     *
     * Preprocesses text parameters and triggers custom query filtering.
     *
     * @return void
     */
    public function submit_search(): void {
        $this->trongate_security->make_sure_allowed();

        $search_query = post('search_query', true);
        $search_column = post('search_column', true);

        // Validate column against known searchable columns.
        $allowed_columns = $this->model->get_searchable_columns();
        if ($search_column !== '' && !in_array($search_column, $allowed_columns, true)) {
            $search_column = '';
        }

        // Preprocess the query
        $search_query = trim($search_query);
        $search_query = preg_replace('/\s+/', ' ', $search_query);

        // Validate minimum 2 character length
        if (strlen($search_query) < 2) {
            set_flashdata('Search query must be at least 2 characters');
            redirect('company_members/manage');
        }

        redirect('company_members/manage?search_query=' . urlencode($search_query) . '&search_column=' . urlencode($search_column));
    }

    /**
     * Display the search modal form for filtering company members.
     *
     * Renders a form with a search input and a dropdown of searchable columns.
     *
     * @return void
     */
    public function search_modal(): void {
        $this->trongate_security->make_sure_allowed();

        $data['view_module'] = 'company_members';
        $data['view_file'] = 'search_modal';
        $this->view('search_modal', $data);
    }

    /**
     * Validation callback: no other company member uses this email.
     *
     * @param string $email
     * @return string|bool
     */
    public function email_check(string $email): string|bool {
        block_url('company_members/email_check');
        return $this->model->email_taken(strtolower(trim($email)), segment(3, 'int'))
            ? 'Another company member already uses that email.'
            : true;
    }

    /**
     * Validation callback: a known role, and the company keeps at least
     * one active owner (as Company_rules has it for the company's own staff).
     *
     * @param string $role
     * @return string|bool
     */
    public function role_check(string $role): string|bool {
        block_url('company_members/role_check');
        if (!isset(Company_rules::ROLES[$role])) {
            return 'Pick owner or recruiter.';
        }
        $member = $this->model->get_data_from_db(segment(3, 'int'));
        if ($member === false || $member['role'] !== 'owner' || (int) $member['active'] !== 1) {
            return true;
        }
        $stays_owner = $role === 'owner' && (bool) post('active', true);
        if ($stays_owner || $this->model->count_other_active_owners($member) > 0) {
            return true;
        }
        return 'A company needs at least one active owner. Make another member owner first.';
    }

}

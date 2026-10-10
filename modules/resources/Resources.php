<?php
require_once __DIR__ . '/Resource_catalog.php';
require_once __DIR__ . '/Resource_refused.php';

/**
 * The admin panel's pages for the app's tables (Resource_catalog):
 *
 *   resources                               every table, grouped
 *   resources/manage/{table}                its rows, newest first; ?q= searches,
 *                                           ?{column}={value} filters on a ref, code or bool
 *   resources/more/{table}?after={key}      the next rows of that list (infinite scroll)
 *   resources/show/{table}/{key}            one row, and the lists that point at it
 *   resources/edit/{table}/{key}            the editable columns (POST submit_edit)
 *   resources/delete/{table}/{key}          asks first (POST submit_delete)
 *
 * Admins only. Tables and columns come from the catalog, never from the URL.
 */
class Resources extends Trongate {

    private const PER_PAGE = 50;

    public function index(): void {
        $this->trongate_security->make_sure_allowed();
        $this->page('index', [
            'groups' => Resource_catalog::groups(),
            'elsewhere' => Resource_catalog::ELSEWHERE,
        ]);
    }

    public function manage(): void {
        $this->trongate_security->make_sure_allowed();
        $resource = $this->resource_or_404();
        $data = $this->rows($resource);
        $data['search'] = trim((string) ($_GET['q'] ?? ''));
        $this->page('manage', $data + ['additional_includes_btm' => ['resources_module/js/more.js']]);
    }

    public function more(): void {
        $this->trongate_security->make_sure_allowed();
        $this->view('rows', $this->rows($this->resource_or_404()));
    }

    public function show(): void {
        $this->trongate_security->make_sure_allowed();
        [$resource, $key, $row] = $this->row_or_404();
        $this->page('show', [
            'resource' => $resource,
            'key' => $key,
            'row' => $row,
            'related' => Resource_catalog::referring_to($resource->table),
        ]);
    }

    public function edit(): void {
        $this->trongate_security->make_sure_allowed();
        [$resource, $key, $row] = $this->row_or_404(fn(Resource $r) => (bool) $r->editable);
        $this->edit_form($resource, $key, $row, $row, []);
    }

    public function submit_edit(): void {
        $this->trongate_security->make_sure_allowed();
        [$resource, $key, $row] = $this->row_or_404(fn(Resource $r) => (bool) $r->editable);
        if ($this->validation->run() !== true) {
            $this->edit_form($resource, $key, $row, $_POST, ['' => "That didn't go through. Reload the page and try again."]);
            return;
        }
        [$values, $errors] = $resource->values_from($_POST);
        if (!$errors) {
            try {
                $this->model->update($resource, $row, $key, $values);
                set_flashdata('Saved.');
                redirect($this->url('show', $resource, $key));
                return;
            } catch (Resource_refused $e) {
                $errors[''] = $e->getMessage();
            }
        }
        $this->edit_form($resource, $key, $row, $_POST, $errors);
    }

    public function delete(): void {
        $this->trongate_security->make_sure_allowed();
        [$resource, $key, $row] = $this->row_or_404(fn(Resource $r) => $r->deletable);
        $this->page('delete', ['resource' => $resource, 'key' => $key, 'row' => $row, 'error' => null]);
    }

    public function submit_delete(): void {
        $this->trongate_security->make_sure_allowed();
        [$resource, $key, $row] = $this->row_or_404(fn(Resource $r) => $r->deletable);
        $error = "That didn't go through. Reload the page and try again.";
        if ($this->validation->run() === true) {
            try {
                $this->model->delete($resource, $key);
                set_flashdata("Deleted #{$resource->key_of($row)}.");
                redirect('resources/manage/' . $resource->table);
                return;
            } catch (Resource_refused $e) {
                $error = $e->getMessage();
            }
        }
        $this->page('delete', ['resource' => $resource, 'key' => $key, 'row' => $row, 'error' => $error]);
    }

    /** The list's rows from the query string: filters, search, the cursor. */
    private function rows(Resource $resource): array {
        $filters = $resource->filters_from($_GET);
        $search = trim((string) ($_GET['q'] ?? ''));
        $after = $resource->parse_key((string) ($_GET['after'] ?? ''));
        $found = $this->model->page($resource, $filters, $search, $after, self::PER_PAGE);
        $query = $filters + array_filter(['q' => $search]);
        return [
            'resource' => $resource,
            'filters' => $filters,
            'rows' => $found['rows'],
            'query' => $query,
            'next' => $found['next'] === null ? null : http_build_query($query + ['after' => $found['next']]),
        ];
    }

    private function edit_form(Resource $resource, array $key, array $row, array $values, array $errors): void {
        if ($errors) {
            http_response_code(422);
        }
        $this->page('edit', [
            'resource' => $resource,
            'key' => $key,
            'row' => $row,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    private function resource_or_404(): Resource {
        $resource = Resource_catalog::find((string) segment(3));
        if ($resource === null) {
            $this->not_found("There's no table called that here.");
        }
        return $resource;
    }

    /**
     * The resource, key and row the URL names, or a 404 page; $allowed
     * says whether the page applies to that table at all.
     *
     * @return array{0: Resource, 1: int[], 2: array}
     */
    private function row_or_404(?Closure $allowed = null): array {
        $resource = $this->resource_or_404();
        if ($allowed !== null && !$allowed($resource)) {
            $this->not_found("$resource->name can't be changed here.");
        }
        $key = $resource->parse_key((string) segment(4));
        $row = $key === null ? null : $this->model->find($resource, $key);
        if ($row === null) {
            $this->not_found("That row of $resource->name doesn't exist, or was deleted.");
        }
        return [$resource, $key, $row];
    }

    private function url(string $page, Resource $resource, array $key): string {
        return "resources/$page/$resource->table/" . implode('-', $key);
    }

    private function not_found(string $message): never {
        http_response_code(404);
        $this->page('not_found', ['message' => $message]);
        exit;
    }

    private function page(string $view, array $data): void {
        $this->templates->admin($data + [
            'view_module' => 'resources',
            'view_file' => $view,
            'additional_includes_top' => ['resources_module/css/resources.css'],
            'page_title' => isset($data['resource']) ? $data['resource']->name . ' - ' . WEBSITE_NAME . ' admin' : WEBSITE_NAME . ' admin',
        ]);
    }
}

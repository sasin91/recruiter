<?php
require_once __DIR__ . '/Resource_refused.php';
/**
 * Runs the SQL a Resource builds. Database errors the admin can do
 * something about (a duplicate value, a row other rows still point at)
 * come back as Resource_refused with a message to show.
 */
class Resources_model extends Model {

    /**
     * One page of a list: ['rows' => ..., 'next' => the key to continue
     * after, or null at the end].
     */
    public function page(Resource $resource, array $filters, string $search, ?array $after, int $limit): array {
        [$sql, $params] = $resource->list_query($filters, $search, $after, $limit);
        $rows = $this->db->query_bind($sql, $params, 'array');
        $next = null;
        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
            $next = $resource->key_of(end($rows));
        }
        return ['rows' => $rows, 'next' => $next];
    }

    public function find(Resource $resource, array $key): ?array {
        [$sql, $params] = $resource->find_query($key);
        return $this->db->query_bind($sql, $params, 'array')[0] ?? null;
    }

    /** @throws Resource_refused */
    public function update(Resource $resource, array $row, array $key, array $values): void {
        if ($resource->guard !== null) {
            $count = fn(string $sql, array $params): int => (int) array_values($this->db->query_bind($sql, $params, 'array')[0])[0];
            if (($reason = ($resource->guard)($row, $values, $count)) !== null) {
                throw new Resource_refused($reason);
            }
        }
        [$sql, $params] = $resource->update_query($key, $values);
        $this->run($resource, $sql, $params);
    }

    /** @throws Resource_refused */
    public function delete(Resource $resource, array $key): void {
        [$sql, $params] = $resource->delete_query($key);
        $this->run($resource, $sql, $params);
    }

    private function run(Resource $resource, string $sql, array $params): void {
        try {
            $this->db->query_bind($sql, $params);
        } catch (PDOException $e) {
            $reason = Resource_refused::explain($resource, $e);
            if ($reason === null) {
                throw $e;
            }
            throw new Resource_refused($reason, previous: $e);
        }
    }
}

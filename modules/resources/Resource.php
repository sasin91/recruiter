<?php
require_once __DIR__ . '/Field.php';

/**
 * A table as the admin panel shows it: its columns (Field), which of them
 * the list shows, searches and filters on, which the admin may edit, and
 * whether a row may be deleted. Builds the SQL for resources/; table and
 * column names only ever come from here, never from the request.
 *
 * Rows are keyed by $key (the primary key, one or more int columns); a key
 * in a URL is its values joined by '-'. Lists run newest first by key and
 * page with a cursor (the key of the last row shown).
 */
final class Resource {

    /** @var string[] columns the list shows */
    public array $listed = [];
    /** @var string[] text columns ?q= searches (plus the id when q is a number) */
    public array $searched = [];
    /** @var string[] columns the admin may change on resources/edit */
    public array $editable = [];
    public bool $deletable = false;
    /** What else goes with a deleted row, said on the confirm page. */
    public string $delete_note = '';
    /** The column that names a row ("Acme ApS"), shown after its key. */
    public ?string $title_column = null;
    /**
     * Refuses an edit the columns alone can't: fn(array $row, array $values,
     * Closure $count): ?string, the reason or null. $count(sql, params) runs
     * a COUNT(*) query.
     */
    public ?Closure $guard = null;

    /**
     * @param array<string, Field> $fields every column of the table, in table order
     * @param string[] $key
     */
    public function __construct(
        public readonly string $table,
        public readonly string $name,
        public readonly string $group,
        public readonly string $note,
        public readonly array $fields,
        public readonly array $key = ['id'],
    ) {
        $this->listed = array_slice($this->visible(), 0, 6);
    }

    public function lists(string ...$columns): self {
        $this->listed = $columns;
        return $this;
    }

    public function searches(string ...$columns): self {
        $this->searched = $columns;
        return $this;
    }

    public function edits(string ...$columns): self {
        $this->editable = $columns;
        return $this;
    }

    public function deletes(string $note = ''): self {
        $this->deletable = true;
        $this->delete_note = $note;
        return $this;
    }

    public function guards(Closure $guard): self {
        $this->guard = $guard;
        return $this;
    }

    public function titled(string $column): self {
        $this->title_column = $column;
        return $this;
    }

    public function read_only(): bool {
        return !$this->editable && !$this->deletable;
    }

    /** @return string[] the columns that may be shown: all but secrets */
    public function visible(): array {
        return array_keys(array_filter($this->fields, fn(Field $f) => $f->kind !== 'secret'));
    }

    /** @return string[] the columns a list filters on with ?column=value: refs, codes and bools */
    public function filterable(): array {
        return array_keys(array_filter($this->fields, fn(Field $f) => in_array($f->kind, ['ref', 'code', 'bool'], true)));
    }

    /** The row's key for a URL, e.g. "12" or "12-5". */
    public function key_of(array $row): string {
        return implode('-', array_map(fn(string $c) => (int) $row[$c], $this->key));
    }

    /** @return int[]|null the key values in $text, or null when it isn't a key of this table */
    public function parse_key(string $text): ?array {
        $parts = explode('-', $text);
        if (count($parts) !== count($this->key)) {
            return null;
        }
        foreach ($parts as $part) {
            if (!ctype_digit($part) || strlen($part) > 10) {
                return null;
            }
        }
        return array_map('intval', $parts);
    }

    /**
     * The filters in a query string that this table has: column => value.
     * Unknown columns, and values that can't be one, are dropped.
     */
    public function filters_from(array $query): array {
        $filters = [];
        foreach ($this->filterable() as $column) {
            $value = $query[$column] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $field = $this->fields[$column];
            if ($field->kind === 'code' ? in_array($value, $field->options, true) : ctype_digit($value)) {
                $filters[$column] = $field->kind === 'code' ? $value : (int) $value;
            }
        }
        return $filters;
    }

    /**
     * One page of the list, newest first: SQL and its parameters. Fetch
     * $limit + 1 rows; a row past $limit means there is a next page.
     *
     * @param int[]|null $after the key of the last row already shown
     * @return array{0: string, 1: array}
     */
    public function list_query(array $filters, string $search, ?array $after, int $limit): array {
        $columns = array_values(array_unique([...$this->key, ...$this->listed, ...($this->title_column ? [$this->title_column] : [])]));
        $where = [];
        $params = [];
        foreach ($filters as $column => $value) {
            $where[] = self::quote($column) . ' = :f_' . $column;
            $params['f_' . $column] = $value;
        }
        $search = trim($search);
        if ($search !== '') {
            $any = [];
            foreach ($this->searched as $i => $column) {
                $any[] = self::quote($column) . " LIKE :q$i";
                $params["q$i"] = '%' . addcslashes($search, '%_\\') . '%';
            }
            if (count($this->key) === 1 && ctype_digit($search) && strlen($search) <= 10) {
                $any[] = self::quote($this->key[0]) . ' = :q_key';
                $params['q_key'] = (int) $search;
            }
            $where[] = $any ? '(' . implode(' OR ', $any) . ')' : '0 = 1';
        }
        if ($after !== null) {
            $names = [];
            foreach ($after as $i => $value) {
                $names[] = ":after$i";
                $params["after$i"] = $value;
            }
            $where[] = '(' . implode(', ', array_map(self::quote(...), $this->key)) . ') < (' . implode(', ', $names) . ')';
        }
        $sql = 'SELECT ' . implode(', ', array_map(self::quote(...), $columns))
            . ' FROM ' . self::quote($this->table)
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . implode(', ', array_map(fn(string $c) => self::quote($c) . ' DESC', $this->key))
            . sprintf(' LIMIT %d', $limit + 1);
        return [$sql, $params];
    }

    /** @return array{0: string, 1: array} one row by key, every visible column */
    public function find_query(array $key): array {
        return ['SELECT ' . implode(', ', array_map(self::quote(...), $this->visible()))
            . ' FROM ' . self::quote($this->table) . $this->where_key(), $this->key_params($key)];
    }

    /** @return array{0: string, 1: array} sets $values (editable columns only) on the row */
    public function update_query(array $key, array $values): array {
        $sets = [];
        $params = $this->key_params($key);
        foreach ($values as $column => $value) {
            if (!in_array($column, $this->editable, true)) {
                throw new LogicException("$this->table.$column isn't editable.");
            }
            $sets[] = self::quote($column) . ' = :v_' . $column;
            $params['v_' . $column] = $value;
        }
        if (isset($this->fields['updated_at'])) {
            $sets[] = '`updated_at` = :v_updated_at';
            $params['v_updated_at'] = time();
        }
        return ['UPDATE ' . self::quote($this->table) . ' SET ' . implode(', ', $sets) . $this->where_key(), $params];
    }

    /** @return array{0: string, 1: array} */
    public function delete_query(array $key): array {
        if (!$this->deletable) {
            throw new LogicException("Rows of $this->table aren't deleted from the admin panel.");
        }
        return ['DELETE FROM ' . self::quote($this->table) . $this->where_key(), $this->key_params($key)];
    }

    /**
     * The editable columns' values from a posted form, and what is wrong
     * with them: [values, errors (column => message)].
     */
    public function values_from(array $post): array {
        $values = [];
        $errors = [];
        foreach ($this->editable as $column) {
            $raw = $post[$column] ?? null;
            try {
                $values[$column] = $this->fields[$column]->parse(is_string($raw) ? $raw : null);
            } catch (InvalidArgumentException $e) {
                $errors[$column] = Field::label($column) . ' ' . $e->getMessage();
            }
        }
        return [$values, $errors];
    }

    private function where_key(): string {
        return ' WHERE ' . implode(' AND ', array_map(fn(string $c) => self::quote($c) . " = :k_$c", $this->key));
    }

    private function key_params(array $key): array {
        if (count($key) !== count($this->key)) {
            throw new InvalidArgumentException("$this->table is keyed by " . implode(', ', $this->key) . '.');
        }
        return array_combine(array_map(fn(string $c) => "k_$c", $this->key), array_values($key));
    }

    private static function quote(string $name): string {
        return '`' . $name . '`';
    }
}

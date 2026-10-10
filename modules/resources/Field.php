<?php
/**
 * One column of a Resource: what kind of value it holds, how it is shown,
 * and, for a column the admin may edit, how typed input becomes a value.
 *
 * Kinds: id, int, decimal, text (varchar, up to $max characters), long
 * (text/mediumtext: shown on the record page only), bool, time (a unix
 * timestamp), ref (an id in $table), code (one of $options) and secret
 * (never selected, shown or edited: password hashes, tokens, encrypted keys).
 */
final class Field {

    private function __construct(
        public readonly string $kind,
        public readonly bool $nullable = false,
        public readonly int $max = 0,
        public readonly ?string $table = null,
        public readonly array $options = [],
        private readonly ?Closure $check = null,
    ) {
    }

    public static function id(): self {
        return new self('id');
    }

    public static function int(bool $null = false): self {
        return new self('int', $null);
    }

    public static function decimal(bool $null = false): self {
        return new self('decimal', $null);
    }

    /** $check gets the trimmed input and returns the value to store, or throws InvalidArgumentException. */
    public static function text(int $max, bool $null = false, ?Closure $check = null): self {
        return new self('text', $null, $max, check: $check);
    }

    public static function long(bool $null = false): self {
        return new self('long', $null);
    }

    public static function bool(): self {
        return new self('bool');
    }

    public static function time(bool $null = false): self {
        return new self('time', $null);
    }

    public static function ref(string $table, bool $null = false): self {
        return new self('ref', $null, table: $table);
    }

    public static function code(array $options, bool $null = false): self {
        return new self('code', $null, options: $options);
    }

    public static function secret(): self {
        return new self('secret');
    }

    /** "company_type_term_id" => "Company type term", "cvr_number" => "Cvr number". */
    public static function label(string $column): string {
        $words = preg_replace('/_id$/', '', $column);
        return ucfirst(str_replace('_', ' ', $words));
    }

    /**
     * The value to store for what was typed (a checkbox posts nothing when
     * unticked, so a bool takes null as false).
     *
     * @throws InvalidArgumentException with a message for the person typing
     */
    public function parse(?string $input): mixed {
        if ($this->kind === 'bool') {
            return $input === '1' ? 1 : 0;
        }
        $input = trim((string) $input);
        if ($input === '') {
            if ($this->nullable) {
                return null;
            }
            throw new InvalidArgumentException('is required.');
        }
        switch ($this->kind) {
            case 'int':
            case 'ref':
                if (!preg_match('/^-?[0-9]{1,10}$/', $input)) {
                    throw new InvalidArgumentException('must be a whole number.');
                }
                return (int) $input;
            case 'decimal':
                if (!is_numeric($input)) {
                    throw new InvalidArgumentException('must be a number.');
                }
                return $input;
            case 'code':
                if (!in_array($input, $this->options, true)) {
                    throw new InvalidArgumentException('must be one of: ' . implode(', ', $this->options) . '.');
                }
                return $input;
            case 'text':
                $value = $this->check !== null ? ($this->check)($input) : $input;
                if (mb_strlen($value, 'UTF-8') > $this->max) {
                    throw new InvalidArgumentException("can't be longer than {$this->max} characters.");
                }
                return $value;
            case 'long':
                return $input;
        }
        throw new LogicException("A {$this->kind} column can't be edited.");
    }

    /** The value as plain text for a list or record page (escape it when printing). */
    public function format(mixed $value): string {
        if ($value === null) {
            return '';
        }
        return match ($this->kind) {
            'bool' => (int) $value === 1 ? 'Yes' : 'No',
            'time' => date('j M Y H:i', (int) $value),
            default => (string) $value,
        };
    }

    /** The value as HTML: escaped, a ref linked to its row, long text kept in its lines. */
    public function html(mixed $value): string {
        $text = htmlspecialchars($this->format($value), ENT_QUOTES, 'UTF-8');
        if ($value === null || $text === '') {
            return '<span class="none">–</span>';
        }
        return match ($this->kind) {
            'ref' => '<a href="resources/show/' . $this->table . '/' . (int) $value . '">#' . (int) $value . '</a>',
            'long' => '<div class="long-text">' . nl2br($text) . '</div>',
            'time' => '<time datetime="' . date('c', (int) $value) . '">' . $text . '</time>',
            default => $text,
        };
    }
}

<?php
/**
 * Checks a tool's arguments against its schema(): a JSON Schema subset, the
 * part tools need. problems() answers what is wrong, one line each, named by
 * path (posts[2].url), so the model can fix the call; [] when nothing is.
 *
 *   Mcp_schema::problems(['type' => 'object', 'required' => ['posts'], ...], $arguments);
 *
 * Keywords: type (object, array, string, integer, number, boolean, null),
 * required, properties, additionalProperties (false only), items, enum,
 * minLength, maxLength (characters), minItems, maxItems, minimum, maximum,
 * and title and description, which check nothing. unsupported() lists any
 * other keyword a schema uses, so a tool's test catches it.
 *
 * Arguments are decoded JSON (json_decode(..., true)), so {} and [] are
 * both an empty PHP array, and either passes as an object or an array.
 */
class Mcp_schema {

    public const KEYWORDS = ['type', 'required', 'properties', 'additionalProperties', 'items', 'enum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'minimum', 'maximum', 'title', 'description'];

    /** @return list<string> */
    public static function problems(array $schema, mixed $value, string $path = ''): array {
        $type = $schema['type'] ?? null;
        if ($type !== null && !self::is_type($value, $type)) {
            return [self::name($path) . ' must be ' . self::type_name($type) . '.'];
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            return [self::name($path) . ' must be one of: ' . implode(', ', array_map('json_encode', $schema['enum'])) . '.'];
        }
        return match (true) {
            is_string($value) => self::string_problems($schema, $value, $path),
            is_int($value) || is_float($value) => self::number_problems($schema, $value, $path),
            is_array($value) && ($type === 'array' || ($type === null && array_is_list($value) && $value !== [])) => self::array_problems($schema, $value, $path),
            is_array($value) => self::object_problems($schema, $value, $path),
            default => [],
        };
    }

    /**
     * Keywords the schema uses that problems() doesn't check, by path.
     *
     * @return list<string>
     */
    public static function unsupported(array $schema, string $path = ''): array {
        $found = [];
        foreach (array_diff(array_keys($schema), self::KEYWORDS) as $keyword) {
            $found[] = self::name($path) . ": $keyword";
        }
        if (($schema['additionalProperties'] ?? false) !== false) {
            $found[] = self::name($path) . ': additionalProperties other than false';
        }
        foreach ($schema['properties'] ?? [] as $key => $property) {
            $found = [...$found, ...self::unsupported($property, self::join($path, $key))];
        }
        if (isset($schema['items'])) {
            $found = [...$found, ...self::unsupported($schema['items'], $path . '[]')];
        }
        return $found;
    }

    private static function string_problems(array $schema, string $value, string $path): array {
        $length = mb_strlen($value, 'UTF-8');
        if (isset($schema['minLength']) && $length < $schema['minLength']) {
            return [self::name($path) . ($schema['minLength'] === 1 ? ' must not be empty.' : " must be at least {$schema['minLength']} characters.")];
        }
        if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
            return [self::name($path) . " is over {$schema['maxLength']} characters."];
        }
        return [];
    }

    private static function number_problems(array $schema, int|float $value, string $path): array {
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            return [self::name($path) . " must be at least {$schema['minimum']}."];
        }
        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            return [self::name($path) . " must be at most {$schema['maximum']}."];
        }
        return [];
    }

    private static function array_problems(array $schema, array $value, string $path): array {
        $count = count($value);
        if (isset($schema['minItems']) && $count < $schema['minItems']) {
            return [self::name($path) . " must have at least {$schema['minItems']} " . ($schema['minItems'] === 1 ? 'item.' : 'items.')];
        }
        if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
            return [self::name($path) . " has $count items; at most {$schema['maxItems']} per call, so send the rest in the next one."];
        }
        $problems = [];
        foreach (isset($schema['items']) ? $value : [] as $i => $item) {
            $problems = [...$problems, ...self::problems($schema['items'], $item, $path . "[$i]")];
        }
        return $problems;
    }

    private static function object_problems(array $schema, array $value, string $path): array {
        $problems = [];
        $properties = $schema['properties'] ?? [];
        foreach ($schema['required'] ?? [] as $key) {
            if (!array_key_exists($key, $value)) {
                $problems[] = self::join($path, $key) . ' is missing.';
            }
        }
        if (($schema['additionalProperties'] ?? true) === false) {
            foreach (array_diff(array_keys($value), array_keys($properties)) as $key) {
                $problems[] = self::join($path, (string) $key) . ' is not a field (' . implode(', ', array_keys($properties)) . ').';
            }
        }
        foreach ($properties as $key => $property) {
            if (array_key_exists($key, $value)) {
                $problems = [...$problems, ...self::problems($property, $value[$key], self::join($path, $key))];
            }
        }
        return $problems;
    }

    private static function is_type(mixed $value, string $type): bool {
        return match ($type) {
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };
    }

    private static function type_name(string $type): string {
        return match ($type) {
            'object' => 'an object',
            'array' => 'a list',
            'integer' => 'a whole number',
            'null' => 'null',
            default => "a $type",
        };
    }

    private static function name(string $path): string {
        return $path === '' ? 'arguments' : $path;
    }

    private static function join(string $path, string $key): string {
        return $path === '' ? $key : "$path.$key";
    }

}

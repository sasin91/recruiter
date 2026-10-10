<?php

/**
 * Whether named parameters fit a method as it is now: every name is one of
 * its parameters, every required one is given, and each value's type fits
 * the declared type. Checked when a job is queued (so a mistake shows in
 * the request, or in a test) and again before a queued job runs (so a job
 * queued under an older signature fails with a clear reason, not a
 * TypeError, and isn't retried).
 */
final class Call_signature {

    /**
     * What doesn't fit, one sentence each; [] when it all fits.
     *
     * @return string[]
     */
    public static function problems(ReflectionMethod $method, array $parameters): array {
        $label = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        $declared = [];
        $variadic = false;
        foreach ($method->getParameters() as $parameter) {
            $declared[$parameter->getName()] = $parameter;
            $variadic = $variadic || $parameter->isVariadic();
        }

        $problems = [];
        foreach ($parameters as $name => $value) {
            $parameter = $declared[$name] ?? null;
            if ($parameter === null) {
                if (!$variadic) {
                    $problems[] = "$label has no parameter \$$name.";
                }
                continue;
            }
            if (!self::fits($parameter->getType(), $value)) {
                $problems[] = "$label's \$$name is " . $parameter->getType() . ', not ' . get_debug_type($value) . '.';
            }
        }
        foreach ($declared as $name => $parameter) {
            if (!$parameter->isOptional() && !$parameter->isVariadic() && !array_key_exists($name, $parameters)) {
                $problems[] = "$label needs \$$name, which the job doesn't give.";
            }
        }
        return $problems;
    }

    /** Whether a JSON-borne value fits a declared type (no type: anything). */
    private static function fits(?ReflectionType $type, mixed $value): bool {
        if ($type === null) {
            return true;
        }
        if ($value === null) {
            return $type->allowsNull();
        }
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($types as $one) {
            if (!$one instanceof ReflectionNamedType) {
                return true; // intersection types: leave it to PHP
            }
            $fits = match ($one->getName()) {
                'mixed' => true,
                'int' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'string' => is_string($value),
                'bool' => is_bool($value),
                'true' => $value === true,
                'false' => $value === false,
                'array', 'iterable' => is_array($value),
                default => false,
            };
            if ($fits) {
                return true;
            }
        }
        return false;
    }
}

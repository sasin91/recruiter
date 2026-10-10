<?php
require_once __DIR__ . '/Unrecoverable_message_exception.php';

/**
 * Turns a message into name/type/value rows (messenger_message_fields) and
 * back, so no json column is needed.
 *
 * A message is a class whose constructor takes its public properties
 * (constructor promotion), each an int, float, bool, string or null:
 *
 *   final class Score_application {
 *       public function __construct(public readonly int $application_id) {}
 *   }
 */
final class Message_fields {

    const TYPES = ['int', 'float', 'bool', 'string', 'null'];

    /**
     * The message's public properties as name => [type, value as string or null].
     *
     * @throws InvalidArgumentException for a property that isn't a scalar or null
     */
    public static function from_message(object $message): array {
        $fields = [];
        foreach ((new ReflectionObject($message))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            $value = $property->getValue($message);
            $fields[$name] = match (true) {
                is_int($value) => ['int', (string) $value],
                is_float($value) => ['float', var_export($value, true)],
                is_bool($value) => ['bool', $value ? '1' : '0'],
                is_string($value) => ['string', $value],
                $value === null => ['null', null],
                default => throw new InvalidArgumentException(
                    get_class($message) . "::\$$name is " . get_debug_type($value) . '; a message holds only int, float, bool, string or null.'
                ),
            };
        }
        return $fields;
    }

    /**
     * Rebuilds a message from its class and fields (as from_message() gave them).
     *
     * @throws Unrecoverable_message_exception when the class is unknown or the fields don't fit its constructor
     */
    public static function to_message(string $class, array $fields): object {
        if (!class_exists($class)) {
            throw new Unrecoverable_message_exception("Message class $class isn't loaded; is it required in config/messenger.php?");
        }
        $arguments = [];
        foreach ($fields as $name => [$type, $value]) {
            $arguments[$name] = match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => $value === '1',
                'string' => (string) $value,
                'null' => null,
                default => throw new Unrecoverable_message_exception("Field $name of $class has unknown type $type."),
            };
        }
        try {
            return new $class(...$arguments);
        } catch (Error $e) {
            throw new Unrecoverable_message_exception("Can't rebuild $class from its fields: " . $e->getMessage(), 0, $e);
        }
    }
}

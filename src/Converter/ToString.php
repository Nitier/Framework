<?php

declare(strict_types=1);

namespace Framework\Converter;

/**
 * Class ToString
 * Transforms various PHP data types into their string representation
 */
class ToString
{
    /**
     * Invokable method to convert a value to string
     *
     * @param mixed $value The value to convert
     * @return string The string representation of the value
     */
    public function __invoke(mixed $value): string
    {
        return self::convert($value);
    }

    /**
     * Converts any PHP value to its string representation
     *
     * @param mixed $value The value to convert
     * @return string The string representation of the value
     */
    public static function convert(mixed $value): string
    {
        // Check for null value and return 'null'
        if ($value === null) {
            return 'null';
        }
        // Check for bool values and return 'true' or 'false'
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        // Check for array or object values and encode to JSON
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT);
            if ($json === false) {
                return 'Error encoding to JSON: ' . json_last_error_msg();
            }
            return $json;
        }
        // Check for string or numeric values and return as string
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }
        // Handle resources
        if (is_resource($value)) {
            return 'Resource cannot be converted to string';
        }

        // Return empty string for any unhandled types
        return '';
    }
}

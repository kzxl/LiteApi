<?php

declare(strict_types=1);

namespace LiteApi\OpenApi;

use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Automatically extracts OpenAPI 3.0 JSON Schema from PHP 8.2+ typed DTO classes.
 * Zero external dependencies.
 */
class SchemaExtractor
{
    /**
     * Extract OpenAPI JSON schema array from a class string.
     *
     * @param class-string $className
     * @return array<string, mixed>
     */
    public static function extract(string $className): array
    {
        if (!class_exists($className)) {
            return ['type' => 'object'];
        }

        $ref = new ReflectionClass($className);
        $properties = [];
        $required = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $propName = $prop->getName();
            $propSchema = self::extractPropertySchema($prop);

            $properties[$propName] = $propSchema;

            // Check if required: has no default value and does not allow null
            $type = $prop->getType();
            $hasDefault = $prop->hasDefaultValue();
            $allowsNull = $type instanceof ReflectionNamedType && $type->allowsNull();

            if (!$hasDefault && !$allowsNull) {
                $required[] = $propName;
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Extract schema for a single property.
     *
     * @return array<string, mixed>
     */
    private static function extractPropertySchema(ReflectionProperty $prop): array
    {
        $type = $prop->getType();
        $schema = ['type' => 'string'];

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            switch ($typeName) {
                case 'int':
                    $schema = ['type' => 'integer'];
                    break;
                case 'float':
                    $schema = ['type' => 'number', 'format' => 'float'];
                    break;
                case 'bool':
                    $schema = ['type' => 'boolean'];
                    break;
                case 'string':
                    $schema = ['type' => 'string'];
                    break;
                case 'array':
                    $schema = ['type' => 'array', 'items' => ['type' => 'string']];
                    break;
                default:
                    if (is_subclass_of($typeName, DateTimeInterface::class) || $typeName === DateTimeInterface::class) {
                        $schema = ['type' => 'string', 'format' => 'date-time'];
                    } elseif (class_exists($typeName)) {
                        // Nested sub-object
                        $schema = self::extract($typeName);
                    }
                    break;
            }

            if ($type->allowsNull()) {
                $schema['nullable'] = true;
            }
        }

        if ($prop->hasDefaultValue()) {
            $schema['default'] = $prop->getDefaultValue();
        }

        return $schema;
    }

    /**
     * Get clean short name for a class (e.g. 'CustomerDTO' from 'App\DTO\CustomerDTO').
     */
    public static function getShortName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}

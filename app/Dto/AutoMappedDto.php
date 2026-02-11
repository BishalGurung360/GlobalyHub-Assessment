<?php

namespace App\Dto;

use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Base DTO that automatically maps validated request/array data
 * into constructor parameters using reflection.
 */
abstract class AutoMappedDto
{
    /**
     * Build the DTO from an associative array, typically $request->validated().
     */
    public static function fromArray(array $data): static
    {
        $reflectionClass = new ReflectionClass(static::class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return new static();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $inputKey = static::inputKeyFor($paramName);

            if (array_key_exists($inputKey, $data)) {
                $value = $data[$inputKey];
                $value = static::castValue($parameter, $value);
                $arguments[] = $value;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                'Missing required key [%s] for DTO %s',
                $inputKey,
                static::class
            ));
        }

        return $reflectionClass->newInstanceArgs($arguments);
    }

    /**
     * Build the DTO from an HTTP request.
     *
     * If the request is a FormRequest, validated() will be used;
     * otherwise all request input will be used.
     */
    public static function fromRequest(Request $request): static
    {
        $data = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        return static::fromArray($data);
    }

    /**
     * Determine the input key name for a given constructor parameter.
     *
     * Default implementation converts camelCase to snake_case.
     */
    protected static function inputKeyFor(string $parameterName): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', $parameterName));
    }

    /**
     * Convert the current DTO instance into an associative array.
     *
     * Uses the same key convention as fromArray() (snake_case) so that
     * fromArray($dto->toArray()) reconstructs an equivalent DTO.
     */
    public function toArray(): array
    {
        $reflectionClass = new ReflectionClass(static::class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $result = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $inputKey = static::inputKeyFor($paramName);

            if (!property_exists($this, $paramName)) {
                continue;
            }

            $value = $this->{$paramName};
            $result[$inputKey] = static::serializeValue($value);
        }

        return $result;
    }

    /**
     * Serialize a single property value for array output.
     *
     * DateTimeInterface -> ISO 8601 string; nested AutoMappedDto -> array; else as-is.
     */
    protected static function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Cast a raw input value into the expected parameter type when needed.
     */
    protected static function castValue(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        if (is_a($typeName, \DateTimeInterface::class, true) && is_string($value)) {
            return new \DateTimeImmutable($value);
        }

        return $value;
    }
}


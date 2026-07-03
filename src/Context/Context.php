<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Context;

/**
 * Central data model for the Beacon context.
 *
 * Framework-agnostic container that holds all
 * scanned project metadata in a structured way.
 */
class Context
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Set a value at the given key.
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get a value by key with dot notation support.
     *
     * For example, `get('models.items')` traverses into
     * `$data['models']['items']`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! str_contains($key, '.')) {
            return $this->data[$key] ?? $default;
        }

        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Determine if a key exists with dot notation support.
     */
    public function has(string $key): bool
    {
        if (! str_contains($key, '.')) {
            return array_key_exists($key, $this->data);
        }

        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Merge the given data into the context.
     */
    public function merge(array $data): self
    {
        /** @var array<string, mixed> $merged */
        $merged = array_merge_recursive($this->data, $data);

        $this->data = $merged;

        return $this;
    }

    /**
     * Return all context data as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
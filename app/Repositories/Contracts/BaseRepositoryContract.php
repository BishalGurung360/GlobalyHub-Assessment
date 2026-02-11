<?php

namespace App\Repositories\Contracts;

/**
 * Contract for base repository operations.
 */
interface BaseRepositoryContract
{
    /**
     * Enable or disable caching for subsequent queries.
     *
     * @param  bool  $isCached
     * @return self
     */
    public function isCached(bool $isCached = true): self;

    /**
     * Update or create a record based on match conditions.
     *
     * @param  array  $match
     * @param  array  $data
     * @return object
     */
    public function updateOrStore(array $match, array $data): object;

    /**
     * Fetch existing record or create a new one.
     *
     * @param  array  $data
     * @return object
     */
    public function fetchOrStore(array $data): object;

    /**
     * Store a new record.
     *
     * @param  array  $data
     * @return object
     */
    public function store(array $data): object;

    /**
     * Update a record by ID.
     *
     * @param  array  $data
     * @param  string|int  $id
     * @return object
     */
    public function update(array $data, string|int $id): object;

    /**
     * Get a record by ID.
     *
     * @param  mixed  $id
     * @param  array  $with
     * @param  array  $tags
     * @return mixed
     */
    public function get(mixed $id, array $with = [], array $tags = []): mixed;

    /**
     * Fetch records by a specific column.
     *
     * @param  string  $column
     * @param  mixed  $value
     * @param  array  $with
     * @param  bool  $multiple
     * @return mixed
     */
    public function getBy(
        string $column,
        mixed $value,
        array $with = [],
        bool $multiple = false
    ): mixed;
}

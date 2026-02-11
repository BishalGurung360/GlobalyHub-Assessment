<?php

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryContract;
use App\Services\CacheManager\CacheManager;

abstract class BaseRepository implements BaseRepositoryContract
{

    public $model;
    public ?string $modelName;
    public ?string $modelKey;
    protected ?string $tableName;

    protected int $perPage = 25;
    protected bool $isCached = true;
    protected int $cacheTTl = 60; // 60 min
    protected bool $multipleFetchQueryCache;

    protected CacheManager $cacheManager;

    /**
     * Initialize class properties
     *
     * @return void
     */
    public function __construct()
    {
        $this->modelName = $this->modelName ?? class_basename($this->model);
        $this->tableName = $this->model->getTable();
        $this->modelKey = $this->modelKey ?? $this->tableName;
        $this->multipleFetchQueryCache = config("cache_manager.status.multiple_fetch_query", true);

        $this->boot();
    }

    public function boot(): void
    {
        $this->cacheManager = resolve(CacheManager::class, [
            "ttl" => $this->cacheTTl * 60,
            "isEnable" => $this->isCached,
            "model" => $this->model,
        ]);
    }

    public function isCached(bool $isCached = true): self
    {
        $this->isCached = $isCached;

        return $this;
    }

    /**
     * Update or Create
     *
     * @param  array $match
     * @param  array $data
     *
     * @return object
     */
    public function updateOrStore(array $match, array $data): object
    {
        $updated = $this->model->updateOrCreate($match, $data);

        $this->cacheManager->flushAllCache();

        return $updated;
    }

    /**
     * fetchOrStore
     *
     * @param  array $data
     *
     * @return object
     */
    public function fetchOrStore(array $data): object
    {
        $created = $this->model->firstOrCreate($data);

        $this->cacheManager->flushAllCache();

        return $created;
    }

    /**
     * store
     *
     * @param  array $data
     *
     * @return object
     */
    public function store(array $data): object
    {
        $created = $this->model->create($data)->fresh();

        $this->cacheManager->flushAllCache();

        return $created;
    }

    /**
     * Query database with id and update.
     *
     * @param  array $data
     * @param  string|int $id
     *
     * @return object
     */
    public function update(array $data, string|int $id): object
    {
        $rows = $this->model->whereId($id);


        $updated = $rows->firstOrFail();
        $updated->update($data);

        $this->cacheManager->flushAllCache();

        return $updated;
    }

    /**
     * Get object
     *
     * @param  mixed $id
     * @param  mixed $with
     *
     * @return object
     */
    public function get(mixed $id, array $with = [], array $tags = []): mixed
    {
        $rows = $this->model::query();
        if ($with != []) {
            $rows = $rows->with($with);
        }
        $fetched = $this->cacheManager->make(
            relates: array_merge($with, $tags),
            callback: function () use ($rows, $id) {
                $rows = $rows->where("id", $id);

                return $rows->first();
            },
            isCached: $this->isCached,
            identifier: [$id, $with]
        );

        return $fetched;
    }

    /**
     * Fetch by specific column
     *
     * @param string $column
     * @param mixed $value
     * @param array $with
     * @param boolean $multiple
     * @return mixed
     */
    public function getBy(
        string $column,
        mixed $value,
        array $with = [],
        bool $multiple = false
    ): mixed {
        $rows = $this->model::query();
        if ($with != []) {
            $rows = $rows->with($with);
        }
        $fetched = $this->cacheManager->make(
            relates: $with,
            callback: function () use ($rows, $column, $value, $multiple) {
                if (is_array($value)) {
                    $rows->whereIn($column, $value);
                } else {
                    $rows->where($column, $value);
                }
                if ($multiple) {
                    return $rows->get();
                } else {
                    return $rows->first();
                }
            },
            isCached: $this->multipleFetchQueryCache,
            identifier: [$column, $value, $with, $multiple]
        );

        return $fetched;
    }
}

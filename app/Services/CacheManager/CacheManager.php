<?php

namespace App\Services\CacheManager;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CacheManager extends CacheResolver
{
    protected ?object $model;
    protected ?int $ttl;
    protected ?string $tableName;
    protected ?bool $isEnable;

    public function __construct(
        ?int $ttl = null,
        ?bool $isEnable = true,
        ?object $model = null,
        ?string $modelName = null,
    ) {
        $this->ttl = $ttl ?? config("cache_manager.default_cache_ttl");
        $this->isEnable = $isEnable ?? config("cache_manager.status.global");
        $this->model = $model;
        $this->tableName = ($model instanceof Model) ? $model->getTable() : $modelName;
    }

    /**
     * Set cache ttl
     *
     * @param integer $ttl
     *
     * @return self
     */
    public function setTTl(int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * Set table name
     *
     * @return self
     */
    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * Set cache status
     *
     * @param boolean $enable
     *
     * @return self
     */
    public function setCacheStatus(bool $enable): self
    {
        $this->isEnable = $enable;
        return $this;
    }

    public function generateHash(mixed ...$identifiers): string
    {
        return md5(json_encode($identifiers));
    }

    public function make(
        array $relates = [],
        callable $callback = null,
        bool $isCached = true,
        mixed ...$identifier
    ): mixed {
        if (!$this->isEnable || !$isCached) {
            return $callback();
        }
        $relationalKeys = $this->resolveRelationKeys($relates);
        $backTraceMethod = Arr::last(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2));
        $identifier[] = [
            "parentMethodName" =>  $backTraceMethod["function"],
            "argument" => $backTraceMethod["args"]
        ];

        $hash = $this->generateHash($identifier, $relationalKeys);
        $tags = $this->generateCacheTags($this->tableName, $relationalKeys);

        $data = Cache::tags($tags)->remember(
            key: $hash,
            ttl: $this->ttl,
            callback: function () use ($callback) {
                return $callback();
            }
        );

        return $data;
    }

    public function flushAllCache(): void
    {
        $taggable = Str::snake(Str::singular($this->tableName));
        Cache::tags([$taggable])->flush();
    }
}

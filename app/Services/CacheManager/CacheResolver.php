<?php

namespace App\Services\CacheManager;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;

class CacheResolver
{
    /**
     * Generate cache tags.
     *
     * @param string $triggeredKey
     * @param array $taggableKeys
     *
     * @return array
     */
    public function generateCacheTags(string $triggeredKey, array $taggableKeys): array
    {
        $triggeredKey = [Str::snake(Str::singular($triggeredKey))];

        $data = array_merge($triggeredKey, $taggableKeys);

        return $data;
    }

    /**
     * Resolve relational keys.
     *
     * @param array $relations
     *
     * @return array
     */
    public function resolveRelationKeys(array $relations): array
    {
        $data = [];
        foreach ($relations as $relation) {
            if ($relation instanceof Closure) {
                continue;
            }
            if (Str::contains($relation, ".")) {
                $nestedRelationKeys = explode(".", $relation);
                $tags = array_map(function ($nestedRelationKey) {
                    return Str::snake(Str::singular($nestedRelationKey));
                }, $nestedRelationKeys);
            } else {
                $tags = Str::snake(Str::singular($relation));
            }

            $data[] = $tags;
        }

        $data = Arr::flatten($data);

        return $data;
    }

    /**
     * Returns model relation type and related model class.
     *
     * @param object $model
     *
     * @return array
     */
    public function getModelRelationships(object $model): array
    {
        $relationships = [];
        $modelMethods = (new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($modelMethods as $method) {
            if (
                $method->class != get_class($model)
                || !empty($method->getParameters())
                || $method->getName() == __FUNCTION__
            ) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                if ($return instanceof Relation) {
                    $relationships[] = [
                        $method->getName(),
                        Str::snake(Str::singular($method->getName())),
                    ];
                }
            } catch (Exception $exception) {
                // do nothing
            }
        }
        $relationships = array_unique(Arr::flatten($relationships));
        return $relationships;
    }

    /**
     * Generate cache keys according to relational keys.
     *
     * @todo This method is for future reference to optimize maintaining cache tags. It is private until we update caching mechanism.
     *
     * @param array $data
     *
     * @return array
     */
    private function generateTaggableCacheKey(array $data): array
    {
        // array reference to generate maintainable tags
        // $data = [
        //     Str::snake(Str::singular($this->tableName)) => [
        //         "hash" => $hash,
        //         "relations" => $relates,
        //     ],
        // ];
        $cacheKeys = [];
        foreach ($data as $key => $generatedFrom) {
            $parentSingularKey = Str::snake(Str::singular($key));
            $relationships = $generatedFrom["relations"];
            $hash = $generatedFrom["hash"];
            foreach ($relationships as $relationship) {
                $singularNestedKey = Str::snake(Str::singular($relationship));
                $nestedCacheKey = "repository_" . "{$singularNestedKey}_" . md5($singularNestedKey);

                if (Str::contains($relationship, ".")) {
                    $nestedRelations = explode(".", $relationship);
                    foreach ($nestedRelations as $nestedRelation) {
                        $singularNestedRelationKey = Str::snake(Str::singular($nestedRelation));
                        $nestedRelationCacheKey = "repository_" . "{$singularNestedRelationKey}_" . md5($singularNestedRelationKey);
                        $cacheKeys[$nestedRelationCacheKey]["{$parentSingularKey}_{$hash}"] = [
                            $parentSingularKey,
                            $singularNestedRelationKey,
                            $hash,
                            "repository_{$singularNestedRelationKey}_{$parentSingularKey}_" . md5($singularNestedRelationKey)
                        ];
                    }
                } else {
                    $cacheKeys[$nestedCacheKey]["{$parentSingularKey}_{$hash}"] = [
                        $parentSingularKey,
                        $singularNestedKey,
                        $hash,
                        "repository_{$singularNestedKey}_{$parentSingularKey}_" . md5($singularNestedKey),
                    ];
                }
            }
            if ($cacheKeys === []) {
                $cacheKeys = [
                    "repository_{$parentSingularKey}_" . md5($parentSingularKey) => [
                        "{$parentSingularKey}_{$hash}" => [
                            $parentSingularKey,
                            $hash,
                            "repository_{$parentSingularKey}_{$hash}"
                        ]
                    ]
                ];
            }
        }

        return $cacheKeys;
    }

    /**
     * Maintains relational cache keys and returns cache tags.
     *
     * @todo This method is for future reference to optimize maintaining cache tags. It is private until we update caching mechanism.
     *
     * @param array $cacheKeys
     * @param string $hash
     *
     * @return array
     */
    private function maintainCacheTag(string $tableName, array $cacheKeys, string $hash): array
    {
        $tableNameSingular = Str::singular($tableName);
        foreach ($cacheKeys as $relationKey => $tag) {
            if (!Redis::exists($relationKey)) {
                Redis::set($relationKey, json_encode($tag));
            } else {
                $prevCacheData = json_decode(Redis::get($relationKey), true, 4);
                Redis::del($relationKey);
                if (isset($prevCacheData["{$tableNameSingular}_{$hash}"])) {
                    $prevCacheData["{$tableNameSingular}_{$hash}"] = array_unique(
                        array_merge($prevCacheData["{$tableNameSingular}_{$hash}"], Arr::flatten($tag))
                    );
                } else {
                    $prevCacheData = array_merge($tag, $prevCacheData);
                }
                Redis::set($relationKey, json_encode($prevCacheData));
            }
        }

        $taggableKeys = Arr::flatten($cacheKeys);

        return $taggableKeys;
    }

    /**
     * Flushes related cache with possible tags.
     *
     * @todo This method is for future reference to optimize maintaining cache tags. It is private until we update caching mechanism.
     *
     * @param string $tableName
     * @return void
     */
    private function flushRelationalCache(string $tableName): void
    {
        $singularize = Str::snake(Str::singular($tableName));
        $key = "repository_{$singularize}_" . md5($singularize);
        $flushableCacheTags = json_decode(Redis::get($key), true, 4) ?? [];

        foreach ($flushableCacheTags as $generatedFrom => $flushableCacheTag) {
            Cache::tags($flushableCacheTag)->flush();
        }
    }
}

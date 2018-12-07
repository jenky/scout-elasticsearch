<?php

namespace Jenky\ScoutElasticsearch;

trait ScoutElasticsearch
{
    /**
     * Perform a search against the model's indexed data.
     *
     * @param  array  $query
     * @param  Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function searchRaw(array $query, $callback = null)
    {
        $query = new ElasticsearchQuery($query);

        return static::search($query, $callback);
    }

    /**
     * Get the Elasticsearch index configuration.
     *
     * @return array
     */
    public function getIndexConfig(): array
    {
        return [
            'index' => $this->searchableAs(),
            'body' => array_filter([
                'settings' => $this->getIndexSettings(),
                'mappings' => $this->getIndexMapping(),
            ]),
        ];
    }

    /**
     * Get the Elasticsearch index settings.
     *
     * @return array
     */
    public function getIndexSettings(): array
    {
        return [
            // 'number_of_shards' => 3,
            // 'number_of_replicas' => 2,
        ];
    }

    /**
     * Get the Elasticsearch index mapping.
     *
     * @return array
     */
    public function getIndexMapping(): array
    {
        return [
            ElasticsearchEngine::DEFAULT_TYPE => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $this->getIndexProperties(),
            ],
        ];
    }

    /**
     * Get the Elasticsearch index mapping properties.
     *
     * @return array
     */
    public function getIndexProperties(): array
    {
        return $this->generateProperties();
    }

    /**
     * Generate dynamic properties.
     *
     * @param  array $data
     * @return array
     */
    protected function generateProperties(array $data = [])
    {
        $properties = [];

        if ($this->getIncrementing()) {
            $properties[$this->getKeyName()] = [
                'type' => 'integer',
            ];
        }

        if ($this->usesTimestamps()) {
            $properties[$this->getCreatedAtColumn()] = [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ];

            $properties[$this->getUpdatedAtColumn()] = [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ];
        }

        $softDelete = static::usesSoftDelete() && config('scout.soft_delete', false);

        if ($softDelete) {
            $properties['__soft_deleted'] = [
                'type' => 'boolean',
            ];
        }

        return array_merge($properties, $data);
    }
}

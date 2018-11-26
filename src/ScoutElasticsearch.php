<?php

namespace Jenky\ScoutElasticsearch;

trait ScoutElasticsearch
{
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
        return [];
    }
}

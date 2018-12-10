<?php

namespace Jenky\ScoutElasticsearch\Elasticsearch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenky\ScoutElasticsearch\ElasticsearchEngine;

class Index
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Create new index instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get the Elasticsearch index configuration.
     *
     * @return array
     */
    public function getConfig() : array
    {
        return [
            'index' => $this->model->searchableAs(),
            'body' => array_filter([
                'settings' => $this->getSettings(),
                'mappings' => $this->getMapping(),
            ]),
        ];
    }

    /**
     * Get the Elasticsearch index settings.
     *
     * @return array
     */
    public function getSettings() : array
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
    public function getMapping() : array
    {
        return [
            ElasticsearchEngine::DEFAULT_TYPE => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $this->getProperties(),
            ],
        ];
    }

    /**
     * Get the Elasticsearch index mapping properties.
     *
     * @return array
     */
    public function getProperties() : array
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

        if ($this->model->getIncrementing()) {
            $properties[$this->model->getKeyName()] = [
                'type' => 'integer',
            ];
        }

        if ($this->model->usesTimestamps()) {
            $properties[$this->model->getCreatedAtColumn()] = [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ];

            $properties[$this->model->getUpdatedAtColumn()] = [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ];
        }

        $softDelete = $this->usesSoftDelete($this->model) && config('scout.soft_delete', false);

        if ($softDelete) {
            $properties['__soft_deleted'] = [
                'type' => 'boolean',
            ];
        }

        return array_merge($properties, $data);
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}

<?php

namespace Jenky\ScoutElasticsearch\Elasticsearch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenky\LaravelElasticsearch\Storage\Index as BaseIndex;

class Index extends BaseIndex
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
        $this->setConnection(config('scout.elasticsearch.connection'));
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return $this->model->searchableAs();
    }

    /**
     * {@inheritdoc}
     */
    public function searchableAs(): string
    {
        $alias = $this->model->searchableAs();

        return $this->multipleIndices ? $alias.'*' : $alias;
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

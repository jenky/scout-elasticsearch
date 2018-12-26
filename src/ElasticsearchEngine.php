<?php

namespace Jenky\ScoutElasticsearch;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenky\ScoutElasticsearch\Elasticsearch\Query;
use Jenky\ScoutElasticsearch\Elasticsearch\Client;

class ElasticsearchEngine extends Engine
{
    /**
     * @var string
     */
    const DEFAULT_TYPE = '_doc';

    /**
     * @var \Elasticsearch\Client
     */
    protected $elastic;

    /**
     * Create new elasticsearch engine driver.
     *
     * @param  \Jenky\ScoutElasticsearch\Elasticsearch\Client $client
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->elastic = $client;
    }

    /**
     * Create index if not exists.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function initIndex(Model $model)
    {
        if (! $this->elastic->indices()->exists(['index' => $model->elasticsearchIndex()->getIndexName()])) {
            // Create new index.
        }
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->initIndex($models->first());

        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            if ($this->usesSoftDelete($model) && config('scout.soft_delete', false)) {
                $model->pushSoftDeleteMetadata();
            }

            $array = array_merge(
                $model->toSearchableArray(),
                $model->scoutMetadata()
            );

            if (empty($array)) {
                return;
            }

            $params['body'][] = [
                'index' => [
                    '_id' => $model->getScoutKey(),
                    '_index' => $model->elasticsearchIndex()->getIndexName(),
                    '_type' => static::DEFAULT_TYPE,
                ],
            ];

            $params['body'][] = $array;
        });

        $this->elastic->bulk($params);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getScoutKey(),
                    '_index' => $model->searchableAs(),
                    '_type' => static::DEFAULT_TYPE,
                ],
            ];
        });

        $this->elastic->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        $result['nbPages'] = $result['hits']['total'] / $perPage;

        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $query = new Query($builder, $options);

        $params = [
            'index' => $builder->model->searchableAs(),
            'type' => static::DEFAULT_TYPE,
            'body' => $query->toArray(),
        ];

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elastic,
                $query,
                $params
            );
        }

        return $this->elastic->search($params);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total'] === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits']['hits'])->pluck('objectID')->values()->all();

        return $model->getScoutModelsByIds(
            $builder, $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        });
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $this->elastic->indices()->flush(['index' => $model->searchableAs()]);
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

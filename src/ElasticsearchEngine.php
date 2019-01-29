<?php

namespace Jenky\ScoutElasticsearch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenky\ScoutElasticsearch\Elasticsearch\Client;
use Jenky\ScoutElasticsearch\Elasticsearch\Index;
use Jenky\ScoutElasticsearch\Elasticsearch\Query;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

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
    // public function __construct()
    // {
    //     $this->elastic = $client;
    // }

    /**
     * Create index if not exists.
     *
     * @param  \Jenky\ScoutElasticsearch\Elasticsearch\Index $index
     * @return void
     */
    protected function initIndex(Index $index)
    {
        if (! $index->exists()) {
            $index->create();
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

        $index = $models->first()->elasticsearchIndex();
        $this->initIndex($index);

        $params['body'] = [];

        foreach ($models as $model) {
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
                    '_index' => $index->getIndex(),
                    '_type' => static::DEFAULT_TYPE,
                ],
            ];

            $params['body'][] = $array;
        }

        $index->getConnection()->bulk($params);
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

        $models->first()->elasticsearchIndex()->bulk($params);
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

        // $result['nbPages'] = $result['hits']['total'] / $perPage;

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
        $query = $this->parseBuilder($builder, $options);

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $query,
                $builder->query,
                $options
            );
        }

        return $query->get();
    }

    /**
     * Parse the builder and add necessary queries.
     *
     * @return void
     */
    protected function parseBuilder(Builder $builder, array $options = [])
    {
        $index = $builder->model->elasticsearchIndex();

        if (is_string($builder->query)) {
            if ($builder->query || $builder->query != '*') {
                $index = $index->queryString($builder->query);
            }
        }

        foreach ($builder->wheres ?: [] as $column => $value) {
            if (is_array($value)) {
                $index = $index->terms($column, $value);
            } else {
                $index = $index->term($column, $value);
            }
        }

        foreach ($builder->orders ?: [] as $order) {
            $index = $index->orderBy($order['column'], $order['direction']);
        }

        $index = $index->limit($options['size'] ?? $builder->model->getPerPage());

        if (isset($options['from'])) {
            $index = $index->skip(intval($options['from']));
        }

        return $index;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return $results->pluck('_id')->values();
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
        if ($results->total() === 0) {
            return $model->newCollection();
        }

        $objectIds = $results->pluck('_id')->values()->all();

        return $model->getScoutModelsByIds(
            $builder,
            $objectIds
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
        return $results->total();
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $model->elasticsearchIndex()->flush();
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

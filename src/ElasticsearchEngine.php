<?php

namespace Jenky\ScoutElasticsearch;

use Elasticsearch\Client;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Cviebrock\LaravelElasticsearch\Manager;

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
     *
     * @var bool
     */
    protected static $indexCreated;

    /**
     * Create new elasticsearch engine driver.
     *
     * @param  \Cviebrock\LaravelElasticsearch\Manager $client
     * @return void
     */
    public function __construct(Manager $client)
    {
        $this->elastic = $client;
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

        $this->createOrUpdateIndex($models->first());

        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            if ($model::usesSoftDelete() && config('scout.soft_delete', false)) {
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
                'update' => [
                    '_id' => $model->getScoutKey(),
                    '_index' => $model->searchableAs(),
                    '_type' => static::DEFAULT_TYPE,
                ],
            ];

            $params['body'][] = [
                'doc' => $array,
                'doc_as_upsert' => true,
            ];
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
        $this->createOrUpdateIndex($models->first());

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
            'numericFilters' => $this->filters($builder),
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
            'numericFilters' => $this->filters($builder),
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
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $filter = config('scout.elasticsearch.filter');
        $query = str_replace($filter, '', $builder->query);

        $params = [
            'index' => $builder->model->searchableAs(),
            'type' => static::DEFAULT_TYPE,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'query_string' => [
                                    'query' => $query,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elastic,
                $builder->query,
                $params
            );
        }

        return $this->elastic->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }
            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    /**
     * Generates the sort if theres any.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return;
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
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
            return collect([]);
        }

        $keys = collect($results['hits']['hits'])
            ->pluck('_id')->values()->all();

        $models = $model->getScoutModelsByIds(
            $builder,
            $keys
        )->keyBy(function ($model) {
            return $model->getScoutKey();
        });

        return collect($results['hits']['hits'])->map(function ($hit) use ($model, $models) {
            return isset($models[$hit['_id']]) ? $models[$hit['_id']] : null;
        })->filter()->values();
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
     * Create new Elasticsearch index.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function createIndex($model)
    {
        $this->elastic->indices()->create($model->getIndexConfig());
    }

    /**
     * Update Elasticsearch index.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function updateIndex($model)
    {
        $data = ['index' => $model->searchableAs()];

        // Todo: Update index settings and mapping.
        // $this->elastic->indices()->putSettings([
        //     array_except($model->getIndexConfig(), 'body.mappings'),
        // ]);

        // $this->elastic->indices()->putMappting([
        //     'index' => $model->searchableAs(),
        //     'type' => static::DEFAULT_TYPE,
        //     'body' => $model->getIndexMapping(),
        // ]);
    }

    /**
     * Delete Elasticsearch index.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function deleteIndex($model)
    {
        $this->elastic->indices()->delete(['index' => $model->searchableAs()]);
    }

    /**
     * Create or update Elasticsearch index.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function createOrUpdateIndex($model)
    {
        if ($this->isIndexExists($model)) {
            $this->updateIndex($model);
        } else {
            $this->createIndex($model);
        }
    }

    /**
     * Check if whether Elasticsearch index existed.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function isIndexExists($model)
    {
        if (is_null(static::$indexCreated)) {
            static::$indexCreated = $this->elastic->indices()->exists(['index' => $model->searchableAs()]);
        }

        return static::$indexCreated;
    }
}

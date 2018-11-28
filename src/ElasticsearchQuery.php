<?php

namespace Jenky\ScoutElasticsearch;

use Illuminate\Contracts\Support\Arrayable;

class ElasticsearchQuery implements Arrayable
{
    /**
     * Elasticsearch query.
     *
     * @var array
     */
    protected $query = [];

    /**
     * Create new query instance.
     *
     * @param  string|array
     * @return void
     */
    public function __construct($query)
    {
        $this->query = is_array($query) ? $query : $this->defaultQuery($query);
    }

    /**
     * Generate default query.
     *
     * @param  string $query
     * @return array
     */
    public function defaultQuery($query)
    {
        $filter = config('scout.elasticsearch.filter');
        $query = str_replace($filter, '', $query);

        return [
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
        ];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->query;
    }
}

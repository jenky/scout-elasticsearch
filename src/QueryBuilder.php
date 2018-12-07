<?php

namespace Jenky\ScoutElasticsearch;

use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Illuminate\Contracts\Support\Arrayable;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;

class QueryBuilder implements Arrayable
{
    /**
     * @var \ONGR\ElasticsearchDSL\Search
     */
    protected $search;

    /**
     * @var \Laravel\Scout\Builder
     */
    protected $builder;

    /**
     * @var array
     */
    protected $options;

    /**
     * Create new query builder instance.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  array $options
     * @return void
     */
    public function __construct(Builder $builder, array $options = [])
    {
        $this->builder = $builder;
        $this->options = $options;

        if ($builder->query instanceof Search) {
            $this->search = $builder->query;
        } else {
            $this->search = new Search;
        }

        $this->parseBuilder();
    }

    /**
     * Parse the builder and add necessary queries.
     *
     * @return void
     */
    protected function parseBuilder()
    {
        if (is_string($this->builder->query)) {
            $this->search->addQuery(new QueryStringQuery($this->builder->query));
        }

        foreach ($this->builder->wheres ? : [] as $column => $value) {
            if (is_array($value)) {
                $this->search->addQuery(new TermsQuery($column, $value));
            } else {
                $this->search->addQuery(new TermQuery($column, $value));
            }
        };

        foreach ($this->builder->orders ?: [] as $order) {
            $this->search->addSort(new FieldSort($order['column']), $order['direction']);
        };

        $this->search->setSize($this->options['size'] ?? $this->builder->model->getPerPage());

        if (isset($this->options['from'])) {
            $this->search->setFrom(intval($this->options['from']));
        }
    }

    /**
     * Get search query as an array.
     *
     * @return array
     */
    public function toArray()
    {
        // dd( $this->search->toArray() );
        return $this->search->toArray();
    }
}

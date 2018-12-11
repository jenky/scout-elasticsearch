<?php

namespace Jenky\ScoutElasticsearch\Elasticsearch;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Scout\Builder;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

class Query implements Arrayable
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
     * Get the Scout builder instance.
     *
     * @return \Laravel\Scout\Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * Get the builder option.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Parse the builder and add necessary queries.
     *
     * @return void
     */
    protected function parseBuilder()
    {
        if (is_string($this->builder->query)) {
            if ($this->builder->query || $this->builder->query != '*') {
                $this->search->addQuery(new QueryStringQuery($this->builder->query));
            }
        }

        foreach ($this->builder->wheres ?: [] as $column => $value) {
            if (is_array($value)) {
                $this->search->addQuery(new TermsQuery($column, $value));
            } else {
                $this->search->addQuery(new TermQuery($column, $value));
            }
        }

        foreach ($this->builder->orders ?: [] as $order) {
            $this->search->addSort(new FieldSort($order['column']), $order['direction']);
        }

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
        return $this->search->toArray();
    }

    /**
     * Dynamically call the default search DSL instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->search->{$method}(...$parameters);
    }
}

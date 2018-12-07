<?php

namespace Jenky\ScoutElasticsearch\Elasticsearch;

use Cviebrock\LaravelElasticsearch\Manager;
use Elasticsearch\Client as ElasticsearchClient;

class Client
{
    /**
     * Elastic search client.
     *
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * Create new elasticsearch instance.
     *
     * @param  string $connection
     * @return void
     */
    public function __construct($connection)
    {
        $this->client = app(Manager::class)->connection($connection);
    }

    /**
     * Get the elasticsearch client.
     *
     * @return \Elasticsearch\Client
     */
    public function elastic(): ElasticsearchClient
    {
        return $this->client;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->elastic()->{$method}(...$parameters);
    }
}

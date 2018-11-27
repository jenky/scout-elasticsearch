<?php

use Cviebrock\LaravelElasticsearch\Manager;

if (! function_exists('elasticsearch')) {
    /**
     * Get a elasticsearch client instance.
     *
     * @param  string  $connection
     * @return \Cviebrock\LaravelElasticsearch\Manager|\Elasticsearch\Client
     */
    function elasticsearch($connection = null)
    {
        return $connection ? app(Manager::class)->connection($connection) : app(Manager::class);
    }
}

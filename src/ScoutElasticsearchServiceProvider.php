<?php

namespace Jenky\ScoutElasticsearch;

use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Cviebrock\LaravelElasticsearch\Manager;
use Cviebrock\LaravelElasticsearch\ServiceProvider as ElasticsearchServiceProvider;

class ScoutElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->app->bound(ElasticsearchServiceProvider::class)) {
            $this->app->register(ElasticsearchServiceProvider::class);
        }

        $this->app[EngineManager::class]->extend('elasticsearch', function ($app) {
            return new ElasticsearchEngine($app[Manager::class]);
        });
    }
}

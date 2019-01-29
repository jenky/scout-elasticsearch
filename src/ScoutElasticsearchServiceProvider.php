<?php

namespace Jenky\ScoutElasticsearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ScoutElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('elasticsearch.scout', function ($app) {
            return $app['elasticsearch']->connection(
                $app['config']->get('scout.elasticsearch.connection')
            );
        });

        $this->app[EngineManager::class]->extend('elasticsearch', function () {
            return new ElasticsearchEngine;
        });
    }
}

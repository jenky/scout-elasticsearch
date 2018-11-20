<?php

namespace Jenky\ScoutElasticsearch;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use ScoutEngines\Elasticsearch\ElasticsearchEngine;

class ScoutElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('scout.elasticsearch', function ($app) {
            return ClientBuilder::fromConfig(
                $app['config']->get('scout.elasticsearch.client', [])
            );
        });

        $this->app[EngineManager::class]->exetend('elasticsearch', function ($app) {
            return new ElasticsearchEngine($app['scout.elasticsearch']);
        });
    }
}

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
        $this->app[EngineManager::class]->exetend('elasticsearch', function ($app) {
            return new ElasticsearchEngine(ClientBuilder::fromConfig(
                $app['config']->get('scout.elasticsearch.client', [])
            ));
        });
    }
}

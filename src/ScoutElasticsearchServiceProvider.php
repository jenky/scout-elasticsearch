<?php

namespace Jenky\ScoutElasticsearch;

use Cviebrock\LaravelElasticsearch\Manager;
use Cviebrock\LaravelElasticsearch\ServiceProvider as ElasticsearchServiceProvider;
use Illuminate\Support\ServiceProvider;
use Jenky\ScoutElasticsearch\Console\CreateIndexCommand;
use Jenky\ScoutElasticsearch\Console\DeleteIndexCommand;
use Jenky\ScoutElasticsearch\Console\UpdateIndexCommand;
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
        if (! $this->app->bound(ElasticsearchServiceProvider::class)) {
            $this->app->register(ElasticsearchServiceProvider::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateIndexCommand::class,
                UpdateIndexCommand::class,
                DeleteIndexCommand::class,
            ]);
        }

        $this->app[EngineManager::class]->extend('elasticsearch', function ($app) {
            $client = $app[Manager::class]->connection($app['config']->get('scout.elasticsearch.connection'));

            return new ElasticsearchEngine($client);
        });
    }
}

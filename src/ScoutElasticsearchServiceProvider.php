<?php

namespace Jenky\ScoutElasticsearch;

use Cviebrock\LaravelElasticsearch\ServiceProvider as ElasticsearchServiceProvider;
use Illuminate\Support\ServiceProvider;
use Jenky\ScoutElasticsearch\Elasticsearch\Client;
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
                Console\CreateIndexCommand::class,
                Console\UpdateIndexCommand::class,
                Console\DeleteIndexCommand::class,
            ]);
        }

        $this->app->singleton('elastic.scout.client', Client::class);

        $this->app->when(Client::class)
            ->needs('$connection')
            ->give($this->app['config']->get('scout.elasticsearch.connection'));

        $this->app[EngineManager::class]->extend('elasticsearch', function ($app) {
            return new ElasticsearchEngine($app->make(Client::class));
        });
    }
}

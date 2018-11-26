<?php

namespace Jenky\ScoutElasticsearch\Console;

use Cviebrock\LaravelElasticsearch\Manager;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;

class DeleteIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:delete-index {model : The model class name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Elasticsearch index for given model';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Manager $elastic, Config $config)
    {
        $class = $this->argument('model');

        $model = new $class;

        $client = $elastic->connection($config->get('scout.elasticsearch.connection'));

        $client->indices()->delete(['index' => $model->searchableAs()]);

        $this->info('Deleted elasticsearch index for model ['.$class.'].');
    }
}

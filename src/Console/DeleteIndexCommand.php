<?php

namespace Jenky\ScoutElasticsearch\Console;

use Illuminate\Console\Command;
use Jenky\ScoutElasticsearch\Elasticsearch\Client;

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
     * @param  \Jenky\ScoutElasticsearch\Elasticsearch\Client $client
     * @return void
     */
    public function handle(Client $client)
    {
        $class = $this->argument('model');

        $model = new $class;

        $client->indices()->delete(['index' => $model->searchableAs()]);

        $this->info('Deleted elasticsearch index for model ['.$class.'].');
    }
}

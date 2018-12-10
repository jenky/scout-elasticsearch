<?php

namespace Jenky\ScoutElasticsearch\Console;

use Illuminate\Console\Command;
use Jenky\ScoutElasticsearch\Elasticsearch\Client;

class CreateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:create-index {model : The model class name}
                            {--i|import : Import to index after creation}
                            {--f|force : Force to create new index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new Elasticsearch index for given model';

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

        if ($this->option('force')) {
            if ($client->indices()->exists(['index' => $model->searchableAs()])) {
                $this->call('elastic:delete-index', [
                    'model' => $class,
                ]);
            }
        }

        $client->indices()->create($model->elasticsearchIndex()->getConfig());

        $this->info('Created elasticsearch index for model ['.$class.'].');

        if ($this->option('import')) {
            $this->call('scout:import', [
                'model' => $class,
            ]);
        }
    }
}

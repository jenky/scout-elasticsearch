<?php

namespace Jenky\ScoutElasticsearch\Console;

use Cviebrock\LaravelElasticsearch\Manager;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;

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
     * @return void
     */
    public function handle(Manager $elastic, Config $config)
    {
        $class = $this->argument('model');

        $model = new $class;

        $client = $elastic->connection($config->get('scout.elasticsearch.connection'));

        if ($this->option('force')) {
            if ($client->indices()->exists(['index' => $model->searchableAs()])) {
                $this->call('elastic:delete-index', [
                    'model' => $class,
                ]);
            }
        }

        $client->indices()->create($model->getIndexConfig());

        $this->info('Created elasticsearch index for model ['.$class.'].');

        if ($this->option('import')) {
            $this->call('scout:import', [
                'model' => $class,
            ]);
        }
    }
}

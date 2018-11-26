<?php

namespace Jenky\ScoutElasticsearch\Console;

use Cviebrock\LaravelElasticsearch\Manager;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;

class UpdateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:update-index {model : The model class name}
                            {--i|import : Flush then re-import all of the model\' s records to the index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing Elasticsearch index for given model';

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

        $client->indices()->putSettings([
            array_except($model->getIndexConfig(), 'body.mappings'),
        ]);

        $client->indices()->putMapping([
            'index' => $model->searchableAs(),
            'type' => static::DEFAULT_TYPE,
            'body' => $model->getIndexMapping(),
        ]);

        $this->info('Update elasticsearch index settings and mapping for model ['.$class.'].');

        if ($this->option('import')) {
            $this->call('scout:flush', [
                'model' => $class,
            ]);
            $this->call('scout:import', [
                'model' => $class,
            ]);
        }
    }
}

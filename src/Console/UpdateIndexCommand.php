<?php

namespace Jenky\ScoutElasticsearch\Console;

use Cviebrock\LaravelElasticsearch\Manager;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;
use Jenky\ScoutElasticsearch\ElasticsearchEngine;

class UpdateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:update-index {model : The model class name}
                            {--i|import : Flush then re-import all of the model\' s records to the index}
                            {--f|force : Force drop then re-create Elasticsearch index}';

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

        if ($this->option('force')) {
            $this->call('elastic:delete-index', [
                'model' => $class,
            ]);

            $this->call('elastic:create-index', [
                'model' => $class,
                '--import' => $this->option('import'),
            ]);

            return;
        }

        $client = $elastic->connection($config->get('scout.elasticsearch.connection'));

        $settings = array_except($model->getIndexConfig(), 'body.mappings');

        if ($this->option('import')) {
            $this->call('scout:flush', [
                'model' => $class,
            ]);
        }

        if (! empty(array_get($settings, 'body.settings'))) {
            $client->indices()->putSettings([
                $settings,
            ]);
        }

        $client->indices()->putMapping([
            'index' => $model->searchableAs(),
            'type' => ElasticsearchEngine::DEFAULT_TYPE,
            'body' => $model->getIndexMapping(),
        ]);

        $this->info('Update elasticsearch index settings and mapping for model ['.$class.'].');

        if ($this->option('import')) {
            $this->call('scout:import', [
                'model' => $class,
            ]);
        }
    }
}

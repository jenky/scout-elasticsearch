<?php

namespace Jenky\ScoutElasticsearch\Console;

use Illuminate\Console\Command;
use Jenky\ScoutElasticsearch\Elasticsearch\Client;
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
     * @param  \Jenky\ScoutElasticsearch\Elasticsearch\Client $client
     * @return void
     */
    public function handle(Client $client)
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

        $config = $model->elasticsearchIndex()->getConfig();

        if ($this->option('import')) {
            $this->call('scout:flush', [
                'model' => $class,
            ]);
        }

        if (! empty(array_get($settings, 'body.settings'))) {
            $client->indices()->putSettings([
                array_except($config, 'body.mappings'),
            ]);
        }

        $client->indices()->putMapping([
            'index' => $model->searchableAs(),
            'type' => ElasticsearchEngine::DEFAULT_TYPE,
            'body' => array_get($config, 'body.mappings'),
        ]);

        $this->info('Update elasticsearch index settings and mapping for model ['.$class.'].');

        if ($this->option('import')) {
            $this->call('scout:import', [
                'model' => $class,
            ]);
        }
    }
}

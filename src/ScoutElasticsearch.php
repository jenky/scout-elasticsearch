<?php

namespace Jenky\ScoutElasticsearch;

use Jenky\ScoutElasticsearch\Elasticsearch\Index;

trait ScoutElasticsearch
{
    /**
     * Get the elasticsearch index instance.
     *
     * @throws \InvalidArgumentException
     * @return \Jenky\ScoutElasticsearch\Elasticsearch\Index
     */
    public function elasticsearchIndex(): Index
    {
        return new Index($this);
    }
}

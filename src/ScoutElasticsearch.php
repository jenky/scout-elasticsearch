<?php

namespace Jenky\ScoutElasticsearch;

use Jenky\ScoutElasticsearch\Elasticsearch\Index;
use InvalidArgumentException;

trait ScoutElasticsearch
{
    /**
     * Get the elasticsearch index instance.
     *
     * @throws \InvalidArgumentException
     * @return \Jenky\ScoutElasticsearch\Elasticsearch\Index
     */
    public function elasticsearchIndex()
    {
        $class = property_exists($this, 'elasticsearchIndex') ? $this->elasticsearchIndex : Index::class;
        $index = new $class($this);

        if ($index instanceof Index) {
            return $index;
        }

        throw new InvalidArgumentException('The index must be instance of '. Index::class);
    }
}

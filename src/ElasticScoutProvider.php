<?php

namespace Tbryan24\LaravelScoutElastic;

use Exception;
use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Tbryan24\LaravelScoutElastic\Engines\ElasticsearchEngine;

class ElasticScoutProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->ensureElasticClientIsInstalled();

        resolve(EngineManager::class)->extend(
            'elasticsearch',
            function () {
                return new ElasticsearchEngine(
                    ClientBuilder::create()
                        ->setHosts(config('scout.elasticsearch.hosts'))
                        ->build()
                );
            }
        );
    }

    /**
     * Ensure the Elastic API client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureElasticClientIsInstalled()
    {
        if (class_exists(ClientBuilder::class)) {
            return;
        }

        throw new Exception('Please install the Elasticsearch PHP client: elasticsearch/elasticsearch.');
    }
}

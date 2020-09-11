<?php

namespace Ngocnm\Elastic;

use Illuminate\Support\ServiceProvider;
use Elasticsearch\ClientBuilder;

class ElasticsearchProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('elastic', function ($app) {
            $client = ClientBuilder::create()->setHosts([env('ELASTIC_HOST','localhost').":".env("ELASTIC_PORT",9200)])->build();
            return  $client;
        });
    }

}

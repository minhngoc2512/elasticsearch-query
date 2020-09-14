<?php

namespace Ngocnm\ElasticQuery;

use Illuminate\Support\ServiceProvider;
use Elasticsearch\ClientBuilder;

class ElasticsearchServiceProvider extends ServiceProvider
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
        $this->app->singleton('elastic_query', function ($app) {
//            if(config('app.elastic_username')!=null){
//                $config = [config('app.elastic_username').":".config('app.elastic_password')."@".config('app.elastic_host').":".config('app.elastic_port')];
//            }else{
//                $config = [config('app.elastic_host').":".config('app.elastic_port')];
//            }
//            $config_elastic['hosts'] = $config;
            $client = ClientBuilder::create()->setHosts([env('ELASTIC_HOST','localhost').":".env('ELASTIC_PORT',9200)])->build();
            return  $client;
        });
    }
}

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
            $hosts = env('ELASTIC_HOST','localhost');
            $port = env('ELASTIC_PORT',9200);
            if(strpos($hosts,',')!==false){
                $hosts = explode(',',$hosts);
            }
            if(is_array($hosts)){
                $hosts = array_map(function ($item)use($port){
                    return "$item:$port";
                },$hosts);
            }else{
                $hosts = ["$hosts:$port"];
            }
            $client = ClientBuilder::create()->setHosts($hosts)->build();
            return  $client;
        });
    }
}

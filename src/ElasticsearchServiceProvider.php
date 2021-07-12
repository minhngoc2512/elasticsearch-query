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
        if(env('APP_DEBUG')===true&&class_exists('Barryvdh\Debugbar\Facade')){
            \Barryvdh\Debugbar\Facade::addCollector(new \DebugBar\DataCollector\MessagesCollector('elasticsearch'));
            define('ELASTICSEARCH_LOG_DEBUGBAR',true);
        }
        if(!defined('ELASTICSEARCH_LOG_DEBUGBAR')) define('ELASTICSEARCH_LOG_DEBUGBAR',false);
        define('ELASTICSEARCH_INDEX_PREFIX',env('ELASTIC_INDEX_PREFIX',''));
        $this->app->singleton('elastic_query', function ($app) {
            $hosts = env('ELASTIC_HOST','localhost');
            $hosts_config = [
                'port'=>env('ELASTIC_PORT',9200),
                'scheme'=>env('ELASTIC_SCHEME','http'),
                'host'=>$hosts
            ];
            if(!empty(env('ELASTIC_PASSWORD',null))) $hosts_config['pass'] = env('ELASTIC_PASSWORD');
            if(!empty(env('ELASTIC_USERNAME',null))) $hosts_config['user'] = env('ELASTIC_USERNAME');
            if(!empty(env('ELASTIC_PATH',null))) $hosts_config['path'] = env('ELASTIC_PATH');
            if(!empty(env('ELASTIC_SCHEME',null))) $hosts_config['scheme'] = env('ELASTIC_SCHEME');
            if(strpos($hosts,',')!==false){
                $hosts = explode(',',$hosts);
            }
            if(is_array($hosts)){
                $hosts = array_map(function ($host)use($hosts_config){
                    $hosts_config['host'] = $host;
                    return $hosts_config;
                },$hosts);
            }else{
                $hosts = [$hosts_config];
            }
            $client = ClientBuilder::create()->setHosts($hosts)->build();
            return  $client;
        });
    }
}

<?php


namespace Ngocnm\ElasticQuery;


class ElasticsearchQueryLog
{
    use SingletonTrait;

    static $query_log = [];

    static function appendLog(array $data){
        self::$query_log[] = $data;
    }

    static function getLog(){
        return self::$query_log;
    }
}

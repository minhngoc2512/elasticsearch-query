<?php

namespace Ngocnm\ElasticQuery;


use Elasticsearch\ClientBuilder;

class ElasticsearchQuery
{
    private $source = null;
    private $terms = [];
    private $search = [];
    private $range_query = [];
    private $limit = 30;
    private $index = null;
    private $doc = null;
    private $from = 0;
    private $sort = null;
    private $convert_data = true;
    private $more_like_this = null;
    private $filter = [];
    private $client = null;
    private $terms_not = [];
    private $range_query_not = [];

    public function __construct(string $index, string $doc=null)
    {
        $this->client = app('elastic_query');
        $this->index = ELASTICSEARCH_INDEX_PREFIX.$index;
        $this->doc =$doc;
    }

    public function select(string $fields)
    {
        $this->source = explode(',', $fields);
        return $this;
    }

    public function where($column, $value_1, $value_2 = null)
    {
        if ($value_2 != null) {
            if ($value_1 == '>') {
                $this->range_query[$column] = ['gte' => $value_2];
            } else if ($value_1 == '<') {
                $this->range_query[$column] = ['lte' => $value_2];
            }
        } else {
            $this->terms[] = [
                "terms" => [
                    $column => [$value_1]
                ]
            ];
        }
        return $this;
    }

    public function whereIn($column,$value){
        $this->terms[] = [
            "terms" => [
                $column => $value
            ]
        ];
        return $this;
    }

    public function whereBetween(string $column, array $value)
    {
        $this->range_query[$column] = ['gte' => $value[0], 'lte' => $value[1]];
        return $this;
    }

    public function whereNot($column, $value_1, $value_2 = null)
    {
        if ($value_2 != null) {
            if ($value_1 == '>') {
                $this->range_query_not[$column] = ['gte' => $value_2];
            } else if ($value_1 == '<') {
                $this->range_query_not[$column] = ['lte' => $value_2];
            }
        } else {
            $this->terms_not[] = [
                "terms" => [
                    $column => [$value_1]
                ]
            ];
        }
        return $this;
    }

    public function whereNotIn($column,$value){
        $this->terms_not[] = [
            "terms" => [
                $column => $value
            ]
        ];
        return $this;
    }

    public function whereNotBetween( $column,  $value)
    {
        $this->range_query_not[$column] = ['gte' => $value[0], 'lte' => $value[1]];
        return $this;
    }

    public function orderBy($column, $sort = 'asc')
    {
//        $this->sort = [$column => ['order' => $sort]];
        $this->sort = [$column =>$sort];
        return $this;
    }

    public function limit($limit)
    {
        $this->limit =(int) $limit;
        return $this;
    }

    public function offset( $offset)
    {
        $this->from =(int) $offset;
        return $this;
    }

    public function queryString($column, $keyword)
    {
        $this->search[] = [
            "match" => [
                $column => $keyword
            ]
        ];
        return $this;
    }

    public function moreLikeThis($column,$keyword,$config=null){
        if($config!=null&&!is_array($column)){
            throw new \Exception('config is array');
        }
        if(isset($config['min_term_freq'])&&intval($config['min_term_freq'])){
            $min_term_freq = (int) $config['min_term_freq'];
        }
        if(isset($config['max_query_terms'])&&intval($config['max_query_terms'])){
            $max_query_terms = (int) $config['max_query_terms'];
        }
        $this->more_like_this = [
            'fields'=>is_array($column)?$column:[$column],
            "like"=>$keyword,
            'min_term_freq'=>!empty($min_term_freq)?$min_term_freq:1,
            'max_query_terms'=>!empty($max_query_terms)?$max_query_terms:30
        ];
        return $this;
    }

    public function fullTextSearchTrigrams($column, $keyword){
        $this->search[] = [
            "match" => [
                $column => $this->buildTrigrams($keyword)
            ]
        ];
        return $this;
    }

    private function buildTrigrams($keyword)
    {
        $t = "__" . $keyword . "__";
        $trigrams = "";
        for ($i = 0; $i < mb_strlen($t, "UTF-8") - 2; $i++)
            $trigrams .= mb_substr($t, $i, 3, "UTF-8") . " ";
        return $trigrams;
    }

    public function delete($id){
        try{
            $params = [
                '_index' => $this->index,
                '_type' => $this->doc,
                '_id' => $id
            ];
            $time_start = microtime(true);
            $this->client->delete($params);
            self::logQuery($params,$time_start,"DELETE");
            return true;
        }catch (\Exception $e){
            throw new \Exception("elasticsearch delete error:".$e->getMessage());
            return false;
        }

    }

    public function insertOrUpdate(array $data,$primary_key){
        $params = ['body' => []];
        if(isset($data[0])){
            foreach ($data as $value){
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->index,
                        '_type' => $this->doc,
                        '_id' => $value[$primary_key]
                    ]
                ];
                $params['body'][] = $value;
            }
        }else{
            $params['body'][] = [
                'index' => [
                    '_index' => $this->index,
                    '_type' => $this->doc,
                    '_id' => $data[$primary_key]
                ]
            ];
            $params['body'][] = $data;
        }
        $time_start = microtime(true);
        $this->client->bulk($params);
        self::logQuery($params,$time_start,"INSERT_OR_UPDATE");
    }

    public function deleteMulti(){
        $params = [
            'index' => $this->index,
            'type' => $this->doc,
            "body"=>[
                "query" => [
                    "bool" => [
                        "must" => []
                    ]

                ]
            ]
        ];
        if(count($this->terms)!=0){
            foreach ($this->terms as $value){
                $params['body']['query']['bool']['must'][] =$value;
            }
        }
        if(count($this->range_query))$params['body']['query']['bool']['must'][] = ['range'=>$this->range_query];
        if(count($this->terms_not)!=0) {
            foreach ($this->terms_not as $value){
                $params['body']['query']['bool']['must_not'][]  =$value;
            }
        }
        if(count($this->range_query_not))$params['body']['query']['bool']['must_not'][] = ['range'=>$this->range_query_not];
        try{
            $time_start = microtime(true);
            $data_search = $this->client->deleteByQuery($params);
            self::logQuery($params,$time_start,"DELETE_MULTI");
            return $data_search;
        }catch (\Exception $error){
            throw new \Exception('elasticsearch error:'.$error->getMessage());
            return false;
        }
    }

    public function whereGeoDistance($lat,$lng,$distance='1km'){
        $this->filter['geo_distance'] =[
            "distance" =>$distance,
            "location" => [
                "lat" => $lat,
                "lon" => $lng
            ]
        ];
        return $this;
    }

    public function first(){
        $this->limit = 1;
        $value = $this->get();
        return isset($value[0])?$value[0]:null;
    }

    private function buildQuery(){
        $params = [
            'index' => $this->index,
            'type' => $this->doc,
            'body' => [
                "query" => [
                    "bool" => [
                        "must" => []
                    ]

                ]
            ]
        ];
        foreach ($this->search as $value){
            $params['body']['query']['bool']['must'][] =$value;
        }
        if(count($this->range_query))$params['body']['query']['bool']['must'][] = ['range'=>$this->range_query];
        if($this->source!=null) $params['body']['_source'] = $this->source;
        if($this->sort!=null) $params['body']['sort'] = [$this->sort];
        if(count($this->filter)!=0)   $params['body']['query']['bool']['filter'] = $this->filter;
        if(count($this->terms_not)!=0) {
            foreach ($this->terms_not as $value){
                $params['body']['query']['bool']['must_not'][]  =$value;
            }
        }
        if(count($this->range_query_not))$params['body']['query']['bool']['must_not'][] = ['range'=>$this->range_query_not];
        if(count($this->terms)!=0){
            foreach ($this->terms as $value){
                $params['body']['query']['bool']['must'][] =$value;
            }
        }
        if($this->more_like_this!=null) $params['body']['query']['bool']['must'][] = ['more_like_this'=>$this->more_like_this];
        return $params;
    }

    public function get(bool $info_query=false){
        $data_search = ['hits'=>['hits'=>[]]];
        $params =  $this->buildQuery();
        $params['body']['from'] = $this->from;
        $params['body']['size'] = $this->limit;
        if($this->source!=null) $params['body']['_source'] = $this->source;
        if($this->sort!=null) $params['body']['sort'] = [$this->sort];
        try{
            $time_start = microtime(true);
            $data_search = $this->client->search($params);
            self::logQuery($params,$time_start,"GET");
        }catch (\Exception $error){
            throw new \Exception('elasticsearch error:'.$error->getMessage());
            return [];
        }
        if($info_query===false){
            $value = [];
            if (isset($data_search['hits']['hits'])) {
                foreach ($data_search['hits']['hits'] as $data) {
                    $value[] = $data['_source'];
                }
            }
            return $value;
        }
        $data_search['query'] = $params;
        return $data_search;
    }

    function count(){
        $params =  $this->buildQuery();
        $data_search = [];
        try{
            $time_start = microtime(true);
            $data_search = $this->client->count($params);
            self::logQuery($params,$time_start,"COUNT");
            $data_search = isset($data_search['count'])?$data_search['count']:0;
        }catch (\Exception $error){
            throw new \Exception('elasticsearch error:'.$error->getMessage());
            return 0;
        }
        return $data_search;
    }

    static function indexExists($name){
        $time_start = microtime(true);
        $query = ['index' => ELASTICSEARCH_INDEX_PREFIX.$name];
        $value = app('elastic_query')->indices()->exists($query);
        self::logQuery($query,$time_start,"INDEX_EXISTS");
        return $value;
    }

    static function deleteIndex($name){
        $time_start = microtime(true);
        $query = ['index' => ELASTICSEARCH_INDEX_PREFIX.$name];
        $value = app('elastic_query')->indices()->delete($query);
        self::logQuery($query,$time_start,"DELETE_INDEX");
        return $value;
    }

    static function createIndex($query){
        $time_start = microtime(true);
        $value =  app('elastic_query')->indices()->create($query);
        self::logQuery($query,$time_start,"CREATE_INDEX");
        return $value;
    }

    static function createIndexByOptions($name,$number_of_shards=15,$number_of_replicas=1,$mappings=null){
        $query = [
            'index' => ELASTICSEARCH_INDEX_PREFIX.$name,
            'body' => [
                'settings' => [
                    'number_of_shards' => $number_of_shards,
                    'number_of_replicas' => $number_of_replicas
                ]
            ]
        ];
        if(!empty($mappings&&is_array($mappings))){
            $query['body']['mappings']  = $mappings;
        }
        $time_start = microtime(true);
        $value =  app('elastic_query')->indices()->create($query);
        self::logQuery($query,$time_start,"CREATE_INDEX");
        return $value;
    }

    static function logQuery($query,$time_start,$type='GET'){
        if(!empty(ELASTICSEARCH_LOG_DEBUGBAR)&&ELASTICSEARCH_LOG_DEBUGBAR===true){
            $time_end = microtime(true);
            $time =(string) (($time_end - $time_start)*1000);
            $time = round($time);
            $debug_backtrace = array_filter(debug_backtrace(),function($file){
                return !isset($file['file'])||strpos($file['file'],'vendor')===false;
            });
            $debug_backtrace = array_map(function ($file){
                unset($file['object']);
                unset($file['args']);
                return $file;
            },$debug_backtrace);
            \Barryvdh\Debugbar\Facade::getCollector('elasticsearch')->addMessage(['time'=>$time.'ms','query'=>$query,'debug_backtrace'=>$debug_backtrace],$type);
        }
    }
}

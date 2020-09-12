<?php

namespace Ngocnm\ElasticQuery;


use Elasticsearch\ClientBuilder;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;

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

    public function __construct(string $index, string $doc)
    {
        $this->client = ClientBuilder::create()->setHosts([env('ELASTIC_HOST','localhost').":".env("ELASTIC_PORT",9200)])->build();
        $this->index = $index;
        $this->doc = $doc;
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
        $this->search = [
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
        $this->search = [
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
                'index' => $this->index,
                'type' => $this->doc,
                'id' => $id
            ];
            $this->client->delete($params);
            return true;
        }catch (\Exception $e){
            return false;
        }

    }

    public function insertOrUpdate(array $data,$primary_key){
        $params = ['body' => []];
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
        $this->client->bulk($params);
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
            $data_search = $this->client->deleteByQuery($params);
            return $data_search;
        }catch (\Exception $error){
            if(env('APP_DEBUG')){
                dd([
                    'status' =>'Error',
                    'message'=>$error->getMessage(),
                    'line'=>$error->getLine(),
                    'code'=>$error->getCode(),
                    'file'=>$error->getFile(),
                    'query'=>$params,
                    'json_query'=>json_encode($params)
                ]);
            }else{
                throw new \Exception('elasticsearch error');
                return false;
            }
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
        return isset($value[0])?$value[0]:[];
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
        if($this->search!=null){
            if(count($this->search)>1){
                foreach ($this->search as $value){
                    $params['body']['query']['bool']['must'][] =$value;
                }
            }else{
                $params['body']['query']['bool']['must'][] =$this->search;   
            }
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
            $data_search = $this->client->search($params);
        }catch (\Exception $error){
            if(env('APP_DEBUG')){
                dd([
                    'status' =>'Error',
                    'message'=>$error->getMessage(),
                    'line'=>$error->getLine(),
                    'code'=>$error->getCode(),
                    'file'=>$error->getFile(),
                    'query'=>$params,
                    'json_query'=>json_encode($params)
                ]);
            }else{
                throw new \Exception('elasticsearch error');
                return [];
            }
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
            $data_search = $this->client->count($params);
            $data_search = isset($data_search['count'])?$data_search['count']:0;
        }catch (\Exception $error){
            if(env('APP_DEBUG')){
                dd([
                    'status' =>'Error',
                    'message'=>$error->getMessage(),
                    'line'=>$error->getLine(),
                    'code'=>$error->getCode(),
                    'file'=>$error->getFile(),
                    'query'=>$params,
                    'json_query'=>json_encode($params)
                ]);
            }else{
                throw new \Exception('elasticsearch error');
                return 0;
            }
        }
        return $data_search;
    }

}


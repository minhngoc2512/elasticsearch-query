<?php

namespace Ngocnm\Elastic;


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

    public function __construct(string $index, string $doc)
    {
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
                $column => BuildTrigrams($keyword)
            ]
        ];
        return $this;
    }

    public function delete($id){
        try{
            $params = [
                'index' => $this->index,
                'type' => $this->doc,
                'id' => $id
            ];
            app('elastic')->delete($params);
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
        app('elastic')->bulk($params);
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
        try{
            $data_search = app('elastic')->deleteByQuery($params);
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
                echo "Error";
                exit();
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

    public function get(bool $info_query=false){
        $params = [
            'index' => $this->index,
            'type' => $this->doc,
            'body' => [
                "query"=>[
                    "bool" => [
                        "must" => []
                    ]
                ],
                "from" =>$this->from,
                "size" => $this->limit
            ]
        ];
        if($this->search!=null) $params['body']['query']['bool']['must'][] =$this->search;
        if(count($this->range_query))$params['body']['query']['bool']['must'][] = ['range'=>$this->range_query];
        if($this->source!=null) $params['body']['_source'] = $this->source;
        if($this->sort!=null) $params['body']['sort'] = [$this->sort];
        if(count($this->filter)!=0)   $params['body']['query']['bool']['filter'] = $this->filter;
        if(count($this->terms)!=0){
            foreach ($this->terms as $value){
                $params['body']['query']['bool']['must'][] =$value;
            }
        }
        if($this->more_like_this!=null) $params['body']['query']['bool']['must'][] = ['more_like_this'=>$this->more_like_this];
        try{
            $data_search = app('elastic')->search($params);
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
                echo "Error";
                exit();
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

}

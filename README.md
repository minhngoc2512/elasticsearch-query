### Elastic Query Builder For Lumen, Laravel
#Install
```
composer require ngocnm/elastic-query
```
# Config
- Register Service Provider
```php
$app->register(\Ngocnm\ElasticQuery\ElasticsearchServiceProvider::class);
```
- Define env
```dotenv
ELASTIC_HOST = localhost,localhost_2 #default: localhost
ELASTIC_PORT = 9200 #default: 9200
ELASTIC_INDEX_PREFIX = project_1 #default: null
ELASTIC_USERNAME= root #default: null
ELASTIC_PASSWORD= admin #default: null
ELASTIC_SCHEME = https #default: http
ELASTIC_PATH= /data/elastic #default: null
```
- Config without laravel lumen
    - Create singleton with key: ```elastic_query```
        ```php
        $this->app->singleton('elastic_query', function ($app) {
            $client = Elasticsearch\ClientBuilder::create()->setHosts([env('ELASTIC_HOST','localhost').":".env('ELASTIC_PORT',9200)])->build();
            return  $client;
        });
```
#Query
- Create Object
```php
 $client = new Ngocnm\ElasticQuery\ElasticsearchQuery('index_name');
```
- Select query
```php
$response = $client->select('field_1,field_2')->get();
```
- Limit query
```php
$response = $client->select('field_1,field_2')->limit(3)->get();
```
- Offset query
```php
$response = $client->select('field_1,field_2')->offset($offset)->limit(3)->get();
```
- Where query
```php
$response = $client->select('field_1,field_2')->where('field_name',$value)->limit(3)->get();
//or
$response = $client->select('field_1,field_2')->where('field_name','>',$value)->limit(3)->get();
```

- OrderBy query(ASC,DESC)
```php
$response = $client->select('field_1,field_2')->orderBy('field_name','asc')->get();
```
- Where between query
```php
$value = ['value_1','value_2'];
$response = $client->select('field_1,field_2')->whereBetween('field_name',$value)->get();
```
- Where GeoDistance query
```php
//$distance = '1km' default 
//column name map: location 
$response = $client->select('field_1,field_2')->whereGeoDistance($lat,$lng,$distance)->get();
```
-  Delete row by id
```php
$response = $client->delete($id);
```
-  Delete Multi rows
```php
$response = $client->where('field_name',$value)->deleteMulti();
```
- QueryString - Fulltext search
```php
$response = $client->queryString('field_name',$keyword)->get();
```
- WhereIn  query
```php
$value = [23,4,5,...];
$response = $client->whereIn('field_name',$value)->get();
```
- WhereNot query
```php
$response = $client->WhereNot('field_name',$value)->get();
//Or
$response = $client->WhereNot('field_name','>',$value)->get();
```
- WhereNotIn  query
```php
$value = [23,4,5,...];
$response = $client->whereNotIn('field_name',$value)->get();
```
- WhereNotBetween query
```php
$value = ['value_1','value_2'];
$response = $client->select('field_1,field_2')->whereNotBetween('field_name',$value)->get();
```
- Full Text Search Trigrams query
```php
$response = $client->select('field_1,field_2')->fullTextSearchTrigrams('field_name',$keyword)->get();
```
- Insert a document or multi documents
    - Insert or update a document
        ```php
        $data = [
            'field_id_unique'=>1,
            'field_1'=>$value_1,
            'field_2'=>$value_2
        ];
        ```
    - Insert or update multi documents
        ```php
        $data = [
              [
                  'field_id_unique'=>1,
                  'field_1'=>$value_1,
                  'field_2'=>$value_2
              ],
              [
                  'field_id_unique'=>2,
                  'field_1'=>$value_1,
                  'field_2'=>$value_2
              ]
      ];
        ```
```php
$reponse = $client->insertOrUpdate($data,'name_field_id_unique');
```
- Update multi documents with condition (Update by query)
```php
  $data_update = ['field'=>'new_value','field_2'=>'new_value_2'];
  $response = $client->where('field_condition','value_condition')->update($data_update);
```
- Delete index
```php
Ngocnm\ElasticQuery\ElasticsearchQuery::deleteIndex($name_index);
```
- CreateIndex index by query
```php
$query_create = [
        'index' => $index_name,
        'body' => [
            'settings' => [
                'number_of_shards' => 15,
                'number_of_replicas' => 1
            ]
        ]
    ];
    
Ngocnm\ElasticQuery\ElasticsearchQuery::createIndex($query_create);
```
- CreateIndex index by options

```php
$index_name = 'index_demo';
$number_of_shards = 15; // default:15
$number_of_replicas = 1; // default:15
$mappings = [
                '_source' => [
                    'enabled' => true
                ],
                'properties' => [
                    'location' => [
                        'type' => 'geo_point'
                    ]
                ]
            ]; // default:[]
Ngocnm\ElasticQuery\ElasticsearchQuery::createIndexByOptions($index_name,$number_of_shards,$number_of_replicas,$mappings);
```
[Document mapping](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index_management.html)

- Check index exist
```php
Ngocnm\ElasticQuery\ElasticsearchQuery::indexExists($name_index);
```
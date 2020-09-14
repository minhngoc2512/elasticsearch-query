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
ELASTIC_HOST = localhost
ELASTIC_PORT = 9200
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
- Delete index
```php
Ngocnm\ElasticQuery\ElasticsearchQuery::deleteIndex($name_index);
```
- CreateIndex index
```php
Ngocnm\ElasticQuery\ElasticsearchQuery::createIndex($query_create);
```
- Check index exist
```php
Ngocnm\ElasticQuery\ElasticsearchQuery::indexExists($name_index);
```
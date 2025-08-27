<?php

namespace Xpkg\Elasticsearch;

use Xpkg\Http\Http;

class ES
{
    public static string $connection = 'main';
    
    public static function translateSQL($sql, array $params = []): Http
    {
        $sql = str_ireplace('limit 0', '', $sql);
        return static::Client('/_sql/translate')
            ->withBody([
                'query'  => str_replace('`', '', $sql),
                'params' => $params,
            ]);
    }
    
    public static function Client(string $uri, $rawResults = false): Http
    {
        $connection = static::$connection;
        $conf = config("elasticsearch.{$connection}");
        $url = $conf['host'] . ':' . $conf['port'];
        $uri = trim($uri, '/');
        $http = Http::make('POST', "{$url}/{$uri}")
            ->withHeaders(['Content-Type' => 'application/json']);
        if (!$rawResults) {
            $http->decode();
        }
        return $http;
    }
    
    public static function get($index, $query): Http
    {
        $index = static::parseIndex($index);
        return static::Client("/{$index}/_search")
            ->withMethod('POST')
            ->withBody($query);
    }
    
    protected static function parseIndex($index, $disableKibanaDocs = true): string
    {
        if ($index === '*' && $disableKibanaDocs) {
            $index = '*,-.kibana*';
        }
        if (is_array($index)) {
            $index = implode(',', $index);
        }
        
        return $index;
    }
    
    public static function update($index, $id, $body): Http
    {
        return static::Client("/{$index}/_update/{$id}")
            ->withMethod('POST')
            ->withBody($body);
    }
    
    public static function create($index, $body, $id = null): Http
    {
        $url = "/{$index}/_doc";
        if ($id !== null) {
            $url .= "/{$id}";
        }
        
        return static::Client($url)
            ->withMethod('PUT')
            ->withBody($body);
    }
    
    public static function delete($index, $id): Http
    {
        return static::Client("/{$index}/_doc/{$id}")
            ->withMethod('DELETE');
    }
    
    public static function deleteByQuery($index, $body): Http
    {
        $index = static::parseIndex($index, false);
        return static::Client("/{$index}/_delete_by_query?conflicts=proceed")
            ->withMethod('POST')
            ->withBody($body);
    }
    
    public static function updateByQuery($index, $body): Http
    {
        $index = static::parseIndex($index, false);
        return static::Client("/{$index}/_update_by_query?conflicts=proceed")
            ->withMethod('POST')
            ->withBody($body);
    }
    
    public static function getDoc($index, $id): Http
    {
        $index = static::parseIndex($index, false);
        return static::Client("/{$index}/_doc/{$id}")
            ->withMethod('GET');
    }
    
    public static function ids($index, array $ids)
    {
        $index = static::parseIndex($index);
        $body = [
            'query' => [
                'ids' => [
                    'values' => $ids,
                ],
            ],
        ];
        return static::Client("/{$index}/_search")
            ->withMethod('GET')
            ->withBody($body);
    }
    
    public static function count($index, $body = null)
    {
        $index = static::parseIndex($index);
        if (empty($body)) {
            return static::Client("/{$index}/_count")
                ->withMethod('GET');
        }
        if (!array_key_exists('query', $body)) {
            $body = [
                'query' => $body,
            ];
        }
        return static::Client("/{$index}/_count")
            ->withMethod('GET')
            ->withBody($body);
    }
    
    public static function getIndexMapping($index)
    {
        return static::Client("/{$index}/_mapping")
            ->withMethod('GET')
            ->run();
    }

    public static function bulk(string $index, array $operations): Http
    {
        // Validate operations format
        if (empty($operations)) {
            throw new \InvalidArgumentException("Operations array cannot be empty for bulk request.");
        }

        // Serialize the operations into the bulk API format
        $body = "";
        foreach ($operations as $operation) {
            $body .= json_encode($operation) . "\n";
        }

        // Make the request
        return static::Client("/{$index}/_bulk")
            ->withMethod('POST')
            ->withBody($body);
    }
}
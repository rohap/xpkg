<?php

namespace Xpkg\Neo4j;

use Xpkg\Arrays\Arr;
use Xpkg\Arrays\Collection;
use Xpkg\Http\Http;

class Neo4j
{
    public static string $connection = 'main';
    protected static Http $client;
    
    public static function runCmd(Cypher $statement): ?string
    {
        $query = $statement->toQuery();
        $statements = static::generateStatements($query);
        return static::getClient()
            ->withBody($statements)
            ->runCmd();
    }
    
    public static function generateStatements($query): array
    {
        if (!is_array($query)) {
            $query = ['statement' => $query];
        }
        return [
            'statements' => [$query],
        ];
    }
    
    public static function getClient()
    {
        if (!empty(static::$client)) {
            return static::$client;
        }
        $connection = static::$connection;
        $config = config("neo4j.{$connection}");
        $url = "{$config['host']}:{$config['port']}/db/data/transaction/commit";
        static::$client = Http::make('POST', $url)
            ->withBasicAuthentication($config['username'], $config['password'])
            ->withHeaders(['Content-Type' => 'application/json']);
        return static::$client;
    }
    
    public static function getCmd(Cypher $statement): string
    {
        $query = $statement->toQuery();
        $statements = static::generateStatements($query);
        return static::getClient()
            ->withBody($statements)
            ->buildCmd();
    }
    
    /**
     * @param Cypher[] $cyphers
     */
    public static function runMulti(array $cyphers)
    {
        $statements = [];
        foreach ($cyphers as $cypher) {
            if ($cypher instanceof Cypher) {
                $statements[] = $cypher->toQuery();
            }
        }
        $statements = [
            'statements' => $statements,
        ];
        return static::runRaw($statements);
    }
    
    public static function runRaw(array $statements)
    {
        return static::getClient()
            ->withBody($statements)
            ->run();
    }
    
    public static function run(Cypher $statement)
    {
        $query = $statement->toQuery();
        $statements = static::generateStatements($query);
        return static::runRaw($statements);
    }
    
    public static function parseSimple($data)
    {
        if (is_array($data) && empty($data['results'])) {
            return $data;
        }
        if (is_string($data)) {
            $data = json_decode($data, true, 2048);
        }
        $resData = [];
        foreach ($data['results'] as $res) {
            $columns = Arr::get($res, 'columns', []);
            $results = Arr::get($res, 'data', []);
            foreach ($results as &$result) {
                foreach ($result['row'] as $k => &$item) {
                    $nodeID = Arr::get($result, "meta.{$k}.id");
                    if (is_int($nodeID) && is_array($item)) {
                        $item['nodeID'] = $result['meta'][$k]['id'];
                    }
                }
                $result = $result['row'];
                $result = array_combine($columns, $result);
            }
            $resData[] = $results;
        }
        return count($resData) > 1 ? $resData : reset($resData);
    }
    
    public static function clear()
    {
        static::$client = null;
        static::$connection = 'main';
    }
    
    public static function allLabels()
    {
        $q = static::generateStatements('CALL db.labels()');
        return static::runRaw($q);
    }
    
    public static function allRelationships()
    {
        $q = static::generateStatements('CALL db.relationshipTypes()');
        return collect(static::runRaw($q))->get('results.0.data.*.row.0');
    }
    
    public static function allPropertyKeys()
    {
        $q = static::generateStatements('CALL db.propertyKeys()');
        return static::runRaw($q);
    }
    
    public static function rawMetadata()
    {
        $all = static::raw('CALL db.labels() YIELD label return {name:"labels", data:COLLECT(label)} as res')
            ->union('ALL')
            ->raw('CALL db.propertyKeys() YIELD propertyKey return {name:"propertyKeys", data:COLLECT(propertyKey)} as res')
            ->union('ALL')
            ->raw('CALL db.relationshipTypes() YIELD relationshipType return {name:"relationshipTypes", data:COLLECT(relationshipType)} as res')
            ->decode()
            ->run();
        $all = collect($all)->collect('results.0.data');
        return [
            'labels'            => $all->get('0.row.0.data'),
            'propertyKeys'      => $all->get('1.row.0.data'),
            'relationshipTypes' => $all->get('2.row.0.data'),
        ];
    }
    
    public static function decode()
    {
        static::$client->decode();
    }
    
    public static function raw($query, array $params = [])
    {
        return Cypher::init()->raw($query)->addParams($params);
    }
    
    public static function match($query, array $params = [])
    {
        return Cypher::init()->match($query)->addParams($params);
    }
    
    public static function merge($query, array $params = [], $onCreate = '', $onMatch = '')
    {
        return Cypher::init()->merge($query, $onCreate, $onMatch)->addParams($params);
    }
    
    public static function getGraph($query, array $params = [])
    {
        return Cypher::init()->path($query)->addParams($params)->returnGraph();
    }
    
    public static function path($query, array $params = [])
    {
        return Cypher::init()->path($query)->addParams($params);
    }
    
    public static function create($label, $properties = []): Collection
    {
        $pairs = [];
        foreach ($properties as $key => $val) {
            if (is_array($val)) {
                $val = json_encode($val);
            }
            $pairs[] = "{$key}: '{$val}'";
        }
        $pairs = implode(',', $pairs);
        $query = "CREATE (n:{$label} {{$pairs}})";
        return collect(static::raw($query)->returnSimple()->run())->collect('0.n');
    }
}
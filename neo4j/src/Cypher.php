<?php

namespace Xpkg\Neo4j;

use Xpkg\Arrays\Arr;

class Cypher
{
    public $connection = 'main';
    protected $matches = [];
    protected $params = [];
    protected $return = [];
    protected $wheres = [];
    protected $orderBy = '';
    protected $tag = '';
    protected $limit = 0;
    protected $offset = 0;
    protected $delete = [];
    protected $includeStats = false;
    protected $resultData = ['ROW'];
    protected $set = [];
    protected $remove = [];
    protected $then = [];
    protected $rawResponse = false;
    
    public function path($query, $withReturn = true): static
    {
        $this->matches[] = 'MATCH p=' . $query;
        if ($withReturn) {
            $this->return('p');
        }
        return $this;
    }
    
    public function return(...$data): static
    {
        foreach ($data as $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            foreach ($value as $item => $as) {
                $this->return[] = is_int($item) ? "{$as}" : "{$item} AS {$as}";
            }
        }
        return $this;
    }
    
    public function merge($query, $onCreate = '', $onMatch = ''): static
    {
        if (!empty($this->matches)) {
            [$statement, $params] = $this->compile(false);
            $match = static::init()->raw($statement)->addParams($params);
        } else {
            $match = $this;
        }
        $q = "MERGE {$query}";
        if (!empty($onCreate)) {
            $q .= " ON CREATE {$onCreate}";
        }
        if (!empty($onMatch)) {
            $q .= " ON MATCH {$onMatch}";
        }
        $match = $match->raw($q);
        return $match;
    }
    
    public function compile($withReturn = true): array
    {
        $this->return = empty($this->return) ? ['*'] : $this->return;
        $match = implode(' ', $this->matches);
        $where = $this->compileWhere();
        if (stripos($match, 'return') !== false) {
            $withReturn = false;
        }
        $return = empty($this->return) || !$withReturn ? '' : 'RETURN ' . implode(',', $this->return);
        $orderBy = empty($this->orderBy) ? '' : "ORDER BY {$this->orderBy}";
        $offset = $this->offset > 0 ? "SKIP {$this->offset}" : '';
        $limit = $this->limit > 0 ? "LIMIT {$this->limit}" : '';
        $set = empty($this->set) ? '' : 'SET ' . implode(',', $this->set);
        $remove = empty($this->remove) ? '' : 'REMOVE ' . implode(',', $this->remove);
        $delete = empty($this->delete) ? '' : 'DETACH DELETE ' . implode(',', $this->delete);
        
        $final = "{$match} {$where} {$remove} {$set} {$delete} {$return} {$orderBy} {$offset} {$limit}";
        $final = preg_replace('/\s+/', ' ', $final);
        
        return [$final, $this->params];
    }
    
    protected function compileWhere(): string
    {
        $whereString = '';
        foreach ($this->wheres as $where) {
            $whereString .= $where['operator'] . ' ' . $where['query'] . ' ';
        }
        $whereString = preg_replace('/^(and|or)/i', '', $whereString);
        $whereString = trim($whereString);
        
        
        return empty($whereString) ? '' : "WHERE {$whereString}";
    }
    
    public function addParams(array $params): static
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    public function raw($query): static
    {
        $this->matches[] = $query;
        return $this;
    }
    
    public static function init(): static
    {
        return new static();
    }
    
    public function orWhere($field, $operator = null, $value = null): static
    {
        return $this->where($field, $operator, $value, 'OR');
    }
    
    public function where($field, $operator = null, $value = null, $type = 'AND'): static
    {
        if (is_null($operator) && is_null($value)) {
            return $this->whereHandler("{$field}", $type);
        }
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        if(is_object($value) && $value instanceof Raw) {
            $value = $value->__toString();
        } elseif(!is_int($value)) {
            $value = "'{$value}'";
        }
        return $this->whereHandler("{$field} {$operator} {$value}", $type);
    }
    
    protected function whereHandler($query, $operator = 'AND'): static
    {
        $this->wheres[] = compact('query', 'operator');
        return $this;
    }
    
    public function whereNull($field, $type = 'AND'): static
    {
        return $this->whereHandler("{$field} IS NULL", $type);
    }
    
    public function whereExists($field, $type = 'AND'): static
    {
        return $this->whereHandler("EXISTS({$field})", $type);
    }
    
    public function whereNotExists($field, $type = 'AND'): static
    {
        return $this->whereHandler("NOT EXISTS({$field})", $type);
    }
    
    public function whereNotNull($field, $type = 'AND'): static
    {
        return $this->whereHandler("{$field} IS NOT NULL", $type);
    }
    
    public function whereNotLabel($key, $label, $type = 'AND'): static
    {
        return $this->whereHandler("NOT {$key}:{$label}", $type);
    }
    
    public function whereNotIn($field, array $array, $type = 'AND'): static
    {
        return $this->whereIn("NOT {$field}", $array, $type);
    }
    
    public function whereIn($field, array $array, $type = 'AND'): static
    {
        if (empty($array)) {
            return $this;
        }
        foreach ($array as &$item) {
            if (!is_int($item)) {
                $item = "'{$item}'";
            }
        }
        $in = implode(',', $array);
        return $this->whereHandler("{$field} IN [{$in}]", $type);
    }
    
    public function whereRaw($query, $type = 'AND'): static
    {
        return $this->whereHandler($query, $type);
    }
    
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }
    
    public function delete(...$nodeRelation): static
    {
        foreach ($nodeRelation as $item) {
            $this->delete[] = $item;
        }
        return $this;
    }
    
    public function with($nodes): Cypher
    {
        [$statement, $params] = $this->compile(false);
        $nodes = implode(',', func_get_args());
        $statement .= " WITH ({$nodes})";
        return (new Cypher())->raw($statement)->addParams($params);
    }

    public function withSingleLine($nodes)
    {
        [$statement, $params] = $this->compile(false);
        $nodes = implode(',', func_get_args());
        $statement .= " WITH {$nodes}";
        return (new Cypher())->raw($statement)->addParams($params);
    }
    
    public function unwind($nodes): Cypher
    {
        if (!is_array($nodes)) {
            $nodes = func_get_args();
        }
        $q = [];
        foreach ($nodes as $node => $as) {
            if (!is_int($node)) {
                $q[] = "{$node} AS {$as}";
            } else {
                $q[] = "{$node}";
            }
        }
        $q = implode(',', $q);
        [$statement, $params] = $this->compile(false);
        $q = $statement . " UNWIND {$q}";
        return (new Cypher())->raw($q)->addParams($params);
    }
    
    public function orderBy($field, $direction = 'ASC'): static
    {
        $this->orderBy = "{$field} {$direction}";
        return $this;
    }
    
    public function union($type = 'ALL'): static
    {
        [$statement, $params] = $this->compile(false);
        $match = static::init()->raw($statement)->addParams($params);
        return $match->raw("UNION {$type}");
    }
    
    public function getNodesExcept(...$nodeNames): array
    {
        $nodes = $this->getNodes();
        $return = [];
        foreach ($nodes as $node) {
            if (in_array($node, $nodeNames)) {
                continue;
            }
            $return[] = $node;
        }
        return $return;
    }
    
    public function getNodes(): array
    {
        $nodes = [];
        foreach ($this->matches as $match) {
            preg_match_all('/\((\w+)(?:[^\)]+\)|\))/', $match, $m);
            $nodes = array_merge($nodes, $m[1] ?? []);
        }
        return $nodes;
    }
    
    public function includeStates(bool $stats = true): static
    {
        $this->includeStats = $stats;
        return $this;
    }
    
    public function returnRow(): static
    {
        return $this->resultDataContents(false, true, false);
    }
    
    public function resultDataContents(bool $graph = false, bool $row = false, bool $rest = false)
    {
        $data = [];
        $data[] = $graph == true ? 'GRAPH' : null;
        $data[] = $row == true ? 'ROW' : null;
        $data[] = $rest == true ? 'REST' : null;
        $data = array_values(array_filter($data));
        $this->resultData = $data;
        return $this;
    }
    
    public function all($debug = false)
    {
        $q = $this
            ->clearReturn()
            ->decode()
            ->returnSimple()
            ->withRelations()
            ->withNodesDetails()
            //->withLabels()
            ->then(function ($all) {
                return collect($all);
            });
        return $debug ? $q->getCmd() : $q->run();
    }
    
    public function then(callable $callback): static
    {
        Neo4j::getClient()->then($callback);
        return $this;
    }
    
    public function withNodesDetails(): static
    {
        $nodes = $this->getNodes();
        foreach ($nodes as $node) {
            $this->return("collect({nodeID:id($node),label:head(labels($node)),data:$node}) AS $node");
        }
        return $this;
    }
    
    public function withRelations(): static
    {
        $relations = $this->getRelationships();
        foreach ($relations as $rel) {
            $this->return(["head(collect({type: type({$rel}), id: id({$rel})}))" => $rel]);
        }
        return $this;
    }
    
    public function getRelationships(): array
    {
        $relations = [];
        foreach ($this->matches as $match) {
            preg_match_all('/\[(\w+)/', $match, $m);
            $relations = array_merge($relations, $m[1] ?? []);
        }
        return $relations;
    }
    
    public function returnSimple(): static
    {
        $this->then(function ($data) {
            return Neo4j::parseSimple($data);
        });
        return $this;
    }
    
    public function decode(): static
    {
        $this->then(function ($res) {
            if (!is_string($res)) {
                return $res;
            }
            $start = $res[0];
            $end = $res[strlen($res) - 1];
            if (in_array($start, ['{', '[']) && in_array($end, ['}', ']'])) {
                return json_decode($res, true, 2048);
            }
            return $res;
        });
        return $this;
    }
    
    public function clearReturn(): static
    {
        $this->return = [];
        return $this;
    }
    
    public function getCmd()
    {
        return Neo4j::getCmd($this);
    }
    
    public function run()
    {
        return Neo4j::run($this);
    }
    
    public function withLabels(): static
    {
        $nodes = $this->getNodes();
        foreach ($nodes as $node) {
            $this->return(["head(labels($node))" => "{$node}__label"]);
        }
        $this->then(function ($results) {
            foreach ($results as &$result) {
                $result = Arr::dot($result, notation: '__');
                $result = Arr::undot($result, '__');
            }
            return $results;
        });
        return $this;
    }
    
    public function returnGraph($withRow = false, $withRest = false): static
    {
        $this->then(function ($res) {
            if (is_array($res)) {
                $res = json_encode($res);
            }
            $replace = [
                '#"fbPicURL":#' => '"PicURL":',
            ];
            return preg_replace(array_keys($replace), array_values($replace), $res);
        });
        return $this->resultDataContents(true, $withRow, $withRest);
    }
    
    public function toQuery()
    {
        [$statement, $params] = $this->compile();
        $return = [
            'statement'          => trim($statement),
            'resultDataContents' => $this->resultData,
            'includeStats'       => $this->includeStats,
        ];
        if (!empty($params)) {
            $return['parameters'] = $params;
        }
        return $return;
    }
    
    public function set(string $node, array $data, bool $append = true)
    {
        $oper = $append ? '+=' : '=';
        $write = [];
        foreach ($data as $key => $value) {
            $value = is_array($value) ? json_encode($value) : $value;
            $write[] = "{$key}:'{$value}'";
        }
        $write = implode(',', $write);
        $this->set[] = "{$node} {$oper} {{$write}}";
        return $this;
    }
    
    public function optionalMatch($query)
    {
        [$statement, $params] = $this->compile(false);
        $match = static::init()->raw($statement)->addParams($params);
        return $match->raw('OPTIONAL')->match($query);
    }
    
    public function match($query): static
    {
        $q = is_array($query) ? implode(',', $query) : $query;
        if (str_starts_with($query, 'MATCH ')) {
            $this->matches[] = $q;
        } else {
            $this->matches[] = 'MATCH ' . $q;
        }
        return $this;
    }
    
    public function setLabels(string $node, array $labels)
    {
        foreach ($labels as $label) {
            $this->set[] = "{$node}:{$label}";
        }
        return $this;
    }
    
    public function removeLabels(string $node, array $labels)
    {
        foreach ($labels as $label) {
            $this->remove[] = "{$node}:{$label}";
        }
        return $this;
    }
    
    public function remove($node, $properties = [])
    {
        if (strpos($node, '.') !== false) {
            $this->remove[] = $node;
        } else {
            $properties = (array)$properties;
            foreach ($properties as $property) {
                $this->remove[] = "{$node}.{$property}";
            }
        }
        return $this;
    }
    
    public function cmd()
    {
        return Neo4j::runCmd($this);
    }
}
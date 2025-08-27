<?php

namespace Xpkg\Elasticsearch;

use Closure;
use Xpkg\Arrays\Arr;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\FuzzyQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\IdsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RegexpQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use stdClass;
use Throwable;

class EsBuilder extends Builder
{
    public $operators = [
        '=', 'lt', 'lte', 'gt', 'gte', '<>', '!=',
        'like', 'not like', 'regexp', 'regex', 'not regex', 'not regexp',
        'ilike', 'not ilike', 'match', 'fuzzy', 'in', 'not in',
        'exists', 'not exists', 'range',
    ];
    public Search $search;
    public BoolQuery $boolQuery;
    protected array $aggregation = [];
    protected array $excludes = [];
    protected array $includes = [];
    protected bool $source = true;
    protected array $bool = [
        'and'      => BoolQuery::MUST,
        'or'       => BoolQuery::SHOULD,
        'not'      => BoolQuery::MUST_NOT,
        'must'     => BoolQuery::MUST,
        'must_not' => BoolQuery::MUST_NOT,
        'should'   => BoolQuery::SHOULD,
    ];
    protected int $minimumShouldMatch = 0;
    /** @var Closure $beforeCallable */
    public static $beforeCallable = null;

    public static function beforeQuery($callable)
    {
        static::$beforeCallable = $callable;
    }

    public function __construct(\Illuminate\Database\Query\Builder $query)
    {
        $this->search = new Search();
        $this->boolQuery = new BoolQuery();
        parent::__construct($query);
    }
    
    public function source($returnSource = true): static
    {
        if (!$returnSource) {
            $this->search->setSource($returnSource);
        }
        return $this;
    }
    
    public function minimumShouldMatch(int $min): static
    {
        $this->minimumShouldMatch = $min;
        return $this;
    }
    
    public function whereNotIn($column, $values, $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }
    
    public function whereIn($column, $values, $boolean = 'and', $not = false): static
    {
        if ($not) {
            $boolean = 'not';
        }
        return $this->where($column, 'in', $values, $boolean);
    }
    
    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        $boolean = $this->bool[strtolower($boolean)] ?: 'and';
        if (in_array($boolean, ['or', 'should'])) {
            $this->minimumShouldMatch = 1;
        }
        
        if (is_object($column)) {
            $this->boolQuery->add($operator, $boolean);
            return $this;
        }
        
        $operator = strtolower($operator);
        $operator = match ($operator) {
            '<' => 'lt',
            '>' => 'gt',
            '<=' => 'lte',
            '>=' => 'gte',
            default => $operator
        };
        if (!in_array($operator, $this->operators) && is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        
        if (str_starts_with($operator, 'not ')) {
            $operator = str_replace('not ', '', $operator);
            return $this->where($column, $operator, $value, 'not');
        } elseif (in_array($operator, ['!=', '<>'])) {
            return $this->where($column, '=', $value, 'not');
        }
        
        if (is_string($value) && str_contains($value, '*') && !in_array($operator, ['like', 'ilike'])) {
            return $this->where($column, 'like', $value, $boolean);
        }
        
        $query = match ($operator) {
            'regex', 'regexp' => new RegexpQuery($column, $value),
            'like', 'ilike' => new WildcardQuery($column, $value),
            '=' => new TermQuery($column, $value),
            'in' => new TermsQuery($column, (array)$value),
            'match' => new MatchQuery($column, $value),
            'lt', 'gt', 'lte', 'gte' => new RangeQuery($column, [$operator => $value]),
            'range' => new RangeQuery($column, $value),
            'fuzzy' => new FuzzyQuery($column, $value),
            'exists' => new ExistsQuery($column),
        };
        
        $this->boolQuery->add($query, $boolean);
        return $this;
    }
    
    public function whereNotBetween($column, iterable $values, $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }
    
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): static
    {
        if ($not) {
            $boolean = 'not';
        }
        return $this->where($column, 'range', $values, $boolean);
    }
    
    public function whereNotExists($column, $boolean = 'and'): static
    {
        return $this->whereExists($column, $boolean, true);
    }
    
    public function whereExists($column, $boolean = 'and', $not = false): static
    {
        if ($not) {
            $boolean = 'not';
        }
        return $this->where($column, 'exists', null, $boolean);
    }
    
    public function whereAll($term): static
    {
        $this->boolQuery->add(new SimpleQueryStringQuery($term));
        return $this;
    }
    
    public function addSearch(Closure $search): static
    {
        $search($this->search);
        return $this;
    }
    
    public function addAggregation(Closure $aggregation): static
    {
        $aggregation($this->search);
        return $this;
    }
    
    public function addFilters(array $filters = []): static
    {
        foreach ($filters as $key => $values) {
            if (empty($values)) continue;
            $this->whereIn($key, (array)$values);
        }
        return $this;
    }
    
    public function ids(array $ids = []): static
    {
        $this->search->addQuery(new IdsQuery($ids));
        return $this;
    }
    
    public function addQuery(BuilderInterface $query, $boolType = BoolQuery::MUST): static
    {
        $this->search->addQuery($query, $boolType);
        return $this;
    }
    
    public function orderBy($column, $direction = 'asc'): static
    {
        $this->search->addSort(new FieldSort($column, $direction));
        return $this;
    }
    
    public function ddd()
    {
        $this->get(true);
    }
    
    public function get($debug = false)
    {
        if(!empty(static::$beforeCallable)) {
            $fn = static::$beforeCallable;
            $fn($this);
        }
        $index = $this->getModel()->getIndex();
        $query = $this->compile();
        try {
            $results = ES::get($index, $query)->run($debug);
            $class = get_class($this->getModel());
            $results = call_user_func([$class, 'fillFromRawResults'], $results);
            return $results;
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    public function compile(): array
    {
        $limit = is_int($this->query->limit) ? $this->query->limit : 10000;
        $this->search->setSize($limit);
        $include = $this->query->columns ?? ['*'];
        $query = $this->uniteSearchBool();
        if (count($include) > 0 && !in_array('*', $include)) {
            Arr::set($query, '_source.includes', $include);
        }
        if (!empty($this->excludes)) {
            Arr::set($query, '_source.excludes', $this->excludes);
        }
        return $query;
    }
    
    protected function uniteSearchBool(): array
    {
        $bool = $this->boolQuery->toArray();
        if (!isset($bool['bool']) || !($bool['bool'] instanceof stdClass)) {
            $this->boolQuery->addParameter('minimum_should_match', $this->minimumShouldMatch);
            $this->boolQuery->addParameter('boost', 1.0);
            $this->search->addQuery($this->boolQuery);
        }
        return $this->search->toArray();
    }
    
    public function update($values = [])
    {
        $id = $this->getModel()->getDocumentID();
        $index = $this->getModel()->getIndex();
        try {
            $script = $this->getModel()->getScript();
            if (!empty($script->getSource())) {
                $values = $script;
            }
        } catch (Throwable) {
        }
        $wheres = $this->uniteSearchBool();
        if (empty($index) || (empty($id) && empty($wheres))) {
            throw new InvalidArgumentException('Update must be provided with _index');
        }
        if (!empty($wheres) && is_array($values) && !empty($values) && empty($id)) {
            $values = (new Script())->fromArray($values);
        }
        if (empty($id) && !empty($wheres) && $values instanceof Script && !empty($values->getSource())) {
            return $this->updateByQuery($values);
        }
        
        $body = [
            "doc"           => $values,
            "doc_as_upsert" => true,
        ];
        if ($values instanceof Script) {
            $body = [
                "script" => $values->getScript(),
                "upsert" => [
                    "counter" => 1,
                ],
            ];
        }
        try {
            $res = ES::update($index, $id, $body)->run();
            return $res;
        } catch (\Throwable $e) {
            return [];
        }
        
    }
    
    public function updateByQuery(Script $script, $debug = false)
    {
        $index = $this->getModel()->getIndex();
        $query = $this->compile();
        $body = Arr::only($query, ['query']);
        $body['script'] = $script->getScript();
        try {
            $res = ES::updateByQuery($index, $body)->run($debug);
            $this->getModel()->fireEvent('updatedByQuery');
            return $res;
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    public function insert(array $values)
    {
        $index = Arr::pull($values, '_index', $this->getModel()->getIndex());
        $id = Arr::pull($values, '_id', $this->getModel()->getDocumentID());
        if (empty($id)) {
            $id = null;
        }
        $values['profileID'] = "$id";
        if (empty($index)) {
            throw new InvalidArgumentException('Index must be set for creating new document');
        }
        try {
            return ES::create($index, $values, $id)->run();
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    public function delete()
    {
        $index = $this->getModel()->getIndex();
        $id = $this->getModel()->getDocumentID();
        $query = $this->compile();
        $query = Arr::only($query, ['query']);
        if (empty($index) || (empty($id) && empty($query))) {
            throw new InvalidArgumentException('Delete must be provided with _index');
        }
        if (empty($id) && !empty($query)) {
            return $this->deleteByQuery();
        }
        try {
            $res = ES::delete($index, $id)->run();
            return $res;
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    public function deleteByQuery($debug = false)
    {
        $model = $this->getModel();
        $index = $model->getIndex();
        $query = $this->compile();
        $body = Arr::only($query, ['query']);
        try {
            $res = ES::deleteByQuery($index, $body)->run($debug);
            $model->fireEvent('deletedByQuery');
            return $res;
        } catch (\Throwable $e) {
            return [];
        }

    }
    
    public function exclude($columns): static
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->excludes = $columns;
        return $this;
    }
    
    public function count($columns = '*')
    {
        $index = $this->getModel()->getIndex();
        $this->limit(0);
        $query = $this->compile();
        $body = Arr::only($query, ['query']);
        $count = ES::count($index, $body)->run();
        return Arr::get($count, 'count', 0);
    }
    
    public function table($table): static
    {
        $this->getModel()->setTable($table);
        return $this;
    }
}
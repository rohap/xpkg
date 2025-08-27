<?php


namespace Xpkg\Elasticsearch;

use Carbon\Carbon;
use Xpkg\Arrays\Arr;
use Xpkg\Arrays\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EsModel extends Model
{
    public static Collection $aggs;
    public int $_total = 0;
    public string $_totalRelation = 'eq';
    public int|string|null $_id = null;
    public Script $script;
    protected EsBuilder $builder;
    protected $primaryKey = '_id';
    
    public static function fillFromRawResults($results)
    {
        $instance = new static();
        $total = Arr::get($results, 'hits.total', ['value' => 0, 'relation' => 'eq']);
        $aggs = Arr::get($results, 'aggregations', []);
        if (!empty($aggs)) {
            $instance::$aggs = collect($aggs);
        }
        return $instance->newCollection(array_map(function ($item) use ($instance, $total, $aggs) {
            $i = $instance->newFromBuilder(Arr::get($item, '_source', []));
            $i->setTable($item['_index']);
            $i->_id = $item['_id'];
            $i->_total = (int)$total['value'];
            $i->_totalRelation = $total['relation'];
            if (empty($i::$aggs)) {
                $i::$aggs = collect($aggs);
            }
            return $i;
        }, $results['hits']['hits']));
    }
    
    public static function find($ids)
    {
        $ids = (array)$ids;
        return static::whereIn('_id', $ids)->get();
    }
    
    public function newEloquentBuilder($query): EsBuilder
    {
        $this->builder = new EsBuilder($query);
        return $this->builder;
    }
    
    public function addScript(Script $script): static
    {
        $this->script = $script;
        return $this;
    }
    
    public function getDocumentID(): int|string|null
    {
        return $this->_id;
    }
    
    public function getScript(): Script
    {
        return $this->script instanceof Script ? $this->script : new Script();
    }
    
    public function aggs(): array
    {
        return static::$aggs;
    }
    
    public function fireEvent($event, $halt = true)
    {
        preg_match('/\w+$/', get_class($this), $match);
        $modelName = array_pop($match);
        event("es.{$event}", $this);
        event("es.{$this->getIndex()}.{$event}", $this);
        event("es.{$modelName}.{$event}", $this);
        return $this->fireModelEvent($event, $halt);
    }
    
    public function getIndex(): string
    {
        if(empty($this->table)) {
            $connection = ES::$connection;
            $conf = config("elasticsearch.{$connection}");
            return $conf['defaultIndex'];
        }
        return $this->table;
    }
    
    public function update(array $attributes = [], array $options = [])
    {
        $attributes['updated_at'] = Carbon::now()->toDateTimeString();
        if (!empty($this->script)) {
            $query = $this->newModelQuery();
            if ($this->exists) {
                $this->setKeysForSaveQuery($query)->update($attributes);
                $this->syncChanges();
            } else {
                $this->performInsert($query);
            }
            $this->syncOriginal();
        }
        return parent::update($attributes, $options);
    }
    
    protected function performInsert(Builder $query): bool
    {
        $attributes = $this->getAttributes();
        $now = Carbon::now()->toDateTimeString();
        $attributes['created_at'] = $now;
        $attributes['updated_at'] = $now;
        $this->builder->insert($attributes);
        $this->exists = true;
        
        $this->wasRecentlyCreated = true;
        
        $this->fireModelEvent('created', false);
        
        return true;
    }
}
<?php

namespace Xpkg\RuleEngine\Models;


use Xpkg\Arrays\Arr;
use Illuminate\Database\Eloquent\Model;
use Xpkg\RuleEngine\TemplateTrait;

class Rules extends Model
{
    public $timestamps = true;
    protected $table = 'rules';
    protected array $originalData = [];
    protected array $nodes = [];
    protected array $actions = [];
    protected array $operators = [];
    protected array $then = [];
    protected array $queries = [];
    protected array $replace = [];

    public function parse(array $data): array
    {
        $this->originalData = $this->cleanOrphans($data);
        $this->splitNodes();
        $this->nodesToQuery();
        $this->simplifyOperators();

        foreach ($this->operators as $k => &$operator) {
            $operator = $this->operatorToQuery($operator);
            if (is_string($operator)) {
                $this->queries[$k] = $operator;
                $operator = null;
            }
        }
        $this->operators = array_filter($this->operators);

        for ($i = 0; $i < 10; $i++) {
            $this->mergeOperators();
            foreach ($this->operators as $k => &$operator) {
                $operator['haveIds'] = !empty(array_filter($operator['inputs'], 'is_numeric'));
                $q = $this->operatorToQuery($operator);
                if (is_string($q)) {
                    $this->queries[$k] = $q;
                    $operator = null;
                }
            }
            $this->operators = array_filter($this->operators);
        }
        $query = Arr::get($this->then, 'inputs.input_1.connections.0.node');
        $query = empty($this->queries) ? $query = Arr::first($this->nodes, default: '') : $query = $this->queries[$query];

        $actions = [];
        foreach ($this->actions as $action) {
            try {
                $actions[] = Action::parse($action);
            } catch (\Throwable $e) {
                // Do Nothing
            }
        }

        return [
            'when' => $query,
            'then' => $actions,
        ];
    }


    public function operatorToQuery($operator)
    {
        if (!$operator['haveIds'] || !array_key_exists('haveIds', $operator)) {
            $op = strtolower($operator['name']) === 'or' ? ' || ' : ' && ';
            $operator = '(' . implode($op, $operator['inputs']) . ')';
        }
        return $operator;
    }

    public function mergeOperators(): void
    {
        foreach ($this->operators as &$operator) {
            $operator['haveIds'] = !empty(array_filter($operator['inputs'], 'is_numeric'));
            foreach ($operator['inputs'] as &$input) {
                if (isset($this->queries[$input])) {
                    $input = $this->queries[$input];
                }
            }
        }
    }

    public function parseTemplateString($values, $fact = null)
    {
        array_walk_recursive($values, function (&$v) use ($fact) {
            if (!is_numeric($v) && is_string($v)) {
                preg_match_all('/\{\{([^\{\}]+)\}\}/', $v, $m);
                if (!empty($m[1])) {
                    foreach ($m[1] as &$match) {
                        $key = str($match)
                            ->explode('.')
                            ->map(fn($v) => $v)
                            ->toArray();

                        if($fact === null) {
                            $key[0] = str("{$key[0]}")->studly()->value();
                        }
                        $key = implode('.', $key);
                        $key = "{$fact}.{$key}";
                        $v = str_replace($match, $key, $v);
                    }
                }
                $v = '"'.$v.'"';
                $v = str_replace(['{{', '}}'], ['"+', '+"'], $v);
                $v = str_replace(['""+', '+""'], '', $v);
            }
        });
        return $values;
    }

    public function simplifyOperators(): void
    {
        foreach ($this->operators as &$operator) {
            $name = $operator['name'];
            $inputs = Arr::get($operator, 'inputs.input_1.connections.*.node');
            $haveIds = false;
            foreach ($inputs as &$input) {
                if (isset($this->nodes[$input])) {
                    $input = $this->nodes[$input];
                } else {
                    $haveIds = true;
                }
            }
            $operator = [
                'name'    => $name,
                'inputs'  => $inputs,
                'haveIds' => $haveIds,
            ];
        }
    }

    public function nodesToQuery(): void
    {
        foreach ($this->nodes as &$node) {
            $data = $node['data'];
            $name = strtolower($node['name']);
            $nodeName = "{$name}";
            $key = "{$nodeName}.{$data['field']}";
            $values = (array)$data['values'];
            $values = $this->parseTemplateString($values, $nodeName);
            $node = $this->translateOperator($key, $data['function'], $values);
        }
    }

    public function splitNodes(): void
    {
        foreach ($this->originalData as $k => $node) {
            $data = $node['data'];
            $class = $node['class'];
            if ($class == 'action') {
                $this->actions[$k] = $node;
            } elseif ($class == 'operator' && $data['function'] == 'then') {
                $this->then = $node;
            } elseif ($class == 'operator') {
                $this->operators[$k] = $node;
            } else {
                $this->nodes[$k] = $node;
            }
        }
    }

    public function cleanOrphans($nodes): array
    {
        $ret = [];
        foreach ($nodes as $id => $node) {
            $outputs = Arr::get($node, 'outputs.output_1.connections', []);
            $inputs = Arr::get($node, 'inputs.input_1.connections', []);
            if (empty($outputs) && empty($inputs)) {
                continue;
            }
            $ret[$id] = $node;
        }
        return $ret;
    }

    public function translateOperator($key, $op, array $values): string
    {
        $key = str($key)
            ->explode('.')
            ->map(fn($v, $k) => $k > 0 ? str($v)->studly()->value() : $v)
            ->implode('.');

        $firstValue = Arr::first($values);

        $rulesClass = config('rules.model.rules');
        if(method_exists($rulesClass, $op)) {
            return call_user_func_array([$rulesClass, $op], [$key, $values]);
        }

        return match ($op) {
            'eq' => "{$key} == {$firstValue}",
            'neq' => "{$key} != {$firstValue}",
            'lt' => "{$key} < {$firstValue}",
            'gt' => "{$key} > {$firstValue}",
            'lte' => "{$key} <= {$firstValue}",
            'gte' => "{$key} >= {$firstValue}",
            'contain' => "{$key}.Contains({$firstValue})",
            'not_contain' => "!{$key}.Contains({$firstValue})",
            'in' => $this->in($key, $values),
            'except' => '!' . $this->in($key, $values),
            'between' => $this->between($key, $values),
            'in_area' => $this->in_area($key, $values),
            'out_area' => $this->out_area($key, $values),
            default => "{$key} {$op} {$values}"//$this->$op($key, $values)
        };
    }

    protected function in($key, array $values): string
    {
        $values = array_map('trim', $values);
        $values = implode(' ', array_values($values));
        return "{$key}.In({$values})";
    }

    protected function between($key, $values): string
    {
        $values = array_map('trim', $values);
        if (count($values) !== 2) {
            return '';
        }
        $values = array_values($values);
        $start = $values[0];
        $end = $values[1];
        return "({$key} >= {$start} && {$key} <= {$end})";
    }

    protected function in_area($key, $values): string
    {
        $topLeftLat = $values['latitudeTopLeft'];
        $topLeftLan = $values['longitudeTopLeft'];
        $topRightLat = $values['latitudeTopRight'];
        $topRightLan = $values['longitudeTopRight'];
        $bottomLeftLat = $values['latitudeBottomLeft'];
        $bottomLeftLan = $values['longitudeBottomLeft'];
        $bottomRightLat = $values['latitudeBottomRight'];
        $bottomRightLan = $values['longitudeBottomRight'];

        return '';
    }

    protected function out_area($key, $values): string
    {
        $lat = $values['latitude'];
        $lat = $values['longitude'];
        $lat = $values['range_km'];
        return '';
    }
}
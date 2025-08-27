<?php

namespace Xpkg\Elasticsearch;

class Script
{
    protected string $source = '';
    protected string $lang = 'painless';
    protected array $params = [];
    protected array $attributes = [];
    protected array $remove = [];

    
    public function fromArray(array $arr): static
    {
        return $this->set($arr);
    }
    
    public function set(string|array $key, $value = null)
    {
        if (!is_array($key)) {
            $key = [$key => $value];
        }
        foreach ($key as $k => $v) {
            $this->attributes[$k] = $v;
        }
        return $this;
    }

    public function remove(string|array $field)
    {
        $field = (array)$field;
        foreach ($field as $item) {
            $this->remove[] = $item;
        }
        return $this;
    }
    
    public function getScript(): array
    {
        $source = $this->getSource();
        $source = str_replace(["\r", "\n", "\t"], '', $source);
        $source = preg_replace('/\s+/', ' ', $source);
        return array_filter([
            'source' => $source,
            'lang'   => $this->getLang(),
            'params' => $this->getParams(),
        ]);
    }
    
    public function getSource(): string
    {
        return empty($this->source)
            ? $this->buildSource()
            : $this->source;
    }
    
    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }
    
    protected function buildSource()
    {
        $ctx = '';
        foreach ($this->attributes as $key => $value) {
            $value = is_array($value) ? json_encode($value) : $value;
            $ctx .= "ctx._source.{$key} = \"{$value}\";";
        }
        foreach ($this->remove as $item) {
            $ctx = "ctx._source.remove(\"{$item}\");";
        }
        return $ctx;
    }
    
    public function getLang(): string
    {
        return $this->lang;
    }
    
    public function setLang(string $lang): static
    {
        $this->lang = $lang;
        return $this;
    }
    
    public function getParams(): array
    {
        return $this->params;
    }
    
    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function addToArray($key, $value, $unique = true)
    {
        $this->setSource("
            if(!ctx._source.containsKey(\"{$key}\")){
                ctx._source[\"{$key}\"]=[]
            }
            ctx._source.{$key}.add(\"{$value}\");
            ctx._source.{$key}=ctx._source.{$key}.stream().distinct().sorted().collect(Collectors.toList())"
        );
        return $this;
    }

    public function removeFromArray($key, $value)
    {
        $this->setSource("
            for (int i=ctx._source.{$key}.length-1; i>=0; i--) {
                if(ctx._source.{$key}[i] == params.param1) {
                    ctx._source.{$key}.remove(i);
                }
            }
        ");
        $this->setParams(['param1' => $value]);
         return $this;
    }
}
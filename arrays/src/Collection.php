<?php

namespace Xpkg\Arrays;

use stdClass;

class Collection extends \Illuminate\Support\Collection
{
    public function __construct($items = [])
    {
        if (is_string($items)) {
            $items = json_decode($items, 1);
        }
        parent::__construct($items);
    }
    
    public function collect()
    {
        $keys = func_get_args();
        if (empty($keys)) {
            return new static();
        }
        $key = $keys[0];
        $default = empty($keys[1]) ? null : $keys[1];
        $res = $this->get($key, $default);
        if (is_object($res)) {
            return $res;
        }
        return new static($res);
    }
    
    public function get($key, $default = null, $caseSensitive = true)
    {
        return Arr::get($this->items, $key, $default, $caseSensitive);
    }
    
    public function first(callable $callback = null, $default = null)
    {
        $res = Arr::first($this->items, $callback, $default);
        if (is_object($res)) {
            if (method_exists($res, 'toArray')) {
                return collect($res->toArray());
            }
            if ($res instanceof stdClass) {
                return collect((array)$res);
            }
            return $res;
        }
        if (is_array($res)) {
            return new static($res);
        }
        return $res;
    }
    
    public function forgetRecursive($keys): static
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $this->forget($keys);
        foreach ($this->items as &$value) {
            if (is_array($value)) {
                Arr::forgetRecursive($value, $keys);
            }
        }
        return $this;
    }
    
    public function forget($keys): static
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        
        foreach ($keys as $key) {
            $this->offsetUnset($key);
        }
        
        return $this;
    }
    
    public function offsetUnset($key): void
    {
        Arr::forget($this->items, $key);
    }
    
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }
    
    public function has($key): bool
    {
        return Arr::has($this->items, $key);
    }
    
    public function keyByAndFetch($key, $fetch): static
    {
        return new static(Arr::keyByAndFetch($this->items, $key, $fetch));
    }
    
    public function trim($charlist): static
    {
        return new static(Arr::trim($this->items, $charlist));
    }
    
    public function set($key, $value): static
    {
        Arr::set($this->items, $key, $value);
        return $this;
    }
    
    public function column($key): static
    {
        if ($this->isEmpty()) {
            return new static([]);
        }
        $results = Arr::column($this->items, $key);
        return new static($results);
    }
    
    public function dot($prepend = '', $notation = '.'): static
    {
        $items = Arr::dot($this->items, $prepend, $notation);
        return collect($items);
    }
    
    public function undot($notation = '.'): static
    {
        $items = Arr::undot($this->items, $notation);
        return collect($items);
    }

    public function flattenWithKeys(array $array): array
    {
        return collect(Arr::flattenWithKeys($array));
    }
}
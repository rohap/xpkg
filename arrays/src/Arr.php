<?php

namespace Xpkg\Arrays;

class Arr extends \Illuminate\Support\Arr
{
    
    public static function trim($array, $chars = ' ')
    {
        $chars = is_array($chars) ? implode('', $chars) : $chars;
        array_walk_recursive($array, function ($value) use ($chars) {
            return is_string($value) ? trim($value, $chars) : $value;
        });
        return $array;
    }
    
    public static function groupBy($array, $groupBy, $default = null): array
    {
        if (is_array($groupBy)) {
            $nextGroups = $groupBy;
            $groupBy = array_shift($nextGroups);
        }
        
        $groupBy = function ($item) use ($groupBy) {
            return static::get($item, $groupBy);
        };
        
        $results = [];
        
        foreach ($array as $key => $value) {
            $groupKeys = $groupBy($value, $key);
            if (!is_array($groupKeys)) {
                $groupKeys = static::wrap($groupKeys);
            }
            
            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int)$groupKey : $groupKey;
                
                if (!array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = $default;
                }
                
                $results[$groupKey][] = $value;
            }
        }
        
        if (!empty($nextGroups)) {
            return static::groupBy($array, $nextGroups);
        }
        
        return $results;
    }
    
    public static function get($array, $key, $default = null, $caseSensitive = true)
    {
        $array = collect($array)->toArray();
        if (!$caseSensitive) {
            $array = static::changeKeyCaseRecursive($array, CASE_LOWER);
            $key = strtolower($key);
        }
        
        if (static::exists($array, $key)) {
            return $array[$key];
        }
        
        return data_get($array, $key, $default);
    }
    
    public static function changeKeyCaseRecursive($array, $case = CASE_LOWER): array
    {
        $array = collect($array)->toArray();
        if (!in_array($case, ['upper', 'lower', CASE_LOWER, CASE_UPPER])) {
            return $array;
        }
        $caseToConvert = $case === 'upper' || $case === 1 ? CASE_UPPER : CASE_LOWER;
        
        $array = array_change_key_case($array, $caseToConvert);
        array_walk_recursive($array, function ($val) use ($caseToConvert) {
            if (is_array($val)) {
                return array_change_key_case($val, $caseToConvert);
            }
            return $val;
        });
        return $array;
    }
    
    public static function keyBy($array, $keyBy): array
    {
        $keyBy = function ($item) use ($keyBy) {
            return static::get($item, $keyBy);
        };
        $results = [];
        foreach ($array as $key => $item) {
            $resolvedKey = $keyBy($item, $key);
            $results[$resolvedKey] = $item;
        }
        return $results;
    }
    
    public static function keyByAndFetch($array, $key, $fetchKey): array
    {
        $result = [];
        foreach ($array as $itemArr) {
            $key_value = !is_string($key) && is_callable($key) ? $key($itemArr) : static::get($itemArr, $key);
            $fetch_value = static::get($itemArr, $fetchKey);
            $result[$key_value] = $fetch_value;
        }
        return $result;
    }
    
    public static function renameKeys(array $array, array $pairs): array
    {
        foreach ($pairs as $originalKey => $newKey) {
            if (str_contains($originalKey, '.')) {
                $array = static::renameKeyByRegex($array, $originalKey, $newKey);
            } else {
                Arr::set($array, $newKey, Arr::get($array, $originalKey));
                Arr::forget($array, $originalKey);
            }
        }
        
        return $array;
    }
    
    public static function renameKeyByRegex(array $array, string $originalKey, string $newKey): array
    {
        $tmp = static::dot($array);
        $original = str_replace(['.', '*'], ['\.', '.*'], $originalKey);
        $tmp = array_intersect_key($tmp, array_flip(preg_grep("/{$original}/", array_keys($tmp))));
        foreach ($tmp as $key => $val) {
            $oKey = $key;
            $key = explode('.', $key);
            array_pop($key);
            $key[] = $newKey;
            Arr::set($array, $key, $val);
            Arr::forget($array, $oKey);
        }
        return $array;
    }
    
    public static function dot($array, $prepend = '', $notation = '.'): array
    {
        $array = collect($array)->toArray();
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . $notation, $notation));
            } else {
                $results[$prepend . $key] = is_array($value) ? [] : $value;
            }
        }
        return $results;
    }
    
    public static function set(&$array, $key, $value, $notation = '.')
    {
        if ($key === null) {
            $array[] = $value;
            return $array;
        }
        
        $keys = is_array($key) ? $key : explode($notation, $key);
        
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            
            $array = &$array[$key];
        }
        
        $array[array_shift($keys)] = $value;
        
        return $array;
    }
    
    public static function forget(&$array, $keys)
    {
        $useDot = false;
        $keys = (array)$keys;
        foreach ($keys as $key) {
            if (str_contains($key, '*')) {
                $useDot = true;
                break;
            }
        }
        
        if ($useDot) {
            return static::forgetRegex($array, $keys);
        }
        
        $array = collect($array)->toArray();
        $original = &$array;
        $keys = (array)$keys;
        if (count($keys) === 0) {
            return;
        }
        
        
        foreach ($keys as $key) {
            if (static::exists($array, $key)) {
                unset($array[$key]);
                
                continue;
            }
            
            $parts = explode('.', $key);
            $array = &$original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }
    
    public static function forgetRegex(&$array, $keys)
    {
        $array = static::dot($array);
        
        foreach ($keys as $key) {
            $pattern = str_replace(['.', '*'], ['\.', '.*'], $key);
            $dataKeys = static::keys($array);
            $keysToForget = preg_grep("/{$pattern}/", $dataKeys);
            foreach ($keysToForget as $item) {
                unset($array[$item]);
            }
        }
        $array = static::undot($array);
    }
    
    public static function keys($array): array
    {
        $array = collect($array)->toArray();
        return array_keys($array);
    }
    
    public static function undot($array, $notation = '.'): array
    {
        $array = collect($array)->toArray();
        $return = [];
        foreach ($array as $key => $value) {
            static::set($return, $key, $value, $notation);
        }
        return $return;
    }
    
    public static function column($array, $key)
    {
        if (is_object($array) && method_exists($array, 'toArray')) {
            $array = $array->toArray();
        }
        if (!is_array($array) || count($array) === 0) {
            return $array;
        }
        
        return array_column($array, $key);
    }
    
    public static function forgetRecursive(&$array, $keys)
    {
        $array = collect($array)->toArray();
        static::forget($array, $keys);
        foreach ($array as &$value) {
            if (is_array($value)) {
                static::forgetRecursive($value, $keys);
            }
        }
    }

    public static function flattenWithKeys(array $array): array
    {
        $flatten = [];
        array_walk_recursive($array, function ($value, $key) use (&$flatten) {
            $flatten[$key] = $value;
        });
        return $flatten;
    }
}
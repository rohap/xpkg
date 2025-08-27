<?php

namespace Xpkg\RuleEngine;

use Xpkg\Arrays\Arr;

abstract class BaseActions
{
    public array $struct = [];
    protected array $pairStruct = [];

    /**
     * @param array $data
     * @return array{action:string,log:string}
     */
    abstract public function handle(array $data): string;

    public function __construct()
    {
        $this->pairStruct = Arr::keyByAndFetch($this->struct, 'name', 'fieldsEmbed');
    }

    public function filter(array $data): array
    {
        $ret = [];
        $struct = Arr::keyBy($this->struct, 'name');
        foreach ($struct as $k => $item) {
            if(isset($data[$k])) {
                $ret[$k] = $data[$k] ?? '';
            }
        }

        return $ret;
    }

    public function parseTemplate($str)
    {
        if (!is_numeric($str) && is_string($str)) {
            $str = str_replace(['%7B', '%7D'], ['{', '}'], $str);
            preg_match_all('/\{\{([^\{\}]+)\}\}/', $str, $m);
            if (!empty($m[1])) {
                foreach ($m[1] as &$match) {
                    $key = str($match)
                        ->explode('.')
                        ->map(fn($v) => ucfirst($v))
                        ->toArray();

                    $key[0] = "{$key[0]}Fact";
                    $key = implode('.', $key);
                    $str = str_replace($match, $key, $str);
                }
            }
            $str = '"'.$str.'"';
            $str = str_replace(['{{', '}}'], ['"+', '+"'], $str);
            $str = str_replace(['""+', '+""'], '', $str);
        }
        return $str;
    }
}
<?php

namespace Xpkg\Geo;

use Xpkg\Arrays\Collection;

class Countries
{
    protected static Collection $countries;
    
    public static function search($iso = null)
    {
        static::initialize();
        if (is_null($iso)) {
            return static::$countries->values();
        }
        if (is_numeric($iso)) {
            return static::$countries->keyBy('phone')->get($iso, [], false);
        }
        if (strlen($iso) === 2) {
            return static::$countries->keyBy('iso2')->get($iso, [], false);
        }
        if (strlen($iso) === 3) {
            return static::$countries->keyBy('iso3')->get($iso, [], false);
        }
        if (strlen($iso) > 3) {
            return static::$countries->keyBy('name')->get($iso, [], false);
        }
        return [];
    }
    
    public static function initialize(): Collection
    {
        if (empty(static::$countries)) {
            $data = file_get_contents(storage_path('jsonFiles/countries.json'));
            static::$countries = collect($data);
        }
        return static::$countries;
    }
}
<?php

namespace Xpkg\RuleEngine\Models;


use Xpkg\Arrays\Arr;
use Illuminate\Database\Eloquent\Model;

class RuleActions extends Model
{
    public $timestamps = false;
    protected $hidden = [];
    protected $guarded = [];
    protected $table = 'rule_actions';
    protected $casts = ['fields' => 'array'];

    public static function parse(array $action): string
    {
        $name = strtolower($action['name']);
        $data = $action['data'];
        $record = static::query()->where('name', '=', $name)->first();
        $fields = $record->fields;
        $data = Arr::only($data, array_keys($fields));
        $func = 'action' . ucfirst($name);

        return $record->$func($data);
    }
}
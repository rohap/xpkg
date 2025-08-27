<?php

namespace Xpkg\RuleEngine\Models;

use Illuminate\Database\Eloquent\Model;

class RuleFacts extends Model
{
    public $timestamps = false;
    protected $table = 'rule_facts';
    protected $hidden = [];
    protected $guarded = [];
    protected $casts = ['use_default' => 'bool'];

    public static function allWithFunctions(): \Illuminate\Support\Collection
    {
        $fieldsClass = config('rules.model.fields');
        $facts = parent::with('fields')->get()->toArray();
        $fields = call_user_func_array([$fieldsClass, 'all'], ['*']);
        $fields = $fields->keyBy('name');
        $functions = [];
        foreach ($facts as $name => &$fact) {
            if ($fact['use_defaults']) {
                $functions = static::getDefaultFields($fact['type']);
            }
            $functions = array_merge($fact['fields'], $functions);

            foreach ($functions as &$function) {
                $ext = [];
                foreach ($function as $type) {
                    $fieldData = $fields->get($type);
                    $ext[] = $fieldData['name'] ?? '';
                }
                $function = $ext;
            }
            $fact['functions'] = $functions;
            unset($fact['fields'], $fact['use_defaults']);
        }

        $ret = collect($facts)->groupBy('fact')->toArray();
        foreach ($ret as $k => &$xa) {
            $xa = [
                'fact'   => $k,
                'fields' => $xa,
            ];
        }
        return collect($ret)->values();
    }

    public static function getDefaultFields($type): array
    {
        $defaults = config('rules.defaults');
        return $defaults[$type] ?? $defaults['text'];
    }

    public function fields(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            config('rules.model.fields'),
            'rule_facts_fields',
            'field_id',
            'id'
        );
    }
}
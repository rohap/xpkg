<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Xpkg\RuleEngine\Models\RuleFields;

class RuleFieldsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fields = [
            [
                'name'        => 'dateEq',
                'type'        => 'date',
                'placeholder' => 'created at',
            ],
            [
                'name'        => 'dateNeq',
                'type'        => 'date',
                'placeholder' => 'not created at',
            ],
            [
                'name'        => 'dateGt',
                'type'        => 'date',
                'placeholder' => 'created after',
            ],
            [
                'name'        => 'dateGte',
                'type'        => 'date',
                'placeholder' => 'created at or after',
            ],
            [
                'name'        => 'dateLt',
                'type'        => 'date',
                'placeholder' => 'created before',
            ],
            [
                'name'        => 'dateLte',
                'type'        => 'date',
                'placeholder' => 'created at or before',
            ],
            [
                'name'        => 'dateFrom',
                'type'        => 'date',
                'placeholder' => 'from',
            ],
            [
                'name'        => 'dateTo',
                'type'        => 'date',
                'placeholder' => 'to',
            ],
            [
                'name'        => 'idArray',
                'type'        => 'array',
                'placeholder' => 'ids',
            ],
            [
                'name'        => 'latitude',
                'type'        => 'float',
                'placeholder' => 'latitude',
            ],
            [
                'name'        => 'longitude',
                'type'        => 'float',
                'placeholder' => 'longitude',
            ],
            [
                'name'        => 'latitudeTopLeft',
                'type'        => 'float',
                'placeholder' => 'top left latitude',
            ],
            [
                'name'        => 'longitudeTopLeft',
                'type'        => 'float',
                'placeholder' => 'top left longitude',
            ],
            [
                'name'        => 'latitudeTopRight',
                'type'        => 'float',
                'placeholder' => 'top right latitude',
            ],
            [
                'name'        => 'longitudeTopRight',
                'type'        => 'float',
                'placeholder' => 'top right longitude',
            ],
            [
                'name'        => 'latitudeBottomLeft',
                'type'        => 'float',
                'placeholder' => 'bottom left latitude',
            ],
            [
                'name'        => 'longitudeBottomLeft',
                'type'        => 'float',
                'placeholder' => 'bottom left longitude',
            ],
            [
                'name'        => 'latitudeBottomRight',
                'type'        => 'float',
                'placeholder' => 'bottom right latitude',
            ],
            [
                'name'        => 'longitudeBottomRight',
                'type'        => 'float',
                'placeholder' => 'bottom right longitude',
            ],
            [
                'name'        => 'range',
                'type'        => 'float',
                'placeholder' => 'range (km)',
            ],
            [
                'name'        => 'textArray',
                'type'        => 'array',
                'placeholder' => 'text',
            ],
            [
                'name'        => 'textEq',
                'type'        => 'text',
                'placeholder' => 'text is',
            ],
            [
                'name'        => 'textNeq',
                'type'        => 'text',
                'placeholder' => 'text is not',
            ],
            [
                'name'        => 'textContain',
                'type'        => 'text',
                'placeholder' => 'text contain',
            ],
            [
                'name'        => 'textNotContain',
                'type'        => 'text',
                'placeholder' => 'text not contain',
            ],
            [
                'name'        => 'intEq',
                'type'        => 'integer',
                'placeholder' => 'is',
            ],
            [
                'name'        => 'intNeq',
                'type'        => 'integer',
                'placeholder' => 'not',
            ],
            [
                'name'        => 'intLt',
                'type'        => 'integer',
                'placeholder' => 'less then',
            ],
            [
                'name'        => 'intLte',
                'type'        => 'integer',
                'placeholder' => 'less then or equal to',
            ],
            [
                'name'        => 'intGt',
                'type'        => 'integer',
                'placeholder' => 'greather then',
            ],
            [
                'name'        => 'intGte',
                'type'        => 'integer',
                'placeholder' => 'greater then or equal to',
            ],
            [
                'name'        => 'floatEq',
                'type'        => 'float',
                'placeholder' => 'is',
            ],
            [
                'name'        => 'floatNeq',
                'type'        => 'float',
                'placeholder' => 'not',
            ],
            [
                'name'        => 'floatLt',
                'type'        => 'float',
                'placeholder' => 'less then',
            ],
            [
                'name'        => 'floatLte',
                'type'        => 'float',
                'placeholder' => 'less then or equal to',
            ],
            [
                'name'        => 'floatGt',
                'type'        => 'float',
                'placeholder' => 'greather then',
            ],
            [
                'name'        => 'floatGte',
                'type'        => 'float',
                'placeholder' => 'greater then or equal to',
            ],
            [
                'name'        => 'bool',
                'type'        => 'boolean',
                'placeholder' => 'true/false',
            ],
        ];
        
        RuleFields::insert($fields);
    }
}
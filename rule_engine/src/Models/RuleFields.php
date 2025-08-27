<?php

namespace Xpkg\RuleEngine\Models;

use Illuminate\Database\Eloquent\Model;

class RuleFields extends Model
{
    public $timestamps = false;
    protected $table = 'rule_fields';
    protected $casts = ['functions' => 'array'];
    protected $hidden = [];
    protected $guarded = [];


}
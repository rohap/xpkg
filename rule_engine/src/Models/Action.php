<?php

namespace Xpkg\RuleEngine\Models;

use Xpkg\RuleEngine\BaseActions;

class Action
{
    public static function parse(array $action)
    {
        $actions = config('rules.actions');
        $actionName = str($action['name'])->camel()->value();
        $class = $actions[$actionName];
        $instance = new $class();
        $data = $instance->filter($action['data']);
        return $instance->handle($data);
    }
}
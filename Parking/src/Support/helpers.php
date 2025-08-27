<?php
declare(strict_types=1);

if (!function_exists('arrayGet')) {
    function arrayGet(array $array, string $key)
    {
        if ($key === '') {
            return null;
        }
        $segments = explode('.', $key);
        $current = $array;
        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return null;
            }
        }
        return $current;
    }
}



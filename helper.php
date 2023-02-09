<?php

if (!function_exists('d')) {
    function d(...$values): void
    {
        foreach ($values as $value) {
            var_export($value);
            echo PHP_EOL;
        }
    }
}

if (!function_exists('dd')) {
    function dd(...$values): void
    {
        d(...$values);
        die();
    }
}

if (!function_exists('E')) {
    /**
     * @throws Exception
     */
    function E($message, $code = 0, Throwable $previous = null)
    {
        throw new Exception($message, $code, $previous);
    }
}

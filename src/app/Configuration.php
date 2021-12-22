<?php


namespace App;


class Configuration
{

    public static array $config; // injected config

    /**
     * @param string $path
     * @return mixed
     */
    public static function get(string $path): mixed
    {
        $path = explode("/", $path);
        $conf = self::$config;

        if (!array_key_exists($path[0], $conf)) {
            return null;
        }
        $result = $conf[$path[0]];

        array_shift($path);

        foreach ($path as $step) {
            if (!array_key_exists($step, $result)) {
                return null;
            }
            $result = $result[$step];
        }

        return $result;
    }
}

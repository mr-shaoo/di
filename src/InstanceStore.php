<?php

namespace Soldierm\Di;

class InstanceStore
{
    /**
     * @var array
     */
    private static $instances = [];

    /**
     * @var Container
     */
    public static $container;

    /**
     * @param string $key
     * @param mixed $config
     * @return mixed
     */
    public static function get(string $key, $config = [])
    {
        if (static::has($key)) {
            return static::$instances[$key];
        }

        if (null === static::$container->get($key)) {
            static::$container->set($key, $config);
        }

        return static::$instances[$key] = static::$container->get($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function has($key)
    {
        return array_key_exists($key, static::$instances);
    }
}
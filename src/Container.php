<?php

namespace Soldierm\Di;

use ReflectionClass;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    private $instances = [];

    /**
     * @var array
     */
    private $classes = [];

    /**
     * @var array
     */
    private $reflections = [];

    /**
     * @var array
     */
    private $dependencies = [];

    /**
     * @var array
     */
    private $defaultConfig = [];

    /**
     * Container constructor.
     * @throws NotSupportException
     */
    public function __construct()
    {
        $this->set(static::class, $this, true);
        InstanceStore::$container = $this;
    }

    /**
     * @param string $id
     * @return mixed|null
     */
    public function get($id)
    {
        return $this->instances[$id] ?? ($this->classes[$id] ?? null);
    }

    /**
     * @inheritdoc
     */
    public function has($id): bool
    {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->classes);
    }

    /**
     * @inheritdoc
     */
    public function set(string $id, $config = [], bool $singleton = false): void
    {
        if (array_key_exists($id, $this->instances)) {
            return;
        } elseif (is_object($config)) {
            $singleton ? $this->instances[$id] = $config : $this->classes[$id] = $config;
        } elseif (is_array($config)) {
            $config = $this->mergeConfig($id, $config);
            $class = $this->build($id, $config);
            $singleton ? $this->instances[$id] = $class : $this->classes[$id] = $class;
        } else {
            throw new NotSupportException('Not Support Di Type');
        }
    }

    /**
     * @inheritdoc
     */
    public function setSingleton(string $id, $config = [])
    {
        $this->set($id, $config, true);
    }

    /**
     * @inheritdoc
     */
    public function setMany(array $configs)
    {
        foreach ($configs as $id => $config) {
            $this->set($id, $config);
        }
    }

    /**
     * @inheritdoc
     */
    public function setSingletonMany(array $configs)
    {
        foreach ($configs as $id => $config) {
            $this->setSingleton($id, $config);
        }
    }

    /**
     * @inheritdoc
     */
    private function mergeConfig(string $id, array $config)
    {
        if (!isset($config['class'])) {
            if (class_exists($id)) {
                $config['class'] = $id;
            } else {
                throw new NotFoundException('Please add field \'class\' into config');
            }
        }
        return array_merge($this->defaultConfig, $config);
    }

    /**
     * @inheritdoc
     */
    private function build(string $id, array $config)
    {
        $reflectClass = $this->reflections[$id] ?? ($this->reflections[$id] = new ReflectionClass($config['class']));
        if (!$reflectClass->isInstantiable()) {
            throw new NotSupportException('Not Support Class Name--' . $reflectClass->getName());
        }
        $constructor = $reflectClass->getConstructor();
        $dependencies = $this->dependencies[$id] ?? [];
        $needModify = [];
        if (null !== $constructor && !isset($this->dependencies[$id])) {
            foreach ($constructor->getParameters() as $parameter) {
                $parameterName = $parameter->getName();
                print_r($parameterName);
                if ($parameter->isVariadic()) {
                    throw new NotSupportException('Not Support Parameter Type--' . $parameter->getName());
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $needModify[$parameterName] = true;
                    $dependencies[$parameterName] = $parameter->getDefaultValue();
                } else {
                    $class = $parameter->getClass();
                    if (array_key_exists($parameterName, $config)) {
                        if (is_array($config[$parameterName])) {
                            $config[$parameterName]['class'] = $config[$parameterName]['class'] ?? $class->getName();
                            $dependencies[$parameterName] = InstanceStore::get($config[$parameterName]['class'], $config[$parameterName]);
                        } elseif (is_object($config[$parameterName])) {
                            $dependencies[$parameterName] = InstanceStore::get($class->getName(), $config[$parameterName]);
                        } else {
                            $dependencies[$parameterName] = $config[$parameterName];
                        }
                    } elseif ($class === null) {
                        $dependencies[$parameterName] = null;
                    } else {
                        $dependencies[$parameterName] = InstanceStore::get($class->getName());
                    }
                    $needModify[$parameterName] = false;
                }
            }
            $this->dependencies[$id] = $dependencies;
        }

        foreach ($config as $modifyKey => $modifyValue) {
            if (array_key_exists($modifyKey, $dependencies)) {
                $dependencies[$modifyKey] = $needModify[$modifyKey] ? $modifyValue : $dependencies[$modifyKey];
            }
        }

        return $reflectClass->newInstanceArgs($dependencies);
    }
}
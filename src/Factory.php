<?php

namespace natlib;

use ReflectionException;

class Factory
{
    private $objects = [];

    public function __construct(...$objects)
    {
        $this->injectObjects($this, ...$objects);
    }

    public function injectObjects(...$objects)
    {
        array_map([$this, 'cache'], $objects);
    }

    /**
     * @param $class
     * @return mixed
     * @throws ReflectionException
     */
    public function secure($class)
    {
        return $this->getSavedObject($class)
            ?: $this->cache($this->make($class), $class);
    }

    /**
     * @param $class
     * @return mixed
     * @throws ReflectionException
     */
    public function make($class)
    {
        if ($this->isAbstract($class)) {
            throw new \Exception("Cannot instantiate abstract class $class");
        }

        $dependencies = $this->getDependencies($class);

        return new $class(...$dependencies);
    }

    private function isAbstract($class)
    {
        $reflection = new \ReflectionClass($class);

        return $reflection->isAbstract();
    }

    /**
     * @param $class
     * @return array
     * @throws ReflectionException
     */
    private function getDependencies($class)
    {
        $dependencyNames = $this->getDependencyNames($class);

        return array_map([$this, "secure"], $dependencyNames);
    }

    /**
     * @param $className
     * @return array|mixed
     * @throws ReflectionException
     */
    private function getDependencyNames($className)
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $params = ($constructor) ? $constructor->getParameters() : [];

        return array_map(function (\ReflectionParameter $param) {
            return $param->getClass()->getName();
        }, $params);
    }

    /**
     * Aggressive caching allows us to avoid expensive identity-based lookups
     */
    private function cache($object, $class = null)
    {
        $class = $class ?? get_class($object);
        $parentClass = get_parent_class($class);

        if ($parentClass) {
            $this->cache($object, $parentClass);
        }

        return $this->objects[$class] = $object;
    }

    /**
     * @param $class
     * @return mixed
     */
    private function getSavedObject($class)
    {
        return $this->objects[$class] ?? null;
    }
}

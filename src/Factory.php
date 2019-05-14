<?php

namespace natlib;

class Factory
{
	private $namespace;
	private $objects = [];

	public function __construct($namespace, ...$objects)
	{
		$this->namespace = $namespace;

		$this->injectObjects(...$objects);
	}

	public function injectObjects(...$objects)
	{
		$this->objects = array_merge($this->objects, $objects);
	}

	/**
	 * @param $class
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public function secure($class)
	{
		if (is_a($this, $class)) return $this;

		$qualifiedName = $this->getQualifiedName($class);
		$dependencies = $this->getDependencies($qualifiedName);

		return $this->secureObject($qualifiedName, ...$dependencies);
	}

	/**
	 * @param $class
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public function obtain($class)
	{
		$qualifiedName = $this->getQualifiedName($class);
		$dependencies = $this->getDependencies($qualifiedName);

		return $this->obtainObject($qualifiedName, ...$dependencies);
	}

	/**
	 * @param $class
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public function make($class)
	{
		$qualifiedName = $this->getQualifiedName($class);
		$dependencies = $this->getDependencies($qualifiedName);

		return $this->makeObject($qualifiedName, ...$dependencies);
	}

	/**
	 * @param $name
	 * @return string
	 */
	private function getQualifiedName($name)
	{
		$isQualified = strpos(trim($name, "\\"), "$this->namespace\\") === 0;

		return $isQualified ? $name : "\\$this->namespace\\$name";
	}

	/**
	 * @param $qualifiedName
	 * @return array
	 * @throws \ReflectionException
	 */
	private function getDependencies($qualifiedName)
	{
		$dependencyNames = $this->getDependencyNames($qualifiedName);

		return array_map([$this, "secure"], $dependencyNames);
	}

	/**
	 * @param $className
	 * @return array|mixed
	 * @throws \ReflectionException
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
	 * @param string $class
	 * @param array ...$dependencies
	 * @return mixed
	 */
	private function secureObject($class, ...$dependencies)
	{
		return $this->getSavedObject($class) ?:
			$this->objects[] = new $class(...$dependencies);
	}

	/**
	 * @param string $class
	 * @param array ...$dependencies
	 * @return mixed
	 */
	private function obtainObject($class, ...$dependencies)
	{
		return $this->getSavedObject($class) ?:
			new $class(...$dependencies);
	}

	/**
	 * @param string $class
	 * @param array ...$dependencies
	 * @return mixed
	 */
	private function makeObject($class, ...$dependencies)
	{
		return new $class(...$dependencies);
	}

	/**
	 * @param $class
	 * @return mixed
	 */
	private function getSavedObject($class)
	{
		$matchingObjects = array_filter($this->objects, function($object) use($class) {
			return is_a($object, $class);
		});

		return end($matchingObjects);
	}
}

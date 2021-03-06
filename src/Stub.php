<?php

namespace natlib;

define('STUB_NULL', 'STUB_NULL');

trait Stub
{
	private $calls = [];
	private $methodCallIndices = [];

	private $indexedReturnValues = [];
	private $mappedReturnValues = [];
	private $consecutiveReturnValues = [];
	private $returnCallbacks = [];
	private $returnValues = [];

	/** @var \PHPUnit\Framework\TestCase $testCase */
	private $testCase;

	/** @noinspection PhpMissingParentConstructorInspection */
	public function __construct(\PHPUnit\Framework\TestCase $testCase)
	{
		$this->testCase = $testCase;
	}

	/**
	 * @param $method
	 * @param $args
	 * @return mixed|null
	 */
	public function handleCall($method, $args)
	{
		$isMagicCall = $method === "__call";
		$method = ($isMagicCall) ? $args[0] : $method;
		$args = ($isMagicCall) ? $args[1] : $args;

		$this->calls[$method][] = $args;

		$indexedReturnValue = $this->getIndexedReturnValue($method);
		if ($indexedReturnValue !== STUB_NULL) return $indexedReturnValue;

		$mappedReturnValue = $this->getMappedReturnValue($method, $args);
		if ($mappedReturnValue !== STUB_NULL) return $mappedReturnValue;

		$consecutiveReturnValue = $this->getConsecutiveReturnValue($method);
		if ($consecutiveReturnValue !== STUB_NULL) return $consecutiveReturnValue;

		$callbackReturnValue = $this->getCallbackReturnValue($method, $args);
		if ($callbackReturnValue !== STUB_NULL) return $callbackReturnValue;

		$returnValue = $this->getReturnValue($method);
		return ($returnValue !== STUB_NULL) ? $returnValue : null;
	}

	private function getConsecutiveReturnValue($method)
	{
		if (!isset($this->consecutiveReturnValues[$method])) return STUB_NULL;

		return (count($this->consecutiveReturnValues[$method]) > 0) ?
			array_shift($this->consecutiveReturnValues[$method]) : STUB_NULL;
	}

	private function getCallbackReturnValue($method, $args)
	{
		if (!isset($this->returnCallbacks[$method])) return STUB_NULL;

		return call_user_func($this->returnCallbacks[$method], ...$args);
	}

	/**
	 * @param $method
	 * @return mixed
	 */
	private function getIndexedReturnValue($method)
	{
		$this->incrementCallIndex($method);

		$currentIndex = $this->methodCallIndices[$method];

		return isset($this->indexedReturnValues[$method][$currentIndex]) ?
			$this->indexedReturnValues[$method][$currentIndex] : STUB_NULL;
	}

	/**
	 * @param $method
	 */
	private function incrementCallIndex($method)
	{
		$this->methodCallIndices[$method] =
			isset($this->methodCallIndices[$method]) ? $this->methodCallIndices[$method] + 1 : 0;
	}

	/**
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	private function getMappedReturnValue($method, $args)
	{
		$callSignature = json_encode($args);

		return isset($this->mappedReturnValues[$method][$callSignature]) ?
			$this->mappedReturnValues[$method][$callSignature] : STUB_NULL;
	}

	/**
	 * @param $method
	 * @return mixed
	 */
	private function getReturnValue($method)
	{
		return isset($this->returnValues[$method]) ? $this->returnValues[$method] : STUB_NULL;
	}

	/**
	 * @param $method
	 * @param $returnValue
	 * @return Stub
	 */
	public function setReturnValue($method, $returnValue)
	{
		$this->returnValues[$method] = $returnValue;
		return $this;
	}

	public function setReturnValues($method, ...$returnValues)
	{
		$this->consecutiveReturnValues[$method] = $returnValues;
		return $this;
	}

	/**
	 * @param $method
	 * @param $callback
	 * @return Stub
	 */
	public function setReturnCallback($method, $callback)
	{
		$this->returnCallbacks[$method] = $callback;
		return $this;
	}

	/**
	 * @param int $index Zero-based call index
	 * @param $method
	 * @param $returnValue
	 * @return Stub
	 */
	public function setReturnValueAt($index, $method, $returnValue)
	{
		$this->indexedReturnValues[$method][$index] = $returnValue;
		return $this;
	}

	/**
	 * @param string $method
	 * @param array $map Array of arrays, each internal array representing a list of arguments followed by a single
	 * return value
	 * @return Stub
	 */
	public function setMappedReturnValues($method, array $map)
	{
		$processedMap = array_reduce($map, function ($carry, $entry) use ($method) {
			$returnValue = array_pop($entry);
			$callSignature = json_encode($entry);
			return array_merge($carry, [
				$callSignature => $returnValue
			]);
		}, []);

		$this->mappedReturnValues[$method] = array_merge(
			isset($this->mappedReturnValues[$method]) ? $this->mappedReturnValues[$method] : [],
			$processedMap
		);

		return $this;
	}

	/**
	 * @param string $method
	 * @return Stub
	 */
	public function assertMethodCalled($method)
	{
		$this->assertMethodExists($method);

		$this->testCase->assertTrue(
			$this->wasMethodCalled($method),
			"Failed asserting that '$method' was called"
		);

		return $this;
	}

	/**
	 * @param string $method
	 * @return Stub
	 */
	public function assertMethodNotCalled($method)
	{
		$this->assertMethodExists($method);

		$this->testCase->assertFalse(
			$this->wasMethodCalled($method),
			"Failed asserting that '$method' was not called"
		);

		return $this;
	}

	/**
	 * @param string $method
	 * @param mixed ...$args
	 * @return Stub
	 */
	public function assertMethodCalledWith($method, ...$args)
	{
		$this->assertMethodExists($method);

		$message = "Failed asserting that '$method' was called with specified args";

		$condition = $this->wasMethodCalledWith($method, ...$args);

		if (!$condition) {
			$this->dumpNeedleAndHaystack(
				$args,
				$this->getCalls($method)
			);
		}

		$this->testCase->assertTrue(
			$condition,
			$message
		);

		return $this;
	}

	/**
	 * @param string $method
	 * @param mixed ...$args
	 * @return Stub
	 */
	public function assertMethodNotCalledWith($method, ...$args)
	{
		$this->assertMethodExists($method);

		$message = "Failed asserting that '$method' was not called with specified args";

		$condition = $this->wasMethodCalledWith($method, ...$args);

		$this->testCase->assertFalse(
			$condition,
			$message
		);

		if ($condition) {
			$this->dumpNeedleAndHaystack(
				$args,
				$this->getCalls($method)
			);
		}

		return $this;
	}

	public function assertAnyCallMatches($method, callable $callable, $message = false)
	{
		$this->assertMethodExists($method);

		$calls = $this->getCalls($method);
		$bool = (bool) array_filter($calls, $callable);
		$error = $message ?: "Failed asserting any call matches callback.";

		if (!$bool) {
			$this->safeDump($calls);
		}

		$this->testCase->assertTrue($bool, $error);

		return $this;
	}

	public function assertNoCallsMatch($method, callable $callable, $message = false)
	{
		$this->assertMethodExists($method);

		$calls = $this->getCalls($method);
		$bool = (bool) array_filter($calls, $callable);
		$error = $message ?: "Failed asserting no call matches callback.";

		if ($bool) {
			$this->safeDump($calls);
		}

		$this->testCase->assertFalse($bool, $error);

		return $this;
	}

	/**
	 * @param string $method
	 * @param string $needle
	 * @return Stub
	 */
	public function assertCallsContain($method, $needle)
	{
		$this->assertMethodExists($method);

		$message = "Failed asserting that '$needle' is in haystack: \r\n" .
			$this->getCallHaystack($method);

		$this->testCase->assertTrue(
			$this->doCallsContain($method, $needle),
			$message
		);

		return $this;
	}

	public function assertNoCallsContain($method, $needle)
	{
		$this->assertMethodExists($method);

		$message = "Failed asserting that '$needle' is not in haystack: \r\n" .
			$this->getCallHaystack($method);

		$this->testCase->assertFalse(
			$this->doCallsContain($method, $needle),
			$message
		);

		return $this;
	}

	public function assertCallCount($method, $count)
	{
		$this->assertMethodExists($method);

		$this->testCase->assertCount($count, $this->getCalls($method));

		return $this;
	}

    public function assertMatchingCallCount($method, $count, callable $filter)
    {
        $this->assertMethodExists($method);

        $calls = $this->getCalls($method);
        $matches = array_filter($calls, $filter);

        $this->testCase->assertCount($count, $matches);

        return $this;
    }

	public function assertMethodExists($method)
	{
		$class = get_class($this);
		$method_exists = method_exists($this, $method);
		$magic_method_exists = method_exists($this, "__call");
		$handler_exists = $method_exists || $magic_method_exists;
		$error = "Method `$method` does not exist in object of type `$class`";

		$this->testCase->assertTrue($handler_exists, $error);
	}

	/**
	 * @param string $method
	 * @return bool
	 */
	public function wasMethodCalled($method)
	{
		return !empty($this->getCalls($method));
	}

	/**
	 * @param string $method
	 * @param mixed ...$args
	 * @return bool
	 */
	public function wasMethodCalledWith($method, ...$args)
	{
		return in_array($args, $this->getCalls($method));
	}

	/**
	 * @param string $method
	 * @param string $needle
	 * @return bool
	 */
	public function doCallsContain($method, $needle)
	{
		$haystack = $this->getCallHaystack($method);

		return strpos($haystack, $needle) !== false;
	}

	/**
	 * @param string $method
	 * @return string
	 */
	private function getCallHaystack($method)
	{
		$calls = $this->getCalls($method);
		$safeCalls = $this::sanitizeDumpContent($calls);

		return stripslashes(var_export($safeCalls, true));
	}

	/**
	 * @param $method
	 * @return array
	 */
	public function getCalls($method)
	{
		return (isset($this->calls[$method])) ? $this->calls[$method] : [];
	}

	private function dumpNeedleAndHaystack($needle, $haystack)
	{
		echo "Needle:\r\n";
		$this->safeDump($needle);

		echo "\r\nHaystack:\r\n";
		$this->safeDump($haystack);
	}

	public static function safeDump(...$contents)
	{
		array_walk($contents, function($content) {
			var_dump(self::sanitizeDumpContent($content));
		});
	}

	public static function sanitizeDumpContent($content)
	{
		if (is_object($content)) return "OBJECT::".get_class($content);

		if (!is_array($content)) return $content;

		return array_map(function($node) {
			return self::sanitizeDumpContent($node);
		}, $content);
	}
}

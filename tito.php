<?php
// Example tiny tool

// modify this to conform with your class-loading strategy:
use dface\tito\Tito;

include_once 'vendor/autoload.php';

class Param implements JsonSerializable
{

	private string $val;

	public function __construct(string $val)
	{
		$this->val = $val;
	}

	public static function deserialize($val) : self
	{
		return new static($val);
	}

	public function jsonSerialize()
	{
		return $this->val;
	}

}

class Param2 implements JsonSerializable
{

	private string $val;

	public function __construct(string $val)
	{
		$this->val = $val;
	}

	public function jsonSerialize()
	{
		return $this->val;
	}

}

// example service class
class Test
{

	public function process($val)
	{
		return $val;
	}

	public function processObj(Param $val) : \Param
	{
		return $val;
	}

	public function processObj2(Param2 $val) : \Param2
	{
		return $val;
	}

	public function info()
	{
		return $_SERVER['argv'];
	}

}

// kinda container
$container = [
	'service1' => new Test(),
	'service2' => new Test(),
];

// Initialize tito - pass in description and service-locator callback:
$tito = new Tito(
	'X-system command line tool.',
	static function ($service_name) use ($container) {
		return $container[$service_name];
	}
);

// ask tito to make the rest
$tito->call();

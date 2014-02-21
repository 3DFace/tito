<?php
print_r($_SERVER);
// Example tiny tool

// please modify this to conform with your class-loading strategy:
include_once 'vendor/autoload.php';

// example service class
class Test {

	function process($val){
		return $val;
	}

	function info(){
		return $_SERVER['argv'];
	}

}

// kinda container
$container = [
	'service1' => new Test('service1'),
	'service2' => new Test('service2'),
];

// Initialize tito - pass in description and service-locator callback:
$tito = new \dface\tito\Tito(
	'X-system command line tool.',
	function ($service_name) use ($container){
		return $container[$service_name];
	}
);

// ask tito to make the rest
$tito->call();

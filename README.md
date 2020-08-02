# dface/tito

### Tiny Tool

Helper class to make a CLI tool to call app services.

`php tito.php <service_name> <method_name> <param1> <param2> ...`

It takes a `<service>` from a container, calls a method and outputs a result.
To locate a service it needs a service-locator callback.

## Setup

```
composer require dface/tito
```

## Usage

Let's create example tiny tool to demonstrate the concept.

Create file named `tito.php` with following content. (You can choose any other name)

``` php
<?php
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
```

Execute the script from command line. Don't pass any params for now.

`php tito.php`

You'll see the info screen.

```
X-system command line tool.

Makes a service method call and outputs a result.

Usage: php tito.php [options] <call>

A <call> can be in the default form:
  <service> <method> [<arg1> <arg2> ...]

or as JSON array, if -j specified:
  '["<service>", "<method>" [,<args array>]]'

A result is either:
  [true, <returned value>] - for successful calls
or
  [false, <exception type>, <message>] - for failed ones.

Results are displayed in JSON format unless -p|-y|-l specified.

Options:
  -j   <call> passed in JSON format
  -p   output a result with print_r instead of JSON
  -y   output a result as YAML instead of JSON
  -l   output a result as list of lines (values only)
  -q   quite mode - skip result status 'true' for successful call
  -s   silent mode - no output for successful calls
  -v   verbose mode - don't suppress service stdout, don't suppress error_reporting
  -t   add a stacktrace to failed results
  -i   input encoding (utf-8 assumed by default)
  -b   service internal encoding (utf-8 assumed by default)
  -o   output encoding (input encoding assumed by default)
  -d   max recursion depth for encoding conversion (default 512)
  -x   eval specified code before making service call
  -e   set exit code to '1' for failed calls


```

Now try to use it:

```
php tito.php service1 process hi
[true,"hi"]
```

It outputs a JSON-formatted array of two elements - status and returned value.

Status `true` indicates that a call was successful and a returned value is located in the second element of array.

Status `false` indicates that a call was failed for some reasons. With `false` you'll also get exception type and message:

```
php tito.php asd process hi
[false,"dface\\tito\\TitoException","No such service 'asd'"]
```

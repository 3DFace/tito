# dface/tito

<br><b>Ti</b>ny <b>To</b>ol<br>

This is a small class that helps you to construct a lightweight command line tool for your application.

You may find it interesting if you utilize any kind of IoC-container in your apps.

Using that tool you can call your services from a command line.

Something like:
```
php <created_tool> <service_name> <method_name> <param1> <param2> ...
```

There is nothing special.
It just takes a `<service>` from your container, calls its method and outputs a result in JSON format.
You don't have to implement any kind of interfaces or adaptors to make it works.
Just provide a service-locator callback.

It is doubtful that the instrument is suitable for an end user. Rather, it is a developer's assistant tool.
Cause a user has to know which services are available and what they actually do.

In our project we use it to attach some jobs to cron.
And for simple rpc-integration - guys from beside project make requests over `ssh` and receive JSON replies.


## Setup

Add to your composer.json file:

``` json
{
    "require": {
		"dface/tito": "dev-master"
	}
}
```

Library organized according to PSR-0.

So you can use composer autoloader:
``` php
require 'vendor/autoload.php';
```
or use custom PSR-0 loader.

## Usage

Lets create example tiny tool to demonstrate the concept.

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

Of course, in a real application, your classes and a container should be defined somewhere else.
Most likely, you will inject your container with `include`.

Execute the script from command line. Don't pass any params for now.

```
php tito.php
```

You'll see the info screen.

```
X-system command line tool.

Makes a service method call and outputs a result.

Usage: php tito.php [options] <call>

Call can be in a default form:
  <service> <method> [<arg1> <arg2> ...]

or as JSON array, if -j specified:
  '["<service>", "<method>" [,<args array>]]'

Result is either:
  [true, <returned value>] - for successful calls
or
  [false, <exception type>, <message>] - for failed ones.

The result is displayed in JSON format unless -p specified.

Options:
  -j   <call> passed in JSON format
  -p   output result with print_r instead of JSON
  -q   quite mode - skip result status (true) for successful calls
  -s   silent mode - no output for successful calls
  -v   verbose mode - don't suppress service stdout
  -r   report errors - set error_reporting to E_ALL (0 by default)
  -t   add a stacktrace to failed results
  -i   input encoding (utf-8 assumed by default)
  -b   service internal encoding (utf-8 assumed by default)
  -o   output encoding (input encoding assumed by default)
  -d   max recursion depth for encoding conversion (default 512)
  -x   eval specified code before making service call
  -e   set exit code (1) for failed calls

```

Now try to use it:

```
php tito.php service1 process hi
[true,"hi"]
```

It outputs a JSON-formatted array of two elements - status and returned value.

Status `true` indicates that a call was successful and returned value can be taken from the second element of array.

Status `false` indicates that a call was failed for some reasons. With `false` you'll also get an exception type and message:

```
php tito.php asd process hi
[false,"dface\\tito\\TitoException","No such service 'asd'"]
```

## Security

`Tito` relies on `PHP_SAPI` constant to prevent execution from non-cli environment. There are no other restrictions.
If you need more advanced policy, please implement it by yourself in your script.

## Tests

```
phpunit
```

<?php

/** @noinspection HtmlUnknownTag */

namespace dface\tito;

use Symfony\Component\Yaml\Yaml;

class Tito
{

	private const UTF8 = 'utf-8';

	/** @var callable */
	private $service_locator;
	private string $default_encoding;
	private int $max_depth;
	private string $system_info;
	private bool $fatal;

	public function __construct(
		string $system_info,
		callable $service_locator,
		string $default_encoding = self::UTF8,
		int $max_depth = 512
	) {
		$this->system_info = $system_info;
		$this->service_locator = $service_locator;
		$this->default_encoding = $default_encoding;
		$this->max_depth = \max(1, $max_depth);
	}

	public function help($script_name) : string
	{
		$script = \basename($script_name);
		$n = PHP_EOL;
		return
			$this->system_info
			."${n}${n}Makes a service method call and outputs a result."
			."${n}${n}Usage: php $script [options] <call>"
			."${n}${n}A <call> can be in the default form:"
			."${n}  <service> <method> [<arg1> <arg2> ...]"
			."${n}${n}or as JSON array, if -j specified:"
			."${n}  '[\"<service>\", \"<method>\" [,<args array>]]'"
			."${n}${n}A result is either:"
			."${n}  [true, <returned value>] - for successful calls "
			."${n}or"
			."${n}  [false, <exception type>, <message>] - for failed ones."
			."${n}${n}Results are displayed in JSON format unless -p|-y|-l specified."
			."${n}${n}Options:"
			."${n}  -j   <call> passed in JSON format"
			."${n}  -p   output a result with print_r instead of JSON"
			."${n}  -y   output a result as YAML instead of JSON"
			."${n}  -l   output a result as list of lines (values only)"
			."${n}  -q   quite mode - skip result status 'true' for successful calls"
			."${n}  -s   silent mode - no output for successful calls"
			."${n}  -v   verbose mode - don't suppress service stdout, don't suppress error_reporting"
			."${n}  -t   add a stacktrace to failed results"
			."${n}  -i   input encoding ($this->default_encoding assumed by default)"
			."${n}  -b   service internal encoding ($this->default_encoding assumed by default)"
			."${n}  -o   output encoding (input encoding assumed by default)"
			."${n}  -d   max recursion depth for encoding conversion (default $this->max_depth)"
			."${n}  -x   eval specified code before making service call"
			."${n}  -e   set exit code to '1' for failed calls"
			."${n}${n}";
	}

	public function call() : void
	{
		if (PHP_SAPI === 'cli') {
			$argv = $_SERVER['argv'];
			/** @noinspection SpellCheckingInspection */
			$opt = \getopt('evtspyljqri:o:d:b:x:');
			$params = $this->exclude_options_from_params($argv, $opt);
			[$out, $exit] = $this->do_call($argv[0], $opt, $params);
			echo $out;
			exit($exit);
		}
	}

	/**
	 * @param $script_name
	 * @param $opt
	 * @param $params
	 * @return array
	 */
	public function do_call($script_name, $opt, $params) : array
	{
		$exit_code = 0;
		$out = '';
		if (!empty($params)) {
			$service_encoding = \strtolower($opt['b'] ?? $this->default_encoding);
			$input_encoding = \strtolower($opt['i'] ?? $this->default_encoding);
			$output_encoding = \strtolower($opt['o'] ?? $input_encoding);
			if (isset($opt['d'])) {
				$this->max_depth = \max(1, $opt['d']);
			}
			try{
				if (isset($opt['x'])) {
					eval($opt['x']);
				}
				if (isset($opt['j'])) {
					[$service_name, $method_name, $call_args] =
						$this->parseJsonCallDefinition($params[0], $input_encoding, $service_encoding);
				}else {
					[$service_name, $method_name, $call_args] =
						$this->parsePlainCallDefinition($params, $input_encoding, $service_encoding);
				}
				$service = ($this->service_locator)($service_name);
				if (!\is_object($service)) {
					throw new TitoException("No such service '$service_name'");
				}
				$callable = [$service, $method_name];
				if (!\is_callable($callable)) {
					throw new TitoException("'$service_name' does not have method '$method_name'");
				}
				$this->fatal = true;
				\register_shutdown_function(function () use ($opt) {
					if ($this->fatal) {
						$e = \error_get_last() ?: [
							'file' => 'unknown',
							'line' => 'unknown',
							'message' => 'error',
						];
						$msg = "Fatal error at $e[file]:$e[line]: $e[message]";
						echo $this->rescueFormatResult([false, 'FatalError', $msg], isset($opt['p'])).PHP_EOL;
					}
				});
				$call_args = $this->deserializeParameters(\get_class($service), $method_name, $call_args);
				if (!isset($opt['v'])) {
					\ob_start();
				}
				try{
					$result = [true, $callable(...$call_args)];
				}finally{
					if (!isset($opt['v'])) {
						\ob_end_clean();
					}
				}
			}catch (\Throwable $e){
				$result = [false, \get_class($e), $e->getMessage()];
				if (isset($opt['t'])) {
					$result[] = $e->getTraceAsString();
					while ($e = $e->getPrevious()) {
						$result[] = $e->getTraceAsString();
					}
				}
			}
			$this->fatal = false;

			if (!isset($opt['s']) || !$result[0]) {
				if (isset($opt['q']) && $result[0]) {
					$result = $result[1];
				}
				$out = $this->formatResult($opt, $result, $service_encoding, $output_encoding);
			}
			if (isset($opt['e']) && !$result[0]) {
				$exit_code = 1;
			}
		}else {
			$out = $this->help($script_name);
		}
		return [$out, $exit_code];
	}

	private function formatResult($opt, $result, $result_encoding, $output_encoding)
	{
		try{
			if (isset($opt['p'])) {
				return \print_r($this->convert_encoding($result, $output_encoding, $result_encoding), 1);
			}
			if (isset($opt['y'])) {
				return $this->result_to_yaml($result, $result_encoding, $output_encoding);
			}
			if (isset($opt['l'])) {
				return $this->result_to_lines($result, 0);
			}
			return $this->result_to_json($result, $result_encoding, $output_encoding).PHP_EOL;
		}catch (\Throwable $e){
			$result = [false, \get_class($e), $e->getMessage()];
			if (isset($opt['t'])) {
				$result[] = $e->getTraceAsString();
			}
			return $this->rescueFormatResult($result, isset($opt['p']));
		}
	}

	private function rescueFormatResult($result, $use_print_r)
	{
		if ($use_print_r) {
			return \print_r($result, 1);
		}
		$result = \array_map(static function ($v) {
			return '"'.\addslashes($v).'"';
		}, \array_slice($result, 1));
		return '[false,'.\implode(',', $result).']'.PHP_EOL;
	}

	public function exclude_options_from_params($argv, array $options) : array
	{
		$args = \array_slice($argv, 1);
		$params = [];
		foreach ($args as $i => $arg) {
			if ($arg[0] !== '-') {
				if ($i === 0) {
					$params[] = $arg;
				}else {
					$prev_arg = $args[$i - 1];
					if ($prev_arg[0] !== '-') {
						$params[] = $arg;
					}else {
						$is_opt_val = false;
						foreach ($options as $o => $val_arr) {
							if (!\is_array($val_arr)) {
								$val_arr = [$val_arr];
							}
							foreach ($val_arr as $val) {
								if ($val === $arg && \ltrim($prev_arg, '-') === $o) {
									$is_opt_val = true;
									break 2;
								}
							}
						}
						if (!$is_opt_val) {
							$params[] = $arg;
						}
					}
				}
			}
		}
		return $params;
	}

	/**
	 * @param $body
	 * @param $input_encoding
	 * @param $service_encoding
	 * @return array
	 * @throws TitoException
	 * @throws \JsonException
	 */
	private function parseJsonCallDefinition($body, $input_encoding, $service_encoding) : array
	{
		$utf8_json = $this->convert_encoding($body, self::UTF8, $input_encoding);
		$call = \json_decode($utf8_json, true, $this->max_depth, JSON_THROW_ON_ERROR);
		if (!\is_array($call)) {
			$type = \gettype($call);
			throw new TitoException("Call must be an array of [service, method, args], $type given");
		}
		$service_name = $call[0];
		if (\count($call) < 2) {
			throw new TitoException("Provide a method name for $service_name");
		}
		$method_name = $call[1];
		if (isset($call[2])) {
			$call_args = $call[2];
			if (!\is_array($call_args)) {
				$type = \gettype($call_args);
				throw new TitoException("Call parameters must be an array, $type given");
			}
			$call_args = $this->convert_encoding($call_args, $service_encoding, self::UTF8);
		}else {
			$call_args = [];
		}
		return [$service_name, $method_name, $call_args];
	}

	/**
	 * @param $params
	 * @param $input_encoding
	 * @param $service_encoding
	 * @return array
	 * @throws TitoException
	 */
	private function parsePlainCallDefinition($params, $input_encoding, $service_encoding) : array
	{
		$service_name = $params[0];
		if (\count($params) < 2) {
			throw new TitoException("Provide a method name for '$service_name'");
		}
		$method_name = $params[1];
		$call_args = \array_slice($params, 2);
		$call_args = $this->convert_encoding($call_args, $service_encoding, $input_encoding);
		return [$service_name, $method_name, $call_args];
	}

	/**
	 * @param $className
	 * @param $methodName
	 * @param array $parameters
	 * @return array
	 * @throws TitoException
	 */
	private function deserializeParameters($className, $methodName, array $parameters) : array
	{
		try{
			$method = new \ReflectionMethod($className, $methodName);
		}catch (\ReflectionException $e){
			throw new TitoException($e->getMessage(), 0, $e);
		}
		$method_parameters = $method->getParameters();
		foreach ($method_parameters as $i => $mp) {
			if (isset($parameters[$i])) {
				$paramClass = $mp->getClass();
				if ($paramClass !== null) {
					$paramClassName = $paramClass->getName();
					$paramName = $mp->getName();
					if (\method_exists($paramClassName, 'deserialize')) {
						try{
							$parameters[$i] = $paramClassName::deserialize($parameters[$i]);
						}catch (\Throwable $e){
							throw new TitoException("Can't deserialize '$paramName': ".$e->getMessage(), 2, $e);
						}
					}else {
						try{
							$parameters[$i] = new $paramClassName($parameters[$i]);
						}catch (\Throwable $e){
							throw new TitoException("Can't construct '$paramName': ".$e->getMessage(), 2, $e);
						}
					}
				}
			}
		}
		return $parameters;
	}

	/**
	 * @param $result
	 * @param $result_encoding
	 * @param $output_encoding
	 * @return array|mixed
	 * @throws \JsonException
	 */
	private function result_to_json($result, $result_encoding, $output_encoding)
	{
		$result_utf8 = $this->convert_encoding($result, self::UTF8, $result_encoding);
		$json_utf8 = \json_encode($result_utf8, JSON_THROW_ON_ERROR, $this->max_depth);
		$json_utf8 = \preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($match) {
			return $this->convert_encoding(\pack('H*', $match[1]), self::UTF8, 'UCS-2BE');
		}, $json_utf8);
		return $this->convert_encoding($json_utf8, $output_encoding, self::UTF8);
	}

	/**
	 * @param $result
	 * @param $result_encoding
	 * @param $output_encoding
	 * @return array|mixed
	 * @throws TitoException
	 */
	private function result_to_yaml($result, $result_encoding, $output_encoding)
	{
		$result_utf8 = $this->convert_encoding($result, self::UTF8, $result_encoding);
		try{
			$yaml_utf8 = Yaml::dump($result_utf8, 4, 4);
		}catch (\Throwable $e){
			throw new TitoException('Cant format result as YAML: '.$e->getMessage());
		}
		return $this->convert_encoding($yaml_utf8, $output_encoding, self::UTF8);
	}

	/**
	 * @param $value
	 * @param $level
	 * @return string
	 * @throws TitoException
	 */
	private function result_to_lines($value, $level) : string
	{
		if ($level === $this->max_depth) {
			throw new TitoException("Recursion is too deep ($level)");
		}
		$result = '';
		if (\is_array($value)) {
			foreach ($value as $v) {
				$result .= $this->result_to_lines($v, $level + 1);
			}
		}else {
			$result .= $value.PHP_EOL;
		}
		return $result;
	}

	/**
	 * @param $val
	 * @param $to
	 * @param $from
	 * @return string|array
	 */
	private function convert_encoding($val, $to, $from)
	{
		if ($to === $from) {
			return $val;
		}
		// convert as array to keep numeric types; otherwise 1(int) becomes '1'(string)
		return \mb_convert_encoding([$val], $to, $from)[0];
	}

}

<?php /* author: Denis Ponomarev <ponomarev@gmail.com> */

namespace dface\tito;

class Tito {

	const UTF8 = 'utf-8';

	/** @var callable */
	protected $service_locator;
	/** @var string */
	protected $default_encoding;
	/** @var int */
	protected $max_depth;
	/** @var string */
	protected $system_info;
	/** @var callable */
	protected $encoding_converter;
	/** @var bool */
	protected $fatal;

	function __construct($system_info, $service_locator, $default_encoding = self::UTF8, $max_depth = 512){
		$this->system_info = $system_info;
		$this->service_locator = $service_locator;
		$this->default_encoding = $default_encoding;
		$this->max_depth = max(1, $max_depth);
		if(function_exists('mb_convert_encoding')){
			$this->encoding_converter = 'mb_convert_encoding';
		}elseif(function_exists('iconv')){
			$this->encoding_converter = function($str, $to, $from){
				return iconv($from, $to, $str);
			};
		}else{
			$this->encoding_converter = function (){
				throw new TitoException("Encoding conversion unavailable. Please install mbstring extension.");
			};
		}
	}

	function help($script_name){
		$script = basename($script_name);
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
			."${n}  [true, <returned value>] - for successful calls"
			."${n}or"
			."${n}  [false, <exception type>, <message>] - for failed ones."
			."${n}${n}Results are displayed in JSON format unless -p specified."
			."${n}${n}Options:"
			."${n}  -j   <call> passed in JSON format"
			."${n}  -p   output a result with print_r instead of JSON"
			."${n}  -q   quite mode - skip result status 'true' for successful calls"
			."${n}  -s   silent mode - no output for successful calls"
			."${n}  -v   verbose mode - don't suppress service stdout, don't suppress error_reporting"
			."${n}  -r   report errors - throw ErrorException on E_ALL"
			."${n}  -t   add a stacktrace to failed results"
			."${n}  -i   input encoding ($this->default_encoding assumed by default)"
			."${n}  -b   service internal encoding ($this->default_encoding assumed by default)"
			."${n}  -o   output encoding (input encoding assumed by default)"
			."${n}  -d   max recursion depth for encoding conversion (default $this->max_depth)"
			."${n}  -x   eval specified code before making service call"
			."${n}  -e   set exit code to '1' for failed calls"
			."${n}${n}";
	}

	function call(){
		if(PHP_SAPI === 'cli'){
			$argv = $_SERVER['argv'];
			$opt = getopt('evtspjqri:o:d:b:x:');
			$params = $this->exclude_options_from_params($argv, $opt);
			list($out, $exit) = $this->do_call($argv[0], $opt, $params);
			echo $out;
			exit($exit);
		}
	}

	function do_call($script_name, $opt, $params){
		$exit_code = 0;
		$out = "";
		if(!empty($params)){
			$service_encoding = isset($opt['b']) ? $opt['b'] : $this->default_encoding;
			$input_encoding = isset($opt['i']) ? $opt['i'] : $this->default_encoding;
			$output_encoding = isset($opt['o']) ? $opt['o'] : $input_encoding;
			if(isset($opt['d'])){
				$this->max_depth = max(1, $opt['d']);
			}
			if(!isset($opt['v'])){
				ob_start();
				error_reporting(0);
			}
			if(isset($opt['r'])){
				set_error_handler(function ($err_no, $err_str, $err_file, $err_line ) {
					if(error_reporting()){
						throw new \ErrorException($err_str, $err_no, 0, $err_file, $err_line);
					}
				}, E_ALL);
				error_reporting(E_ALL);
			}
			try{
				if(isset($opt['x'])){
					echo "code is ".$opt['x']."\n";
					eval($opt['x']);
				}
				if(isset($opt['j'])){
					list($service_name, $method_name, $call_args) =
						$this->parseJsonCallDefinition($params[0], $input_encoding, $service_encoding);
				}else{
					list($service_name, $method_name, $call_args) =
						$this->parsePlainCallDefinition($params, $input_encoding, $service_encoding);
				}
				$service = call_user_func($this->service_locator, $service_name);
				if(!is_object($service)){
					throw new TitoException("No such service '$service_name'");
				}
				$callable = [$service, $method_name];
				if(!is_callable($callable)){
					throw new TitoException("'$service_name' does not have method '$method_name'");
				}
				$this->fatal = true;
				register_shutdown_function(function()use($opt){
					if($this->fatal) {
						$e = error_get_last() ?: [
							'file' => 'unknown',
							'line' => 'unknown',
							'message' => 'error',
						];
						$msg = "Fatal error at $e[file]:$e[line]: $e[message]";
						echo $this->rescueFormatResult([false, 'FatalError', $msg], isset($opt['p'])).PHP_EOL;
					}
				});
				$result = [true, call_user_func_array($callable, $call_args)];
			}catch(\Exception $e){
				$result = [false, get_class($e), $e->getMessage()];
				if(isset($opt['t'])){
					$result[] = $e->getTraceAsString();
				}
			}
			$this->fatal = false;
			if(!isset($opt['v']) && ob_get_level() > 0){
				ob_end_clean();
			}
			if(!isset($opt['s']) || !$result[0]){
				if(isset($opt['q']) && $result[0]){
					$result = $result[1];
				}
				$out = $this->formatResult($opt, $result, $service_encoding, $output_encoding).PHP_EOL;
			}
			if(isset($opt['e']) && !$result[0]){
				$exit_code = 1;
			}
		}else{
			$out = $this->help($script_name);
		}
		return [$out, $exit_code];
	}

	protected function formatResult($opt, $result, $result_encoding, $output_encoding){
		try{
			if(isset($opt['p'])){
				return print_r($this->convert($result_encoding, $output_encoding, $result), 1);
			}else{
				return $this->result_to_json($result, $result_encoding, $output_encoding);
			}
		}catch(\Exception $e){
			$result = [false, get_class($e), $e->getMessage()];
			if(isset($opt['t'])){
				$result[] = $e->getTraceAsString();
			}
			return $this->rescueFormatResult($result, isset($opt['p']));
		}
	}

	protected function rescueFormatResult($result, $use_print_r){
		if($use_print_r){
			return print_r($result, 1);
		}else{
			$result = array_map(function($v){
				return '"'.addslashes($v).'"';
			}, array_slice($result, 1));
			return '[false,'.implode(",", $result).']';
		}
	}

	function exclude_options_from_params($argv, $options){
		$args = array_slice($argv, 1);
		$params = [];
		foreach($args as $i => $arg){
			if($arg[0] !== '-'){
				if($i === 0){
					$params[] = $arg;
				}else{
					$prev_arg = $args[$i - 1];
					if($prev_arg[0] !== '-'){
						$params[] = $arg;
					}else{
						$is_opt_val = false;
						foreach($options as $o => $val_arr){
							if(!is_array($val_arr)){
								$val_arr = [$val_arr];
							}
							foreach($val_arr as $val){
								if($val === $arg && ltrim($prev_arg, '-') === $o){
									$is_opt_val = true;
									break 2;
								}
							}
						}
						if(!$is_opt_val){
							$params[] = $arg;
						}
					}
				}
			}
		}
		return $params;
	}

	protected function parseJsonCallDefinition($body, $input_encoding, $service_encoding){
		$utf8_json = $this->convert($input_encoding, self::UTF8, $body);
		$call = json_decode($utf8_json, true, $this->max_depth);
		if($call === null){
			throw new TitoException("Cant parse json call: $body");
		}
		if(!is_array($call)){
			$type = gettype($call);
			throw new TitoException("Call must be an array of [service, method, args], $type given");
		}
		$service_name = $call[0];
		if(count($call) < 2){
			throw new TitoException("Provide a method name for $service_name");
		}
		$method_name = $call[1];
		if(isset($call[2])){
			$call_args = $call[2];
			if(!is_array($call_args)){
				$type = gettype($call_args);
				throw new TitoException("Call parameters must be an array, $type given");
			}
			$call_args = $this->convert(self::UTF8, $service_encoding, $call_args);
		}else{
			$call_args = [];
		}
		return [$service_name, $method_name, $call_args];
	}

	protected function parsePlainCallDefinition($params, $input_encoding, $service_encoding){
		$service_name = $params[0];
		if(count($params) < 2){
			throw new TitoException("Provide a method name for $service_name");
		}
		$method_name = $params[1];
		$call_args = array_slice($params, 2);
		$call_args = $this->convert($input_encoding, $service_encoding, $call_args);
		return [$service_name, $method_name, $call_args];
	}

	protected function result_to_json($result, $result_encoding, $output_encoding){
		$result_utf8 = $this->convert($result_encoding, self::UTF8, $result);
		$json_utf8 = json_encode($result_utf8, 0, $this->max_depth);
		if($json_utf8 === false){
			throw new TitoException("Cant format result as JSON: ".json_last_error_msg());
		}
		$json_utf8 = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($match){
			return $this->convert_encoding(pack('H*', $match[1]), self::UTF8, 'UCS-2BE');
		}, $json_utf8);
		return $this->convert(self::UTF8, $output_encoding, $json_utf8);
	}

	protected function convert($in_encoding, $out_encoding, $value){
		if(strcasecmp($in_encoding, $out_encoding)){
			if(is_array($value)){
				return $this->convert_recursive($in_encoding, $out_encoding, $value, 0);
			}else{
				if(is_string($value)){
					return $this->convert_encoding($value, $out_encoding, $in_encoding);
				}else{
					return $value;
				}
			}
		}else{
			return $value;
		}
	}

	protected function convert_recursive($in_encoding, $out_encoding, $value, $level){
		if($level === $this->max_depth){
			throw new TitoException("Recursion is too deep ($level)");
		}
		$result = [];
		foreach($value as $k => $v){
			if(is_string($k)){
				$k = $this->convert_encoding($k, $out_encoding, $in_encoding);
			}
			if(is_array($v)){
				$v = $this->convert_recursive($in_encoding, $out_encoding, $v, $level + 1);
			}elseif(is_string($v)){
				$v = $this->convert_encoding($v, $out_encoding, $in_encoding);
			}
			$result[$k] = $v;
		}
		return $result;
	}

	protected function convert_encoding($v, $out_encoding, $in_encoding){
		$result = call_user_func($this->encoding_converter, $v, $out_encoding, $in_encoding);
		if($result === false){
			$e = error_get_last();
			throw new TitoException("Cant convert encoding: ".$e['message']);
		}
		return $result;
	}

}

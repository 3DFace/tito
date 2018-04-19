<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\tito;

class TitoTest extends \PHPUnit_Framework_TestCase {

	protected function createTestTito(){
		$services = [
			'service1' => new MockService(),
			'service2' => 'not_a_service',
		];
		return new Tito('Test system', function ($name) use ($services){
			return $services[$name];
		});
	}

	protected function assertCallFailed($reason_substring, $out){
		$this->assertStringStartsWith('[false', trim($out));
		$this->assertContains($reason_substring, $out);
	}

	protected function titoExternalCall($opt, $params){
		$opt = array_map(function($v, $k){
			return "-$k$v";
		}, $opt, array_keys($opt));
		$cmd = PHP_BINARY.' '.__DIR__.'/../../test-tito.php '.implode(' ', $opt).' '.implode(' ', $params);
		exec($cmd, $out, $exit);
		return [implode("\n", $out), $exit];
	}

	protected function titoCall($opt, $params){
		$x = $this->createTestTito();
		return $x->do_call('test-script.php', $opt, $params);
	}

	public function testHelpNotFails(){
		list($out) = $this->titoCall([], []);
		$this->assertContains('test-script', $out, 'help problems');
	}

	public function testPlainSuccessfulCall(){
		list($out) = $this->titoCall([], ['service1', 'reply', 'asd']);
		$this->assertEquals('[true,"asd"]', trim($out));
	}

	public function testPlainSuccessfulCallP(){
		list($out) = $this->titoCall(['p'=>1], ['service1', 'reply', 'asd']);
		$this->assertEquals(trim(print_r([true, 'asd'], 1)), trim($out));
	}

	public function testPlainNoMethod(){
		list($out, $exit) = $this->titoCall(['e'=>1], ['service1']);
		$this->assertCallFailed('Provide a method name', $out);
		$this->assertEquals(1, $exit);
	}

	public function testPlainBadMethodT(){
		list($out) = $this->titoCall(['t'=>1], ['service1', 'asd']);
		$this->assertCallFailed('does not have method', $out);
	}

	public function testJsonSuccessfulCall(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply", ["asd"]]']);
		$this->assertEquals('[true,"asd"]', trim($out));
	}

	public function testJsonSuccessfulCallNoArgs(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply"]']);
		$this->assertEquals('[true,null]', trim($out));
	}

	public function testJsonBadFormat(){
		list($out) = $this->titoCall(['j'=>1], ['["asd"']);
		$this->assertCallFailed('Cant parse json call', $out);
	}

	public function testJsonBadDefinition(){
		list($out) = $this->titoCall(['j'=>1], ['"asd"']);
		$this->assertCallFailed('Call must be an array', $out);
	}

	public function testJsonNoMethod(){
		list($out) = $this->titoCall(['j'=>1], ['["service1"]']);
		$this->assertCallFailed('Provide a method name', $out);
	}

	public function testJsonBadArgs(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply", "asd"]']);
		$this->assertCallFailed('Call parameters must be an array', $out);
	}

	public function testNoSuchService(){
		list($out) = $this->titoCall([], ['asd', 'asd']);
		$this->assertCallFailed('No such service', $out);
	}

	public function testPlainEncoding(){
		list($out) = $this->titoCall(['b'=>'latin1'], ['service1', 'reply', 'asd']);
		$this->assertEquals('[true,"asd"]', trim($out));
	}

	public function testPlainNumericResult(){
		list($out) = $this->titoCall(['b'=>'latin1'], ['service1', 'length', 'asd']);
		$this->assertEquals('[true,3]', trim($out));
	}

	public function testPlainAssocResult(){
		list($out) = $this->titoCall(['b'=>'latin1'], ['service1', 'key_val', 'asd']);
		$this->assertEquals('[true,{"asd":1}]', trim($out));
	}

	public function testJsonQuiteEncoding(){
		list($out) = $this->titoCall(['j'=>1, 'i'=>'cp1251', 'q'=>1], ['["service1", "reply", ["'.chr(244).'"]]']);
		$this->assertEquals('"'.chr(244).'"', trim($out));
	}

	public function testJsonBadInputEncoding(){
		list($out) = $this->titoCall(['j'=>1, 'i'=>'xxx'], ['["service1", "reply", ["'.chr(244).'"]]']);
		$this->assertCallFailed('Cant convert encoding', $out);
	}

	public function testJsonBadValueEncoding(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply", ["'.chr(244).'"]]']);
		$this->assertCallFailed('Cant format result as JSON', $out);
	}

	public function testJsonQuiteNumeric(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1', 'q'=>1], ['["service1", "reply", [1]]']);
		$this->assertEquals('1', trim($out));
	}

	public function testJsonAssoc(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1'], ['["service1", "reply", [{"asd":1}]]']);
		$this->assertEquals('[true,{"asd":1}]', trim($out));
	}

	public function testJsonTooDeepRecursion(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1', 'd'=>3], ['["service1", "recursive", [5]]']);
		$this->assertCallFailed('Recursion is too deep', $out);
	}

	public function testJsonTooDeepRecursionPTR(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1', 'd'=>3, 'p'=>1, 't'=>1, 'r'=>1], ['["service1", "recursive", [5]]']);
		$this->assertContains('Recursion is too deep', $out);
	}

	public function testCleanParams(){
		$x = $this->createTestTito();
		$argv = ['test.php', '-pt', '-se', '-d', '1', 'service1', 'reply', '1'];
		$opt = ['p'=>1, 't'=>1, 's'=>1, 'e'=>1, 'd'=>'1'];
		$params = $x->exclude_options_from_params($argv, $opt);
		$this->assertEquals(['service1', 'reply', '1'], $params);
	}

	public function testCleanWeirdParams(){
		$x = $this->createTestTito();
		$argv = ['test.php', 'service1', '-pt', '-se', 'reply', '1'];
		$opt = ['p'=>1, 't'=>1, 's'=>1, 'e'=>1];
		$params = $x->exclude_options_from_params($argv, $opt);
		$this->assertEquals(['service1', 'reply', '1'], $params);
	}

	public function testEval(){
		/** @noinspection SpellCheckingInspection */
		list($out) = $this->titoCall(['x' => 'putenv("asd=zxc");'], ['service1', 'getEnv', 'asd']);
		$this->assertEquals('[true,"zxc"]', trim($out));
	}

	public function testErrorReporting(){
		list($out) = $this->titoCall(['r'=>1], ['service1', 'reply']);
		$this->assertCallFailed('Missing argument', $out);
	}

	public function testFatalMethod(){
		list($out) = $this->titoExternalCall(['e'=>1], ['service1', 'call_undefined']);
		$this->assertCallFailed('Call to undefined method', $out);
	}

} 

<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\tito;

class TitoTest extends \PHPUnit_Framework_TestCase {

	protected function createTestTito(){
		$services = [
			'service1' => new MockService(),
			'service2' => 'not_a_service',
		];
		return new Tito("Test system", function ($name) use ($services){
			return $services[$name];
		});
	}

	protected function assertCallFailed($reason_substring, $out){
		$this->assertStringStartsWith('[false', trim($out));
		$this->assertContains($reason_substring, $out);
	}

	protected function titoCall($opt, $params){
		$x = $this->createTestTito();
		return $x->do_call('test-script.php', $opt, $params);
	}

	function testHelpNotFails(){
		list($out) = $this->titoCall([], []);
		$this->assertContains('test-script', $out, 'help problems');
	}

	function testPlainSuccessfulCall(){
		list($out) = $this->titoCall([], ['service1', 'reply', 'asd']);
		$this->assertEquals('[true,"asd"]', trim($out));
	}

	function testPlainSuccessfulCallP(){
		list($out) = $this->titoCall(['p'=>1], ['service1', 'reply', 'asd']);
		$this->assertEquals(trim(print_r([true, "asd"], 1)), trim($out));
	}

	function testPlainNoMethod(){
		list($out, $exit) = $this->titoCall(['e'=>1], ['service1']);
		$this->assertCallFailed('Provide a method name', $out);
		$this->assertEquals(1, $exit);
	}

	function testPlainBadMethodT(){
		list($out) = $this->titoCall(['t'=>1], ['service1', 'asd']);
		$this->assertCallFailed('does not have method', $out);
	}

	function testJsonSuccessfulCall(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply", ["asd"]]']);
		$this->assertEquals('[true,"asd"]', trim($out));
	}

	function testJsonSuccessfulCallNoArgs(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply"]']);
		$this->assertEquals('[true,null]', trim($out));
	}

	function testJsonBadFormat(){
		list($out) = $this->titoCall(['j'=>1], ['["asd"']);
		$this->assertCallFailed('Cant parse json call', $out);
	}

	function testJsonBadDefinition(){
		list($out) = $this->titoCall(['j'=>1], ['"asd"']);
		$this->assertCallFailed('Call must be an array', $out);
	}

	function testJsonNoMethod(){
		list($out) = $this->titoCall(['j'=>1], ['["service1"]']);
		$this->assertCallFailed('Provide a method name', $out);
	}

	function testJsonBadArgs(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply", "asd"]']);
		$this->assertCallFailed('Call parameters must be an array', $out);
	}

	function testNoSuchService(){
		list($out) = $this->titoCall([], ['asd', 'asd']);
		$this->assertCallFailed('No such service', $out);
	}

	function testPlainEncoding(){
		list($out) = $this->titoCall(['b'=>'latin1'], ['service1', 'reply', 'asd']);
		$this->assertEquals('[true,"asd"]', trim($out));
	}

	function testPlainNumericResult(){
		list($out) = $this->titoCall(['b'=>'latin1'], ['service1', 'length', 'asd']);
		$this->assertEquals('[true,3]', trim($out));
	}

	function testPlainAssocResult(){
		list($out) = $this->titoCall(['b'=>'latin1'], ['service1', 'key_val', 'asd']);
		$this->assertEquals('[true,{"asd":1}]', trim($out));
	}

	function testJsonQuiteEncoding(){
		list($out) = $this->titoCall(['j'=>1, 'i'=>'cp1251', 'q'=>1], ['["service1", "reply", ["'.chr(244).'"]]']);
		$this->assertEquals('"'.chr(244).'"', trim($out));
	}

	function testJsonBadInputEncoding(){
		list($out) = $this->titoCall(['j'=>1, 'i'=>'xxx'], ['["service1", "reply", ["'.chr(244).'"]]']);
		$this->assertCallFailed('Cant convert encoding', $out);
	}

	function testJsonBadValueEncoding(){
		list($out) = $this->titoCall(['j'=>1], ['["service1", "reply", ["'.chr(244).'"]]']);
		$this->assertCallFailed('Cant format result as JSON', $out);
	}

	function testJsonQuiteNumeric(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1', 'q'=>1], ['["service1", "reply", [1]]']);
		$this->assertEquals('1', trim($out));
	}

	function testJsonAssoc(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1'], ['["service1", "reply", [{"asd":1}]]']);
		$this->assertEquals('[true,{"asd":1}]', trim($out));
	}

	function testJsonTooDeepRecursion(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1', 'd'=>3], ['["service1", "recursive", [5]]']);
		$this->assertCallFailed('Recursion is too deep', $out);
	}

	function testJsonTooDeepRecursionPTR(){
		list($out) = $this->titoCall(['j'=>1, 'b'=>'latin1', 'd'=>3, 'p'=>1, 't'=>1, 'r'=>1], ['["service1", "recursive", [5]]']);
		$this->assertContains('Recursion is too deep', $out);
	}

	function testCleanParams(){
		$x = $this->createTestTito();
		$argv = ['test.php', '-pt', '-se', '-d', '1', 'service1', 'reply', '1'];
		$opt = ['p'=>1, 't'=>1, 's'=>1, 'e'=>1, 'd'=>'1'];
		$params = $x->exclude_options_from_params($argv, $opt);
		$this->assertEquals(['service1', 'reply', '1'], $params);
	}

	function testCleanWeirdParams(){
		$x = $this->createTestTito();
		$argv = ['test.php', 'service1', '-pt', '-se', 'reply', '1'];
		$opt = ['p'=>1, 't'=>1, 's'=>1, 'e'=>1];
		$params = $x->exclude_options_from_params($argv, $opt);
		$this->assertEquals(['service1', 'reply', '1'], $params);
	}

} 

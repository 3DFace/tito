<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\tito;

use PHPUnit\Framework\TestCase;

class TitoTest extends TestCase
{

	protected function createTestTito() : Tito
	{
		$services = [
			'service1' => new MockService(),
			'service2' => 'not_a_service',
		];
		return new Tito('Test system', static function ($name) use ($services) {
			return $services[$name];
		});
	}

	protected static function assertCallFailed($reason_substring, $out) : void
	{
		self::assertStringStartsWith('[false', \trim($out));
		self::assertStringContainsString($reason_substring, $out);
	}

	protected function titoExternalCall($opt, $params) : array
	{
		$opt = \array_map(static function ($v, $k) {
			return "-$k$v";
		}, $opt, \array_keys($opt));
		$cmd = PHP_BINARY.' '.__DIR__.'/../../test-tito.php '.\implode(' ', $opt).' '.\implode(' ', $params);
		\exec($cmd, $out, $exit);
		return [\implode("\n", $out), $exit];
	}

	protected function titoCall($opt, $params) : array
	{
		$x = $this->createTestTito();
		return $x->do_call('test-script.php', $opt, $params);
	}

	public function testHelpNotFails() : void
	{
		[$out] = $this->titoCall([], []);
		self::assertStringContainsString('test-script', $out, 'help problems');
	}

	public function testPlainSuccessfulCall() : void
	{
		[$out] = $this->titoCall([], ['service1', 'reply', 'asd']);
		self::assertEquals('[true,"asd"]', \trim($out));
	}

	public function testPlainSuccessfulCallP() : void
	{
		[$out] = $this->titoCall(['p' => 1], ['service1', 'reply', 'asd']);
		self::assertEquals(\trim(\print_r([true, 'asd'], 1)), \trim($out));
	}

	public function testPlainNoMethod() : void
	{
		[$out, $exit] = $this->titoCall(['e' => 1], ['service1']);
		self::assertCallFailed('Provide a method name', $out);
		self::assertEquals(1, $exit);
	}

	public function testPlainBadMethodT() : void
	{
		[$out] = $this->titoCall(['t' => 1], ['service1', 'asd']);
		self::assertCallFailed('does not have method', $out);
	}

	public function testJsonSuccessfulCall() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['["service1", "reply", ["asd"]]']);
		self::assertEquals('[true,"asd"]', \trim($out));
	}

	public function testJsonFailedCallNoArgs() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['["service1", "reply"]']);
		self::assertCallFailed('few arguments', $out);
	}

	public function testJsonBadFormat() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['["asd"']);
		self::assertCallFailed('Syntax error', $out);
	}

	public function testJsonBadDefinition() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['"asd"']);
		self::assertCallFailed('Call must be an array', $out);
	}

	public function testJsonNoMethod() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['["service1"]']);
		self::assertCallFailed('Provide a method name', $out);
	}

	public function testJsonBadArgs() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['["service1", "reply", "asd"]']);
		self::assertCallFailed('Call parameters must be an array', $out);
	}

	public function testNoSuchService() : void
	{
		[$out] = $this->titoCall([], ['asd', 'asd']);
		self::assertCallFailed('No such service', $out);
	}

	public function testPlainEncoding() : void
	{
		[$out] = $this->titoCall(['b' => 'latin1'], ['service1', 'reply', 'asd']);
		self::assertEquals('[true,"asd"]', \trim($out));
	}

	public function testPlainNumericResult() : void
	{
		[$out] = $this->titoCall(['b' => 'latin1'], ['service1', 'length', 'asd']);
		self::assertEquals('[true,3]', \trim($out));
	}

	public function testPlainAssocResult() : void
	{
		[$out] = $this->titoCall(['b' => 'latin1'], ['service1', 'key_val', 'asd']);
		self::assertEquals('[true,{"asd":1}]', \trim($out));
	}

	public function testJsonQuiteEncoding() : void
	{
		[$out] = $this->titoCall(['j' => 1, 'i' => 'cp1251', 'q' => 1], ['["service1", "reply", ["'.\chr(244).'"]]']);
		self::assertEquals('"'.\chr(244).'"', \trim($out));
	}

	public function testJsonBadInputEncoding() : void
	{
		[$out] = $this->titoCall(['j' => 1, 'i' => 'xxx'], ['["service1", "reply", ["'.\chr(244).'"]]']);
		self::assertCallFailed('Unknown encoding', $out);
	}

	public function testJsonBadValueEncoding() : void
	{
		[$out] = $this->titoCall(['j' => 1], ['["service1", "reply", ["'.\chr(244).'"]]']);
		self::assertCallFailed('Malformed', $out);
	}

	public function testJsonQuiteNumeric() : void
	{
		[$out] = $this->titoCall(['j' => 1, 'b' => 'latin1', 'q' => 1], ['["service1", "reply", [1]]']);
		self::assertEquals('1', \trim($out));
	}

	public function testJsonAssoc() : void
	{
		[$out] = $this->titoCall(['j' => 1, 'b' => 'latin1'], ['["service1", "reply", [{"asd":1}]]']);
		self::assertEquals('[true,{"asd":1}]', \trim($out));
	}

	public function testJsonTooDeepRecursion() : void
	{
		[$out] = $this->titoCall(['j' => 1, 'b' => 'latin1', 'd' => 3], ['["service1", "recursive", [5]]']);
		self::assertCallFailed('Maximum stack depth exceeded', $out);
	}

	public function testJsonTooDeepRecursionPTR() : void
	{
		[$out] = $this->titoCall(['j' => 1, 'b' => 'latin1', 'd' => 3,  't' => 1, 'r' => 1],
			['["service1", "recursive", [5]]']);
		self::assertStringContainsString('Maximum stack depth exceeded', $out);
	}

	public function testCleanParams() : void
	{
		$x = $this->createTestTito();
		$argv = ['test.php', '-pt', '-se', '-d', '1', 'service1', 'reply', '1'];
		$opt = ['p' => 1, 't' => 1, 's' => 1, 'e' => 1, 'd' => '1'];
		$params = $x->exclude_options_from_params($argv, $opt);
		self::assertEquals(['service1', 'reply', '1'], $params);
	}

	public function testCleanWeirdParams() : void
	{
		$x = $this->createTestTito();
		$argv = ['test.php', 'service1', '-pt', '-se', 'reply', '1'];
		$opt = ['p' => 1, 't' => 1, 's' => 1, 'e' => 1];
		$params = $x->exclude_options_from_params($argv, $opt);
		self::assertEquals(['service1', 'reply', '1'], $params);
	}

	public function testEval() : void
	{
		/** @noinspection SpellCheckingInspection */
		[$out] = $this->titoCall(['x' => 'putenv("asd=zxc");'], ['service1', 'getEnv', 'asd']);
		self::assertEquals('[true,"zxc"]', \trim($out));
	}

	public function testErrorReporting() : void
	{
		[$out] = $this->titoCall(['r' => 1], ['service1', 'reply']);
		self::assertCallFailed('Too few arguments', $out);
	}

	public function testFatalMethod() : void
	{
		[$out] = $this->titoExternalCall(['e' => 1], ['service1', 'call_undefined']);
		self::assertCallFailed('Call to undefined method', $out);
	}

} 

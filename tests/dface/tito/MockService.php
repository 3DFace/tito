<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\tito;

class MockService
{

	public function reply($val)
	{
		return $val;
	}

	public function length($val) : int
	{
		return strlen($val);
	}

	public function key_val($val) : array
	{
		return [$val => 1];
	}

	public function getEnv($name)
	{
		return getenv($name);
	}

	public function getIni($name) : string
	{
		return ini_get($name);
	}

	public function recursive($deep) : array
	{
		if ($deep <= 0) {
			return [$deep];
		}
		return [$this->recursive($deep - 1)];
	}

	public function call_undefined()
	{
		/** @noinspection PhpUndefinedMethodInspection */
		return $this->a();
	}

}

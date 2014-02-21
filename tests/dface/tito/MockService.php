<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\tito;

class MockService {

	function reply($val){
		return $val;
	}

	function length($val){
		return strlen($val);
	}

	function key_val($val){
		return [$val=>1];
	}

	function recursive($deep){
		if($deep<=0){
			return $deep;
		}else{
			return [$this->recursive($deep - 1)];
		}
	}

	function call_undefined(){
		return $this->a();
	}

} 

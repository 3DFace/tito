<?php
/* author: Denis Ponomarev <ponomarev@gmail.com> */

use dface\tito\MockService;
use dface\tito\Tito;

include_once __DIR__.'/bootstrap.php';

$tito = new Tito('Test', static function (){
	return new MockService();
});
$tito->call();

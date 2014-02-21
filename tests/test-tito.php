<?php
/* author: Denis Ponomarev <ponomarev@gmail.com> */

include_once __DIR__.'/bootstrap.php';

$tito = new \dface\tito\Tito('Test', function (){
	return new \dface\tito\MockService();
});
$tito->call();

<?php
return [
    'storage' => 'D:/Catch',
    'version' => date('Y', time()),
    'database' => [
    	'driver' => 'mysql',
	    'host' => 'localhost',
	    'port' => '3309',
	    'database' => 'information_schema',
	    'username' => 'root',
	    'password' => 'root',
	    'charset' => 'utf8',
	    'collation' => 'utf8_unicode_ci',
	    'prefix' => ''
    ],
];
<?php
	echo "Converting {$_SERVER['argv'][1]} To Unsigned Integer\n";
	echo sprintf("%u\n", ip2long($_SERVER['argv'][1]));

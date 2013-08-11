<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
	require_once('sqlbldr.php');

	$test = db::i()->select()->from('table1')
					->where('something', '=', 'someval')
					->open()
						->select()->from('othertable')
						->where('badguys', '!=', 'goodguys')
					->close()
					->debug(false, false);
					
	$something = db::i()->select()->from('sg')->debug();
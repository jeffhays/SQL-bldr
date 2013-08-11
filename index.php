<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
	require_once('sqlbldr.php');

	// Sub Query Example
	$test = db::i()->select('ID')->from('table1')
					->where('someguys', 'NOT LIKE', 'badguy%')
					->andwhere('firstname', 'IN')
					->open()
						->select('firstname')->from('othertable')
						->where('badguys', '!=', 'goodguys')
					->close()
					->debug();

	
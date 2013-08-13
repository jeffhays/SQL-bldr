<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);


/*
	// Old bldr class
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
*/




	// New PDO class testing
	require_once('sqlbldr.pdo.php');

	// Basic SELECT
/*
	$test = db::i()->select()->from('table1')->asobject();
	db::i()->debug($test);
*/

	// SELECT Sub Query Example
/*
	$test = db::i()->select()->from('table1')
					->where('name', 'LIKE', "jeffrey'")
					->andwhere('ID', 'IN')
					->open()
						->select('ID')->from('othertable')->where('firstname', '=', 'jeff')
					->close()
					->debug();
*/

	$test = db::i()->select()->from('table1')
					->where('something', '=', 'jeff@email.net')->debug();

/*
	// Export results as CSV
	db::i()->select()->from('table1')->ascsv();
*/
	
	 	
/*
	// Basic INSERT
	$id = db::i()->insert('table1')->values(array('name' => 'stupid', 'option' => 'fun value'))->run();
	db::i()->debug($id);
*/

	
	// Basic UPDATE
	db::i()->update('table1')->set(array('option' => 'newval'))->where('name', '=', 'jeffrey')->debug();
	
	
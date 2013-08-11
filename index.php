<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);


/*
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

	// Sub Query Example
/*
	$test = db::i()->select()->from('table1')
					->where('name', 'LIKE', "jeffrey'")
					->andwhere('ID', 'IN')
					->open()
						->select('ID')->from('othertable')->where('firstname', '=', 'jeff')
					->close()
					->debug();
*/
	
	$test = db::i()->select()->from('table1')->rows();
	db::i()->debug($test);

/*
	// Export results as CSV
	db::i()->select()->from('table1')->ascsv();
*/

/*
		// Basic insert
		db::i()->insert('table1')->values(array('name' => 'stupid', 'option' => 'fun value'))->run()->debug();
*/
	
	
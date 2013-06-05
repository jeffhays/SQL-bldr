<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
	require_once('sqlbldr.new.php');
/* 	require_once('db/loader.php'); */
	$test = db::i()->select()->from('table1')->where('name', 'LIKE', '%e%')->asobject();
/* 	$test = db::i()->insert('table1')->values(array('name'=>'jeffrey', 'option' => 1337))->run(); */
/*
	$test = db::select('post_author', 'ping_status')
			->from('wp_posts')
			->where('post_content = ""')
			->order(array('post_content'), 'DESC')
			->asobject();
*/
/*
    $i = 0;
		$test = db::insert('table1', array('name', 'option'))->values(array('someguy'.$i, 'funny'.$i));
*/

/* 	$test = db::select()->setdb('test')->from('table1')->asarray(); */
/*
	$test = db::select()->disconnect();
	$test = db::select()->connect();
	
*/
/* 	$test = db::delete('table1')->where('option', '=', "'funny2"); */
/*
  $test = db::select()->from('table1')->where('option', '>', 1)->order('option')->asobject();
  $test = db::select()->from('table1')->asobject();
  $test = db::select()->from('table1')->where('option', '>', 1)->asobject();
*/
/*   $test = db::select()->from('table1')->where('`option` LIKE "%un%"')->asobject(); */
/* 	$test = db::select()->from('table1')->asobject(); */
/* 	$test = db::select()->from('table1')->where('option', 'NOT IN', array(11))->asobject(); */


/*   $test = db::insert('table1')->values(array('name'=>'someguy', 'option'=>rand())); */
/*
  $test = db::insert('table1')->values(array('name'=>'someguy1', 'option'=>15));
  $test = db::insert('table1')->values(array('name'=>'someguy2', 'option'=>15));
  $test = db::insert('table1')->values(array('name'=>'someguy3', 'option'=>15));
  $test = db::insert('table1')->values(array('name'=>'someguy4', 'option'=>15));
  $test = db::insert('table1')->values(array('name'=>'someguy5', 'option'=>15));
*/

	echo '<pre>';
	print_r($test);
	echo '</pre>';
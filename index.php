<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
	require_once('sqlbldr.php');


$array = unserialize('a:3:{i:2;a:6:{s:5:"title";s:13:"Join Our Team";s:7:"content";s:252:"<a href="http://infinity.offthewallmedia.com/jobs">Search Jobs</a>

<a href="http://infinity.offthewallmedia.com/careers/employee-testimonials/">Employee Testimonials</a>

<a href="http://infinity.offthewallmedia.com/contact">Contact Recruiting</a>";s:5:"image";s:67:"http://infinity.offthewallmedia.com/files/2013/03/join-our-team.jpg";s:4:"more";s:0:"";s:9:"show_fade";s:3:"yes";s:11:"flush_right";s:0:"";}i:3;a:6:{s:5:"title";s:15:"Upcoming Events";s:7:"content";s:60:"2013 Midwest
Symposium
April 13, 2013
Rolling Meadows, IL";s:5:"image";s:67:"http://infinity.offthewallmedia.com/files/2013/04/infinity-book.png";s:4:"more";s:0:"";s:9:"show_fade";s:0:"";s:11:"flush_right";s:3:"yes";}s:12:"_multiwidget";i:1;}');


die(var_dump($array));

foreach($array as $k=>$v) $array[$k] = str_replace('infinityrehab.com', 'infinityrehab.com', $v);
die(serialize($array));




/* 	require_once('db/loader.php'); */
/* 	$test = db::i()->select()->from('table1')->where('name', 'LIKE', '%e%')->asobject(); */
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
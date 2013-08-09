<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
	require_once('sqlbldr.php');


	$test = db::i()->select()->from('test')->row();
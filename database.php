<?php
// MySQL Settings
$db_host = 'localhost';
$db_user = '';
$db_pass = '';
$db_database = '';

// Connect to the database
mysql_connect ($db_host, $db_user, $db_pass) or die(mysql_error());
mysql_selectdb ($db_database) or die(mysql_error());

// $query = "insert into user (username, password, salt) values ('$username', '$encrypted', '$salt')";
// mysql_query ($query) or die ('Could not create user.');

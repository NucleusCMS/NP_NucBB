<?php
$name = basename(__FILE__, '.php');

$CONF = array();
$CONF['Self'] = $name.'.php';

include('./config.php');

selectBlog($name);
selector();

?>

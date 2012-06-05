<?php

  include '../lib/library.inc.php';

  $system = get_system();
  $action = 'result';
  $url  = urldecode( $_REQUEST[ 'url' ] );

  _log_action_sql_insert( $system, $action, $url );

  header( 'Location: ' . $url ) ;
  
?>

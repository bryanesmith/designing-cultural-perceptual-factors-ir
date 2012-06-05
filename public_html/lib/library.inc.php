<?php

  define( 'NORMAL', 1 );
  define( 'URL_SAFE', 2 );

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_query() {

    // Sice get_query is called once per page, perfect place
    // to log action
    _log_action();

    return _get_param( 'q' );
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_system() {
    preg_match( '/^\/(.*?)\/.*$/', $_SERVER[ 'SCRIPT_NAME' ], $matches );
    return $matches[1];
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_system() {
    $system = $_REQUEST[ 'system' ];

    if ( !isset( $system ) ) {
      $system = _get_system();
    }

    return $system;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _log_action() {

    $action = $_REQUEST[ 'action' ];

    $system = get_system();
    
    $notes;

    switch( $action ) {

      case 'query':
        $query = _get_param( 'q' );
        $notes = $query[ NORMAL ];
        break;

      case 'pagination':
        $notes = get_current_page();
        break;

      case 'cluster':
        $current_cluster = get_current_cluster();
        $notes = $current_cluster[ NORMAL ];
        break;

    }

    _log_action_sql_insert( $system, $action, $notes );

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function wrap_item_link( $url ) {
    return '/actions/result.php?system=' . get_system() . '&url=' . urlencode( $url );
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _log_action_sql_insert( $system, $action, $notes ) {

    $sql = "INSERT INTO user_actions( timestamp, system, action, notes ) VALUES ( CURRENT_TIMESTAMP, ?, ?, ?)";
    $args = array( $system, $action, $notes );

    $dbh = get_dbh();

    $sth = $dbh->prepare( $sql );
    
    $sth->execute( $args );
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_current_cluster() {

    if ( $_REQUEST[ 'cluster' ] ) {
      return _get_param( 'cluster' );
    } else {
      $val = array();

      $val[ URL_SAFE ] = 'all';
      $val[ NORMAL ] = 'all';

      return $val;
    }

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_param( $name ) {
    $val = array();

    $val[ URL_SAFE ] = $_REQUEST[ $name ];
    $val[ NORMAL ] = urldecode( $val[ URL_SAFE ] );

    return $val;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_current_page() {
    $current_page = $_REQUEST[ 'page' ];
    if ( ! $current_page ) { 
      $current_page = 1;
    } else {
      $current_page = intval( $current_page );
    }

    return $current_page;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_item_start( $current_page, $page_size ) {
    return ( $current_page - 1 ) * $page_size + 1;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_item_end( $item_start, $page_size, $total_count ) {
    $item_end =  $item_start + $page_size - 1; 

    if ( $item_end > $total_count ) {
      $item_end = $total_count;
    }

    return $item_end;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_settings() {
    return parse_ini_file("../../settings.ini");
  }

  // - - - - - - - - - - - - - - - - - - - - - - - - 
  function search( $query ) {
    $json = _get_json( $query );

    return json_decode( $json, TRUE );
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_json( $query ) {
    $json = _get_json_cache( $query );

    if ( !isset( $json ) ) {
      //$json = _get_json_google( $query );
      $json = _get_json_bing( $query );
      _set_json_cache( $query, $json );
    }

    return $json;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _purge_cache() {
    $sql = "DELETE FROM search_cache WHERE retrieved < DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $args = array();

    $dbh = get_dbh();

    $sth = $dbh->prepare( $sql );
    
    $sth->execute( $args );
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_json_cache( $query ) {

    _purge_cache();

    $sql = "SELECT json FROM search_cache WHERE query = ?";
    $args = array( $query );

    $dbh = get_dbh();

    $sth = $dbh->prepare( $sql );
    $sth->setFetchMode(PDO::FETCH_ASSOC);  
    $sth->execute($args);

    if( $row = $sth->fetch() ) {  
      return $row[ 'json' ];
    }  

    return NULL;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _set_json_cache( $query, $json ) {

    $sql = "INSERT INTO search_cache( query, retrieved, json ) VALUES ( ?, CURRENT_TIMESTAMP, ? )";
    $args = array( $query, $json );

    $dbh = get_dbh();

    $sth = $dbh->prepare( $sql );
    
    $sth->execute( $args );

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_bing_url( $query, $start ) {
    $settings = get_settings();

    return 'http://api.bing.net/json.aspx?Appid=' . $settings[ 'bing_appid' ] .  '&query=' . urlencode( $query ) . '&sources=web&web.count=50&web.offset=' . $start;
  }
  
  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_json_bing( $query ) {

    $pages = 2;

    // Create list of curl handles
    $curl_handles = array();
    $urls = array();
    
    for( $i = 0; $i < $pages; $i++ ) {
      $start = 50 * $i + 1;
      $url = _get_bing_url( $query, $start );
      array_push( $urls, $url );
      array_push( $curl_handles, _get_curl_handle( $url ) );
    }

//die( 'DEBUG: ' . count( $urls ) . ' - ' . implode( ' ', $urls ) );

    // Create the multiple curl handle
    $mh = curl_multi_init();

    // Add all curl handles
    foreach( $curl_handles as $ch ) {
      curl_multi_add_handle( $mh, $ch );
    }

    $active = null;

    //execute the handles
    do {
      $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
      if (curl_multi_select($mh) != -1) {
        do {
          $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
      }
    }

    $results = array();

    // Get the results
    foreach($curl_handles as $ch ) { 

      // Get the json
      $json = curl_multi_getcontent( $ch ); 

      // Parse json and add individual results
      $page = json_decode( $json, TRUE );

      $theseResults = $page[ 'SearchResponse' ][ 'Web' ][ 'Results' ];

      foreach( $theseResults as $bing_item ) {
        $google_item = convert_bing_to_google( $bing_item );
        array_push( $results, $google_item );
      }

    } 

    // Close the handles
    foreach($curl_handles as $ch ) { 
      curl_multi_remove_handle( $mh, $ch );
    }

    curl_multi_close( $mh );
    
    // Encode all ordered results as json
    return json_encode( $results );

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function convert_bing_to_google( $bing_item ) {
    $google_item = array();

    $google_item[ 'title' ] = $bing_item[ 'Title' ];
    $google_item[ 'htmlSnippet' ] = $bing_item[ 'Description' ];
    $google_item[ 'link' ] = $bing_item[ 'Url' ];
    $google_item[ 'display-link' ] = $bing_item[ 'DisplayUrl' ];

    return $google_item;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_json_google( $query ) {

    $pages = 10;

    // Create list of curl handles
    $curl_handles = array();
    for( $i = 0; $i < $pages; $i++ ) {
      $start = 10 * $i + 1;
      $url = _get_google_url( $query, $start );
      array_push( $curl_handles, _get_curl_handle( $url ) );
    }

    // Create the multiple curl handle
    $mh = curl_multi_init();

    // Add all curl handles
    foreach( $curl_handles as $ch ) {
      curl_multi_add_handle( $mh, $ch );
    }

    $active = null;

    //execute the handles
    do {
      $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
      if (curl_multi_select($mh) != -1) {
        do {
          $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
      }
    }

    $results = array();

    // Get the results
    foreach($curl_handles as $ch ) { 

      // Get the json
      $json = curl_multi_getcontent( $ch ); 

      // Parse json and add individual results
      $page = json_decode( $json, TRUE );

      foreach( $page[ 'items' ] as $item ) {
        array_push( $results, $item );
      }

    } 

    // Close the handles
    foreach($curl_handles as $ch ) { 
      curl_multi_remove_handle( $mh, $ch );
    }

    curl_multi_close( $mh );
    
    // Encode all ordered results as json
    return json_encode( $results );

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_curl_handle( $url ) {

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    //curl_setopt($ch, CURLOPT_HEADER, 0);

    return $ch;

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  /*
  function _get_json_google( $query ) {

    $results = array();

    for( $i = 0; $i < 10; $i++ ) {

      $start = 10 * $i + 1;

      $url = _get_google_url( $query, $start );

      $json = file_get_contents( $url );

      // Returns false if failed
      if ( ! $json ) {
        die( "Failed: $json" );
      }

      $page = json_decode( $json, TRUE );

      foreach( $page[ 'items' ] as $item ) {
        array_push( $results, $item );
      }

    }

    return json_encode( $results );
  }
  */

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _get_google_url( $query, $start ) {

    $settings = get_settings();

    return 'https://www.googleapis.com/customsearch/v1?key=' . $settings[ 'key' ] . '&cx=' . $settings[ 'cx' ] . '&start=' . $start . '&q=' . urlencode( $query );

  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  $dbh = NULL;
  function get_dbh() {

    global $dbh;

    if ( ! isset( $dbh ) ) {

      $settings = get_settings();

      $host   = $settings[ 'mysql_host' ];
      $dbname = $settings[ 'mysql_dbname' ];
      $user   = $settings[ 'mysql_user' ];
      $pass   = $settings[ 'mysql_password' ];

      $str = "mysql:host=${host};dbname=${dbname}";
      $dbh = new PDO( $str, $user, $pass );

    }

    return $dbh;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function cluster( $results ) {
    $xml_in = get_xml_carrot2( $results );

    // Source: http://davidwalsh.name/execute-http-post-php-curl
    $url = 'http://localhost:8080/dcs/rest';

    $fields = array(
                'dcs.c2stream'     => urlencode( $xml_in ),
                'dcs.output.format'=> 'JSON',
                'dcs.algorithm'    => 'lingo',
              );

    //url-ify the data for the POST
    $fields_string = '';
    foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    rtrim($fields_string,'&');

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_POST,count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    //execute post
    //ob_start();      // Prevent output 
    $result = curl_exec($ch); 
    //ob_end_clean();  // End preventing output 

    //close connection
    curl_close($ch);

    return json_decode( $result, TRUE );
    //return $result;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_xml_carrot2( $results ) {

    $xml = '<searchresult>';
    foreach( $results as $item ) {
      $xml .= _document_xml_carrot2( $item );
    }
    $xml .= '</searchresult>';

    return $xml;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function _document_xml_carrot2( $item ) {

    $title   = xmlentities( $item[ 'title' ] );
    $link    = xmlentities( $item[ 'link' ] );
    $snippet = xmlentities( $item[ 'snippet' ] );
  
    /*
    $title   = $item[ 'title' ] ;
    $link    = $item[ 'link' ] ;
    $snippet = $item[ 'snippet' ] ;
    */

    $xml  = '<document>';
    $xml .= "<title>${title}</title>";
    $xml .= "<snippet>${snippet}</snippet>";
    $xml .= "<url>${link}</url>";
    $xml .= '</document>';

    return $xml;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  // Source: http://stackoverflow.com/questions/3957360/generating-xml-document-in-php-escape-characters
  function xmlentities($string) {
    return str_replace(array("&", "<", ">", "\"", "'"), array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;" ), $string);
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function html_anchor( $label, $link, $target_blank = false ) {
    if ( $target_blank ) {
      return "<a href=\"$link\" target=\"_blank\">${label}</a>";
    } else {
      return "<a href=\"$link\">${label}</a>";
    }
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function find_cluster( $cluster_name, $clusters ) {

    $cluster = null;

    foreach( $clusters[ 'clusters' ] as $next_cluster ) {
      $phrases = implode( ',', $next_cluster[ 'phrases' ] );
      if ( $cluster_name == $phrases ) {
        $cluster = $next_cluster;
        break;
      }
    }

    return $cluster;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function filter($current_cluster, $clusters, $results) {
    $cluster = find_cluster( $current_cluster, $clusters );

    $new_results = array();

    foreach( $cluster[ 'documents' ] as $index ) {
      array_push( $new_results, $results[ $index ] );
    }
    
    return $new_results;
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function print_pagination( $path, $current_cluster, $query,  $current_page, $total_pages ) {
  ?>
        <div class="results-pagination">
          <span>Page: </span>
          <ul>

            <?php

              for ( $this_page = 1; $this_page <= $total_pages; $this_page++ ) {
                if ( $this_page == $current_page ) {
                  print "<li><strong>${this_page}</strong></li>" . "\n";
                } else {
                  print "<li><a href=\"${path}?action=pagination&page=${this_page}&cluster=" . $current_cluster[ URL_SAFE ] . "&q=" . $query[ URL_SAFE ] . "\">${this_page}</a></li>" . "\n";
                }
              }

            ?>
          </ul>
        </div>

  <?php
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function print_item_summary( $item_start, $item_end, $total_count ) {
  ?>
        <div class="result-summary">
          Results <strong><?php echo $item_start ?>-<?php echo $item_end ?></strong> of <strong><?php print $total_count ?></strong>
        </div>
  <?php
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -
  function get_cluster_name( $cluster ) {
    return implode( ',', $cluster[ 'phrases' ] );
  }

  // - - - - - - - - - - - - - - - - - - - - - - - -

?>

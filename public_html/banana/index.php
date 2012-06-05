<?php

  include '../lib/library.inc.php';

  // - - - - - - - - - - - - - - - - - - - - - - - - - 
  $query   = get_query();
  $current_cluster = get_current_cluster();

  // - - - - - - - - - - - - - - - - - - - - - - - - - 
  // Perform search, but only if there is a query
  if ( $query[ NORMAL ] ) {

    $results = search( $query[ NORMAL ] );
    $clusters = cluster( $results );

    $total_unfiltered_results = count( $results );

    // - - - - - - - - - - - - - - - - - - - - - - - - - 

    // Filter if cluster
    if ( $current_cluster[ NORMAL ] != 'all' ) {
      $results = filter($current_cluster[ NORMAL ], $clusters, $results);
    }

  }

  // - - - - - - - - - - - - - - - - - - - - - - - - - 
  $path = $_SERVER['SCRIPT_NAME'];

  // - - - - - - - - - - - - - - - - - - - - - - - - - 
  $total_count = count( $results );

  $page_size = 10;

  $current_page = get_current_page();

  $item_start = get_item_start( $current_page, $page_size );
  $item_end = get_item_end( $item_start, $page_size, $total_count );

  $total_pages = ceil( $total_count / $page_size );

?>

<html>

<head>

  <link type="text/css" rel="stylesheet" media="all" href="/styles/twitter-bootstrap/bootstrap.css" />
  <link type="text/css" rel="stylesheet" media="all" href="/styles/style.css" />

  <title>Banana search</title>
</head>

<body class="banana <?php print $query[ NORMAL ] ? 'results' : 'no-results' ?>">
  <div class="wrapper">

    <div class="header">
      <h1>Banana <span>search</span></h1>

      <form method="get" class="query-box">
        <input type="input" name="q" placeholder="e.g., europe" value="<?php print $query[ NORMAL ] ?>" />
        <input type="hidden" name="action" value="query"/>
        <input type="submit" value="search"/>
      </form>

      <?php 
        if ( $query[ NORMAL ] ) {

          print_pagination( $path, $current_cluster, $query,  $current_page, $total_pages );

          print_item_summary( $item_start, $item_end, $total_count );

        } 
      ?>

    </div>

    
    <!-- - - - - - - - - - - - - - - - - - - - - - - - - - - - -->
    <?php if ( $query[ NORMAL ] ) { ?>

      <div class="search-result-listings">
        <ul>
        <?php

          for( $i = $item_start - 1; $i < $item_end; $i++ ) {
            $item = $results[ $i ];
          ?>
            <li>
              <span class="number"><?php print $i + 1 ?></span>
              <?php print html_anchor( $item[ 'title' ], wrap_item_link( $item[ 'link' ] ), true ); ?>
              <div class="snippet"><?php print $item[ 'htmlSnippet' ] ?></div> 
              <div class="display-link"><?php print $item[ 'displayLink' ] ?></div> 
            </li>

          <? } ?>

        </ul>
      </div>

      <ul class="clusters">
        
      <?php

        $url = "${path}?action=cluster&page=1&cluster=all&q=" . $query[ URL_SAFE ];

        $label = "All (${total_unfiltered_results})";

        if ( $current_cluster[ NORMAL ] == 'all' ) {
          print '<li class="current">' . html_anchor( $label, $url ) . '</li>' . "\n";
        } else {
          print '<li>' . html_anchor( $label, $url ) . '</li>' . "\n";
        }

        foreach( $clusters[ 'clusters' ] as $cluster ) {

          $phrases = get_cluster_name( $cluster );

          $doc_count = count( $cluster[ 'documents' ] );

          $url = "${path}?action=cluster&page=1&cluster=" . urlencode( $phrases ) . "&q=" . $query[ URL_SAFE ];

          $label = "$phrases (${doc_count})";

          if ( $current_cluster[ NORMAL ] == $phrases ) {
            print '<li class="current">' . html_anchor( $label, $url ) . '</li>' . "\n";
          } else {
            print '<li>' . html_anchor( $label, $url ) . '</li>' . "\n";
          }
        }
        
      ?>
      </ul>

    <?php } ?>

    <!-- - - - - - - - - - - - - - - - - - - - - - - - - - - - -->

    <br class="clear"/>

  </div> <!-- /.wrapper -->
</body> <!-- /.banana -->
</html>

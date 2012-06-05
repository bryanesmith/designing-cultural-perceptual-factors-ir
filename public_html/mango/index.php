<?php

  include '../lib/library.inc.php';

  // - - - - - - - - - - - - - - - - - - - - - - - - - 
  $query   = get_query();

  // Perform search, but only if there is a query
  if ( $query[ NORMAL ] ) {

    $results = search( $query[ NORMAL ] );
    $clusters = cluster( $results );

    $total_unfiltered_results = count( $results );

  }

  // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - 
  function render_cluster( $desc ) {
    
    $phrases = $desc{ 'phrases' };
    $doc_count = $desc{ 'doc_count' };
    $max_to_show = $desc{ 'max_to_show' };
    $count = $desc{ 'count' };
    $results = $desc{ 'results' };
    $cluster = $desc{ 'cluster' };
    $last_item = $doc_count > $max_to_show? $max_to_show : $doc_count;

    $classes = 'cluster';

    if ( $count % 3 == 0 ) {
      $classes .= ' third';
    }

    $classCount = "modal_${count}";

    // ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ 
    // Print the modal
    // ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ 
    print "<div class=\"${classCount} cluster_modal\">";
    ?>
      <div class="search-result-listings">

        <h2> <?php print $phrases; ?> <span>(<?php print $doc_count; ?>)</span></h2>

        <ul>
        <?php

          for( $i = 0; $i < $doc_count; $i++ ) {
            $result_index = $cluster[ $i ];
            $item = $results[ $result_index ];
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
      <br class="clear" />
    <?php
    print '</div>';

    // ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ 
    // Print the summary box
    // ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ ~ 
    print "<div class=\"${classes}\">" . "\n";
    print "  <h2>${phrases} <span>(${doc_count})</span></h2>" . "\n";

    print '<ul>' . "\n";

    for( $i = 0; $i < $last_item; $i++ ) {
      $result_index = $cluster[ $i ];
      $item = $results[ $result_index ];

      print '<li>' . html_anchor( $item[ 'title' ], wrap_item_link( $item[ 'link' ] ), true ) . '</li>' . "\n";
    }

    print '</ul>' . "\n";

    // Make it a jQuery-style class
    $classCount = ".${classCount}";

    if ( $doc_count > $max_to_show ) {
      $label = "View all&hellip;";
      print '<div class="more" href="#" onclick="return show_modal( \'' . $classCount . '\', \'' . $phrases . '\' )">' . html_anchor( $label, '' ) . '</div>';
    }
  
    print '</div>' . "\n";
  }

?>

<html>

<head>

  <link type="text/css" rel="stylesheet" media="all" href="/styles/twitter-bootstrap/bootstrap.css" />
  <link type="text/css" rel="stylesheet" media="all" href="/styles/style.css" />

  <script type="text/javascript" src="/scripts/jquery-1.6.3.min.js"></script>
  <script type="text/javascript" src="/scripts/jquery.simplemodal.1.4.1.min.js"></script>
  <script type="text/javascript" src="/scripts/mango.js"></script>

  <title>Mango</title>

</head>

<body class="mango <?php print $query[ NORMAL ] ? 'results' : 'no-results' ?>">
  <div class="wrapper">

    <div class="header">
      <h1>Mango <span>search</span></h1>

      <form method="get" class="query-box">
        <input type="input" name="q" placeholder="e.g., europe" value="<?php print $query[ NORMAL ] ?>" />
        <input type="hidden" name="action" value="query"/>
        <input type="submit" value="search"/>
      </form>

    </div>

    <!-- - - - - - - - - - - - - - - - - - - - - - - - - - - - -->
    <?php if ( $query[ NORMAL ] ) { ?>

      <?php

        $count = 0;

        // Show the "All" cluster
        {
          $count++;

          $desc = array();
          $desc{ 'phrases' } = 'All';
          $desc{ 'doc_count' } = count( $results );
          $desc{ 'max_to_show' } = 3;
          $desc{ 'count' } = $count;
          $desc{ 'results' } = $results;
          $desc{ 'cluster' } = range( 0, count( $results ) );

          render_cluster( $desc );
        }

        // Show clusters
        foreach( $clusters[ 'clusters' ] as $cluster ) {

          $count++;

          $desc = array();
          $desc{ 'phrases' } = get_cluster_name( $cluster );
          $desc{ 'doc_count' } = count( $cluster[ 'documents' ] );
          $desc{ 'max_to_show' } = 3;
          $desc{ 'count' } = $count;
          $desc{ 'results' } = $results;
          $desc{ 'cluster' } = $cluster[ 'documents' ];

          render_cluster( $desc );

        }
      ?>

    <?php } ?>

    <!-- - - - - - - - - - - - - - - - - - - - - - - - - - - - -->
    <br class="clear"/>
  </div> <!-- /.wrapper -->
</body> <!-- /.mango -->
</html>

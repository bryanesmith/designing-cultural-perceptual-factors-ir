$(document).ready(function() {

});

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - 
function show_modal( classToShow, phrases ) {

  // Log
  $.post("/actions/log.php", { action: "cluster", cluster: phrases, system: 'mango' } );

  $( classToShow ).show();

  $( classToShow ).modal({
    opacity:40,
    overlayClose:true,
    onClose: function (dialog) {
      $.modal.close();
      $( classToShow ).hide();
    },
  });

  return false;
}


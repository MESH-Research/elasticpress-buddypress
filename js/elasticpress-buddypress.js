$( function() {
  var initTabSelect = function( formElement, targetElement ) {
    var tabElements = [];
    var selectedTabs = [];

    $( formElement ).children().each( function( i, el ) {
      tabElements.push( el.innerHTML );

      if ( $( el ).is( ':selected' ) ) {
        selectedTabs.push( el.innerHTML );
      }
    } );

    $( targetElement ).tabSelect( {
      tabElements: tabElements,
      selectedTabs: selectedTabs,
      formElement: formElement,
      onChange: function( selected ) {
        $( formElement ).children().each( function( i, el ) {
          if ( $.inArray( el.innerHTML, selected ) !== -1 ) {
            $( el ).attr( 'selected', true );
          }
        } );
      }
    } );
  }

  initTabSelect( 'select[name=post_type\\[\\]]', '#ep_bp_post_type_facet' );
  initTabSelect( 'select[name=index\\[\\]]', '#ep_bp_index_facet' );
} );

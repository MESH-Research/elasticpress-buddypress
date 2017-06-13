$( function() {
  var initTabSelect = function( formElement, targetElement ) {
    var tabElements = [];
    var selectedTabs = [];

    $( formElement ).children().each( function( i, el ) {
      tabElements.push( el.innerHTML );

      if ( $( el ).prop( 'selected' ) ) {
        selectedTabs.push( el.innerHTML );
      }
    } );

    $( targetElement ).tabSelect( {
      tabElements: tabElements,
      selectedTabs: selectedTabs,
      formElement: formElement,
      onChange: function( selected ) {
        // select & deselect options
        $( formElement ).children().each( function() {
          // use .attr() rather than .prop() so that serializeArray() finds these elements.
          $( this ).attr( 'selected', ( $.inArray( this.innerHTML, selected ) !== -1 ) );
        } );

        // trigger handleFacetChange
        $( formElement ).trigger( 'change' );
      }
    } );
  }

  var handleFacetChange = function( event ) {
    var target = $( '#content' );
    var params = $.param( $( '.ep-bp-search-facets' ).serializeArray() );

    target.addClass( 'in-progress' );

    // TODO set ajax path with wp_localize_script() from EPR_REST_Posts_Controller property
    $.getJSON( '/wp-json/epr/v1/query?' + params, function( data ) {
      if ( window.history && window.history.pushState ) {
        // if we're on a page after 1, remove that bit of the path
        window.history.pushState( data, '', window.location.pathname.replace( /\/page\/\d+/, '' ) + '?' + params );
      }
      target.html( data.results_html );
      target.removeClass( 'in-progress' );
    } );
  }

  $( '#ep-bp-facets' ).find( 'select' ).on( 'change', handleFacetChange );
  $( '#ep-bp-facets' ).find( 'input' ).on( 'keyup', handleFacetChange );

  // prevent native form submission since we're running on ajax instead
  $( '#ep-bp-facets' ).on( 'submit', function( e ) {
    e.preventDefault();
  } );

  // TODO handle browser "back" button clicks; refresh results
  //window.onpopstate = function( e ) {
  //  console.log( e.state );
  //  // TODO url changes, but form values don't so results remain the same if we just re-run the change handler.
  //  // need to set values in the actual form according to the querystring before calling the handler.
  //  //handleFacetChange();
  //};

  initTabSelect( 'select[name=post_type\\[\\]]', '#ep_bp_post_type_facet' );
  initTabSelect( 'select[name=index\\[\\]]', '#ep_bp_index_facet' );
} );

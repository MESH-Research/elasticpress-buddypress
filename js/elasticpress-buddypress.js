window.elasticPressBuddyPress = {
  // are we presently awaiting results?
  loading: false,

  // request in progress, if any
  xhr: null,

  // what page of results are we loading?
  page: 1,

  // element to which results are appended ( set in init() since it doesn't exist until document.ready )
  target: null,

  initTabSelect: function( formElement, targetElement ) {
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
  },

  handleFacetChange: function( event ) {
    elasticPressBuddyPress.page = 1;
    elasticPressBuddyPress.loadResults();
  },

  loadResults: function() {
    var params = $( '.ep-bp-search-facets' ).serializeArray();

    params.push( {
      name: 'paged',
      value: elasticPressBuddyPress.page
    } );

    params = $.param( params );

    elasticPressBuddyPress.loading = true;
    elasticPressBuddyPress.target.addClass( 'in-progress' );

    // abort pending request, if any, before starting a new one
    if ( elasticPressBuddyPress.xhr && 'abort' in elasticPressBuddyPress.xhr ) {
      elasticPressBuddyPress.xhr.abort();
    }

    // TODO set ajax path with wp_localize_script() from EPR_REST_Posts_Controller property
    elasticPressBuddyPress.xhr = $.getJSON( '/wp-json/epr/v1/query?' + params )
      .success( function( data ) {
        if ( window.history && window.history.pushState ) {
          window.history.pushState( data, '', window.location.pathname + '?' + params );
        }

        // clear existing results unless we're infinite scrolling
        if ( elasticPressBuddyPress.page === 1 || data.results_html.indexOf( 'no-results' ) !== -1 ) {
          elasticPressBuddyPress.target.html( '' );
        }

        elasticPressBuddyPress.target.append( data.results_html );
      } )
      .error( function( request ) {
        if ( request.statusText !== 'abort' ) {
          elasticPressBuddyPress.target.html(
            '<article class="post no-results not-found"><div class="entry-content"><p>Something went wrong! Please try a different query.</p></div></article>'
          );
        }
      } )
      .complete( function( request ) {
        if ( request.statusText !== 'abort' ) {
          elasticPressBuddyPress.target.removeClass( 'in-progress' );
          elasticPressBuddyPress.loading = false;
        }
      } );
  },

  init: function() {
    elasticPressBuddyPress.target = $( '#content' );

    elasticPressBuddyPress.initTabSelect( 'select[name=post_type\\[\\]]', '#ep_bp_post_type_facet' );
    elasticPressBuddyPress.initTabSelect( 'select[name=index\\[\\]]', '#ep_bp_index_facet' );

    $( '#ep-bp-facets' ).find( 'select' ).on( 'change', elasticPressBuddyPress.handleFacetChange );
    $( '#ep-bp-facets' ).find( 'input' ).on( 'keyup', elasticPressBuddyPress.handleFacetChange );

    $( '#s' ).val( $( '#ep-bp-facets [name=s]' ).val() );

    // disable fade effects in titlebar. really a theme thing.
    $.fx.off = true;

    // prevent native form submission since we're running on ajax instead
    $( '#s' ).on( 'submit', function( e ) {
      e.preventDefault();
    } );
    $( '#ep-bp-facets' ).on( 'submit', function( e ) {
      e.preventDefault();
    } );

    $( '#s' ).on( 'keyup', function( e ) {
      $( '#ep-bp-facets [name=s]' ).val( $( '#s' ).val() );
      elasticPressBuddyPress.page = 1;
      elasticPressBuddyPress.loadResults();
    } );

    $( window ).on( 'scroll', function ( event ) {
      if(
        $( window ).scrollTop() >= elasticPressBuddyPress.target.offset().top + elasticPressBuddyPress.target.outerHeight() - window.innerHeight &&
          ! elasticPressBuddyPress.loading
      ) {
        elasticPressBuddyPress.page++;
        elasticPressBuddyPress.xhr = elasticPressBuddyPress.loadResults();
      }
    } );
  }
}

$( elasticPressBuddyPress.init );

window.elasticPressBuddyPress = {

  loaderDiv: '<div class="epbp-loader"><img src="/app/plugins/elasticpress-buddypress/img/ajax-loader.gif"></div>',

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
    $( '.epbp-loader' ).remove();
    // TODO localize
    $( '.ep-bp-search-facets' ).append( elasticPressBuddyPress.loaderDiv );
    elasticPressBuddyPress.page = 1;
    elasticPressBuddyPress.loadResults();
  },

  loadResults: function() {
    var serializedFacets = $( '.ep-bp-search-facets' ).serializeArray();

    serializedFacets.push( {
      name: 'paged',
      value: elasticPressBuddyPress.page
    } );

    for ( var i = 0; i < serializedFacets.length; i++ ) {
      serializedFacets[ i ].value = $.trim( serializedFacets[ i ].value );
    }

    serializedFacets = $.param( serializedFacets );

    elasticPressBuddyPress.loading = true;
    if ( elasticPressBuddyPress.page > 1 ) {
      elasticPressBuddyPress.target.append( elasticPressBuddyPress.loaderDiv );
    } else {
      elasticPressBuddyPress.target.addClass( 'in-progress' );
    }

    // abort pending request, if any, before starting a new one
    if ( elasticPressBuddyPress.xhr && 'abort' in elasticPressBuddyPress.xhr ) {
      elasticPressBuddyPress.xhr.abort();
    }

    // TODO set ajax path with wp_localize_script() from EPR_REST_Posts_Controller property
    elasticPressBuddyPress.xhr = $.getJSON( '/wp-json/epr/v1/query?' + serializedFacets )
      .success( function( data ) {
        // clear existing results unless we're infinite scrolling
        if ( elasticPressBuddyPress.page === 1 ) {
          elasticPressBuddyPress.target.html( '' );
        }

        if ( data.posts.length ) {
          elasticPressBuddyPress.target.append( data.posts.join( '' ) );

          if ( window.history && window.history.pushState ) {
            window.history.pushState( data, '', window.location.pathname + '?' + serializedFacets );
          }
        } else {
          if ( elasticPressBuddyPress.page > 1 ) {
            elasticPressBuddyPress.target.append( '<div class="epbp-msg no-more-results">No more results.</div>' );
          } else {
            elasticPressBuddyPress.target.append( '<div class="epbp-msg no-results">No results.</div>' );
          }
        }
      } )
      .error( function( request ) {
        if ( request.statusText !== 'abort' ) {
          elasticPressBuddyPress.target.html(
            '<div class="epbp-msg error">Something went wrong! Please try a different query.</div>'
          );
        }
      } )
      .complete( function( request ) {
        if ( request.statusText !== 'abort' ) {
          $( '.epbp-loader' ).remove();
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

    $( '#s' ).val( $.trim( $( '#ep-bp-facets [name=s]' ).val() ) );

    // disable fade effects in titlebar. really a theme thing.
    $.fx.off = true;

    // prevent native form submission since we're running on ajax instead
    $( '#searchform' ).on( 'submit', function( e ) {
      e.preventDefault();
    } );
    $( '#ep-bp-facets' ).on( 'submit', function( e ) {
      e.preventDefault();
    } );

    // makes no sense to offer the option to sort least relevant results first,
    // so hide order when sorting by score.
    // boss adds markup to all selects so we must hide those too for now.
    var updateOrderSelect = function() {

      if ( $( '#orderby' ).val() === '_score' ) {

        // in case user had selected asc, reset
        if ( $( '#order' ).val() !== 'desc' ) {
          $( '#order [value="asc"]' ).attr( 'selected', false );
          $( '#order [value="desc"]' ).attr( 'selected', true );
          $( this ).trigger( 'change' );
        }

        $( '#order' ).hide(); // theme-independent, hopefully
        $( '#order' ).parents( '.buddyboss-select' ).css( 'opacity', 0 ); // boss

      } else {

        $( '#order' ).show();
        $( '#order' ).parents( '.buddyboss-select' ).css( 'opacity', 1 );

      }

    }

    $( '#orderby' ).on( 'change', updateOrderSelect );

    // trigger the #orderby change handler once boss Selects have initialized
    var observer = new MutationObserver( function() {
      if ( $( '.ep-bp-search-facets' ).children( '.buddyboss-select' ).length && $( '#orderby' ).val() === '_score' ) {
        updateOrderSelect();
        observer.disconnect();
      }
    } );

    observer.observe( $( '.ep-bp-search-facets' )[0], { childList: true } );

    $( '#s' ).on( 'keyup', function( e ) {
      // only process change if the value of the input actually changed (not some other key press)
      if ( $( '#s' ).val() !== $( '#ep-bp-facets [name=s]' ).val() ) {
        $( '.epbp-loader' ).remove();
        $( '.ep-bp-search-facets' ).append( elasticPressBuddyPress.loaderDiv );
        $( '#ep-bp-facets [name=s]' ).val( $( '#s' ).val() );
        elasticPressBuddyPress.page = 1;
        elasticPressBuddyPress.loadResults();
      }
    } );

    $( window ).on( 'scroll', function ( event ) {
      if(
        $( window ).scrollTop() >= elasticPressBuddyPress.target.offset().top + elasticPressBuddyPress.target.outerHeight() - window.innerHeight &&
          ! elasticPressBuddyPress.loading &&
          ! elasticPressBuddyPress.target.children( '.epbp-msg' ).length
      ) {
        elasticPressBuddyPress.page++;
        elasticPressBuddyPress.xhr = elasticPressBuddyPress.loadResults();
      }
    } );
  }
}

$( elasticPressBuddyPress.init );

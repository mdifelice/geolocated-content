/**
 * Geolocated Content JS handler.
 *
 * @package Geolocated_Content
 */

( function() {
	var areCookiesEnabled = function() {
		var cookiesEnabled = ( 'undefined' !== navigator.cookieEnabled && navigator.cookieEnabled ) ? true : null;

		if ( ! cookiesEnabled ) {
			document.cookie = 'geolocated_content_testcookie=1';

			if ( -1 !== document.cookie.indexOf( 'geolocated_content_test_cookie=1' ) ) {
				cookiesEnabled = true;
			}

			var expired_date = new Date( 1981, 7, 16 );

			document.cookie = 'geolocated_content_test_cookie=1;expires=' + expired_date.toUTCString();
		}

		return cookiesEnabled;
	};

	var parseKeyPairString = function( from, parameter, separator ) {
		var tokens = from.split( separator );
		var value  = '';

		for ( var i = 0; i < tokens.length; i++ ) {
			while ( tokens[ i ].charAt( 0 ) === ' ' ) {
				tokens[ i ] = tokens[ i ].substring( 1 );
			}

			if ( tokens[ i ].indexOf( parameter + '=' ) == 0 ) {
				value = tokens[ i ].substring( parameter.length + 1, tokens[ i ].length );

				break;
			}
		}

		return value;
	};

	var isValidLocation = function( locationSlug ) {
		return 'undefined' !== typeof( geolocated_content.locations[ locationSlug ] );
	};

	var degreesToRadians = function( degrees ) {
		return degrees * ( Math.PI / 180 );
	};

	// Returns the distance in kilometers between two coordinates using the Haversine formula.
	var getDistance = function( latitudeFrom, longitudeFrom, latitudeTo, longitudeTo ) {
		var latitudeFromRadians  = degreesToRadians( latitudeFrom );
		var longitudeFromRadians = degreesToRadians( longitudeFrom );
		var latitudeToRadians    = degreesToRadians( latitudeTo );
		var longitudeToRadians   = degreesToRadians( longitudeTo );
		// Average earth radius in kilometers.
		var earthRadius = 6371;

		return (
			2 * earthRadius *
			Math.asin(
				Math.sqrt(
					Math.pow( Math.sin( ( latitudeToRadians - latitudeFromRadians) / 2 ), 2 ) +
					( Math.cos( latitudeFromRadians ) *
					  Math.cos( latitudeToRadians ) *
					  Math.pow( Math.sin( ( longitudeToRadians - longitudeFromRadians ) / 2 ), 2 )
					)
				)
			)
		);
	};

	var getLocationSlug = function( xhr ) {
		var locationSlug = null;

		if ( 200 === xhr.status && window.JSON ) {
			var response = JSON.parse( xhr.responseText );

			if ( response ) {
				var latitude    = response.latitude;
				var longitude   = response.longitude;
				var maxDistance = geolocated_content.tolerance_radius ? geolocated_content.tolerance_radius : null;

				for ( var slug in geolocated_content.locations ) {
					var location          = geolocated_content.locations[ slug ];
					var locationLatitude  = parseFloat( location[0] );
					var locationLongitude = parseFloat( location[1] );

					if ( locationLatitude && locationLongitude ) {
						var distance = getDistance( latitude, longitude, locationLatitude, locationLongitude );

						if ( null === maxDistance || distance < maxDistance ) {
							maxDistance = distance;

							locationSlug = slug;
						}
					}
				}
			}
		}

		if ( areCookiesEnabled() ) {
			document.cookie = encodeURIComponent( cookieName ) + '=' + encodeURIComponent( locationSlug ? locationSlug : 'default' ) + '; expires=' + geolocated_content.cookie.expires + '; path=/';
		}

		return locationSlug;
	};

	var overrideLocation = parseKeyPairString( window.location.search.substring( 1 ), 'override_location', '&' );
	var redirect 		 = null;

	if ( overrideLocation && isValidLocation( overrideLocation ) ) {
		if ( overrideLocation !== geolocated_content.current_location_slug ) {
			redirect = window.location.pathname.replace( /\?override_location=.+/, '' );

			if ( geolocated_content.current_location_slug ) {
				redirect = redirect.replace( '/' + geolocated_content.current_location_slug + '/', '/' );
			}

			redirect = '/' + overrideLocation + redirect;
		}
	} else {
		var cookieName = geolocated_content.cookie.name;

		if ( cookieName ) {
			var locationSlug = parseKeyPairString( document.cookie, geolocated_content.cookie.name, ';' );

			if ( locationSlug ) {
				if ( ! geolocated_content.current_location_slug && isValidLocation( locationSlug ) ) {
					redirect = '/' + locationSlug + window.location.pathname;
				}
			} else {
				if ( window.XMLHttpRequest ) {
					var xhr   = new XMLHttpRequest();
					var async = ! ! geolocated_content.current_location_slug;

					xhr.open( 'GET', geolocated_content.service, async );

					if ( async ) {
						xhr.onreadystatechange = function() {
							if ( XMLHttpRequest.DONE === xhr.readyState ) {
								// We do not redirect, since there is already a specific location the visitor is navigating.
								getLocationSlug( xhr );
							}
						};
					}

					xhr.send( null );

					if ( ! async ) {
						locationSlug = getLocationSlug( xhr );

						if ( locationSlug ) {
							redirect = '/' + locationSlug + window.location.pathname;
						}
					}
				}
			}
		}
	}

	if ( redirect ) {
		window.location = redirect;
	}
} )();

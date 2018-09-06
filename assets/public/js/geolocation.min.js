( function() {
 	var areCookiesEnabled = function() {
		var cookiesEnabled = ( 'undefined' !== navigator.cookieEnabled && navigator.cookieEnabled ) ? true : null;

		if ( ! cookiesEnabled ) {
			document.cookie = '__wpcomgeo_testcookie=1';

			if ( -1 !== document.cookie.indexOf( '__wpcomgeo_testcookie=1' ) ) {
				cookiesEnabled = true;
			}

			var expired_date = new Date( 1981, 7, 16 );

			document.cookie = '__wpcomgeo_testcookie=1;expires=' + expired_date.toUTCString();
		}

		return cookiesEnabled;
	};

	var parseKeyPairString = function( from, parameter, separator ) {
		var tokens = from.split( separator );
		var value  = '';

		for ( var i = 0; i < tokens.length; i++ ) {
			while ( tokens[ i ].charAt( 0 ) == ' ' ) tokens[ i ] = tokens[ i ].substring( 1 );

			if ( tokens[ i ].indexOf( parameter + '=' ) == 0 ) {
				value = tokens[ i ].substring( parameter.length + 1, tokens[ i ].length );

				break;
			}
		}

		return value;
	};

	var isValidLocation = function( locationSlug ) {
		return 'undefined' !== typeof( geolocation.locations[ locationSlug ] );
	};

	var degreesToRadians = function( degrees ) {
		return degrees * ( Math.PI / 180 );
	};

	// Returns the distance in kilometers between two coordinates using the Haversine formula.
	var getDistance = function( latitudeFrom, longitudeFrom, latitudeTo, longitudeFrom ) {
		var latitudeFromRadians  = degreesToRadians( latitudeFrom );
		var longitudeFromRadians = degreesToRadians( longitudeFrom );
		var latitudeToRadians    = degreesToRadians( latitudeTo );
		var longitudeToRadians   = degreesToRadians( longitudeTo );
		// Average earth radius in kilometers.
		var earthRadius          = 6371;

		return
			2 * earthRadius *
			Math.asin(
				Math.sqrt(
					Math.pow( Math.sin( ( latitudeToRadians - latitudeFromRadians) / 2 ), 2 ) +
					( Math.cos( latitudeFromRadians ) *
					  Math.cos( latitudeToRadians ) *
					  Math.pow( Math.sin( ( longitudeToRadians - longitudeFromRadians ) / 2 ), 2 )
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
				var maxDistance = geolocation.tolerance_radius ? geolocation.tolerance_radius : null;

				for ( var slug in geolocation.locations ) {
					var location = geolocation.locations[ slug ];

					var distance = getDistance( latitude, longitude, location.latitude, location.longitude );

					if ( null === maxDistance || distance < maxDistance ) {
						maxDistance = distance;

						locationSlug = slug;
					}
				}
			}
		}

		if ( areCookiesEnabled() ) {
			document.cookie = encodeURIComponent( cookieName ) + '=' + encodeURIComponent( locationSlug ) + '; expires=' + geolocation.cookie.expires + '; path=/';
		}

		return locationSlug;
	};

	var overrideLocation = parseKeyPairString( window.location.search.substring( 1 ), 'override_location', '&' );
	var redirect 		  = null;

	if ( overrideLocation && isValidLocation( overrideLocation ) ) {
		if ( overrideLocation !== geolocation.current_location_slug ) {
			redirect = window.location.pathname.replace( /\?override_location=.+/, '' );

			if ( geolocation.current_location_slug ) {
				redirect = redirect.replace( '/' + geolocation.current_location_slug + '/', '/' );
			}

			redirect = '/' + overrideLocation + redirect;
		}
	} else {
		var cookieName = geolocation.cookie.name;

		if ( cookieName ) {
			var locationSlug = parseKeyPairString( document.cookie, geolocation.cookie.name, ';' );

			if ( ! locationSlug ) {
				if ( ! geolocation.current_location_slug && isValidLocation( locationSlug ) ) {
					redirect = '/' + locationSlug + window.location.pathname;
				}
			} else {
				if ( window.XmlHttpRequest ) {
					var xhr   = new XmlHttpRequest();
					var async = ! ! geolocation.current_location_slug;

					xhr.open( 'GET', geolocation.service, async );

					if ( async ) {
						xhr.onreadystatechange = function() {
							if ( XmlHttpRequest.DONE === xhr.readyState ) {
								// We do not redirect, since there is already a specific location the visitor is navigatin.
								getLocationSlug( xhr );
							}
						};
					}

					xhr.send( null );

					if ( ! async ) {
						locationSlug = getLocationSlug( xhr );

						if ( locationSlug ) {
							redirect = '/' + market + window.location.pathname;
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

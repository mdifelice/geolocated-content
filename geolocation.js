var geolocation_are_cookies_enabled = function() {
	var cookies_enabled = ( 'undefined' !== navigator.cookieEnabled && navigator.cookieEnabled ) ? true : null;

	if( ! cookies_enabled ) {
		document.cookie = '__wpcomgeo_testcookie=1';

		if( -1 !== document.cookie.indexOf( '__wpcomgeo_testcookie=1' ) )
			cookies_enabled = true;

		var expired_date = new Date( 1981, 7, 16 );

		document.cookie = '__wpcomgeo_testcookie=1;expires=' + expired_date.toUTCString();
	}

	return cookies_enabled;
};

var geolocation_parse_from = function( from, parameter, separator ) {
	var tokens = from.split( separator );
	var value	= '';

	for( var i = 0; i < tokens.length; i++ ) {
		while ( tokens[ i ].charAt( 0 ) == ' ' ) tokens[ i ] = tokens[ i ].substring( 1 );

		if( tokens[ i ].indexOf( parameter + '=' ) == 0 ) {
			value = tokens[ i ].substring( parameter.length + 1, tokens[ i ].length );

			break;
		}
	}

	return value;
};

var override_market = geolocation_parse_from( window.location.search.substring( 1 ), 'override_market', '&' );
var redirect 		= false;

if( override_market &&
	geolocation_settings.markets.indexOf( override_market ) != -1 &&	
	override_market != geolocation_settings.current_market ) {
	redirect = window.location.pathname.replace( /\?override_market=.+/, '' );

	if( geolocation_settings.current_market )
		redirect = redirect.replace( '/' + geolocation_settings.current_market + '/', '/' );

	redirect = '/' + override_market + redirect;
}
else {
	var cookie_name = geolocation_settings.cookie.name;

	var market = cookie_name ? geolocation_parse_from( document.cookie, geolocation_settings.cookie.name, ';' ) : false;

	if( market ) {
		if( market != geolocation_settings.default_location ) {
			if( ! geolocation_settings.current_market )
				for( var i = 0; i < geolocation_settings.markets.length; i++ )
					if( geolocation_settings.markets[ i ] == market ) {
						redirect = '/' + market + window.location.pathname;

						break;
					}
		}
	}
	else {
		market = geolocation_settings.default_location;

		try {
			if( ! geolocation_are_cookies_enabled() )
				throw 'Geolocation Error: Cookies disabled';

			if( ! window.XMLHttpRequest )
				throw 'Geolocation Error: AJAX disabled';
			
			var xhr = new XMLHttpRequest();

			xhr.open( 'GET', geolocation_settings.service, false ); // we want a synchronous request since we want geo to happen before other things
			xhr.send( null );
			
			// Only geolocate modern browsers
			if( xhr.status === 200 && window.JSON ) {
				var response = JSON.parse( xhr.responseText );
				
				if( response )
					market = response;
			}
		}
		catch(e) {
		}

		if( cookie_name )
			document.cookie = encodeURIComponent( cookie_name ) + '=' + encodeURIComponent( market ) + '; expires=' + geolocation_settings.cookie.expires + '; path=/';
		
		if(	! geolocation_settings.current_market &&
			market != geolocation_settings.default_location)
			redirect = '/' + market + window.location.pathname;
	}
}

if( redirect )
	window.location = redirect;

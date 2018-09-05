<?php
/**
 * Plugin Name: Geolocation
 * Description: Allows to deliver different content to users in different locations.
 * Plugin URI:  https://github.com/mdifelice/geolocation
 * Author:      Martín Di Felice
 * Author URI:  https://github.com/mdifelice
 * Text Domain: geolocated-content
 * Domain Path: /languages
 * Version:     1.0.0
 * License:     GPL2
 *
 * Geolocation is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 2 of the License, or any later version.
 *
 * Geolocation is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Geolocation. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package Geolocation
 */

include_once __DIR__ . '/classes/class-geolocated-content-walker-category.php';

include_once __DIR__ . '/settings.php';
include_once __DIR__ . '/shortcodes.php';
include_once __DIR__ . '/widgets.php';

/**
  * Constants:
  * GEOLOCATION_COOKIE:
  *		Default name of the cookie set by this plugin.
  * GEOLOCATION_COOKIE_EXPIRATION:
  *		Default expiration time for the cookie (right now, it lasts 24
  *		hours).
  * GEOLOCATION_DEFAULT_LOCATION:
  *		When no valid location is found, the cookie will be saved with this
  *		value.
  *	GEOLOCATION_QUERY_STRING:
  *		This value indicates a query string parameter that will allow to
  *		override the cookie set by the geolocator. For example, if the page
  *		is called this way:
  *
  *		- http://mysite.com/?override-market=los-angeles
  *
  *		then, the visitor will be redirected to:
  *
  *		- http://mysite.com/los-angeles/
  *
  *		no matter where the visitor is located.
  * GEOLOCATION_VERSION:
  *		Every time the JS file inside this plugin is modified, this value
  *		should be increased, to force visitors to download it again.
  * GEOLOCATION_WEBSERVICE:
  *		URL of the webservice that will be called to determine the visitor
  *		location.
  */
define( 'GEOLOCATION_COOKIE_EXPIRATION',	time() + 86400 );
define( 'GEOLOCATION_DEFAULT_LOCATION', 	'default' );
define( 'GEOLOCATION_QUERY_STRING',			'override_market' );
define( 'GEOLOCATION_VERSION',				'3' );
define( 'GEOLOCATION_COOKIE', 				'geolocation_market_v' . GEOLOCATION_VERSION );
define( 'GEOLOCATION_WEBSERVICE',			'https://webservices3.entravision.com/geolocation/market' );

/**
  * We define these variables as globals because they are used in several
  * functions. It should not be used outside this file.
  * $geolocation_market will contain the market the user is navigating.
  * If the user is navigating the default or global market, it will be
  * empty.
  * $geolocation_markets will be a list of market slugs. The default or
  * global market won't be included.
  * $geolocation_providers will be an array with the posibles providers.
  */
global $geolocation_market, $geolocation_markets, $geolocation_providers;

$geolocation_providers = array(
	'dig-el'	=> 'Digital Element',
	'local'		=> 'Local'
);

/**
  * This function returns the user market ID from the current request.
  *
  * For front-end calls it uses geolocation_get_user_location() that will
  * extract the market slug from the request, and thus using that slug to
  * retrieve the ID.
  *
  * For back-end calls it checks if the query string parameter
  * "geolocation_market" is set. If so, it returns it.
  *
  * @param string $context Optional. If provided it will restrict the search.
  *						   If "administrator" is sent, it will only check if
  *						   for the query string parameter "geolocation_market".
  *						   By default is NULL, meaning it will search for both
  *						   front and back ends.
  *
  * @return int The market ID. Or NULL if there is not a selected market.
  */
function geolocation_get_current_market_id( $context = null ) {
	$current_market_id = null;

	if ( 'administrator' !== $context ) {
		$user_location = geolocation_get_user_location();
	} else {
		$user_location = false;
	}

	if ( false !== $user_location ) {
		$markets = geolocation_get_markets();

		foreach ( $markets as $market ) {
			if ( $user_location === $market->slug ) {
				$current_market_id = $market->term_id;

				break;
			}
		}
	} else {
		if ( isset( $_GET['geolocation_market'] ) ) {
			$current_market_id = sanitize_text_field( wp_unslash( $_GET['geolocation_market'] ) );
			/**
			 * If this a post, we will try to check if the referer is
			 * containing the market. This is experimental.
			 */
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] &&
				   isset( $_SERVER['HTTP_REFERER'] ) ) {
			$url = parse_url( $_SERVER['HTTP_REFERER'] );

			if ( isset( $url['query'] ) ) {
				wp_parse_str( $url['query'], $query );

				if ( isset( $query['geolocation_market'] ) ) {
					$current_market_id = $query['geolocation_market'];
				}
			}
		}
	}

	return $current_market_id;
}

/**
 * Retrieves the current market name or "Default" if none is selected.
 *
 * @return string The market name.
 */
function geolocation_get_current_market_name() {
	$current_market_name 	= __( 'Default', 'geolocation' );
	$current_market_id 		= geolocation_get_current_market_id();

	if ( $current_market_id ) {
		$current_market = geolocation_get_market( $current_market_id );

		if ( $current_market ) {
			$current_market_name = $current_market->name;
		}
	}

	return $current_market_name;
}

/**
 * Retrieves the current market name or null if none is selected.
 *
 * @return WP_Term The market object.
 */
function geolocation_get_current_market() {
	$current_market 	= null;
	$current_market_id 	= geolocation_get_current_market_id();

	if ( $current_market_id ) {
		$current_market = geolocation_get_market( $current_market_id );
	}

	return $current_market;
}

/**
  * This function determines if a slash must be appended to a URL.
  * It basically checks that the URL does not ends with an slash, and also
  * it does not contain a dot (if so, it could be mean that the URL is a
  * real file, with some extension, for example: wp-login.php).
  */
function geolocation_must_add_slash( $url ) {
	$must_add_slash = false;

	if( ! empty( $url ) &&
		$url[ strlen( $url ) - 1] != '/' ) {
		$parsed_url = parse_url( $url );

		if( empty( $parsed_url['query'] ) &&
			empty( $parsed_url['fragment'] ) ) {
			$basename = basename( $parsed_url['path'] );

			if( strpos( $basename, '.' ) === false )
				$must_add_slash = true;
		}
	}

	return $must_add_slash;
}

/**
  * This function is hooked to 'wp_enqueue_scripts'.
  * It enqueues the needed scripts to geolocate and redirect visitors.
  */
function geolocation_wp_enqueue_scripts() {
	global $geolocation_market;

	/**
	  * If 'avoid_redirection' is true (we are in Backend Mode), we will
	  * not enqueue any file, and the user won't be geolocated nor
	  * redirected.
	  */
	$avoid_redirection = get_option( 'geolocation_avoid_redirection' );

	if( ! $avoid_redirection ) {
		/**
		  * If it's not an empty market, we place these scripts at the
		  * footer. In those cases we geolocate but do not redirect.
		  * If the market is empty, we will include the script code in
		  * the HEAD in order to try to geolocate and redirect faster.
		  */
		if( ! empty( $geolocation_market ) ) {
			wp_enqueue_script( 'geolocation', WP_CONTENT_URL . '/themes/vip/entravision-plugins/geolocation/geolocation.min.js', array(), GEOLOCATION_VERSION, true );

			wp_localize_script( 'geolocation', 'geolocation_settings', geolocation_javascript_settings( false ) );
		}
	}
}

/**
  * This function is hooked to 'wp_head'.
  * In those cases where the geolocation.js file was not enqueued via the
  * traditional method, it will added to the website's head.
  */
function geolocation_wp_head() {
	global $geolocation_market;

	/**
	  * If 'avoid_redirection' is true (we are in Backend Mode), we will
	  * not enqueue any file, and the user won't be geolocated nor
	  * redirected.
	  */
	$avoid_redirection = get_option( 'geolocation_avoid_redirection' );

	if( ! $avoid_redirection ) {
		/**
		  * If it's an empty market, the geolocation.js was not enqueued,
		  * so we print its contents here.
		  */
		if( empty( $geolocation_market ) ) {
			echo '<script>';

			echo 'var geolocation_settings=' . wp_json_encode( geolocation_javascript_settings( true ) ) . ';';

			echo file_get_contents( __DIR__ . '/geolocation.min.js' );

			echo '</script>';
		}
	}
}

/**
  * Functions that returns the URL of the webservice that geolocates users.
  */
function geolocation_get_webservice_url() {
	$url = GEOLOCATION_WEBSERVICE;

	if( $default_market = get_option( 'geolocation_default_market' ) )
		$url = add_query_arg( 'default', $default_market, $url );

	if( $type = get_option( 'geolocation_type' ) )
		$url = add_query_arg( 'type', $type, $url );

	if( $provider = get_option( 'geolocation_provider' ) )
		$url = add_query_arg( 'provider', $provider, $url );

	return $url;
}
/**
  * Function that returns the JavaScript settings that will be sent to
  * the geolocation.js file.
  * It receives the parameter $sync, that basically will indicate if
  * calling the geo webservice will be made synchronously or
  * asynchronously.
  * It will return an array will all the settings.
  */
function geolocation_javascript_settings( $sync ) {
	global $geolocation_market, $geolocation_markets;

	/**
	  * We apply a filter here so any theme could overwrite any of the
	  * settings.
	  */
	return apply_filters( 'geolocation_javascript_settings', array(
		'default_location'	=> GEOLOCATION_DEFAULT_LOCATION,
		'service'			=> geolocation_get_webservice_url(),
		'current_market'	=> $geolocation_market,
		'markets'			=> $geolocation_markets,
		'sync'				=> $sync,
		'cookie'			=> array(
			'name'		=> get_option( 'geolocation_cookie', GEOLOCATION_COOKIE ),
			'expires'	=> date( 'D, d M Y H:i:s T', GEOLOCATION_COOKIE_EXPIRATION )
			)
		)
	);
}

/**
  * This function is hooked to the 'redirect_canonical' filter.
  * Some times, for 404 errors, there was a loop redirection if this filter
  * is not enabled.
  */
function geolocation_redirect_canonical_filter( $redirect_url ) {
	return is_404() ? false : $redirect_url;
}

/**
 * Filters the URL for a market.
 *
 * @param string  $url		Original URL.
 * @param WP_Term $term		Term to be analyzed.
 * @param string  $taxonomy	Taxonomy of the term.
 *
 * @return string Filtered URL.
 */
function geolocation_term_link_filter( $url, $term, $taxonomy ) {
	if ( 'market' === $taxonomy ) {
		remove_filter( 'home_url', 'geolocation_convert_url', 10, 2 );

		$url = get_home_url();

		$global_market = geolocation_get_global_market();

		if ( ! $global_market || $global_market->term_id !== $term->term_id ) {
			$url .= '/' . $term->slug;
		}

		add_filter( 'home_url', 'geolocation_convert_url', 10, 2 );
	}

	return $url;
}

/**
  * We want top priority for the next two hooks. The geolocation needs to
  * be executed as earlier as it's possible.
  */
add_action( 'wp_enqueue_scripts', 'geolocation_wp_enqueue_scripts', 1 );
add_action( 'wp_head', 'geolocation_wp_head', 1 );

/**
  * First, we hooked 'actions', and now filters.
  */
add_filter( 'redirect_canonical', 'geolocation_redirect_canonical_filter' );
add_filter( 'home_url',	'geolocation_convert_url', 10, 2 );

/**
  * These last two filters were add to make the Jetpack Contact Form
  * compatible with this plugin.
  */
add_filter( 'grunion_contact_form_redirect_url', 'geolocation_convert_url' );
add_filter( 'grunion_contact_form_form_action', function( $url ) {
	return preg_replace( '/([^\/])#contact-form/', '$1/#contact-form', $url );
} );

/**
 *
 * New filter to update market on SEO tags.
 */
add_filter( 'amt_metatags', function( $text ) {
	$market_slug = geolocation_get_user_location();
	$market_name = '';

	if ( $market_slug ) {
		$market = wpcom_vip_get_term_by( 'slug', $market_slug, 'market' );

		if ( $market ) {
			$market_name = $market->name;
		}
	}

	return str_replace( '%market%', $market_name, $text );
} );

/**
 * When the link for a market is displayed, we really would like to print
 * the home page link to that market.
 */
add_filter( 'term_link', 'geolocation_term_link_filter', 10, 3 );

add_filter( 'body_class', function( $classes ) {
	$user_location = geolocation_get_user_location();

	if ( ! $user_location ) {
		$user_location = 'default';
	}

	$classes[] = 'geolocation-market-' . sanitize_title( $user_location );

	return $classes;
} );

add_filter( 'wp_nav_menu_items', function( $items ) {
	$replacements = array(
		'#market',
		'#local_market',
		'%market%',
	);

	$market_id 		= geolocation_get_current_market_id();
	$market			= null;
	$market_prefix	= '';
	$market_name	= '';

	if ( $market_id ) {
		$market = geolocation_get_market( $market_id );

		if ( $market ) {
			$market_prefix = '/' . $market->slug;
			$market_name   = $market->name;
		}
	}

	foreach ( $replacements as $replacement ) {
		$items = preg_replace_callback( '/href="[^"]*"/', function( $matches ) use ( $replacement, $market_prefix ) {
			return str_replace( $replacement, $market_prefix, $matches[0] );
		}, $items );
	}

	$replacements = array(
		'#market_name',
		'#local_market_name',
		'%market_name%',
	);

	if ( $market_name ) {
		foreach ( $replacements as $replacement ) {
			$items = preg_replace_callback( '|\>(.*?)\<|', function( $matches ) use ( $replacement, $market_name ) {
				return str_replace( $replacement, $market_name, $matches[0] );
			}, $items );
		}
	}

	return $items;
} );

/**
 * Widget visibility.
 */
add_filter( 'widget_update_callback', function( $instance ) {
	$markets = array();

	if ( isset( $_POST['geolocation_markets'] ) ) {
		$possible_markets = $_POST['geolocation_markets'];

		if ( is_array( $possible_markets ) ) {
			foreach ( $possible_markets as $possible_market ) {
				$market = absint( $possible_market );

				if ( $market ) {
					$markets[] = $market;
				}
			}	
		}
	}

	if ( ! empty( $markets ) ) {
		$instance['geolocation_markets'] = $markets;
	} else {
		if ( isset( $instance['geolocation_markets'] ) ) {
			unset( $instance['geolocation_markets'] );
		}

		/**
		 * For backwards compatibility.
		 */
		if ( isset( $instance['geolocation_market'] ) ) {
			unset( $instance['geolocation_market'] );
		}
	}

	return $instance;
} );

add_filter( 'widget_display_callback', function( $instance ) {
	if ( ! empty( $instance['geolocation_markets'] ) ) {
		$markets = $instance['geolocation_markets'];
	} elseif ( ! empty( $instance['geolocation_market'] ) ) {
		/**
		 * For backwards compatibility.
		 */
		$markets = array( $instance['geolocation_market'] );
	} else {
		$markets = null;
	}

	if ( ! empty( $markets ) ) {
		$current_market = absint( geolocation_get_current_market_id() );
		$is_allowed     = false;

		foreach ( $markets as $market ) {
			if ( $market === $current_market ) {
				$is_allowed = true;

				break;
			}
		}

		if ( ! $is_allowed ) {
			$instance = false;
		}
	}

	return $instance;
} );

add_action( 'in_widget_form', function( $widget, $return, $instance ) {
	$markets = geolocation_get_markets();

	if( isset( $instance['geolocation_markets'] ) ) {
		$instance_markets = $instance['geolocation_markets'];
	} else if( isset( $instance['geolocation_markets'] ) ) {
		/**
		 * For backwards compatibility.
		 */
		$instance_markets = array( $instance['geolocation_market'] );
	} else {
		$instance_markets = null;
	}

	if ( ! empty( $instance_markets ) ) {
		$instance_markets_map = array_flip( $instance_markets );
	}
	?>
<p>
	<label><?php esc_html_e( 'Show in market?:', 'geolocation' ); ?></label>
	<select name="geolocation_markets[]" class="widefat" multiple>
		<option value=""<?php selected( empty( $instance_markets ) ); ?>><?php esc_html_e( 'Any', 'geolocation' ); ?></option>
		<?php foreach ( $markets as $market ) { ?>
		<option value="<?php echo esc_attr( $market->term_id ); ?>"<?php selected( isset( $instance_markets_map[ $market->term_id ] ) ); ?>><?php echo esc_html( $market->name ); ?></option>
		<?php } ?>
	</select>
</p>
	<?php
}, 10, 3 );

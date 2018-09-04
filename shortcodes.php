<?php
add_shortcode( 'redirect', function( $atts ) {
	$html 		= '';
	$atts 		= shortcode_atts( array(
		'url'		=> '',
		'market'	=> false
		), $atts
	);

	if ( ! empty( $atts['url'] ) ) {
		$market_id = geolocation_get_current_market_id();

		if ( $market_id ) {
			$market = geolocation_get_market( $market_id );
		} else {
			$market = null;
		}

		if ( empty( $atts['market'] ) 	||
			( ! empty( $market ) 		&&
			  $market->slug == $atts['market'] ) ) {
			$html = '<script>window.location.href = ' . wp_json_encode( $atts['url'] ) . ';</script>';
		}
	}

	return $html;
} );

add_shortcode( 'geolocation_market_choice', function( $atts ) {
	$html 		= '';
	$atts 		= shortcode_atts( array(
		'list_label'	=> __( 'Where are you?', 'geolocation' )
		), $atts
	);

	global $geolocation_market;

	$list_label = sanitize_text_field( $atts['list_label'] );

	$global_market_id = get_option( 'geolocation_global_market' );

	$actual_geolocation_market = $geolocation_market;

	$markets = get_terms( array(
		'hide_empty'		=> 0,
		'taxonomy'			=> 'market'
	) );

	if ( $markets ) {
		$html_escaped	 = '<div class="geolocation-markets-url-list-container"><ul class="geolocation-markets-url-list">';
		$html_escaped	.= '<li>' . esc_html( $list_label ) . '</li>';
		$html_aux		 = '';

		foreach ( $markets as $market ) {

			if ( $global_market_id != $market->term_id ){
				$geolocation_market = $market->slug;

				if ( $actual_geolocation_market == $geolocation_market ) {
					$html_escaped .= '<li class="current-market"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $market->name ) . '</a></li>';
				} else {
					$html_aux .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( $market->name ) . '</a></li>';
				}
			}
		}
		$html_escaped .= $html_aux;
		$html_escaped .= '</ul></div>';
	}

	$geolocation_market = $actual_geolocation_market;

	return $html_escaped;
} );

add_shortcode( 'geolocation_case_market', function( $atts, $content ) {
	$atts = shortcode_atts( array(
		'markets'	=> false,
	), $atts );

	$html = '';

	if ( $atts['markets'] ){
		$markets = explode( ',', $atts['markets'] );

		foreach ( $markets as $key => $market )
			$markets[ $key ] = trim( $market );

		if ( $current_market = geolocation_get_user_location() )
			if ( ( in_array( $current_market, $markets, true ) ) && ( ! empty( $content ) ) )
				$html .= do_shortcode( $content );
	} else {
		if ( ( ! empty( $content ) ) && ( ! geolocation_get_user_location() ) )
			$html .= do_shortcode( $content );
	}

	return $html;
} );

/**
 * This filter inserts the market parameter in every shortcode.
 */
add_filter( 'pre_do_shortcode_tag', function( $html, $tag, $attr ) {
	if ( isset( $attr['market'] ) ) {
		$is_allowed = true;
		$market_id 	= geolocation_get_current_market_id();

		if ( $market_id ) {
			$is_allowed = false;
			$market 	= geolocation_get_market( $market_id );

			if ( $market ) {
				$allowed_markets = array_map( function( $value ) {
					return strtolower( trim( $value ) );
				}, explode( ',', $attr['market'] ) );

				if ( in_array( $market->slug, $allowed_markets, true ) ) {
					$is_allowed = true;
				}
			}
		} else {
			$is_allowed = false;
		}

		if ( ! $is_allowed ) {
			/**
			 * Returning an empty string we shortcircuit the shortcode
			 * processing.
			 */
			$html = '';
		}
	}

	return $html;
}, 10, 3 );

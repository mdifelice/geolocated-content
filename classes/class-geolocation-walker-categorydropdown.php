<?php
/**
 *
 * @package Geolocation
 */

class Geolocation_Walker_CategoryDropdown extends Walker_CategoryDropdown {
	function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		if ( empty( $output ) ) {
			$output .= '<option value="">' . __( 'All locations', 'geolocation' ) . '</option>';
		}

		$output .= '<option class="level-' . esc_attr( $depth ).'" value="' . esc_attr( $category->slug ) . '"';

		if ( $category->slug === $args['selected'] ) {
			$output .= ' selected="selected"';
		}

		$output .= '>';
		$output .= esc_html( $category->name );
		
		$output .= '</option>';
	}
}

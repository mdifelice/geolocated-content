<?php
/**
 * Custom walker for printing location checklist.
 *
 * @package Geolocated_Content
 */

/**
 * Class definition.
 */
class Geolocated_Content_Walker_Location_Checklist extends Walker {
	/**
	 * Start the element output.
	 *
	 * @see Walker::start_el()
	 *
	 * @param string $output   Used to append additional content (passed by
	 *                         reference).
	 * @param object $category The current term object.
	 * @param int    $depth    Optional. Depth of the term in reference to
	 *                         parents. Default 0.
	 * @param array  $args     Optional. An array of arguments. Default empty
	 *                         array (@see wp_terms_checklist()).
	 * @param int    $id       Optional. ID of the current term. Default 0.
	 */
	public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		$output .= sprintf(
			'<p><label class="selectit"><input type="checkbox" value="%s" name="%s[]"%s /> %s</label></p>',
			$category->term_id,
			apply_filters( 'geolocated_content_walker_location_checklist_input_name', 'geolocated_content_location_id' ),
			checked( in_array( $category->term_id, $args['selected_cats'], true ), true, false ),
			esc_html( $category->name )
		);
	}
}

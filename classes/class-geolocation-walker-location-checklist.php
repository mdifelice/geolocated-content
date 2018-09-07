<?php
class Geolocation_Walker_Location_Checklist extends Walker {
    /**
     * Start the element output.
     *
     * @see Walker::start_el()
     *
     * @param string $output   Used to append additional content (passed by reference).
     * @param object $category The current term object.
     * @param int    $depth    Depth of the term in reference to parents. Default 0.
     * @param array  $args     An array of arguments. @see wp_terms_checklist()
     * @param int    $id       ID of the current term.
     */
	public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		$output .= sprintf(
			'<p><label class="selectit"><input type="checkbox" value="%s" name="%s[]"%s /> %s</label></p>',
			$category->term_id,
			apply_filters( 'geolocation_walker_location_checklist_input_name', 'geolocation_location_id' ),
			checked( in_array( $category->term_id, $args['selected_cats'], true ), true, false ),
			esc_html( $category->name )
		);
	}
}

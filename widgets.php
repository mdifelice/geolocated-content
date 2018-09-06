<?php
/**
 * Widgets generated by this plugin.
 *
 * @package Entravision_Plugins
 */

/**
 * Registers widgets.
 */
add_action( 'widgets_init', function() {
	register_widget( 'Geolocation_Location_List_Widget' );

	register_widget( 'Geolocation_Location_Link_Widget' );
} );

/**
 * Allows to select in which locations to show a widget.
 */
add_action( 'in_widget_form', function( $widget, $return, $instance ) {
	$locations = geolocation_get_locations();

	if( isset( $instance['geolocation_location_ids'] ) ) {
		$instance_location_ids = $instance['geolocation_location_ids'];
	} else {
		$instance_location_ids = null;
	}

	if ( ! empty( $instance_location_ids ) ) {
		$instance_location_ids_map = array_flip( $instance_location_ids );
	}
	?>
<p>
	<label><?php esc_html_e( 'Show in location?:', 'geolocation' ); ?></label>
	<?php geolocation_dropdown( $instance_location_ids, true ); ?>
</p>
	<?php
}, 10, 3 );

/**
 * When a widget is updated we save the locations where we want to display it.
 */
add_filter( 'widget_update_callback', function( $instance ) {
	$location_ids = array();

	if ( isset( $_POST['geolocation_location_ids'] ) ) {
		$possible_location_ids = $_POST['geolocation_location_ids'];

		if ( is_array( $possible_location_ids ) ) {
			foreach ( $possible_location_ids as $possible_location_id ) {
				$location_id = absint( $possible_location_id );

				if ( $location_id ) {
					$location_ids[] = $location_id;
				}
			}	
		}
	}

	if ( ! empty( $location_ids ) ) {
		$instance['geolocation_location_ids'] = $location_ids;
	} else {
		if ( isset( $instance['geolocation_location_ids'] ) ) {
			unset( $instance['geolocation_location_ids'] );
		}
	}

	return $instance;
} );

/**
 * Determines if the widget must be displayed or not.
 */
add_filter( 'widget_display_callback', function( $instance ) {
	if ( ! empty( $instance['geolocation_location_ids'] ) ) {
		$location_ids = $instance['geolocation_location_ids'];
	} else {
		$location_ids = null;
	}

	if ( ! empty( $location_ids ) ) {
		$visitor_location_id = geolocation_get_visitor_location_id();
		$is_allowed          = false;

		foreach ( $location_ids as $location_id ) {
			if ( $location_id === $visitor_location_id ) {
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

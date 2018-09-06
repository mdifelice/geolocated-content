<?php
/**
 * Prints a list of locations.
 *
 * @package Geolocation
 */

class Geolocation_Location_List_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( false, __( 'Geolocation Location List', 'geolocation' ) );
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
		   echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		geolocation_print_location_list( array(
			'home' => ! empty( $instance['home'] ),
		) );

		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wp_parse_args(
			$instance,
			array(
				'title' => '',
				'home'  => 'yes',
			)
		);
		?>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'geolocation' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'home' ) ); ?>"><?php esc_html_e( 'Link to location home?:', 'geolocation' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'home' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'home' ) ); ?>" type="checkbox" value="yes"<?php checked( 'yes', $instance['home'] ); ?> />
</p>
		<?php
	}
}

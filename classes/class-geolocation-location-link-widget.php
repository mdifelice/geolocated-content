<?php
/**
 * Widget that prints the current location archive link.
 *
 * @package Geolocation.
 */

/**
 * Class definition.
 */
class Geolocation_Location_Link_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( false, __( 'Geolocation Location Link', 'geolocation' ) );
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		geolocation_print_location_link(
			array(
				'text' => ! empty( $instance['text'] ) ? $instance['text'] ) : '',
				'home' => ! empty( $instance['home'] ),
			)
		);

		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$instance = wp_parse_args(
			$instance,
			array(
				'text' => '',
				'home' => 'yes',
			)
		);
		?>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>"><?php esc_html_e( 'Text:', 'geolocation' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'text' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['text'] ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'home' ) ); ?>"><?php esc_html_e( 'Link to location home?:', 'geolocation' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'home' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'home' ) ); ?>" type="checkbox" value="yes"<?php checked( 'yes', $instance['home'] ); ?> />
</p>
		<?php
	}
}

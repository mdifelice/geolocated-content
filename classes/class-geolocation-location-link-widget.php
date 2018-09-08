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
	/**
	 * Widget constructor.
	 */
	public function __construct() {
		parent::__construct( false, __( 'Geolocation Location Link', 'geolocation' ) );
	}

	/**
	 * Prints widget.
	 *
	 * @param array $args     Sidebar options.
	 * @param array $instance Widget options.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // WPCS: XSS ok.

		geolocation_template_location_link(
			array(
				'text' => ! empty( $instance['text'] ) ? $instance['text'] : '',
				'home' => ! empty( $instance['home'] ),
			)
		);

		echo $args['after_widget']; // WPCS: XSS ok.
	}

	/**
	 * Prints widget form.
	 *
	 * @param array $instance Widget data.
	 */
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

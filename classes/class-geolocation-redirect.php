<?php
/**
 * Redirects to another URL if a specified market is being visited.
 *
 * @package Geolocation.
 */

/**
 * Class definition.
 */
class Geolocation_Redirect extends WP_Widget {
	/**
	 * Widget constructor.
	 */
	public function __construct() {
		parent::__construct( false, __( 'Geolocation Location Redirect', 'geolocation' ) );
	}

	/**
	 * Prints widget.
	 *
	 * @param array $args     Sidebar options.
	 * @param array $instance Widget options.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // WPCS: XSS ok.

		geolocation_template_redirection(
			array(
				'url'         => ! empty( $instance['url'] ) ? $instance['url'] : '',
				'location_id' => ! empty( $instance['location_id'] ) ? $instance['location_id'] : '',
			)
		);

		echo $args['after_widget']; // WPCS: XSS ok.
	}

	/**
	 * Updates the widget.
	 *
	 * @param array $new_instance New widget data.
	 * @param array $old_instance Old widget data.
	 *
	 * @return array New widget data.
	 */
	public function update( $new_instance, $old_instance ) {
		$new_instance['url']         = esc_url( $new_instance['url'] );
		$new_instance['location_id'] = absint( $new_instance['location_id'] );

		return $new_instance;
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
				'url'         => null,
				'location_id' => null,
			)
		);
		?>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>"><?php esc_html_e( 'Redirection URL:', 'geolocation' ); ?></label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'url' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'url' ) ); ?>" type="url" value="<?php echo esc_attr( $instance['url'] ); ?>" />
</p>
<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'location_id' ) ); ?>"><?php esc_html_e( 'Location:', 'geolocation' ); ?></label>
		<?php
		geolocation_dropdown( array(
			'id'       => $this->get_field_id( 'location_id' ),
			'name'     => $this->get_field_name( 'location_id' ),
			'selected' => $instance['location_id'],
		) );
		?>
</p>
		<?php
	}
}

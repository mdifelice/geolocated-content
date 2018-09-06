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
	public function __construct() {
		parent::__construct( false, __( 'Geolocation Location Redirect', 'geolocation' ) );
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		geolocation_print_redirection(
			array(
				'url'         => ! empty( $instance['url'] ) ? $instance['url'] ) : '',
				'location_id' => ! empty( $instance['location_id'] ) ? $instance['location_id'] : '',
			)
		);

		echo $args['after_widget'];
	}

	public function update( $instance ) {
		$instance['url']         = esc_url( $instance['url'] );
		$instance['location_id'] = absint( $instance['location_id'] );

		return $instance;
	}

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
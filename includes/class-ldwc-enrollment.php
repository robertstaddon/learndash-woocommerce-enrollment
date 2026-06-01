<?php
/**
 * WooCommerce Product enrollment mode for LearnDash courses.
 *
 * @package LearnDash_WooCommerce_Enrollment
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wc_product price type, product selector, and frontend enrollment UI.
 */
class LDWC_Enrollment {

	/**
	 * LearnDash course_price_type slug for this mode.
	 */
	public const PRICE_TYPE = 'wc_product';

	/**
	 * Setting field keys saved with the course enrollment metabox.
	 *
	 * @var string[]
	 */
	private array $settings_fields = array(
		'course_price_type_wc_product_id',
	);

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'learndash_settings_fields', array( $this, 'register_settings_fields' ), 10, 2 );
		add_filter( 'learndash_metabox_save_fields', array( $this, 'save_metabox_fields' ), 10, 3 );
		add_filter( 'learndash_settings_save_values', array( $this, 'filter_saved_values' ), 10, 2 );

		add_filter( 'learndash_get_course_price', array( $this, 'filter_course_price' ), 10, 1 );
		add_action( 'learndash-course-infobar-action-cell-before', array( $this, 'render_infobar_action' ), 10, 3 );
		add_filter( 'learndash_no_price_price_label', array( $this, 'filter_price_label' ) );
		add_filter( 'learndash_course_grid_ribbon_text_allow_html', '__return_true' );
		add_filter( 'learndash_payment_button', array( $this, 'filter_payment_button' ), 10, 2 );
	}

	/**
	 * Add WooCommerce Product option to Course Enrollment settings.
	 *
	 * @param array<string, mixed> $setting_option_fields Metabox fields.
	 * @param string               $settings_metabox_key    Metabox key.
	 * @return array<string, mixed>
	 */
	public function register_settings_fields( $setting_option_fields, $settings_metabox_key ) {
		if ( 'learndash-course-enrollment' !== $settings_metabox_key ) {
			return $setting_option_fields;
		}

		if (
			! class_exists( 'LearnDash_Settings_Metabox' )
			|| empty( $settings_instance = LearnDash_Settings_Metabox::get_metabox_instance( 'LearnDash_Settings_Metabox_Course_Enrollment' ) )
		) {
			return $setting_option_fields;
		}

		global $post;

		if ( ! $post instanceof WP_Post ) {
			return $setting_option_fields;
		}

		$existing_option_values = learndash_get_setting( $post->ID );
		$product_id             = isset( $existing_option_values['course_price_type_wc_product_id'] )
			? (string) $existing_option_values['course_price_type_wc_product_id']
			: '';

		$select_product_options = array(
			'' => esc_html__( 'Select Product', 'learndash-woocommerce-enrollment' ),
		);

		$products = $this->select_a_product();
		if ( ! empty( $products ) ) {
			$select_product_options = $select_product_options + $products;
		}

		$product_field = array(
			'name'        => 'course_price_type_wc_product_id',
			'type'        => 'select',
			'label'       => esc_html__( 'Enrollment Product', 'learndash-woocommerce-enrollment' ),
			'default'     => '',
			'value'       => $product_id,
			'options'     => $select_product_options,
			'placeholder' => '',
			'help_text'   => sprintf(
				/* translators: %s: course label lower */
				esc_html_x(
					'Select the WooCommerce product customers must purchase to enroll in this %s.',
					'placeholder: course',
					'learndash-woocommerce-enrollment'
				),
				learndash_get_custom_label_lower( 'course' )
			),
			'rest'        => array(
				'show_in_rest' => class_exists( 'LearnDash_REST_API' ) && LearnDash_REST_API::enabled(),
				'rest_args'    => array(
					'schema' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			),
		);

		$product_field = $settings_instance->load_settings_field( $product_field );

		$inline_fields = array(
			'course_price_type_wc_product_id' => $product_field,
		);

		$setting_option_fields['course_price_type']['options'][ self::PRICE_TYPE ] = array(
			'label'       => esc_html__( 'WooCommerce Product', 'learndash-woocommerce-enrollment' ),
			'description' => sprintf(
				/* translators: %s: course label lower */
				esc_html_x(
					'The %s will be closed unless purchased or manually enrolled. The enrollment button adds the selected WooCommerce product to the cart and sends the student to checkout.',
					'placeholder: course',
					'learndash-woocommerce-enrollment'
				),
				learndash_get_custom_label_lower( 'course' )
			),
			'inline_fields'       => array(
				'course_price_type_wc_product' => $inline_fields,
			),
			'inner_section_state' => ( self::PRICE_TYPE === ( $existing_option_values['course_price_type'] ?? '' ) ) ? 'open' : 'closed',
		);

		return $setting_option_fields;
	}

	/**
	 * Persist custom enrollment fields on course save.
	 *
	 * @param array<string, mixed> $settings_field_updates Fields to update.
	 * @param string               $settings_metabox_key   Metabox key.
	 * @param string               $settings_screen_id     Screen id.
	 * @return array<string, mixed>
	 */
	public function save_metabox_fields( $settings_field_updates, $settings_metabox_key, $settings_screen_id ) {
		unset( $settings_screen_id );

		if ( 'learndash-course-enrollment' !== $settings_metabox_key ) {
			return $settings_field_updates;
		}

		if ( ! isset( $_POST['learndash-course-enrollment'] ) || ! is_array( $_POST['learndash-course-enrollment'] ) ) {
			return $settings_field_updates;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- LearnDash verifies on save.
		$post_values = wp_unslash( $_POST['learndash-course-enrollment'] );

		foreach ( $this->settings_fields as $setting_field ) {
			$settings_field_updates[ $setting_field ] = isset( $post_values[ $setting_field ] )
				? absint( $post_values[ $setting_field ] )
				: 0;
		}

		return $settings_field_updates;
	}

	/**
	 * Sync course_price from WooCommerce product HTML when using wc_product type.
	 *
	 * @param array<string, mixed> $settings_values      Values being saved.
	 * @param string               $settings_metabox_key Metabox key.
	 * @return array<string, mixed>
	 */
	public function filter_saved_values( $settings_values, $settings_metabox_key ) {
		if ( 'learndash-course-enrollment' !== $settings_metabox_key ) {
			return $settings_values;
		}

		if ( self::PRICE_TYPE !== ( $settings_values['course_price_type'] ?? '' ) ) {
			return $settings_values;
		}

		$post_id = 0;
		if ( isset( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- LearnDash verifies on save.
			$post_id = absint( $_POST['post_ID'] );
		}

		if ( $post_id > 0 ) {
			$settings_values['course_price'] = $this->get_price_display( $post_id );
		}

		return $settings_values;
	}

	/**
	 * Output enrollment button in LearnDash 3.0 course infobar.
	 *
	 * @param string $post_type Post type.
	 * @param int    $course_id Course ID.
	 * @param int    $user_id   User ID.
	 */
	public function render_infobar_action( $post_type, $course_id, $user_id ): void {
		unset( $post_type, $user_id );

		if ( ! $this->course_uses_wc_product( $course_id ) ) {
			return;
		}

		$button_html = $this->get_enroll_button_html( $course_id );

		if ( '' === $button_html ) {
			echo '<span class="ld-text">' . esc_html__( 'This course is currently closed', 'learndash' ) . '</span>';
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Button HTML is escaped when built.
		echo $button_html;

		echo '<style>.ld-course-status .ld-course-status-price .ld-currency { display: none; }</style>';
	}

	/**
	 * Inject WooCommerce product price into LearnDash course pricing.
	 *
	 * LearnDash only renders the price cell when `price` is non-empty, or when the
	 * type is `closed`/`free` (learndash_no_price_price_label). Custom types like
	 * wc_product otherwise show a blank price area.
	 *
	 * @param array<string, mixed> $pricing Course price array from LearnDash.
	 * @return array<string, mixed>
	 */
	public function filter_course_price( $pricing ) {
		if ( ! is_array( $pricing ) || self::PRICE_TYPE !== ( $pricing['type'] ?? '' ) ) {
			return $pricing;
		}

		$course_id = $this->resolve_course_id_for_pricing();
		if ( $course_id <= 0 ) {
			return $pricing;
		}

		$price_display = $this->get_price_display( $course_id );
		if ( '' !== $price_display ) {
			$pricing['price'] = $price_display;
		}

		return $pricing;
	}

	/**
	 * Show WooCommerce price when LearnDash uses the no-price label branch.
	 *
	 * @param string $default_price_display Default label.
	 * @return string
	 */
	public function filter_price_label( $default_price_display ) {
		$course_id = $this->resolve_course_id_for_pricing();

		if ( $course_id <= 0 || ! $this->course_uses_wc_product( $course_id ) ) {
			return $default_price_display;
		}

		$price_display = $this->get_price_display( $course_id );

		if ( '' === $price_display ) {
			return $default_price_display;
		}

		return $price_display;
	}

	/**
	 * Replace payment button for legacy themes (e.g. Boss).
	 *
	 * @param string               $payment_button Payment button HTML.
	 * @param array<string, mixed> $payment_params Payment parameters.
	 * @return string
	 */
	public function filter_payment_button( $payment_button, $payment_params ) {
		if ( ! isset( $payment_params['post'] ) || ! $payment_params['post'] instanceof WP_Post ) {
			return $payment_button;
		}

		$course_id = $payment_params['post']->ID;

		if ( ! $this->course_uses_wc_product( $course_id ) ) {
			return $payment_button;
		}

		$button_html = $this->get_enroll_button_html( $course_id );

		return '' !== $button_html ? $button_html : $payment_button;
	}

	/**
	 * Whether the course enrollment mode is WooCommerce Product.
	 *
	 * @param int $course_id Course post ID.
	 */
	private function course_uses_wc_product( int $course_id ): bool {
		$course_pricing = learndash_get_course_price( $course_id );

		return self::PRICE_TYPE === ( $course_pricing['type'] ?? '' );
	}

	/**
	 * Configured WooCommerce product ID for a course.
	 *
	 * @param int $course_id Course post ID.
	 */
	private function get_product_id( int $course_id ): int {
		if ( function_exists( 'learndash_get_setting' ) ) {
			$from_settings = absint( learndash_get_setting( $course_id, 'course_price_type_wc_product_id' ) );
			if ( $from_settings > 0 ) {
				return $from_settings;
			}
		}

		$meta = get_post_meta( $course_id, '_sfwd-courses', true );

		if ( ! is_array( $meta ) ) {
			return 0;
		}

		$key = 'sfwd-courses_course_price_type_wc_product_id';

		return isset( $meta[ $key ] ) ? absint( $meta[ $key ] ) : 0;
	}

	/**
	 * Resolve course ID when filtering course price (filter has no course param).
	 */
	private function resolve_course_id_for_pricing(): int {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );

		foreach ( $trace as $frame ) {
			if ( 'learndash_get_course_price' !== ( $frame['function'] ?? '' ) ) {
				continue;
			}

			if ( empty( $frame['args'][0] ) ) {
				continue;
			}

			$course = $frame['args'][0];

			if ( is_numeric( $course ) ) {
				return (int) $course;
			}

			if ( $course instanceof WP_Post ) {
				return (int) $course->ID;
			}
		}

		global $post;

		if ( $post instanceof WP_Post && 'sfwd-courses' === $post->post_type ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * WooCommerce product for course enrollment, if valid and purchasable.
	 *
	 * @param int $course_id Course post ID.
	 * @return WC_Product|null
	 */
	private function get_product( int $course_id ) {
		$product_id = $this->get_product_id( $course_id );

		if ( $product_id <= 0 ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			return null;
		}

		return $product;
	}

	/**
	 * Checkout URL that adds the product to the cart.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	private function get_checkout_enroll_url( int $product_id ): string {
		return add_query_arg(
			'add-to-cart',
			$product_id,
			wc_get_checkout_url()
		);
	}

	/**
	 * Enrollment link HTML for the course.
	 *
	 * @param int $course_id Course post ID.
	 */
	private function get_enroll_button_html( int $course_id ): string {
		$product = $this->get_product( $course_id );

		if ( ! $product ) {
			return '';
		}

		$button_text = $this->get_enroll_button_label();
		$button_url  = $this->get_checkout_enroll_url( $product->get_id() );

		return sprintf(
			'<a class="ld-button btn-join" href="%1$s" id="btn-join">%2$s</a>',
			esc_url( $button_url ),
			esc_html( $button_text )
		);
	}

	/**
	 * Standard LearnDash enroll button label.
	 */
	private function get_enroll_button_label(): string {
		if ( function_exists( 'learndash_get_custom_label' ) ) {
			$label = learndash_get_custom_label( 'button_take_this_course' );
			if ( is_string( $label ) && '' !== $label ) {
				return $label;
			}
		}

		return __( 'Take this Course', 'learndash' );
	}

	/**
	 * Price HTML from the linked WooCommerce product.
	 *
	 * @param int $course_id Course post ID.
	 */
	private function get_price_display( int $course_id ): string {
		$product_id = $this->get_product_id( $course_id );

		if ( $product_id <= 0 ) {
			return '';
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return '';
		}

		return $product->get_price_html();
	}

	/**
	 * Product options for the admin select field.
	 *
	 * @return array<int, string> Product ID => title.
	 */
	private function select_a_product(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'numberposts'    => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'suppress_filters' => false,
			)
		);

		$post_array = array();

		foreach ( $posts as $p ) {
			if ( $p instanceof WP_Post ) {
				$post_array[ $p->ID ] = $p->post_title;
			}
		}

		return $post_array;
	}
}

<?php
/**
 * Plugin Name: WP Notification Bar
 * Plugin URI: https://github.com/Azanumer/wp-notification-bar
 * Description: A dismissible announcement bar at the top of your site — sales, notices, maintenance warnings — with scheduling, page targeting and audience rules. No JavaScript libraries, no database table.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-notification-bar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WNB_VERSION', '1.0.0' );
define( 'WNB_OPTION', 'wnb_settings' );
define( 'WNB_COOKIE', 'wnb_dismissed' );

/**
 * Default settings.
 *
 * @return array
 */
function wnb_defaults() {
	return array(
		'enabled'       => 0,
		'text'          => '',
		'link_url'      => '',
		'link_text'     => '',
		'bg_color'      => '#1d2327',
		'text_color'    => '#ffffff',
		'position'      => 'top-fixed', // top-fixed | top-static.
		'show_on'       => 'all',       // all | home | posts | pages.
		'show_to'       => 'all',       // all | logged_in | logged_out.
		'dismissible'   => 1,
		'dismiss_days'  => 7,
		'start_date'    => '',
		'end_date'      => '',
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function wnb_get_settings() {
	$saved = get_option( WNB_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( wnb_defaults(), $saved );
}

/**
 * Should the bar render on this request?
 *
 * @param array $s Settings.
 * @return bool
 */
function wnb_should_show( $s ) {
	if ( empty( $s['enabled'] ) || '' === trim( $s['text'] ) ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	// Audience.
	if ( 'logged_in' === $s['show_to'] && ! is_user_logged_in() ) {
		return false;
	}
	if ( 'logged_out' === $s['show_to'] && is_user_logged_in() ) {
		return false;
	}

	// Page targeting.
	switch ( $s['show_on'] ) {
		case 'home':
			if ( ! is_front_page() && ! is_home() ) {
				return false;
			}
			break;
		case 'posts':
			if ( ! is_singular( 'post' ) ) {
				return false;
			}
			break;
		case 'pages':
			if ( ! is_page() ) {
				return false;
			}
			break;
	}

	// Schedule window (dates are local site dates, YYYY-MM-DD).
	$today = current_time( 'Y-m-d' );
	if ( '' !== $s['start_date'] && $today < $s['start_date'] ) {
		return false;
	}
	if ( '' !== $s['end_date'] && $today > $s['end_date'] ) {
		return false;
	}

	// Dismissed cookie.
	if ( ! empty( $s['dismissible'] ) && ! empty( $_COOKIE[ WNB_COOKIE ] ) ) {
		return false;
	}

	return (bool) apply_filters( 'wnb_show_bar', true, $s );
}

/**
 * Render the bar.
 */
function wnb_render_bar() {
	$s = wnb_get_settings();
	if ( ! wnb_should_show( $s ) ) {
		return;
	}

	$text      = apply_filters( 'wnb_bar_text', $s['text'] );
	$bg        = sanitize_hex_color( $s['bg_color'] ) ?: '#1d2327';
	$color     = sanitize_hex_color( $s['text_color'] ) ?: '#ffffff';
	$position  = 'top-static' === $s['position'] ? 'static' : 'fixed';
	$dismiss   = ! empty( $s['dismissible'] );
	$dismiss_d = max( 1, (int) $s['dismiss_days'] );

	$inner = esc_html( $text );
	if ( '' !== trim( $s['link_url'] ) ) {
		$label = '' !== trim( $s['link_text'] ) ? $s['link_text'] : __( 'Learn more', 'wp-notification-bar' );
		$inner .= ' <a href="' . esc_url( $s['link_url'] ) . '">' . esc_html( $label ) . '</a>';
	}

	echo '<div id="wnb-bar" role="region" aria-label="' . esc_attr__( 'Announcement', 'wp-notification-bar' ) . '" style="position:' . esc_attr( $position ) . ';top:0;left:0;right:0;z-index:99999;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $color ) . ';text-align:center;padding:10px 48px 10px 16px;font-size:15px;line-height:1.5;">';
	echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	if ( $dismiss ) {
		echo '<button type="button" id="wnb-dismiss" aria-label="' . esc_attr__( 'Dismiss announcement', 'wp-notification-bar' ) . '" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:transparent;border:0;color:inherit;font-size:20px;line-height:1;cursor:pointer;padding:4px 8px;">&times;</button>';
	}
	echo '</div>';

	if ( $dismiss ) {
		echo '<script>(function(){var b=document.getElementById("wnb-dismiss");if(!b)return;b.addEventListener("click",function(){var bar=document.getElementById("wnb-bar");if(bar)bar.remove();var d=new Date();d.setDate(d.getDate()+' . (int) $dismiss_d . ');document.cookie="' . WNB_COOKIE . '=1;expires="+d.toUTCString()+";path=/;SameSite=Lax";});})();</script>';
	}
}
add_action( 'wp_body_open', 'wnb_render_bar' );

/**
 * Fallback for themes without wp_body_open(): render at wp_footer and move
 * the bar to the top with CSS. The fixed bar works either way.
 */
function wnb_footer_fallback() {
	if ( ! did_action( 'wp_body_open' ) ) {
		wnb_render_bar();
	}
}
add_action( 'wp_footer', 'wnb_footer_fallback', 1 );

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

function wnb_register_settings() {
	register_setting(
		'wnb_settings_group',
		WNB_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wnb_sanitize_settings',
			'default'           => wnb_defaults(),
		)
	);

	add_settings_section( 'wnb_main', __( 'Announcement Bar', 'wp-notification-bar' ), '__return_null', 'wnb-settings' );
	add_settings_field( 'wnb_enabled', __( 'Enable bar', 'wp-notification-bar' ), 'wnb_field_checkbox', 'wnb-settings', 'wnb_main', array( 'key' => 'enabled' ) );
	add_settings_field( 'wnb_text', __( 'Announcement text', 'wp-notification-bar' ), 'wnb_field_text', 'wnb-settings', 'wnb_main', array( 'key' => 'text' ) );
	add_settings_field( 'wnb_link_url', __( 'Link URL (optional)', 'wp-notification-bar' ), 'wnb_field_text', 'wnb-settings', 'wnb_main', array( 'key' => 'link_url', 'type' => 'url' ) );
	add_settings_field( 'wnb_link_text', __( 'Link text (optional)', 'wp-notification-bar' ), 'wnb_field_text', 'wnb-settings', 'wnb_main', array( 'key' => 'link_text' ) );
	add_settings_field( 'wnb_bg_color', __( 'Background colour', 'wp-notification-bar' ), 'wnb_field_color', 'wnb-settings', 'wnb_main', array( 'key' => 'bg_color' ) );
	add_settings_field( 'wnb_text_color', __( 'Text colour', 'wp-notification-bar' ), 'wnb_field_color', 'wnb-settings', 'wnb_main', array( 'key' => 'text_color' ) );
	add_settings_field( 'wnb_position', __( 'Position', 'wp-notification-bar' ), 'wnb_field_select', 'wnb-settings', 'wnb_main', array(
		'key'     => 'position',
		'options' => array(
			'top-fixed'  => __( 'Fixed at top (stays visible while scrolling)', 'wp-notification-bar' ),
			'top-static' => __( 'Static at top (scrolls away)', 'wp-notification-bar' ),
		),
	) );
	add_settings_field( 'wnb_show_on', __( 'Show on', 'wp-notification-bar' ), 'wnb_field_select', 'wnb-settings', 'wnb_main', array(
		'key'     => 'show_on',
		'options' => array(
			'all'   => __( 'Every page', 'wp-notification-bar' ),
			'home'  => __( 'Home page only', 'wp-notification-bar' ),
			'posts' => __( 'Single posts only', 'wp-notification-bar' ),
			'pages' => __( 'Pages only', 'wp-notification-bar' ),
		),
	) );
	add_settings_field( 'wnb_show_to', __( 'Show to', 'wp-notification-bar' ), 'wnb_field_select', 'wnb-settings', 'wnb_main', array(
		'key'     => 'show_to',
		'options' => array(
			'all'        => __( 'Everyone', 'wp-notification-bar' ),
			'logged_in'  => __( 'Logged-in users only', 'wp-notification-bar' ),
			'logged_out' => __( 'Logged-out visitors only', 'wp-notification-bar' ),
		),
	) );
	add_settings_field( 'wnb_dismissible', __( 'Dismissible', 'wp-notification-bar' ), 'wnb_field_checkbox', 'wnb-settings', 'wnb_main', array( 'key' => 'dismissible' ) );
	add_settings_field( 'wnb_dismiss_days', __( 'Remember dismissal for (days)', 'wp-notification-bar' ), 'wnb_field_number', 'wnb-settings', 'wnb_main', array( 'key' => 'dismiss_days' ) );
	add_settings_field( 'wnb_start_date', __( 'Start date (optional)', 'wp-notification-bar' ), 'wnb_field_date', 'wnb-settings', 'wnb_main', array( 'key' => 'start_date' ) );
	add_settings_field( 'wnb_end_date', __( 'End date (optional)', 'wp-notification-bar' ), 'wnb_field_date', 'wnb-settings', 'wnb_main', array( 'key' => 'end_date' ) );
}
add_action( 'admin_init', 'wnb_register_settings' );

function wnb_add_menu() {
	add_options_page(
		__( 'Notification Bar', 'wp-notification-bar' ),
		__( 'Notification Bar', 'wp-notification-bar' ),
		'manage_options',
		'wnb-settings',
		'wnb_settings_page'
	);
}
add_action( 'admin_menu', 'wnb_add_menu' );

function wnb_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Notification Bar', 'wp-notification-bar' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wnb_settings_group' );
			do_settings_sections( 'wnb-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Sanitize the settings array.
 *
 * @param array $input Raw input.
 * @return array
 */
function wnb_sanitize_settings( $input ) {
	$out      = wnb_defaults();
	$input    = is_array( $input ) ? $input : array();
	$allowed  = array( 'top-fixed', 'top-static' );
	$show_on  = array( 'all', 'home', 'posts', 'pages' );
	$show_to  = array( 'all', 'logged_in', 'logged_out' );

	$out['enabled']      = ! empty( $input['enabled'] ) ? 1 : 0;
	$out['dismissible']  = ! empty( $input['dismissible'] ) ? 1 : 0;
	$out['text']         = isset( $input['text'] ) ? sanitize_text_field( wp_unslash( $input['text'] ) ) : '';
	$out['link_url']     = isset( $input['link_url'] ) ? esc_url_raw( wp_unslash( $input['link_url'] ) ) : '';
	$out['link_text']    = isset( $input['link_text'] ) ? sanitize_text_field( wp_unslash( $input['link_text'] ) ) : '';
	$out['bg_color']     = isset( $input['bg_color'] ) ? sanitize_hex_color( wp_unslash( $input['bg_color'] ) ) ?: '#1d2327' : '#1d2327';
	$out['text_color']   = isset( $input['text_color'] ) ? sanitize_hex_color( wp_unslash( $input['text_color'] ) ) ?: '#ffffff' : '#ffffff';
	$out['position']     = isset( $input['position'] ) && in_array( $input['position'], $allowed, true ) ? $input['position'] : 'top-fixed';
	$out['show_on']      = isset( $input['show_on'] ) && in_array( $input['show_on'], $show_on, true ) ? $input['show_on'] : 'all';
	$out['show_to']      = isset( $input['show_to'] ) && in_array( $input['show_to'], $show_to, true ) ? $input['show_to'] : 'all';
	$out['dismiss_days'] = isset( $input['dismiss_days'] ) ? max( 1, (int) $input['dismiss_days'] ) : 7;
	$out['start_date']   = isset( $input['start_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['start_date'] ) ? $input['start_date'] : '';
	$out['end_date']     = isset( $input['end_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['end_date'] ) ? $input['end_date'] : '';

	return $out;
}

// Field renderers (all read from the merged settings array).
function wnb_field_checkbox( $args ) {
	$s = wnb_get_settings();
	printf(
		'<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>',
		esc_attr( WNB_OPTION ),
		esc_attr( $args['key'] ),
		checked( ! empty( $s[ $args['key'] ] ), true, false ),
		esc_html__( 'Yes', 'wp-notification-bar' )
	);
}

function wnb_field_text( $args ) {
	$s    = wnb_get_settings();
	$type = isset( $args['type'] ) ? $args['type'] : 'text';
	printf(
		'<input type="%s" name="%s[%s]" value="%s" class="regular-text">',
		esc_attr( $type ),
		esc_attr( WNB_OPTION ),
		esc_attr( $args['key'] ),
		esc_attr( $s[ $args['key'] ] )
	);
}

function wnb_field_color( $args ) {
	$s = wnb_get_settings();
	printf(
		'<input type="color" name="%s[%s]" value="%s">',
		esc_attr( WNB_OPTION ),
		esc_attr( $args['key'] ),
		esc_attr( $s[ $args['key'] ] )
	);
}

function wnb_field_select( $args ) {
	$s = wnb_get_settings();
	printf( '<select name="%s[%s]">', esc_attr( WNB_OPTION ), esc_attr( $args['key'] ) );
	foreach ( $args['options'] as $value => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $s[ $args['key'] ], $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}

function wnb_field_number( $args ) {
	$s = wnb_get_settings();
	printf(
		'<input type="number" min="1" name="%s[%s]" value="%d" class="small-text">',
		esc_attr( WNB_OPTION ),
		esc_attr( $args['key'] ),
		(int) $s[ $args['key'] ]
	);
}

function wnb_field_date( $args ) {
	$s = wnb_get_settings();
	printf(
		'<input type="date" name="%s[%s]" value="%s">',
		esc_attr( WNB_OPTION ),
		esc_attr( $args['key'] ),
		esc_attr( $s[ $args['key'] ] )
	);
}

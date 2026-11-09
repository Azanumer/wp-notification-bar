<?php
/**
 * Uninstall WP Notification Bar.
 *
 * Removes the plugin's single options row. The dismissal cookie is
 * client-side and expires on its own; nothing else is stored.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wnb_settings' );

<?php
/**
 *
 *
 */

defined( 'ABSPATH' ) || die( 'File cannot be accessed directly' );

// See if block editor is available.
if ( ! function_exists( 'register_block_type' ) ) {
	return;
}

// Register block type.
function pmpromd_register_profile_block() {
	register_block_type( 'pmpro-member-directory/profile', array(
		'editor_script' => 'pmpromd-profile-block',
		'render_callback' => 'pmpromd_profile_shortcode'
	) );
}
add_action( 'init', 'pmpromd_register_profile_block' );	

/**
 * Register block editor scripts and styles.
 *
 * @since TBD
 */
function pmpromd_enqueue_profile_block_editor_assets() {
	// Only load on post and page edit screens.
	if ( ! in_array( get_post_type(), array( 'page', 'post' ) ) ) {
		return;
	}

	wp_register_script( 
		'pmpromd-profile-block', 
		plugins_url( 'block.build.js', __FILE__ ), 
		array( 'wp-i18n', 'wp-element', 'wp-blocks', 'wp-components', 'wp-api', 'wp-editor', 'pmpro_admin' )
	);
}
add_action( 'enqueue_block_editor_assets', 'pmpromd_enqueue_profile_block_editor_assets' );
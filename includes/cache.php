<?php
/**
 * Member Directory query result caching.
 *
 * Caches the user list + total count for [pmpro_member_directory] queries
 * keyed by the final SQL string. Invalidated by user / membership lifecycle
 * hooks via a version counter so stale entries fall out naturally on TTL.
 */

defined( 'ABSPATH' ) || exit;

define( 'PMPROMD_CACHE_GROUP', 'pmpro_member_directory' );

/**
 * Get the current cache version. Incrementing this invalidates every cached
 * directory query in one shot. Stored in a non-autoloaded option so it
 * survives an object-cache flush.
 *
 * @return int
 */
function pmpromd_get_cache_version() {
	$version = wp_cache_get( 'cache_version', PMPROMD_CACHE_GROUP );
	if ( false === $version ) {
		$version = (int) get_option( 'pmpromd_cache_version', 1 );
		wp_cache_set( 'cache_version', $version, PMPROMD_CACHE_GROUP, HOUR_IN_SECONDS );
	}
	return (int) $version;
}

/**
 * Bump the cache version, invalidating all cached query results.
 *
 * @return void
 */
function pmpromd_bump_cache_version() {
	$version = pmpromd_get_cache_version() + 1;
	update_option( 'pmpromd_cache_version', $version, false );
	wp_cache_set( 'cache_version', $version, PMPROMD_CACHE_GROUP, HOUR_IN_SECONDS );
}

/**
 * Build a cache key for a directory query. Hash includes the version counter
 * so a bump invalidates every existing key in one operation.
 *
 * @param string $sql The final assembled SQL string.
 * @return string
 */
function pmpromd_get_cache_key( $sql ) {
	return 'query_' . md5( pmpromd_get_cache_version() . '|' . $sql );
}

/**
 * Look up a cached result set for the given SQL.
 *
 * @param string $sql The final assembled SQL string.
 * @return array|false { users: array, totalrows: int } on hit, false on miss or when caching is disabled.
 */
function pmpromd_get_cached_results( $sql ) {
	/**
	 * Filter: 'pmpro_member_directory_cache_enabled' - Toggle the query cache.
	 *
	 * @param bool $enabled True to read/write the cache, false to bypass.
	 */
	if ( ! apply_filters( 'pmpro_member_directory_cache_enabled', true ) ) {
		return false;
	}
	return wp_cache_get( pmpromd_get_cache_key( $sql ), PMPROMD_CACHE_GROUP );
}

/**
 * Store directory query results in cache.
 *
 * @param string $sql       The final assembled SQL string.
 * @param array  $users     User result rows from $wpdb->get_results().
 * @param int    $totalrows COUNT(DISTINCT u.ID) result.
 * @return void
 */
function pmpromd_set_cached_results( $sql, $users, $totalrows ) {
	if ( ! apply_filters( 'pmpro_member_directory_cache_enabled', true ) ) {
		return;
	}
	/**
	 * Filter: 'pmpro_member_directory_cache_ttl' - Cache lifetime in seconds.
	 *
	 * @param int $ttl Default 15 * MINUTE_IN_SECONDS.
	 */
	$ttl = (int) apply_filters( 'pmpro_member_directory_cache_ttl', 15 * MINUTE_IN_SECONDS );
	wp_cache_set(
		pmpromd_get_cache_key( $sql ),
		array(
			'users'     => $users,
			'totalrows' => (int) $totalrows,
		),
		PMPROMD_CACHE_GROUP,
		$ttl
	);
}

/**
 * Invalidate when a member's directory-relevant user meta changes.
 *
 * @param int    $meta_id    Meta row ID.
 * @param int    $object_id  User ID.
 * @param string $meta_key   Meta key being changed.
 * @param mixed  $meta_value New value.
 * @return void
 */
function pmpromd_maybe_bump_on_user_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
	/**
	 * Filter: 'pmpro_member_directory_watched_user_meta' - Meta keys that
	 * should invalidate the directory cache when they change. Sites that
	 * expose custom fields in the directory should add their keys here.
	 *
	 * @param array $watched Default: first_name, last_name, pmpromd_hide_directory, pmpromd_pin_location.
	 */
	$watched = apply_filters(
		'pmpro_member_directory_watched_user_meta',
		array( 'first_name', 'last_name', 'pmpromd_hide_directory', 'pmpromd_pin_location' )
	);
	if ( in_array( $meta_key, $watched, true ) ) {
		pmpromd_bump_cache_version();
	}
}

// User lifecycle invalidation.
add_action( 'user_register',                       'pmpromd_bump_cache_version' );
add_action( 'profile_update',                      'pmpromd_bump_cache_version' );
add_action( 'deleted_user',                        'pmpromd_bump_cache_version' );
add_action( 'pmpro_after_change_membership_level', 'pmpromd_bump_cache_version' );

// Directory-relevant meta invalidation.
add_action( 'added_user_meta',   'pmpromd_maybe_bump_on_user_meta', 10, 4 );
add_action( 'updated_user_meta', 'pmpromd_maybe_bump_on_user_meta', 10, 4 );
add_action( 'deleted_user_meta', 'pmpromd_maybe_bump_on_user_meta', 10, 4 );

<?php
/**
 * WordPress Context Provider
 *
 * Builds WordPress-specific context including post, user, query, and constants.
 *
 * @package     MilliRules
 * @subpackage  WordPress
 * @author      Philipp Wellmer
 * @since 1.0.0
 */

namespace MilliRules\Packages\WordPress;

use MilliRules\Packages\PHP\ContextProvider as PhpContextProvider;

/**
 * Class ContextProvider
 *
 * Extends PHP context provider with WordPress-specific data.
 * Provides nested 'wp' context with post, user, query, and constants sub-contexts.
 *
 * Context structure:
 * [
 *     'request' => [...],  // from parent PhpContextProvider
 *     'wp' => [
 *         'post' => [...],      // Post data
 *         'user' => [...],      // User data
 *         'query' => [...],     // Query conditionals
 *         'constants' => [...], // WordPress constants
 *     ],
 * ]
 *
 * @since 1.0.0
 */
class ContextProvider extends PhpContextProvider {
	/**
	 * Build the context array with WordPress data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The structured context with HTTP and WordPress data.
	 */
	public function build_context(): array {
		// Start with HTTP context from parent.
		$context = parent::build_context();

		// Add WordPress data if WordPress is loaded.
		if ( $this->is_wordpress_loaded() ) {
			$context['wp'] = array(
				'post'      => $this->build_post_context(),
				'user'      => $this->build_user_context(),
				'query'     => $this->build_query_context(),
				'constants' => $this->build_constants_context(),
			);
		}

		return $context;
	}

	/**
	 * Check if WordPress is loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if WordPress is loaded, false otherwise.
	 */
	protected function is_wordpress_loaded(): bool {
		return function_exists( 'get_queried_object' ) && function_exists( 'wp_get_current_user' );
	}

	/**
	 * Build post context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Post data.
	 */
	protected function build_post_context(): array {
		$post_data = array(
			'id'     => 0,
			'type'   => '',
			'status' => '',
			'author' => 0,
			'parent' => 0,
			'name'   => '',
			'title'  => '',
		);

		// Try to get queried object first.
		if ( function_exists( 'get_queried_object' ) ) {
			$queried_object = get_queried_object();
			if ( $queried_object instanceof \WP_Post ) {
				$post_data['id']     = $queried_object->ID;
				$post_data['type']   = $queried_object->post_type;
				$post_data['status'] = $queried_object->post_status;
				$post_data['author'] = (int) $queried_object->post_author;
				$post_data['parent'] = (int) $queried_object->post_parent;
				$post_data['name']   = $queried_object->post_name;
				$post_data['title']  = $queried_object->post_title;
				return $post_data;
			}
		}

		// Fallback to global $post.
		global $post;
		if ( $post instanceof \WP_Post ) {
			$post_data['id']     = $post->ID;
			$post_data['type']   = $post->post_type;
			$post_data['status'] = $post->post_status;
			$post_data['author'] = (int) $post->post_author;
			$post_data['parent'] = (int) $post->post_parent;
			$post_data['name']   = $post->post_name;
			$post_data['title']  = $post->post_title;
		}

		return $post_data;
	}

	/**
	 * Build user context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> User data.
	 */
	protected function build_user_context(): array {
		$user_data = array(
			'id'           => 0,
			'logged_in'    => false,
			'roles'        => array(),
			'login'        => '',
			'email'        => '',
			'display_name' => '',
		);

		if ( ! function_exists( 'wp_get_current_user' ) || ! function_exists( 'is_user_logged_in' ) ) {
			return $user_data;
		}

		$user_data['logged_in'] = is_user_logged_in();

		if ( ! $user_data['logged_in'] ) {
			return $user_data;
		}

		$user = wp_get_current_user();
		if ( $user instanceof \WP_User && $user->exists() ) {
			$user_data['id']           = $user->ID;
			$user_data['roles']        = $user->roles;
			$user_data['login']        = $user->user_login;
			$user_data['email']        = $user->user_email;
			$user_data['display_name'] = $user->display_name;
		}

		return $user_data;
	}

	/**
	 * Build query context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Query conditionals.
	 */
	protected function build_query_context(): array {
		$query_data = array(
			'is_singular'    => false,
			'is_home'        => false,
			'is_front_page'  => false,
			'is_archive'     => false,
			'is_search'      => false,
			'is_404'         => false,
			'is_attachment'  => false,
			'is_single'      => false,
			'is_page'        => false,
			'is_category'    => false,
			'is_tag'         => false,
			'is_tax'         => false,
			'is_author'      => false,
			'is_date'        => false,
			'is_year'        => false,
			'is_month'       => false,
			'is_day'         => false,
			'is_time'        => false,
			'is_admin'       => false,
			'is_ajax'        => false,
		);

		// Populate with actual values if functions exist.
		if ( function_exists( 'is_singular' ) ) {
			$query_data['is_singular'] = is_singular();
		}
		if ( function_exists( 'is_home' ) ) {
			$query_data['is_home'] = is_home();
		}
		if ( function_exists( 'is_front_page' ) ) {
			$query_data['is_front_page'] = is_front_page();
		}
		if ( function_exists( 'is_archive' ) ) {
			$query_data['is_archive'] = is_archive();
		}
		if ( function_exists( 'is_search' ) ) {
			$query_data['is_search'] = is_search();
		}
		if ( function_exists( 'is_404' ) ) {
			$query_data['is_404'] = is_404();
		}
		if ( function_exists( 'is_attachment' ) ) {
			$query_data['is_attachment'] = is_attachment();
		}
		if ( function_exists( 'is_single' ) ) {
			$query_data['is_single'] = is_single();
		}
		if ( function_exists( 'is_page' ) ) {
			$query_data['is_page'] = is_page();
		}
		if ( function_exists( 'is_category' ) ) {
			$query_data['is_category'] = is_category();
		}
		if ( function_exists( 'is_tag' ) ) {
			$query_data['is_tag'] = is_tag();
		}
		if ( function_exists( 'is_tax' ) ) {
			$query_data['is_tax'] = is_tax();
		}
		if ( function_exists( 'is_author' ) ) {
			$query_data['is_author'] = is_author();
		}
		if ( function_exists( 'is_date' ) ) {
			$query_data['is_date'] = is_date();
		}
		if ( function_exists( 'is_year' ) ) {
			$query_data['is_year'] = is_year();
		}
		if ( function_exists( 'is_month' ) ) {
			$query_data['is_month'] = is_month();
		}
		if ( function_exists( 'is_day' ) ) {
			$query_data['is_day'] = is_day();
		}
		if ( function_exists( 'is_time' ) ) {
			$query_data['is_time'] = is_time();
		}
		if ( function_exists( 'is_admin' ) ) {
			$query_data['is_admin'] = is_admin();
		}

		// Check for AJAX.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$query_data['is_ajax'] = true;
		}

		return $query_data;
	}

	/**
	 * Build constants context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> WordPress constants.
	 */
	protected function build_constants_context(): array {
		$constants = array();

		// Common WordPress constants to check.
		$constant_names = array(
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'WP_CACHE',
			'DOING_AJAX',
			'DOING_CRON',
			'DOING_AUTOSAVE',
			'WP_ENVIRONMENT_TYPE',
			'WP_DEVELOPMENT_MODE',
			'SCRIPT_DEBUG',
			'CONCATENATE_SCRIPTS',
			'COMPRESS_SCRIPTS',
			'COMPRESS_CSS',
			'WP_LOCAL_DEV',
			'ABSPATH',
			'WP_CONTENT_DIR',
			'WP_PLUGIN_DIR',
			'WPMU_PLUGIN_DIR',
			'WP_LANG_DIR',
		);

		foreach ( $constant_names as $constant_name ) {
			if ( defined( $constant_name ) ) {
				$constants[ $constant_name ] = constant( $constant_name );
			}
		}

		return $constants;
	}
}

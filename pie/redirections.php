<?php
/**
 * PIE Redirections - High priority URL redirections with configurable regex patterns and destinations.
 * 
 * Usage:
 * 
 * 1. Add custom redirect rules via filter:
 *    add_filter('PIE\Redirections\filters\redirect_rules', function($rules) {
 *        $rules[] = [
 *            'pattern' => '#^/old-path/*$#',
 *            'destination' => '/new-path/',
 *            'condition' => 'not_admin',  // 'always', 'not_admin', 'not_logged_in' or callable
 *            'status_code' => 301
 *        ];
 *        return $rules;
 *    });
 * 
 * Patterns use regex syntax. Common examples:
 * - '#^/old-page/?$#' matches /old-page or /old-page/
 * - '#^/category/(.+)$#' captures and can use $1 in destination
 * - '#^/blog/(\d+)/?$#' matches blog posts with numeric IDs
 */
namespace PIE\Redirections;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle all configured redirects
 * 
 * Only processes front-end requests to avoid interfering with admin, AJAX, REST API, etc.
 */
function handle_redirects() {
    // Skip processing for admin area, AJAX, REST API, and CLI requests
    if ( is_admin() || wp_doing_ajax() || wp_is_json_request() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }
    
    // Get the current request path
    $request_path = get_request_path();
    
    // Get all redirect rules
    $redirect_rules = get_redirect_rules();
    
    // Debug logging

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        debug_redirect_attempts( $request_path, $redirect_rules );
    }

    // Process each redirect rule
    foreach ( $redirect_rules as $rule ) {
        if ( path_matches_pattern( $request_path, $rule['pattern'] ) ) {
            if ( should_redirect_user( $rule ) ) {
                perform_redirect( $rule, $request_path );
                break; // Stop after first match
            }
        }
    }
}

/**
 * Get all configured redirect rules
 * 
 * @return array Array of redirect rule configurations
 */
function get_redirect_rules() {

    /**
     * Filter to allow adding custom redirect rules
     * 
     * @param array $rules Array of redirect rule configurations
     * 
     * Rule format:
     * [
     *     'pattern' => '#regex#',           // Regex pattern to match against path
     *     'destination' => '/target/',      // Destination path or URL
     *     'condition' => 'not_admin',       // Condition: 'not_admin', 'not_logged_in', 'always', or custom callable
     *     'status_code' => 302,             // HTTP status code (301, 302, etc.)
     *     'description' => 'Rule desc'      // Optional description for debugging
     * ]
     */
    return apply_filters( __NAMESPACE__ . '\filters\redirect_rules', array() );
}

/**
 * Get the sanitized request path
 * 
 * @return string The sanitized request path
 */
function get_request_path() {
    // Use WordPress function to get request URI safely
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
    
    // Parse and clean the path
    $path = wp_parse_url( $request_uri, PHP_URL_PATH );
    
    // Remove trailing slash and ensure we have a string
    return rtrim( $path ?: '', '/' );
}

/**
 * Check if the current path matches the given pattern
 * 
 * @param string $path The request path to check
 * @param string $pattern The regex pattern to match against
 * @return bool True if path matches pattern
 */
function path_matches_pattern( $path, $pattern ) {
    // Handle both site root and subdirectory installations
    $site_path = wp_parse_url( home_url(), PHP_URL_PATH );
    $site_path = rtrim( $site_path ?: '', '/' );
    
    // Remove site path from request path if present
    if ( $site_path && 0 === strpos( $path, $site_path ) ) {
        $path = substr( $path, strlen( $site_path ) );
    }
    
    // Ensure path starts with / for consistent matching
    if ( ! str_starts_with( $path, '/' ) ) {
        $path = '/' . $path;
    }
    
    return preg_match( $pattern, $path );
}

/**
 * Determine if the current user should be redirected based on rule condition
 * 
 * @param array $rule The redirect rule configuration
 * @return bool True if user should be redirected
 */
function should_redirect_user( $rule ) {
    $condition = isset( $rule['condition'] ) ? $rule['condition'] : 'not_admin';
    
    switch ( $condition ) {
        case 'always':
            return true;
            
        case 'not_logged_in':
            return ! is_user_logged_in();
            
        case 'not_admin':
            return ! current_user_can( 'manage_options' );
            
        case 'logged_in':
            return is_user_logged_in();
            
        default:
            // Allow custom callable conditions
            if ( is_callable( $condition ) ) {
                return call_user_func( $condition, $rule );
            }
            
            // Default to not redirecting if condition is unknown
            return false;
    }
}

/**
 * Perform the redirect based on rule configuration
 * 
 * @param array $rule The redirect rule configuration
 * @param string $request_path The original request path
 */
function perform_redirect( $rule, $request_path ) {
    $destination = isset( $rule['destination'] ) ? $rule['destination'] : '/';
    $status_code = isset( $rule['status_code'] ) ? $rule['status_code'] : 302;
    
    // Convert relative paths to full URLs
    if ( str_starts_with( $destination, '/' ) ) {
        $redirect_url = home_url( $destination );
    } else {
        $redirect_url = $destination;
    }
    
    // Allow filtering of the redirect URL
    $redirect_url = apply_filters( __NAMESPACE__ . '\filters\redirect_url', $redirect_url, $rule, $request_path );
    
    // Validate the redirect URL for security
    $redirect_url = wp_validate_redirect( $redirect_url, home_url() );
    
    // Log the redirect if debugging is enabled

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        log_redirect( $request_path, $redirect_url, $rule );
    }
    
    // Perform redirect with specified status code
    wp_redirect( $redirect_url, $status_code );
    exit;
}

/**
 * Log redirect events for debugging (only in WP_DEBUG mode)
 * 
 * @param string $from_path The original path
 * @param string $to_url The redirect destination
 * @param array $rule The rule that triggered the redirect
 */
function log_redirect( $from_path, $to_url, $rule ) {
        $description = isset( $rule['description'] ) ? $rule['description'] : 'Custom rule';
        error_log( sprintf(
            'PIE Redirections: [%s] Redirected "%s" to "%s" for user %s',
            $description,
            $from_path,
            $to_url,
            is_user_logged_in() ? get_current_user_id() : 'guest'
        ) );
}

/**
 * Debug function to log all redirect attempts (only in WP_DEBUG mode)
 * 
 * @param string $path The path being checked
 * @param array $rules All redirect rules
 */
function debug_redirect_attempts( $path, $rules ) {
    error_log( sprintf(
        'PIE Redirections DEBUG: Checking path "%s" against %d rules',
        $path,
        count( $rules )
    ) );
    
    foreach ( $rules as $index => $rule ) {
        $matches = path_matches_pattern( $path, $rule['pattern'] );
        $should_redirect = $matches ? should_redirect_user( $rule ) : false;
        
        error_log( sprintf(
            'PIE Redirections DEBUG: Rule %d [%s] - Pattern: %s, Matches: %s, Should redirect: %s',
            $index + 1,
            isset( $rule['description'] ) ? $rule['description'] : 'No description',
            $rule['pattern'],
            $matches ? 'YES' : 'NO',
            $should_redirect ? 'YES' : 'NO'
        ) );
    }
}

// Initialize the redirections handler on 'parse_request' hook (runs very early, before query setup to prevent the Events Calendar from taking over)
add_action( 'parse_request', __NAMESPACE__ . '\handle_redirects', 1 );
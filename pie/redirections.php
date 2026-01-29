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
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle all configured redirects for front-end requests
 * 
 * Processes redirect rules from the configured filter and applies them
 * to the current request. Validates rule structure and handles errors
 * gracefully. Only processes front-end requests to avoid interfering 
 * with admin area, AJAX, REST API, or CLI operations.
 * 
 * @since 1.0.0
 * @return void
 */
function handle_redirects(): void {
    // Skip processing for admin area, AJAX, REST API, and CLI requests
    if ( is_admin() || wp_doing_ajax() || wp_is_json_request() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }
    
    /**
     * Filters the array of redirect rules.
     *
     * Allows plugins and themes to add custom redirect rules to the PIE Redirections system.
     * Each rule should be an associative array containing pattern, destination, condition, and status_code.
     *
     * @since 1.0.0
     *
     * @param array $rules {
     *     Array of redirect rule configurations.
     *
     *     @type array $rule {
     *         Individual redirect rule configuration.
     *
     *         @type string $pattern      Required. Regex pattern to match against the request path.
     *                                   Should be a valid PHP regex with delimiters (e.g., '#^/old-path/?$#').
     *         @type string $destination  Required. Destination path or full URL to redirect to.
     *                                   Can be relative path (e.g., '/new-path/') or absolute URL.
     *         @type string $condition    Optional. Condition for when to apply the redirect.
     *                                   Accepts 'always', 'not_admin', 'not_logged_in', 'logged_in', or callable.
     *                                   Default 'not_admin'.
     *         @type int    $status_code  Optional. HTTP status code for the redirect.
     *                                   Accepts 301 (permanent) or 302 (temporary). Default 302.
     *         @type string $description  Optional. Human-readable description for debugging purposes.
     *     }
     * }
     */
    $redirect_rules = apply_filters( __NAMESPACE__ . '\filters\redirect_rules', array() );

    // Get all redirect rules - return early if none configured
    if ( ! isset( $redirect_rules ) || ! is_array( $redirect_rules ) || 0 === count( $redirect_rules ) ) {
        return;
    }
    
    // Get the current request path
    $request_path = get_request_path();
    
    // Debug logging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        debug_redirect_attempts( $request_path, $redirect_rules );
    }

    // Process each redirect rule
    foreach ( $redirect_rules as $index => $rule ) {
        // Validate rule structure
        if ( ! is_array( $rule ) ) {
            error_log( sprintf(
                '[PIE Redirections] Invalid rule at index %d: expected array, got %s',
                $index,
                gettype( $rule )
            ) );
            continue;
        }
        
        // Check required keys
        if ( ! isset( $rule['pattern'] ) ) {
            error_log( sprintf(
                '[PIE Redirections] Missing required "pattern" key in rule at index %d',
                $index
            ) );
            continue;
        }
        
        if ( ! isset( $rule['destination'] ) ) {
            error_log( sprintf(
                '[PIE Redirections] Missing required "destination" key in rule at index %d',
                $index
            ) );
            continue;
        }
        
        if ( path_matches_pattern( $request_path, $rule['pattern'] ) ) {
            if ( should_redirect_user( $rule ) ) {
                perform_redirect( $rule, $request_path );
                break; // Stop after first match
            }
        }
    }
}

/**
 * Get the sanitized and normalized request path
 * 
 * Extracts and sanitizes the request path from the current request URI
 * using URL-specific sanitization that preserves URL structure, encoded
 * characters, and query parameters. Handles URL parsing and removes
 * trailing slashes for consistent processing.
 * 
 * @since 1.0.0
 * @return string The sanitized request path without trailing slash
 */
function get_request_path(): string {
    // Use WordPress function to get request URI safely
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    
    // Parse and clean the path
    $path = wp_parse_url( $request_uri, PHP_URL_PATH );
    
    // Remove trailing slash and ensure we have a string
    return rtrim( $path ?: '', '/' );
}

/**
 * Check if the current path matches the given regex pattern
 * 
 * Handles both site root and subdirectory WordPress installations
 * by normalizing the path before pattern matching. Validates regex
 * pattern structure and handles compilation errors gracefully.
 * Ensures consistent matching behavior regardless of installation type.
 * 
 * @since 1.0.0
 * @param string $path The request path to check
 * @param string $pattern The regex pattern to match against (with delimiters)
 * @return bool True if path matches pattern, false for invalid patterns or no match
 */
function path_matches_pattern( string $path, string $pattern ): bool {
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
    
    // Validate regex pattern before use
    if ( ! is_string( $pattern ) || '' === $pattern ) {
        error_log( sprintf(
            '[PIE Redirections] Invalid pattern type or empty pattern: %s',
            var_export( $pattern, true )
        ) );
        return false;
    }
    
    // Test pattern validity with a simple string first
    $test_result = preg_match( $pattern, '/test' );
    
    // Check for regex compilation errors
    if ( false === $test_result ) {
        error_log( sprintf(
            '[PIE Redirections] Invalid regex pattern: %s. Error: %s',
            $pattern,
            preg_last_error_msg()
        ) );
        return false;
    }
    
    // Now safely perform the actual match
    $result = preg_match( $pattern, $path );
    
    return (bool) $result;
}

/**
 * Determine if the current user should be redirected based on rule condition
 * 
 * Evaluates the condition specified in the redirect rule to determine
 * if the current user should be redirected. Supports built-in conditions
 * and custom callable conditions with comprehensive error handling.
 * 
 * @since 1.0.0
 * @param array $rule {
 *     The redirect rule configuration.
 *     @type string|callable $condition Condition to evaluate: 'always', 'not_admin',
 *                                     'not_logged_in', 'logged_in', or callable.
 * }
 * @return bool True if user should be redirected, false otherwise
 */
function should_redirect_user( array $rule ): bool {
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
            // Allow custom callable conditions with error handling
            if ( is_callable( $condition ) ) {
                try {
                    return (bool) call_user_func( $condition, $rule );
                } catch ( \Throwable $throwable ) {
                    error_log(
                        sprintf(
                            '[PIE Redirections] Condition callback error for rule: %s. Error: %s',
                            isset( $rule['description'] ) ? (string) $rule['description'] : 'N/A',
                            $throwable->getMessage()
                        )
                    );
                }
            }
            
            // Default to not redirecting if condition is unknown or failed
            return false;
    }
}

/**
 * Perform the redirect based on rule configuration
 * 
 * Executes the actual redirect with proper URL validation, status code
 * validation, and comprehensive error handling. Supports both relative
 * and absolute destination URLs.
 * 
 * @since 1.0.0
 * @param array $rule {
 *     The redirect rule configuration.
 *     @type string $destination  Destination path or URL to redirect to.
 *     @type int    $status_code  HTTP status code (301, 302, 303, 307, 308).
 *     @type string $description  Optional description for debugging.
 * }
 * @param string $request_path The original request path that triggered redirect
 * @return void This function performs redirect and exits
 */
function perform_redirect( array $rule, string $request_path ): void {
    $destination = isset( $rule['destination'] ) ? $rule['destination'] : '/';
    $status_code = isset( $rule['status_code'] ) ? $rule['status_code'] : 302;
    
    // Validate status code - only allow valid redirect codes
    $valid_status_codes = [ 301, 302, 303, 307, 308 ];
    if ( ! in_array( $status_code, $valid_status_codes, true ) ) {
        error_log( sprintf(
            '[PIE Redirections] Invalid status code %s for rule: %s. Using 302 instead.',
            $status_code,
            isset( $rule['description'] ) ? $rule['description'] : 'N/A'
        ) );
        $status_code = 302;
    }
    
    // Convert relative paths to full URLs
    if ( str_starts_with( $destination, '/' ) ) {
        $redirect_url = home_url( $destination );
    } else {
        $redirect_url = $destination;
    }
    
    /**
     * Filters the redirect URL before performing the redirect.
     *
     * Allows plugins and themes to modify the destination URL before the redirect is executed.
     * This can be used to add query parameters, change domains, or apply custom logic
     * to the redirect destination.
     *
     * @since 1.0.0
     *
     * @param string $redirect_url  The redirect URL that will be used for wp_redirect().
     * @param array  $rule {
     *     The redirect rule configuration that triggered this redirect.
     *
     *     @type string $pattern      Regex pattern that matched the request path.
     *     @type string $destination  Original destination from the rule configuration.
     *     @type string $condition    Condition that was evaluated for this redirect.
     *     @type int    $status_code  HTTP status code for the redirect.
     *     @type string $description  Optional description for debugging purposes.
     * }
     * @param string $request_path  The original request path that triggered the redirect.
     */
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
 * Log redirect events for debugging purposes
 * 
 * Records redirect activity to the debug log. This function performs
 * unconditional logging - DEBUG mode checks should be handled by the caller.
 * Includes user context and rule information for troubleshooting.
 * 
 * @since 1.0.0
 * @param string $from_path The original request path that was redirected
 * @param string $to_url The redirect destination URL
 * @param array $rule {
 *     The rule configuration that triggered the redirect.
 *     @type string $description Optional description for identification.
 * }
 * @return void
 */
function log_redirect( string $from_path, string $to_url, array $rule ): void {
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
 * Debug function to log all redirect rule evaluation attempts
 * 
 * Provides detailed logging of redirect rule processing for troubleshooting.
 * This function performs unconditional logging - DEBUG mode checks should
 * be handled by the caller. Shows pattern matching and condition evaluation
 * for troubleshooting redirect configurations.
 * 
 * @since 1.0.0
 * @param string $path The request path being evaluated
 * @param array $rules Array of all redirect rules to check
 * @return void
 */
function debug_redirect_attempts( string $path, array $rules ): void {
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

/**
 * Initialize the redirections handler
 * 
 * Registers the redirect handler on the 'parse_request' hook with priority 1
 * to run very early in the WordPress request lifecycle, before query setup
 * and before other plugins like Events Calendar can interfere.
 * 
 * @since 1.0.0
 */
add_action( 'parse_request', __NAMESPACE__ . '\handle_redirects', 1 );
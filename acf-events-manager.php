<?php

/*
Plugin Name: ACF Events Manager
Description: Provides the [event_date] shortcode to display event dates/times using Advanced Custom Fields.
Version: 1.0.3
Author: Daniel Waters
Author URI: https://github.com/daniellwaters
Text Domain: acf-events-manager
Requires at least: 5.6
Requires PHP: 7.4
License: GPLv2 or later
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// No Composer autoloader. This plugin expects the Plugin Update Checker library in plugin-update-checker/.

/**
 * Activation check: ensure Advanced Custom Fields is active.
 */
function acf_events_manager_on_activation() {
    // Load plugin API helpers for is_plugin_active.
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $has_acf = function_exists('get_field')
        || class_exists('ACF')
        || (function_exists('is_plugin_active') && (
            is_plugin_active('advanced-custom-fields/acf.php')
            || is_plugin_active('advanced-custom-fields-pro/acf.php')
        ));

    if (!$has_acf) {
        // Deactivate self and show a helpful message.
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('ACF Events Manager requires Advanced Custom Fields to be installed and active. Please activate ACF and try again.', 'acf-events-manager'),
            __('Plugin dependency check', 'acf-events-manager'),
            ['back_link' => true]
        );
    }
}
register_activation_hook(__FILE__, 'acf_events_manager_on_activation');

/**
 * Admin notice if ACF becomes inactive after activation.
 */
function acf_events_manager_admin_notice_missing_acf() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    if (function_exists('get_field') || class_exists('ACF')) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html__(
        'ACF Events Manager requires Advanced Custom Fields. Please activate ACF to use the [event_date] shortcode.',
        'acf-events-manager'
    ) . '</p></div>';
}
add_action('admin_notices', 'acf_events_manager_admin_notice_missing_acf');

/**
 * Optional: Enable GitHub updates using the Plugin Update Checker library, if present.
 * - Install via Composer or drop the library into vendor/ or plugin-update-checker/.
 * - Releases/tags on GitHub trigger update availability in WP.
 */
function acf_events_manager_maybe_enable_github_updates() {
    // Try to load the library from common locations.
    $puc_path = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
    if (file_exists($puc_path)) {
        require_once $puc_path;
    }

    if (class_exists('Puc_v4_Factory')) {
        $updateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/daniellwaters/acf-events-manager',
            __FILE__,
            plugin_basename(__FILE__)
        );
        // Prefer GitHub release assets if available.
        if (method_exists($updateChecker, 'getVcsApi')) {
            $api = $updateChecker->getVcsApi();
            if ($api && method_exists($api, 'enableReleaseAssets')) {
                $api->enableReleaseAssets();
            }
        }
        // Mark updater as enabled so we can surface admin notices if missing.
        if (!defined('ACFEM_UPDATER_ENABLED')) {
            define('ACFEM_UPDATER_ENABLED', true);
        }
    }
}
// Initialize as early as possible so background cron-based update checks can see it.
add_action('plugins_loaded', 'acf_events_manager_maybe_enable_github_updates');

/**
 * Admin notice if the updater library is missing (so auto-updates won’t work).
 */
function acf_events_manager_admin_notice_missing_updater() {
    if (!current_user_can('update_plugins')) {
        return;
    }
    if (defined('ACFEM_UPDATER_ENABLED') && ACFEM_UPDATER_ENABLED) {
        return;
    }
    echo '<div class="notice notice-warning"><p>' . esc_html__(
        'ACF Events Manager: GitHub auto-updates are disabled because the Plugin Update Checker library is not present. Add the library to plugin-update-checker/.',
        'acf-events-manager'
    ) . '</p></div>';
}
add_action('admin_notices', 'acf_events_manager_admin_notice_missing_updater');

/**
 * Shortcode to display event date/time based on ACF fields.
 * Usage: [event_date]
 * Assumes fields are on the current post.
 */

/**
 * Helper: normalize the ACF checkbox "all_day_event" to boolean.
 * Accepts array (default ACF checkbox return), string ("yes"/"Yes"), or boolean.
 */
function is_all_day_event( $post_id ) {
    $val = get_field( 'all_day_event', $post_id );

    if (is_array($val)) {
        // ACF checkbox usually returns an array of selected values/labels
        $lower = array_map('strtolower', array_map('strval', $val));
        return in_array('yes', $lower, true) || in_array('true', $lower, true) || in_array('all day', $lower, true);
    }

    if (is_bool($val)) {
        return $val;
    }

    if (is_string($val)) {
        $v = strtolower(trim($val));
        return $v === 'yes' || $v === 'true' || $v === '1';
    }

    return false;
}

function display_event_date() {
    $post_id = get_the_ID();
    $is_recurring = get_field('is_recurring_event', $post_id);
    $all_day = is_all_day_event($post_id);

    if ($is_recurring === 'Yes') {
        // Recurring event
        $rrule = get_field('recurring_event', $post_id);
        if ($rrule && isset($rrule['first_date']) && isset($rrule['last_date'])) {
            $first = $rrule['first_date'];
            $last  = $rrule['last_date'];

            // Ensure we have DateTime objects if strings are provided
            if (!$first instanceof DateTime) {
                $first = DateTime::createFromFormat('F j, Y g:i a', $first) ?: DateTime::createFromFormat('F j, Y', $rrule['first_date']);
            }
            if (!$last instanceof DateTime) {
                $last = DateTime::createFromFormat('F j, Y g:i a', $last) ?: DateTime::createFromFormat('F j, Y', $rrule['last_date']);
            }
            if (!$first || !$last) {
                return 'Invalid date';
            }

            // For recurring: if all-day, output dates only; otherwise defer to range formatter (which is date-only anyway)
            if ($all_day) {
                return format_date_range_dates_only($first, $last);
            }
            return format_date_range_dates_only($first, $last); // recurring display is typically dates only
        }
    } else {
        // Single event
        $start_str = get_field('event_start_date', $post_id);
        $end_str   = get_field('event_end_date', $post_id);

        if (!$start_str) {
            return '';
        }

        $start = DateTime::createFromFormat('F j, Y g:i a', $start_str) ?: DateTime::createFromFormat('F j, Y', $start_str);
        $end   = $end_str
            ? (DateTime::createFromFormat('F j, Y g:i a', $end_str) ?: DateTime::createFromFormat('F j, Y', $end_str))
            : null;

        if (!$start) {
            return 'Invalid date';
        }

        // If no end provided, treat as single moment/day
        if (!$end) {
            return $all_day ? $start->format('F j, Y') : ($start->format('F j, Y') . ' at ' . $start->format('g:i a'));
        }

        // All-day: remove times entirely
        if ($all_day) {
            if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                return $start->format('F j, Y');
            } else {
                return $start->format('F j, Y') . ' - ' . $end->format('F j, Y');
            }
        }

        // Not all-day: original behavior
        if ($start == $end) {
            // Same start and end
            return $start->format('F j, Y') . ' at ' . $start->format('g:i a');
        } elseif ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            // Same day, different times
            $start_time = $start->format('g:i');
            $end_time   = $end->format('g:i a');
            return $start->format('F j, Y') . ' from ' . $start_time . '-' . $end_time;
        } else {
            // Multi-day single event
            return $start->format('F j, Y g:i a') . ' to ' . $end->format('F j, Y g:i a');
        }
    }
    return '';
}
add_shortcode('event_date', 'display_event_date');

/**
 * Helper function to format date range (dates only).
 */
function format_date_range_dates_only($start, $end) {
    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
        return $start->format('F j, Y');
    } elseif ($start->format('Y-m') === $end->format('Y-m')) {
        return $start->format('F j') . '-' . $end->format('j, Y');
    } elseif ($start->format('Y') === $end->format('Y')) {
        return $start->format('F j') . ' - ' . $end->format('F j, Y');
    } else {
        return $start->format('F j, Y') . ' - ' . $end->format('F j, Y');
    }
}

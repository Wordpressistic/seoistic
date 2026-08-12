<?php

if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (! defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (! function_exists('add_filter')) {
    function add_filter() {}
}
if (! function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}
if (! function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        return $default;
    }
}
if (! function_exists('update_option')) {
    function update_option($name, $value, $autoload = true)
    {
        return true;
    }
}
if (! function_exists('delete_option')) {
    function delete_option($name)
    {
        return true;
    }
}
if (! function_exists('get_transient')) {
    function get_transient($name)
    {
        return false;
    }
}
if (! function_exists('set_transient')) {
    function set_transient($name, $value, $expiration = 0)
    {
        return true;
    }
}
if (! function_exists('delete_transient')) {
    function delete_transient($name)
    {
        return true;
    }
}
if (! function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4()
    {
        return 'uuid';
    }
}
if (! function_exists('home_url')) {
    function home_url()
    {
        return 'https://example.com';
    }
}
if (! function_exists('wp_date')) {
    function wp_date($format, $timestamp)
    {
        return date($format, $timestamp);
    }
}
if (! function_exists('mysql2date')) {
    function mysql2date($date_format, $date)
    {
        return $date;
    }
}
if (! function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return $text;
    }
}
if (! function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}
if (! function_exists('sanitize_key')) {
    function sanitize_key($value)
    {
        return $value;
    }
}
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return $value;
    }
}
if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}
if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($value)
    {
        return $value;
    }
}
if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value)
    {
        return json_encode($value);
    }
}
if (! function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array())
    {
        return array();
    }
}
if (! function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array())
    {
        return array();
    }
}
if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return json_encode(array());
    }
}
if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return 200;
    }
}
if (! function_exists('wp_die')) {
    function wp_die($message = '')
    {
        throw new Exception($message);
    }
}
if (! function_exists('current_user_can')) {
    function current_user_can($cap)
    {
        return true;
    }
}
if (! function_exists('check_admin_referer')) {
    function check_admin_referer($action)
    {
        return true;
    }
}
if (! function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://example.com/wp-admin/' . $path;
    }
}
if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field($action)
    {
        echo '';
    }
}
if (! function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location)
    {
        return true;
    }
}
if (! function_exists('esc_url')) {
    function esc_url($url)
    {
        return $url;
    }
}
if (! function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default')
    {
        return $text;
    }
}
if (! function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default')
    {
        return;
    }
}
if (! function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default')
    {
        return;
    }
}
if (! function_exists('add_action')) {
    function add_action() {}
}
if (! function_exists('add_submenu_page')) {
    function add_submenu_page() {}
}
if (! function_exists('wp_schedule_event')) {
    function wp_schedule_event() {}
}
if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled()
    {
        return false;
    }
}
if (! function_exists('register_activation_hook')) {
    function register_activation_hook() {}
}
if (! function_exists('register_deactivation_hook')) {
    function register_deactivation_hook() {}
}
if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . '/';
    }
}
if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'https://example.com/wp-content/plugins/seoistic/';
    }
}
if (! function_exists('wp_kses')) {
    function wp_kses($value, $allowed)
    {
        return $value;
    }
}

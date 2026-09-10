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
        return $GLOBALS['seoistic_test_options'][$name] ?? $default;
    }
}
if (! function_exists('update_option')) {
    function update_option($name, $value, $autoload = true)
    {
        $GLOBALS['seoistic_test_options'][$name] = $value;
        return true;
    }
}
if (! function_exists('delete_option')) {
    function delete_option($name)
    {
        unset($GLOBALS['seoistic_test_options'][$name]);
        return true;
    }
}
if (! function_exists('get_transient')) {
    function get_transient($name)
    {
        return $GLOBALS['seoistic_test_transients'][$name] ?? false;
    }
}
if (! function_exists('set_transient')) {
    function set_transient($name, $value, $expiration = 0)
    {
        $GLOBALS['seoistic_test_transients'][$name] = $value;
        return true;
    }
}
if (! function_exists('delete_transient')) {
    function delete_transient($name)
    {
        unset($GLOBALS['seoistic_test_transients'][$name]);
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
    function home_url($path = '')
    {
        return 'https://example.com' . ('/' === substr($path, 0, 1) ? $path : ($path ? '/' . $path : ''));
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
if (! function_exists('sanitize_email')) {
    function sanitize_email($value)
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }
}
if (! function_exists('is_email')) {
    function is_email($value)
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}
if (! function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        return preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color) ? $color : '';
    }
}
if (! function_exists('esc_html')) {
    function esc_html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (! function_exists('esc_attr')) {
    function esc_attr($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (! function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format((float) $number, (int) $decimals);
    }
}
if (! function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = true)
    {
        return 'mysql' === $type ? '2026-09-11 10:00:00' : 1787143200;
    }
}
if (! function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = array())
    {
        $GLOBALS['seoistic_test_mail'][] = compact('to', 'subject', 'message');
        return $GLOBALS['seoistic_test_mail_result'] ?? true;
    }
}
if (! function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook() {}
}
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
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
        return trim(strip_tags((string) $value));
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
        $response = array_shift($GLOBALS['seoistic_test_responses']) ?? array();
        $GLOBALS['seoistic_test_last_response'] = $response;
        return $response;
    }
}
if (! function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array())
    {
        return array_shift($GLOBALS['seoistic_test_responses']) ?? array();
    }
}
if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value)
    {
        return trim(strip_tags((string) $value));
    }
}
if (! class_exists('WP_Post')) {
    class WP_Post {
        public int $ID;
        public string $post_title = '';
        public string $post_content = '';
        public string $post_modified_gmt = '';
        public string $post_type = 'post';
    }
}
$GLOBALS['seoistic_test_posts'] = array();
$GLOBALS['seoistic_test_post_meta'] = array();
if (! function_exists('get_post')) {
    function get_post($post)
    {
        if ($post instanceof WP_Post) {
            return $post;
        }
        return $GLOBALS['seoistic_test_posts'][(int) $post] ?? null;
    }
}
if (! function_exists('get_posts')) {
    function get_posts($args = array())
    {
        return array_map('intval', $GLOBALS['seoistic_test_posts']['query_ids'] ?? array());
    }
}
if (! function_exists('get_post_types')) {
    function get_post_types($args = array(), $output = 'names', $operator = 'and')
    {
        return array('post' => 'post', 'page' => 'page');
    }
}
if (! function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        return $GLOBALS['seoistic_test_post_meta'][(int) $post_id][$key] ?? '';
    }
}
if (! function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value)
    {
        $GLOBALS['seoistic_test_post_meta'][(int) $post_id][$key] = $value;
        return true;
    }
}
if (! function_exists('get_permalink')) {
    function get_permalink($post)
    {
        return 'https://example.com/page-' . (is_object($post) ? $post->ID : (int) $post) . '/';
    }
}
if (! function_exists('get_the_title')) {
    function get_the_title($post = 0)
    {
        $post = get_post($post);
        return $post ? $post->post_title : '';
    }
}
if (! function_exists('get_edit_post_link')) {
    function get_edit_post_link($post, $context = 'display')
    {
        return 'https://example.com/wp-admin/post.php?post=' . (is_object($post) ? $post->ID : (int) $post) . '&action=edit';
    }
}
if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return json_encode($response['body'] ?? array());
    }
}
if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return $response['status'] ?? 200;
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

if (! function_exists('wp_parse_url')) {
    function wp_parse_url($value, $component = -1)
    {
        return parse_url($value, $component);
    }
}
if (! function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default')
    {
        return 1 === (int) $number ? $single : $plural;
    }
}
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('WEEK_IN_SECONDS', 604800);
define('OBJECT', 'object');
define('ARRAY_A', 'array');
define('SEOISTIC_DIR', dirname(__DIR__) . '/');
function wp_salt($scheme = 'auth') { return 'isolated-test-salt-not-a-production-secret'; }
if (! function_exists('untrailingslashit')) {
    function untrailingslashit($value)
    {
        return rtrim($value, '/');
    }
}
if (! function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        return 'Testing SEOistic';
    }
}
class WP_Error {
    private array $errors = array();
    private array $data = array();
    public function __construct($code = '', $message = '', $data = array()) {
        if ('' !== $code) { $this->errors[$code] = array($message); $this->data[$code] = $data; }
    }
    public function get_error_message($code = '') { return (string) reset($this->errors[$code]); }
    public function get_error_code() { return (string) key($this->errors); }
    public function get_error_data($code = '')
    {
        if ('' === $code) {
            return $this->data[$this->get_error_code()] ?? array();
        }
        return $this->data[$code] ?? array();
    }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function absint($value) { return abs((int) $value); }
$GLOBALS['seoistic_test_responses'] = array();
$GLOBALS['seoistic_test_wpdb'] = null;
require_once SEOISTIC_DIR . 'src/autoload.php';

if (! isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = $GLOBALS['seoistic_test_wpdb'] ?? new class {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $last_error = '';
        public function prepare($query, ...$args)
        {
            if (1 === count($args) && is_array($args[0])) {
                $args = $args[0];
            }
            $positions = array();
            preg_match_all('/%[sd]/', $query, $positions, PREG_OFFSET_CAPTURE);
            foreach (array_reverse($positions[0]) as $index => $match) {
                $value = $args[$index] ?? '';
                $query = substr_replace($query, is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'", (int) $match[1], 2);
            }
            return $query;
        }
    };
}

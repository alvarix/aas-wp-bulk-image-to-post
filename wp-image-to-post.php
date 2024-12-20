<?php
/*
Plugin Name: Bulk Image to Post Converter
Description: Creates posts from selected media images using image titles as post titles
Version: 1.2
Author: Alvar Sirlin
Author URI: https://alvarsirlin.com
*/

if (!defined('ABSPATH')) {
    exit;
}

class BulkImageToPostConverter {
    private $selected_post_type;
    private $publish_status;
    private $options_key = 'bulk_image_converter_options';

    public function __construct() {
        $this->selected_post_type = get_option($this->options_key . '_post_type', 'post');
        $this->publish_status = get_option($this->options_key . '_publish_status', 'draft');
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_bulk_create_posts', array($this, 'handle_bulk_create_posts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    public function register_settings() {
        register_setting($this->options_key, $this->options_key . '_post_type');
        register_setting($this->options_key, $this->options_key . '_publish_status');
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=image-to-post">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function get_available_post_types() {
        $args = array(
            'public' => true,
            '_builtin' => false
        );
        
        $post_types = get_post_types($args, 'objects');
        $post_types = array_merge(
            array('post' => get_post_type_object('post')),
            $post_types
        );

        return $post_types;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Image to Post Converter',
            'Image to Post',
            'manage_options',
            'image-to-post',
            array($this, 'render_admin_page'),
            'dashicons-images-alt2'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_image-to-post') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'image-to-post-admin',
            plugin_dir_url(__FILE__) . 'css/admin.css',
            array(),
            '1.2'
        );
        wp_enqueue_script(
            'image-to-post',
            plugin_dir_url(__FILE__) . 'js/admin.js',
            array('jquery'),
            '1.2',
            true
        );

        wp_localize_script('image-to-post', 'imageToPost', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('image-to-post-nonce'),
            'publishStatus' => $this->publish_status
        ));
    }

    public function render_admin_page() {
        $post_types = $this->get_available_post_types();
        $current_post_type = get_post_type_object($this->selected_post_type);
        $status_display = ucfirst($this->publish_status);
        ?>
        <div class="wrap">
            <h1>Image to Post Converter</h1>
            
            <div class="post-type-selection">
                <form method="post" action="options.php">
                    <?php settings_fields($this->options_key); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="post-type-select">Select Post Type:</label>
                            </th>
                            <td>
                                <select name="<?php echo $this->options_key . '_post_type'; ?>" 
                                        id="post-type-select">
                                    <?php foreach ($post_types as $type): ?>
                                        <option value="<?php echo esc_attr($type->name); ?>"
                                                <?php selected($this->selected_post_type, $type->name); ?>>
                                            <?php echo esc_html($type->labels->singular_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="publish-status">Publication Status:</label>
                            </th>
                            <td>
                                <select name="<?php echo $this->options_key . '_publish_status'; ?>" 
                                        id="publish-status">
                                    <option value="draft" <?php selected($this->publish_status, 'draft'); ?>>
                                        Save as Draft
                                    </option>
                                    <option value="publish" <?php selected($this->publish_status, 'publish'); ?>>
                                        Publish Immediately
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <div class="current-settings">
                <h2>Current Settings</h2>
                <p>Selected Post Type: <strong><?php echo esc_html($current_post_type->labels->singular_name); ?></strong></p>
                <p>Publication Status: <strong><?php echo esc_html($status_display); ?></strong></p>
            </div>

            <div class="image-to-post-container">
                <button id="select-images" class="button button-primary">Select Images</button>
                <div id="selected-images"></div>
                <button id="create-posts" class="button button-primary" style="display:none;">
                    Create Posts
                </button>
                <div id="conversion-status"></div>
            </div>
        </div>
        <?php
    }

    public function handle_bulk_create_posts() {
        check_ajax_referer('image-to-post-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : array();
        $created_posts = array();

        foreach ($image_ids as $image_id) {
            $attachment = get_post($image_id);
            if (!$attachment) {
                continue;
            }

            $title = pathinfo($attachment->post_title, PATHINFO_FILENAME);

            $post_data = array(
                'post_title' => $title,
                'post_status' => $this->publish_status,
                'post_type' => $this->selected_post_type,
                'post_content' => '',
            );

            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                set_post_thumbnail($post_id, $image_id);
                $created_posts[] = array(
                    'post_id' => $post_id,
                    'title' => $title,
                    'post_type' => $this->selected_post_type,
                    'status' => $this->publish_status
                );
            }
        }

        wp_send_json_success($created_posts);
    }
}

// Initialize the plugin
new BulkImageToPostConverter();
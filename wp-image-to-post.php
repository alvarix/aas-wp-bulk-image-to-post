<?php
/*
Plugin Name: Bulk Image to Post Converter
Description: Creates posts from selected media images using image titles as post titles
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BulkImageToPostConverter {
    private $post_type;

    public function __construct() {
        $this->post_type = 'illo'; // Change this to your desired post type
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_bulk_create_posts', array($this, 'handle_bulk_create_posts'));
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
        wp_enqueue_script(
            'image-to-post',
            plugin_dir_url(__FILE__) . 'js/admin.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script('image-to-post', 'imageToPost', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('image-to-post-nonce')
        ));
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Image to Post Converter</h1>
            <div class="image-to-post-container">
                <button id="select-images" class="button button-primary">Select Images</button>
                <div id="selected-images"></div>
                <button id="create-posts" class="button button-primary" style="display:none;">Create Posts</button>
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

            // Get image title without extension
            $title = pathinfo($attachment->post_title, PATHINFO_FILENAME);

            // Create post
            $post_data = array(
                'post_title' => $title,
                'post_status' => 'draft',
                'post_type' => $this->post_type,
                'post_content' => '',
            );

            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                // Set featured image
                set_post_thumbnail($post_id, $image_id);
                $created_posts[] = array(
                    'post_id' => $post_id,
                    'title' => $title
                );
            }
        }

        wp_send_json_success($created_posts);
    }
}

// Initialize the plugin
new BulkImageToPostConverter();
?>
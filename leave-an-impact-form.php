<?php
/**
 * Plugin Name: Leave an Impact Form
 * Description: This is a simple petition form plugin designed for WordPress to collect signatures and feedback from their supporters/audience.
 * Version: 0.0.1
 * Author: Ren Stanforth
 * Author URI: https://www.renstanforth.com/
 */

// Plugin constants
define('LIF_PLUGIN_VERSION', '0.0.1');
define('LIF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LIF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
// require_once LIF_PLUGIN_DIR . 'includes/...php';

class LIF_plugin
{
    private static $instance = null;
    private $available_items = ['name',
        'firstname',
        'lastname',
        'email',
        'country'
    ];
    private $options = [
        "site_key" => null,
        "secret_key" => null
    ];

    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        
        // Get plugin options
        $this->options['site_key'] = get_option( 'lif_custom_post_option_site_key' );
        $this->options['secret_key'] = get_option( 'lif_custom_post_option_secret_key' );

        // Add plugin actions and filters here
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('lif_shortcode', array($this, 'lif_shortcode'));
        add_action('init', array($this, 'register_custom_post_type'));
        add_action( 'add_meta_boxes_lif-plugin-settings', array( $this, 'add_custom_post_type_checkbox_option' ) );
        add_action( 'save_post_lif-plugin-settings', array( $this, 'save_custom_post_type_checkbox_option' ) );
        add_action( 'admin_menu', array( $this, 'add_custom_post_type_settings' ) );
        register_setting( 'lif-custom-post-settings-group', 'lif_custom_post_option_site_key' );
        register_setting( 'lif-custom-post-settings-group', 'lif_custom_post_option_secret_key' );
        add_filter( 'manage_lif-plugin-settings_posts_columns', array( $this, 'add_lif_plugin_settings_custom_columns' ), 10, 2 );
        add_action( 'manage_lif-plugin-settings_posts_custom_column', array( $this, 'fill_lif_plugin_settings_posts_custom_column' ), 10, 2 );
        add_filter( 'manage_edit-lif-plugin-settings_sortable_columns', array( $this, 'sortable_post_columns' ), 10, 2 );

    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('lif-plugin-script', LIF_PLUGIN_URL . 'assets/js/lif-main.js', array('jquery'), LIF_PLUGIN_VERSION, true);
    }

    public function lif_shortcode($atts)
    {
        $post = $this->get_custom_post_details($atts['id']);
        $form_template = '<div>
            <h2>' . $post['title'] . '</h2>
            <div>' . $post['content'] . '</div>
            <form>
                <table>';

        foreach ($post['meta'] as $key => $value) {
            $type = 'text';
            if ($value) {
                switch ($key) {
                    case 'email':
                        $type = 'email';
                        break;
                    case 'name':
                    case 'firstname':
                    case 'lastname':
                        $type = 'text';
                        break;
                    case 'country':
                        $type = 'select';
                        break;
                }

                $input_template = '';
                if ($type === 'select') {
                    $input_template = '<select name="country" id="country">
                        <option value="" selected disabled hidden>Choose here</option>
                        <option value="1">One</option>
                        <option value="2">Two</option>
                        <option value="3">Three</option>
                        <option value="4">Four</option>
                        <option value="5">Five</option>
                    </select>';
                } else {
                    $input_template = '<input type="' . $type . '" name="' . $key . '" placeholder="' . $key . '"/>';
                }

                $form_template .= '<tr><td>' . $input_template . '</td></tr>';
            }
        }

        $form_template .= '<tr><td><input type="submit" value="Submit" /></td></tr></table></form></div>';

        return $form_template;
    }

    public function get_custom_post_details($post_id)
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'lif-plugin-settings') {
            return false;
        }

        $details = [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta' => [],
        ];

        // Retrieve post meta data
        foreach ($this->available_items as $value) {
            $meta = get_post_meta($post_id, '_lif_custom_checkbox_' . $value . '_value', true);
            if ($meta) {
                $details['meta'][$value] = $meta;
            }
        }

        return $details;
    }

    // Register custom post type
    public function register_custom_post_type()
    {
        $labels = array(
            'name' => _x('Leave an Impact Forms', 'post type general name', 'lif-plugin'),
            'singular_name' => _x('Leave an Impact Form', 'post type singular name', 'lif-plugin'),
            'menu_name' => _x('Leave an Impact Forms', 'admin menu', 'lif-plugin'),
            'name_admin_bar' => _x('Leave an Impact Form', 'add new on admin bar', 'lif-plugin'),
            'add_new' => _x('Add New', 'lif-plugin-settings', 'lif-plugin'),
            'add_new_item' => __('Add New Leave an Impact Form', 'lif-plugin'),
            'new_item' => __('New Leave an Impact Form', 'lif-plugin'),
            'edit_item' => __('Edit Leave an Impact Form', 'lif-plugin'),
            'view_item' => __('View Leave an Impact Form', 'lif-plugin'),
            'all_items' => __('All Forms', 'lif-plugin'),
            'search_items' => __('Search Leave an Impact Forms', 'lif-plugin'),
            'parent_item_colon' => __('Parent Leave an Impact Forms:', 'lif-plugin'),
            'not_found' => __('No Leave an Impact Forms found.', 'lif-plugin'),
            'not_found_in_trash' => __('No Leave an Impact Forms found in Trash.', 'lif-plugin'),
        );

        $args = array(
            'labels' => $labels,
            'description' => __('Description.', 'lif-plugin'),
            'public' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'lif-plugin-settings'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => 'dashicons-welcome-write-blog',
        );

        register_post_type('lif-plugin-settings', $args);
    }

    public function add_custom_post_type_checkbox_option()
    {
        add_meta_box(
            'lif_custom_post_meta_box',
            'Form Input Fields',
            array($this, 'render_custom_post_type_checkbox_option'),
            'lif-plugin-settings',
        );
    }

    public function render_custom_post_type_checkbox_option($post)
    {
        wp_nonce_field('lif_custom_post_nonce', 'lif_custom_post_nonce');
        $available_items = $this->available_items;
        ?>
                <table>
            <?php foreach ($available_items as $value): ?>
                        <tr>
                            <td>
                                <label for="lif_custom_checkbox_<?php echo $value; ?>">
                                    <input type="checkbox" name="lif_custom_checkbox_<?php echo $value; ?>" id="lif_custom_checkbox_<?php echo $value; ?>" value="1" <?php checked(get_post_meta($post->ID, '_lif_custom_checkbox_' . $value . '_value', true), '1'); ?>>
                                    <?php _e($value, 'lif-plugin'); ?>
                                </label>
                            </td>
                        </tr>
            <?php endforeach; ?>
                </table>
            <?php
    }

    public function save_custom_post_type_checkbox_option($post_id)
    {
        if (!isset($_POST['lif_custom_post_nonce']) || !wp_verify_nonce($_POST['lif_custom_post_nonce'], 'lif_custom_post_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $available_items = $this->available_items;

        foreach ($available_items as $value) {
            $custom_checkbox_value = isset($_POST['lif_custom_checkbox_' . $value]) ? '1' : '0';
            update_post_meta($post_id, '_lif_custom_checkbox_' . $value . '_value', $custom_checkbox_value);
        }
    }

    public function add_custom_post_type_settings()
    {
        add_submenu_page(
            'edit.php?post_type=lif-plugin-settings',
            'Settings',
            'Settings',
            'manage_options',
            'lif-custom-post-settings',
            array($this, 'render_custom_post_type_settings_page')
        );
    }

    public function render_custom_post_type_settings_page()
    {
        $site_key = esc_attr($this->options['site_key']);
        $secret_key = esc_attr($this->options['secret_key']);
        ?>
            <div class="wrap">
                <h1><?php echo esc_html__('Settings', 'lif-plugin'); ?></h1>
                <h2><?php echo esc_html__('Google reCAPTCHA v3', 'lif-plugin'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('lif-custom-post-settings-group'); ?>
                    <?php do_settings_sections('lif-custom-post-settings-group'); ?>
                    <table class="form-table" style="width: 50%;">
                        <tr valign="top">
                            <th scope="row"><?php echo esc_html__('Site Key', 'lif-plugin'); ?></th>
                            <td><input type="text" name="lif_custom_post_option_site_key" value="<?php echo $site_key; ?>" style="width: 100%;"/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo esc_html__('Secret Key', 'lif-plugin'); ?></th>
                            <td><input type="text" name="lif_custom_post_option_secret_key" value="<?php echo $secret_key; ?>" style="width: 100%;"/></td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
    }

    function add_lif_plugin_settings_custom_columns($columns)
    {
        $columns['shortcode'] = 'Shortcode';

        return $columns;
    }

    function fill_lif_plugin_settings_posts_custom_column($column_id, $post_id)
    {
        switch ($column_id) {
            case 'shortcode':
                echo '[lif_shortcode id="' . $post_id . '"]';
                break;
        }
    }

    function sortable_post_columns($columns)
    {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }
}

// Get the plugin instance
LIF_plugin::get_instance();
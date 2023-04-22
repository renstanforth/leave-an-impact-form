<?php
/**
 * Plugin Name: Leave an Impact Form
 * Description: This is a simple petition form plugin designed for WordPress to collect signatures and feedback from their supporters/audience.
 * Version: 0.1.0
 * Author: Ren Stanforth
 * Author URI: https://www.renstanforth.com/
 */

// Plugin constants
define('LIF_PLUGIN_VERSION', '0.1.0');
define('LIF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LIF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once LIF_PLUGIN_DIR . 'inc/CountryList.php';
require_once LIF_PLUGIN_DIR . 'inc/SignaturesTable.php';

class LIF_plugin
{
    private static $instance = null;
    private $available_items = [
        'name',
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
        // Ready db tables and needed hooks
        register_activation_hook(__FILE__, array($this, 'create_table'));
        register_uninstall_hook(__FILE__, array($this, 'delete_table'));

        // Get plugin options
        $this->options['site_key'] = get_option('lif_custom_post_option_site_key');
        $this->options['secret_key'] = get_option('lif_custom_post_option_secret_key');

        // Add plugin actions and filters here
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
        add_shortcode('lif_shortcode', array($this, 'lif_shortcode'));
        add_action('init', array($this, 'register_custom_post_type'));
        add_action('add_meta_boxes_lif-plugin-settings', array($this, 'add_custom_post_type_option'));
        add_action('save_post_lif-plugin-settings', array($this, 'save_custom_post_type_checkbox_option'));
        add_action('admin_menu', array($this, 'add_custom_post_type_settings'));
        register_setting('lif-custom-post-settings-group', 'lif_custom_post_option_site_key');
        register_setting('lif-custom-post-settings-group', 'lif_custom_post_option_secret_key');
        add_filter('manage_lif-plugin-settings_posts_columns', array($this, 'add_lif_plugin_settings_custom_columns'), 10, 2);
        add_action('manage_lif-plugin-settings_posts_custom_column', array($this, 'fill_lif_plugin_settings_posts_custom_column'), 10, 2);
        add_filter('manage_edit-lif-plugin-settings_sortable_columns', array($this, 'sortable_post_columns'), 10, 2);
        add_action('wp_ajax_lif_record', array($this, 'lif_record_callback'));
        add_action('wp_ajax_nopriv_lif_record', array($this, 'lif_record_callback'));
    }

    public function create_table()
    {
        $signatures_table = new SignaturesTable();
        $signatures_table->create_table();
    }

    public function delete_table()
    {
        $signatures_table = new SignaturesTable();
        $signatures_table->delete_table();
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('lif-plugin-script', LIF_PLUGIN_URL . 'assets/js/lif-main.js', array('jquery'), LIF_PLUGIN_VERSION, true);
        wp_localize_script('lif-plugin-script', 'lif_ajaxurl', admin_url('admin-ajax.php'));
        wp_enqueue_style('lif-plugin-styles', plugin_dir_url(__FILE__) . 'assets/css/main.css', array(), LIF_PLUGIN_VERSION, 'all');
    }

    public function lif_shortcode($atts)
    {
        $post = $this->get_custom_post_details($atts['id']);
        $form_template = '<h2 class="lif-form__label">' . $post['title'] . '</h2><div class="lif-form"><form class="lif-form__form" id="lif_' . $atts['id'] . '" data-id="' . $atts['id'] . '"><input type="hidden" name="lif-form_id" value="' . $atts['id'] . '"/>';

        $signatures_table = new SignaturesTable();
        $total_signatures = $signatures_table->getTotalByID($atts['id']);

        if ($post['meta']['progress']['display'] == 1) {
            $form_template .= '<div class="lif-form__progressbar">
                <div></div>
            </div>
            <div class="lif-form__stats"><span class="lif-form__signed">' . $total_signatures . '</span> of <span class="lif-form__target">' . $post['meta']['progress']['target'] . '</span> Signatures</div>';
        }
        $form_template .= '<div class="lif-form__desc">' . $post['content'] . '</div>';

        foreach ($post['meta'] as $key => $value) {
            $input_template = '';
            $type = 'text';

            switch ($key) {
                case 'country':
                    $input_template = '<fieldset class="float-label-field">
                        <select class="lif-form__select" name="lif-country" id="country">
                            <option value="" selected disabled hidden>-- Select Country--</option>';

                    $countries = new CountryList();

                    foreach ($countries->getAllCountries() as $country_code => $country_name) {
                        $input_template .= '<option value="' . $country_code . '">' . $country_name . '</option>';
                    }

                    $input_template .= '</select></fieldset>';
                    break;
                case 'progress':
                    break;
                case 'email':
                    $type = 'email';
                default:
                    if ($value) {
                        $input_template = '<fieldset class="float-label-field">
                        <label for="' . $key . '">' . $key . '</label>
                        <input name="lif-' . $key . '" id="' . $key . '" type="' . $type . '" required>
                    </fieldset>';
                    }
            }

            if (!empty($input_template)) {
                $form_template .= '<div class="lif-form__row">' . $input_template . '</div>';
            }
        }

        $form_template .= '<div class="lif-form__row"><button class="lif-form__submit" role="button">Submit</button></div></form></div>';

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

        $details['meta']['progress']['target'] = get_post_meta($post->ID, '_lif_custom_signature_amount_value', true);
        $details['meta']['progress']['display'] = get_post_meta($post->ID, '_lif_custom_progress_value', true);

        return $details;
    }

    // Register custom post type
    public function register_custom_post_type()
    {
        $labels = array(
            'name' => _x('Leave an Impact Forms', 'post type general name', 'lif-plugin'),
            'singular_name' => _x('Leave an Impact Form', 'post type singular name', 'lif-plugin'),
            'menu_name' => _x('Leave an Impact Simple Forms', 'admin menu', 'lif-plugin'),
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

    public function add_custom_post_type_option()
    {

        add_meta_box(
            'lif_custom_post_meta_box_signatures',
            'Signature Settings',
            array($this, 'render_custom_post_type_signatures_option'),
            'lif-plugin-settings',
        );

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
                            <input type="checkbox" name="lif_custom_checkbox_<?php echo $value; ?>"
                                id="lif_custom_checkbox_<?php echo $value; ?>" value="1" <?php checked(get_post_meta($post->ID, '_lif_custom_checkbox_' . $value . '_value', true), '1'); ?>>
                            <?php _e($value, 'lif-plugin'); ?>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public function render_custom_post_type_signatures_option($post)
    {
        wp_nonce_field('lif_custom_post_nonce', 'lif_custom_post_nonce');
        $target = get_post_meta($post->ID, '_lif_custom_signature_amount_value', true);
        ?>
        <table>
            <tr>
                <td>
                    <?php _e('Display:', 'lif-plugin'); ?>
                </td>
                <td><input type="checkbox" name="lif_custom_progress" id="lif_custom_progress" value="1" <?php checked(get_post_meta($post->ID, '_lif_custom_progress_value', true), '1'); ?>><?php _e('Enable', 'lif-plugin'); ?></td>
            </tr>
            <tr>
                <td>
                    <?php _e('Target Signature Total:', 'lif-plugin'); ?>
                </td>
                <td><input type="number" name="lif_custom_signature_amount" id="lif_custom_signature_amount"
                        value="<?= $target; ?>" /></td>
            </tr>
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

        $total_signatures = isset($_POST['lif_custom_signature_amount']) ? $_POST['lif_custom_signature_amount'] : '0';
        update_post_meta($post_id, '_lif_custom_signature_amount_value', $total_signatures);

        $display_stats = isset($_POST['lif_custom_progress']) ? '1' : '0';
        update_post_meta($post_id, '_lif_custom_progress_value', $display_stats);
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
            <h1>
                <?php echo esc_html__('Settings', 'lif-plugin'); ?>
            </h1>
            <h2>
                <?php echo esc_html__('Google reCAPTCHA v3', 'lif-plugin'); ?>
            </h2>
            <form method="post" action="options.php">
                <?php settings_fields('lif-custom-post-settings-group'); ?>
                <?php do_settings_sections('lif-custom-post-settings-group'); ?>
                <table class="form-table" style="width: 50%;">
                    <tr valign="top">
                        <th scope="row">
                            <?php echo esc_html__('Site Key', 'lif-plugin'); ?>
                        </th>
                        <td><input type="text" name="lif_custom_post_option_site_key" value="<?php echo $site_key; ?>"
                                style="width: 100%;" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php echo esc_html__('Secret Key', 'lif-plugin'); ?>
                        </th>
                        <td><input type="text" name="lif_custom_post_option_secret_key" value="<?php echo $secret_key; ?>"
                                style="width: 100%;" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function add_lif_plugin_settings_custom_columns($columns)
    {
        $columns['shortcode'] = 'Shortcode';

        return $columns;
    }

    public function fill_lif_plugin_settings_posts_custom_column($column_id, $post_id)
    {
        switch ($column_id) {
            case 'shortcode':
                echo '[lif_shortcode id="' . $post_id . '"]';
                break;
        }
    }

    public function sortable_post_columns($columns)
    {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }

    public function lif_record_callback()
    {
        $raw_data = str_replace('lif-', '', filter_input(INPUT_POST, 'formData', FILTER_SANITIZE_STRING));
        parse_str($raw_data, $parsed_data);

        $sanitized_data = array_map('strip_tags', $parsed_data);

        $signatures_table = new SignaturesTable();
        $result = $signatures_table->insert($sanitized_data);

        wp_send_json_success(array('result' => $result, 'form_id' => $sanitized_data['form_id']));
        wp_die();
    }
}

LIF_plugin::get_instance();
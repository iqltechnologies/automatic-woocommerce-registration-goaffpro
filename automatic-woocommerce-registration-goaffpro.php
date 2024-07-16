<?php
/**
 * Plugin Name: IQL Automatic Woocommerce Registration Goaffpro
 * Description: Automatically creates a GoAffPro account when a user registers in WooCommerce.
 * Version: 1.0
 * Author: IQL Technologies
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook into WooCommerce user registration
add_action('woocommerce_created_customer', 'create_goaffpro_account', 10, 1);

function create_goaffpro_account($customer_id) {
    // Get user data
    $user = get_userdata($customer_id);
    $email = $user->user_email;
    $first_name = get_user_meta($customer_id, 'billing_first_name', true);
    $last_name = get_user_meta($customer_id, 'billing_last_name', true);
    $password = isset($_POST['password']) ? $_POST['password'] : wp_generate_password(); // Get the password from the registration form
    
    // Get GoAffPro API credentials from options
    $api_key = get_option('goaffpro_api_key');
    $api_secret = get_option('goaffpro_api_secret');
    
    // GoAffPro API endpoint
    $url = 'https://api.goaffpro.com/v1/sdk/user/register';
    
    // Prepare data for API request
    $data = array(
        'name' => $first_name . ' ' . $last_name,
        'email' => $email,
        'password' => $password,
    );
    
    // Send API request to GoAffPro
    $response = wp_remote_post($url, array(
        'method'    => 'POST',
        'body'      => json_encode($data),
        'headers'   => array(
            'Content-Type' => 'application/json',
            'accept'       => 'application/json',
        ),
    ));
    
    // Handle response
    if (is_wp_error($response)) {
        // Log error if needed
        error_log('GoAffPro API request failed: ' . $response->get_error_message());
    } else {
        // Decode response
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($result['success']) {
            // Account created successfully
            // Store the affiliate ID in user meta
            update_user_meta($customer_id, 'goaffpro_affiliate_id', $result['data']['affiliate_id']);
        } else {
            // Handle failure
            error_log('GoAffPro API request failed: ' . $result['message']);
        }
    }
}

// Add settings menu
add_action('admin_menu', 'goaffpro_add_admin_menu');

function goaffpro_add_admin_menu() {
    add_options_page(
        'GoAffPro API Keys',
        'GoAffPro API Keys',
        'manage_options',
        'goaffpro-api-keys',
        'goaffpro_options_page'
    );
}

// Register settings
add_action('admin_init', 'goaffpro_settings_init');

function goaffpro_settings_init() {
    register_setting('goaffpro_settings', 'goaffpro_api_key');
    register_setting('goaffpro_settings', 'goaffpro_api_secret');
    register_setting('goaffpro_settings', 'goaffpro_show_refer_and_earn');
    register_setting('goaffpro_settings', 'goaffpro_add_name_fields');

    add_settings_section(
        'goaffpro_settings_section',
        __('GoAffPro API Settings', 'goaffpro'),
        'goaffpro_settings_section_callback',
        'goaffpro_settings'
    );
    
    add_settings_field(
        'goaffpro_api_key',
        __('API Key', 'goaffpro'),
        'goaffpro_api_key_render',
        'goaffpro_settings',
        'goaffpro_settings_section'
    );
    
    add_settings_field(
        'goaffpro_api_secret',
        __('API Secret', 'goaffpro'),
        'goaffpro_api_secret_render',
        'goaffpro_settings',
        'goaffpro_settings_section'
    );

    add_settings_field(
        'goaffpro_show_refer_and_earn',
        __('Show Refer and Earn', 'goaffpro'),
        'goaffpro_show_refer_and_earn_render',
        'goaffpro_settings',
        'goaffpro_settings_section'
    );

    add_settings_field(
        'goaffpro_add_name_fields',
        __('Add Name Fields to Registration Form', 'goaffpro'),
        'goaffpro_add_name_fields_render',
        'goaffpro_settings',
        'goaffpro_settings_section'
    );
}

function goaffpro_api_key_render() {
    $value = get_option('goaffpro_api_key');
    echo '<input type="text" name="goaffpro_api_key" value="' . esc_attr($value) . '">';
}

function goaffpro_api_secret_render() {
    $value = get_option('goaffpro_api_secret');
    echo '<input type="text" name="goaffpro_api_secret" value="' . esc_attr($value) . '">';
}

function goaffpro_show_refer_and_earn_render() {
    $value = get_option('goaffpro_show_refer_and_earn');
    echo '<input type="checkbox" name="goaffpro_show_refer_and_earn" ' . checked(1, $value, false) . ' value="1">';
}

function goaffpro_add_name_fields_render() {
    $value = get_option('goaffpro_add_name_fields');
    echo '<input type="checkbox" name="goaffpro_add_name_fields" ' . checked(1, $value, false) . ' value="1">';
}

function goaffpro_settings_section_callback() {
    echo __('Enter your GoAffPro API credentials here.', 'goaffpro');
}

function goaffpro_options_page() {
    ?>
    <form action="options.php" method="post">
        <h2>GoAffPro API Keys</h2>
        <?php
        settings_fields('goaffpro_settings');
        do_settings_sections('goaffpro_settings');
        submit_button();
        ?>
    </form>
    <?php
}

// Conditionally add first name and last name fields to the registration form
$add_name_fields = get_option('goaffpro_add_name_fields');
if ($add_name_fields) {
    add_action('woocommerce_register_form_start', 'add_name_fields_to_registration_form');
}

function add_name_fields_to_registration_form() {
    ?>
    <p class="form-row form-row-first">
        <label for="reg_billing_first_name"><?php _e('First Name', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name" value="<?php if (!empty($_POST['billing_first_name'])) echo esc_attr($_POST['billing_first_name']); ?>" />
    </p>
    <p class="form-row form-row-last">
        <label for="reg_billing_last_name"><?php _e('Last Name', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_last_name" id="reg_billing_last_name" value="<?php if (!empty($_POST['billing_last_name'])) echo esc_attr($_POST['billing_last_name']); ?>" />
    </p>
    <div class="clear"></div>
    <?php
}

// Validate the fields
add_action('woocommerce_register_post', 'validate_name_fields_on_registration', 10, 3);

function validate_name_fields_on_registration($username, $email, $validation_errors) {
    $add_name_fields = get_option('goaffpro_add_name_fields');
    if ($add_name_fields) {
        if (empty($_POST['billing_first_name'])) {
            $validation_errors->add('billing_first_name_error', __('First name is required!', 'woocommerce'));
        }

        if (empty($_POST['billing_last_name'])) {
            $validation_errors->add('billing_last_name_error', __('Last name is required!', 'woocommerce'));
        }
    }
    return $validation_errors;
}

// Save the fields
add_action('woocommerce_created_customer', 'save_name_fields_on_registration');

function save_name_fields_on_registration($customer_id) {
    $add_name_fields = get_option('goaffpro_add_name_fields');
    if ($add_name_fields) {
        if (isset($_POST['billing_first_name'])) {
            update_user_meta($customer_id, 'billing_first_name', sanitize_text_field($_POST['billing_first_name']));
            update_user_meta($customer_id, 'first_name', sanitize_text_field($_POST['billing_first_name']));
        }

        if (isset($_POST['billing_last_name'])) {
            update_user_meta($customer_id, 'billing_last_name', sanitize_text_field($_POST['billing_last_name']));
            update_user_meta($customer_id, 'last_name', sanitize_text_field($_POST['billing_last_name']));
        }
    }
}

// Add "Refer and Earn" tab to WooCommerce "My Account" page
add_filter('woocommerce_account_menu_items', 'add_refer_and_earn_link');

function add_refer_and_earn_link($menu_links) {
    $show_refer_and_earn = get_option('goaffpro_show_refer_and_earn');
    if ($show_refer_and_earn) {
        // Insert "Refer and Earn" after Dashboard
        $new = array_slice($menu_links, 0, 1, true) + 
               array('refer-and-earn' => __('Refer and Earn', 'goaffpro')) + 
               array_slice($menu_links, 1, null, true);
        return $new;
    }
    return $menu_links;
}

// Add content to "Refer and Earn" tab
add_action('woocommerce_account_refer-and-earn_endpoint', 'refer_and_earn_content');

function refer_and_earn_content() {
    $user_id = get_current_user_id();
    $affiliate_id = get_user_meta($user_id, 'goaffpro_affiliate_id', true);
    
    if ($affiliate_id) {
        $referral_link = 'https://your-goaffpro-domain.com/?ref=' . $affiliate_id;
        echo '<h3>' . __('Here is your link', 'goaffpro') . '</h3>';
        echo '<p>' . esc_url($referral_link) . '</p>';
    } else {
        echo '<p>' . __('You do not have an affiliate account yet.', 'goaffpro') . '</p>';
        echo '<a href="#" id="create-affiliate-account" class="button">' . __('Create Affiliate Account', 'goaffpro') . '</a>';
        echo '<div id="affiliate-account-error" style="color: red;"></div>';
    }
}

// Register "Refer and Earn" endpoint
add_action('init', 'add_refer_and_earn_endpoint');

function add_refer_and_earn_endpoint() {
    add_rewrite_endpoint('refer-and-earn', EP_ROOT | EP_PAGES);
}

// Enqueue scripts
add_action('wp_enqueue_scripts', 'enqueue_goaffpro_scripts');

function enqueue_goaffpro_scripts() {
    if (is_account_page()) {
        wp_enqueue_script('goaffpro-scripts', plugin_dir_url(__FILE__) . 'goaffpro-scripts.js', array('jquery'), '1.0', true);
        wp_localize_script('goaffpro-scripts', 'goaffpro_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}

// Handle AJAX request to create affiliate account
add_action('wp_ajax_create_affiliate_account', 'create_affiliate_account_ajax');
add_action('wp_ajax_nopriv_create_affiliate_account', 'create_affiliate_account_ajax');

function create_affiliate_account_ajax() {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $email = $user->user_email;
    $first_name = get_user_meta($user_id, 'billing_first_name', true);
    $last_name = get_user_meta($user_id, 'billing_last_name', true);
    $password = wp_generate_password();
    
    $api_key = get_option('goaffpro_api_key');
    $api_secret = get_option('goaffpro_api_secret');
    
    $url = 'https://api.goaffpro.com/v1/sdk/user/register';
    
    $data = array(
        'name' => $first_name . ' ' . $last_name,
        'email' => $email,
        'password' => $password,
    );
    
    $response = wp_remote_post($url, array(
        'method'    => 'POST',
        'body'      => json_encode($data),
        'headers'   => array(
            'Content-Type' => 'application/json',
            'accept'       => 'application/json',
        ),
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
    } else {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($result['success']) {
            update_user_meta($user_id, 'goaffpro_affiliate_id', $result['data']['affiliate_id']);
            wp_send_json_success(array('message' => __('Affiliate account created successfully.', 'goaffpro')));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
}

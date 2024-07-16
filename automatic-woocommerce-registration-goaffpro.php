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
    $first_name = $user->first_name;
    $last_name = $user->last_name;
    $password = wp_generate_password(); // Generate a random password or use a predefined one
    
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
            // You can store the affiliate ID or perform other actions if needed
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
}

function goaffpro_api_key_render() {
    $value = get_option('goaffpro_api_key');
    echo '<input type="text" name="goaffpro_api_key" value="' . esc_attr($value) . '">';
}

function goaffpro_api_secret_render() {
    $value = get_option('goaffpro_api_secret');
    echo '<input type="text" name="goaffpro_api_secret" value="' . esc_attr($value) . '">';
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

    <p>
        <a href="https://iqltech.com">Need Help?</a> Hire IQL Technologies as Your Developer
    </p>

    <?php
}

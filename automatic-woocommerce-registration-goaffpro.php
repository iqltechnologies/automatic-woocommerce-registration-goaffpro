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
    }
}

// Register "Refer and Earn" endpoint
add_action('init', 'add_refer_and_earn_endpoint');

function add_refer_and_earn_endpoint() {
    add_rewrite_endpoint('refer-and-earn', EP_ROOT | EP_PAGES);
}


<?php
/**
 * Plugin Name: Aivoma AI Chat
 * Plugin URI: https://aivoma.com/plugins
 * Description: Aivoma AI Chat Agent - An advanced AI-powered chat assistant for your WordPress site.
 * Version: 7.6
 * Author: Aivoma Innovations Ltd
 * Author URI: https://aivoma.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Support URI: https://aivoma.com/contact/
 * Text Domain: aivoma-ai-chat
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------------------------------------------------
// Register Activation Hook
// -------------------------------------------------------------------
register_activation_hook( __FILE__, 'ai_chatbot_activate' );
function ai_chatbot_activate() {
    if ( ! function_exists( 'register_rest_route' ) ) {
        wp_die( 'REST API is required for this plugin. Please update your WordPress installation.' );
    }
}

// -------------------------------------------------------------------
// Initialize Plugin Hooks after all plugins are loaded
// -------------------------------------------------------------------
function ai_chatbot_init() {
    add_action( 'init', 'ai_chatbot_start_session' );
    add_action( 'wp_enqueue_scripts', 'ai_chatbot_enqueue_assets' );
    add_shortcode( 'ai_chatbot', 'ai_chatbot_shortcode' );
    add_action( 'rest_api_init', 'ai_chatbot_register_rest_routes' );
    add_action( 'admin_menu', 'ai_chatbot_add_admin_menu' );
}
add_action( 'plugins_loaded', 'ai_chatbot_init' );

// -------------------------------------------------------------------
// 1. Start PHP Session
// -------------------------------------------------------------------
function ai_chatbot_start_session() {
    if ( ! session_id() ) {
        session_start();
    }
}

// -------------------------------------------------------------------
// 2. Enqueue Chatbot Assets (CSS and JS)
// -------------------------------------------------------------------
function ai_chatbot_enqueue_assets() {
    wp_enqueue_style( 'ai-chatbot-style', plugin_dir_url( __FILE__ ) . 'ai-chatbot.css' );
    wp_enqueue_script( 'ai-chatbot-script', plugin_dir_url( __FILE__ ) . 'ai-chatbot.js', array( 'jquery' ), null, true );

    wp_localize_script( 'ai-chatbot-script', 'aiChatbotVars', array(
        'restUrl' => esc_url_raw( rest_url( 'ai-chatbot/v1/chat' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
    ) );

    // Build custom inline CSS based on saved color options.
    $custom_css = '';

    // Chat Container Background Color
    $container_bg = get_option( 'ai_chatbot_container_bg_color' ) ?: 'transparent';
    $custom_css .= "#ai-chatbot { background: " . esc_attr( $container_bg ) . "; } ";

    // Chat Header Background Color
    $header_bg = get_option( 'ai_chatbot_header_bg_color' );
    if ( $header_bg ) {
        $custom_css .= "#ai-chatbot-header { background: " . esc_attr( $header_bg ) . "; } ";
    }

    // Chatbot Body Background Color (new option)
    $body_bg = get_option( 'ai_chatbot_body_bg_color' );
    if ( $body_bg ) {
        $custom_css .= "#ai-chatbot-body { background: " . esc_attr( $body_bg ) . "; } ";
    }

    // Chat Input Container Background Color (new option)
    $input_container_bg = get_option( 'ai_chatbot_input_container_bg_color' );
    if ( $input_container_bg ) {
        $custom_css .= "#ai-chatbot-input-container { background: " . esc_attr( $input_container_bg ) . "; } ";
    }

    // Chat Input Field Background and Text Color (new options)
    $input_bg = get_option( 'ai_chatbot_input_bg_color' );
    if ( $input_bg ) {
        $custom_css .= "#ai-chatbot-input { background: " . esc_attr( $input_bg ) . "; } ";
    }
    $input_text_color = get_option( 'ai_chatbot_input_text_color' );
    if ( $input_text_color ) {
        $custom_css .= "#ai-chatbot-input { color: " . esc_attr( $input_text_color ) . "; } ";
    }

    // Chat Message Backgrounds and Font Colors
    $user_msg_bg = get_option( 'ai_chatbot_user_msg_bg_color' );
    if ( $user_msg_bg ) {
        $custom_css .= ".chat-message.user { background: " . esc_attr( $user_msg_bg ) . "; } ";
    }
    $bot_msg_bg = get_option( 'ai_chatbot_bot_msg_bg_color' );
    if ( $bot_msg_bg ) {
        $custom_css .= ".chat-message.bot { background: " . esc_attr( $bot_msg_bg ) . "; } ";
    }
    $header_font = get_option( 'ai_chatbot_header_font_color' );
    if ( $header_font ) {
        $custom_css .= "#ai-chatbot-header { color: " . esc_attr( $header_font ) . "; } ";
    }
    $user_msg_font = get_option( 'ai_chatbot_user_msg_font_color' );
    if ( $user_msg_font ) {
        $custom_css .= ".chat-message.user { color: " . esc_attr( $user_msg_font ) . "; } ";
    }
    $bot_msg_font = get_option( 'ai_chatbot_bot_msg_font_color' );
    if ( $bot_msg_font ) {
        $custom_css .= ".chat-message.bot { color: " . esc_attr( $bot_msg_font ) . "; } ";
    }

    // Chat Send Button Background and Text Color (new options)
    $send_bg = get_option( 'ai_chatbot_send_bg_color' );
    if ( $send_bg ) {
        $custom_css .= "#ai-chatbot-send { background: " . esc_attr( $send_bg ) . "; } ";
    }
    $send_text_color = get_option( 'ai_chatbot_send_text_color' );
    if ( $send_text_color ) {
        $custom_css .= "#ai-chatbot-send { color: " . esc_attr( $send_text_color ) . "; } ";
    }

    if ( ! empty( $custom_css ) ) {
        wp_add_inline_style( 'ai-chatbot-style', $custom_css );
    }
}

// -------------------------------------------------------------------
// 3. Shortcode for the Chatbot
// -------------------------------------------------------------------
function ai_chatbot_shortcode() {
    ob_start(); ?>
    <div id="ai-chatbot">
        <!-- The JavaScript will build the chat UI inside this container -->
    </div>
    <?php
    return ob_get_clean();
}

// -------------------------------------------------------------------
// 4. REST API Endpoint to Handle Chatbot Requests
// -------------------------------------------------------------------
function ai_chatbot_register_rest_routes() {
    register_rest_route( 'ai-chatbot/v1', '/chat', array(
        'methods'             => 'POST',
        'callback'            => 'ai_chatbot_handle_request',
        'permission_callback' => '__return_true'
    ) );
}

function ai_chatbot_handle_request( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'Invalid nonce' ], 403 );
    }
    $message = sanitize_text_field( $request->get_param( 'message' ) );
    if ( empty( $message ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'Message is required' ], 400 );
    }
    $api_key = get_option( 'ai_chatbot_api_key', '' );
    if ( empty( $api_key ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'API key is missing' ], 500 );
    }
    if ( ! isset( $_SESSION['ai_chatbot_conversation'] ) ) {
        $_SESSION['ai_chatbot_conversation'] = array();
    }
    $current_prompt_version = get_option( 'ai_chatbot_prompt_version' );
    if ( ! isset( $_SESSION['ai_chatbot_prompt_version'] ) || $_SESSION['ai_chatbot_prompt_version'] != $current_prompt_version ) {
        $_SESSION['ai_chatbot_conversation'] = array();
        $_SESSION['ai_chatbot_prompt_version'] = $current_prompt_version;
    }
    $conversation = $_SESSION['ai_chatbot_conversation'];
    if ( empty( $conversation ) ) {
        $custom_prompt = get_option( 'ai_chatbot_custom_prompt' );
        if ( ! $custom_prompt ) {
            $custom_prompt = "You are a helpful chat assistant on this website. However, you haven't been trained yet. If someone asks you a question, kindly respond:\n\nI'm sorry, but my owner hasn't trained me yet. Please contact the website owner for assistance.";
        }
        $conversation[] = array(
            'role'    => 'system',
            'content' => $custom_prompt
        );
    }
    $conversation[] = array( 'role' => 'user', 'content' => $message );
    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'body'    => json_encode( array(
            'model'      => 'gpt-3.5-turbo',
            'messages'   => $conversation,
            'max_tokens' => 200,
        ) ),
        'timeout' => 30,
    ) );
    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => $response->get_error_message() ], 500 );
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || ! isset( $body['choices'][0]['message']['content'] ) ) {
        return new WP_REST_Response( [ 'success' => false, 'error' => 'Unexpected response from OpenAI' ], 500 );
    }
    $reply_content = wp_kses_post( $body['choices'][0]['message']['content'] );
    $conversation[] = array( 'role' => 'assistant', 'content' => $reply_content );
    $_SESSION['ai_chatbot_conversation'] = $conversation;
    return new WP_REST_Response( [
        'success' => true,
        'reply'   => $reply_content
    ] );
}

// -------------------------------------------------------------------
// 5. Admin Settings Page (with Custom Prompt and Color Options)
// -------------------------------------------------------------------
function ai_chatbot_add_admin_menu() {
    add_menu_page(
        'Aivoma AI Chat Settings',
        'Aivoma AI Chat',
        'manage_options',
        'ai-chatbot-settings',
        'ai_chatbot_settings_page'
    );
}

function ai_chatbot_settings_page() {
    // Process form submission
    if ( isset( $_POST['ai_chatbot_save_settings'] ) || isset( $_POST['ai_chatbot_clear_memory'] ) ) {
        check_admin_referer( 'ai_chatbot_save_settings_action' );
        $api_key = sanitize_text_field( $_POST['ai_chatbot_api_key'] ?? '' );
        update_option( 'ai_chatbot_api_key', $api_key );
        $old_prompt = get_option( 'ai_chatbot_custom_prompt', '' );
        $custom_prompt = sanitize_textarea_field( $_POST['ai_chatbot_custom_prompt'] ?? '' );
        update_option( 'ai_chatbot_custom_prompt', $custom_prompt );
        if ( $custom_prompt !== $old_prompt || isset( $_POST['ai_chatbot_clear_memory'] ) ) {
            update_option( 'ai_chatbot_prompt_version', time() );
            echo '<div class="updated notice"><p>Chat memory cleared. New prompt will take effect.</p></div>';
        } else {
            echo '<div class="updated notice"><p>Settings saved successfully.</p></div>';
        }
        // Save color options with transparent checkboxes where applicable.
        // Chat Container Background Color
        if ( isset( $_POST['ai_chatbot_container_bg_transparent'] ) && $_POST['ai_chatbot_container_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_container_bg_color', 'transparent' );
        } else {
            $container_bg = sanitize_hex_color( $_POST['ai_chatbot_container_bg_color'] ?? '' );
            update_option( 'ai_chatbot_container_bg_color', $container_bg );
        }
        // Chat Header Background Color
        if ( isset( $_POST['ai_chatbot_header_bg_transparent'] ) && $_POST['ai_chatbot_header_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_header_bg_color', 'transparent' );
        } else {
            $header_bg = sanitize_hex_color( $_POST['ai_chatbot_header_bg_color'] ?? '' );
            update_option( 'ai_chatbot_header_bg_color', $header_bg );
        }
        // Chatbot Body Background Color (new)
        if ( isset( $_POST['ai_chatbot_body_bg_transparent'] ) && $_POST['ai_chatbot_body_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_body_bg_color', 'transparent' );
        } else {
            $body_bg = sanitize_hex_color( $_POST['ai_chatbot_body_bg_color'] ?? '' );
            update_option( 'ai_chatbot_body_bg_color', $body_bg );
        }
        // User Message Background Color
        if ( isset( $_POST['ai_chatbot_user_msg_bg_transparent'] ) && $_POST['ai_chatbot_user_msg_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_user_msg_bg_color', 'transparent' );
        } else {
            $user_msg_bg = sanitize_hex_color( $_POST['ai_chatbot_user_msg_bg_color'] ?? '' );
            update_option( 'ai_chatbot_user_msg_bg_color', $user_msg_bg );
        }
        // Bot Message Background Color
        if ( isset( $_POST['ai_chatbot_bot_msg_bg_transparent'] ) && $_POST['ai_chatbot_bot_msg_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_bot_msg_bg_color', 'transparent' );
        } else {
            $bot_msg_bg = sanitize_hex_color( $_POST['ai_chatbot_bot_msg_bg_color'] ?? '' );
            update_option( 'ai_chatbot_bot_msg_bg_color', $bot_msg_bg );
        }
        // Chat Header Font Color
        if ( isset( $_POST['ai_chatbot_header_font_color'] ) ) {
            $header_font_color = sanitize_hex_color( $_POST['ai_chatbot_header_font_color'] ?? '' );
            update_option( 'ai_chatbot_header_font_color', $header_font_color );
        }
        // User Message Font Color
        if ( isset( $_POST['ai_chatbot_user_msg_font_color'] ) ) {
            $user_msg_font_color = sanitize_hex_color( $_POST['ai_chatbot_user_msg_font_color'] ?? '' );
            update_option( 'ai_chatbot_user_msg_font_color', $user_msg_font_color );
        }
        // Bot Message Font Color
        if ( isset( $_POST['ai_chatbot_bot_msg_font_color'] ) ) {
            $bot_msg_font_color = sanitize_hex_color( $_POST['ai_chatbot_bot_msg_font_color'] ?? '' );
            update_option( 'ai_chatbot_bot_msg_font_color', $bot_msg_font_color );
        }
        // Chat Input Container Background Color (new)
        if ( isset( $_POST['ai_chatbot_input_container_bg_transparent'] ) && $_POST['ai_chatbot_input_container_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_input_container_bg_color', 'transparent' );
        } else {
            $input_container_bg = sanitize_hex_color( $_POST['ai_chatbot_input_container_bg_color'] ?? '' );
            update_option( 'ai_chatbot_input_container_bg_color', $input_container_bg );
        }
        // Chat Input Field Background Color (new)
        if ( isset( $_POST['ai_chatbot_input_bg_transparent'] ) && $_POST['ai_chatbot_input_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_input_bg_color', 'transparent' );
        } else {
            $input_bg = sanitize_hex_color( $_POST['ai_chatbot_input_bg_color'] ?? '' );
            update_option( 'ai_chatbot_input_bg_color', $input_bg );
        }
        // Chat Input Field Text Color (new)
        if ( isset( $_POST['ai_chatbot_input_text_color'] ) ) {
            $input_text_color = sanitize_hex_color( $_POST['ai_chatbot_input_text_color'] ?? '' );
            update_option( 'ai_chatbot_input_text_color', $input_text_color );
        }
        // Chat Send Button Background Color (new)
        if ( isset( $_POST['ai_chatbot_send_bg_transparent'] ) && $_POST['ai_chatbot_send_bg_transparent'] === 'on' ) {
            update_option( 'ai_chatbot_send_bg_color', 'transparent' );
        } else {
            $send_bg = sanitize_hex_color( $_POST['ai_chatbot_send_bg_color'] ?? '' );
            update_option( 'ai_chatbot_send_bg_color', $send_bg );
        }
        // Chat Send Button Text Color (new)
        if ( isset( $_POST['ai_chatbot_send_text_color'] ) ) {
            $send_text_color = sanitize_hex_color( $_POST['ai_chatbot_send_text_color'] ?? '' );
            update_option( 'ai_chatbot_send_text_color', $send_text_color );
        }
    }

    // Retrieve saved options (with defaults)
    $api_key              = get_option( 'ai_chatbot_api_key', '' );
    $custom_prompt        = get_option( 'ai_chatbot_custom_prompt', '' );
    $container_bg         = get_option( 'ai_chatbot_container_bg_color', '' );
    $header_bg            = get_option( 'ai_chatbot_header_bg_color', '' );
    $body_bg              = get_option( 'ai_chatbot_body_bg_color', '' );
    $user_msg_bg          = get_option( 'ai_chatbot_user_msg_bg_color', '' );
    $bot_msg_bg           = get_option( 'ai_chatbot_bot_msg_bg_color', '' );
    $header_font_color    = get_option( 'ai_chatbot_header_font_color', '#ffffff' );
    $user_msg_font_color  = get_option( 'ai_chatbot_user_msg_font_color', '#ffffff' );
    $bot_msg_font_color   = get_option( 'ai_chatbot_bot_msg_font_color', '#ffffff' );
    $input_container_bg   = get_option( 'ai_chatbot_input_container_bg_color', '#000000' );
    $input_bg             = get_option( 'ai_chatbot_input_bg_color', '#ffffff' );
    $input_text_color     = get_option( 'ai_chatbot_input_text_color', '#252525' );
    $send_bg              = get_option( 'ai_chatbot_send_bg_color', '#3f0088' );
    $send_text_color      = get_option( 'ai_chatbot_send_text_color', '#ffffff' );
    ?>
    <div class="wrap">
        <h1>Aivoma AI Chat Settings</h1>
        <form method="POST">
            <?php wp_nonce_field( 'ai_chatbot_save_settings_action' ); ?>
            <table class="form-table">
                <!-- API Key and Custom Prompt -->
                <tr>
                    <th scope="row"><label for="ai_chatbot_api_key">OpenAI API Key</label></th>
                    <td><input type="text" name="ai_chatbot_api_key" id="ai_chatbot_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_custom_prompt">Custom GPT Prompt</label></th>
                    <td>
                        <textarea name="ai_chatbot_custom_prompt" id="ai_chatbot_custom_prompt" class="large-text" rows="5"><?php echo esc_textarea( $custom_prompt ); ?></textarea>
                        <p class="description">Enter the system prompt for the AI chatbot. Insert the following shortcode at the desired location to display the chat interface: [ai_chatbot].</p>
                    </td>
                </tr>
                <!-- Color Options -->
                <tr>
                    <th scope="row"><label for="ai_chatbot_container_bg_color">Chat Container Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_container_bg_color" id="ai_chatbot_container_bg_color" value="<?php echo esc_attr( ( $container_bg !== 'transparent' && $container_bg ) ? $container_bg : '#ffffff' ); ?>" <?php echo ( $container_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_container_bg_transparent" id="ai_chatbot_container_bg_transparent" <?php checked( $container_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_container_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_header_bg_color">Chat Header Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_header_bg_color" id="ai_chatbot_header_bg_color" value="<?php echo esc_attr( ( $header_bg !== 'transparent' && $header_bg ) ? $header_bg : '#000000' ); ?>" <?php echo ( $header_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_header_bg_transparent" id="ai_chatbot_header_bg_transparent" <?php checked( $header_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_header_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_body_bg_color">Chatbot Body Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_body_bg_color" id="ai_chatbot_body_bg_color" value="<?php echo esc_attr( $body_bg ? $body_bg : '#000000' ); ?>" <?php echo ( $body_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_body_bg_transparent" id="ai_chatbot_body_bg_transparent" <?php checked( $body_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_body_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_user_msg_bg_color">User Message Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_user_msg_bg_color" id="ai_chatbot_user_msg_bg_color" value="<?php echo esc_attr( ( $user_msg_bg !== 'transparent' && $user_msg_bg ) ? $user_msg_bg : '#60b660' ); ?>" <?php echo ( $user_msg_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_user_msg_bg_transparent" id="ai_chatbot_user_msg_bg_transparent" <?php checked( $user_msg_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_user_msg_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_bot_msg_bg_color">Bot Message Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_bot_msg_bg_color" id="ai_chatbot_bot_msg_bg_color" value="<?php echo esc_attr( ( $bot_msg_bg !== 'transparent' && $bot_msg_bg ) ? $bot_msg_bg : '#4571d0' ); ?>" <?php echo ( $bot_msg_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_bot_msg_bg_transparent" id="ai_chatbot_bot_msg_bg_transparent" <?php checked( $bot_msg_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_bot_msg_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <!-- Font Color Options -->
                <tr>
                    <th scope="row"><label for="ai_chatbot_header_font_color">Chat Header Font Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_header_font_color" id="ai_chatbot_header_font_color" value="<?php echo esc_attr( $header_font_color ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_user_msg_font_color">User Message Font Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_user_msg_font_color" id="ai_chatbot_user_msg_font_color" value="<?php echo esc_attr( $user_msg_font_color ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_bot_msg_font_color">Bot Message Font Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_bot_msg_font_color" id="ai_chatbot_bot_msg_font_color" value="<?php echo esc_attr( $bot_msg_font_color ); ?>" />
                    </td>
                </tr>
                <!-- New Options: Chat Input and Send Button -->
                <tr>
                    <th scope="row"><label for="ai_chatbot_input_container_bg_color">Chat Input Container Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_input_container_bg_color" id="ai_chatbot_input_container_bg_color" value="<?php echo esc_attr( $input_container_bg ); ?>" <?php echo ( $input_container_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_input_container_bg_transparent" id="ai_chatbot_input_container_bg_transparent" <?php checked( $input_container_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_input_container_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_input_bg_color">Chat Input Field Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_input_bg_color" id="ai_chatbot_input_bg_color" value="<?php echo esc_attr( $input_bg ); ?>" <?php echo ( $input_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_input_bg_transparent" id="ai_chatbot_input_bg_transparent" <?php checked( $input_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_input_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_input_text_color">Chat Input Field Text Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_input_text_color" id="ai_chatbot_input_text_color" value="<?php echo esc_attr( $input_text_color ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_send_bg_color">Chat Send Button Background Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_send_bg_color" id="ai_chatbot_send_bg_color" value="<?php echo esc_attr( $send_bg ); ?>" <?php echo ( $send_bg === 'transparent' ) ? 'disabled' : ''; ?> />
                        <input type="checkbox" name="ai_chatbot_send_bg_transparent" id="ai_chatbot_send_bg_transparent" <?php checked( $send_bg, 'transparent' ); ?> />
                        <label for="ai_chatbot_send_bg_transparent">Transparent</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_chatbot_send_text_color">Chat Send Button Text Color</label></th>
                    <td>
                        <input type="color" name="ai_chatbot_send_text_color" id="ai_chatbot_send_text_color" value="<?php echo esc_attr( $send_text_color ); ?>" />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="ai_chatbot_save_settings" class="button-primary">Save Settings</button>
                <button type="submit" name="ai_chatbot_clear_memory" class="button-secondary" style="margin-left:10px;">Clear Chat Memory</button>
            </p>
        </form>
        <h2>Chatbot Preview</h2>
        <div id="ai-chatbot-settings-preview" style="width:350px; border:1px solid #ccc; border-radius:15px; overflow:hidden;">
            <div id="preview-header" style="padding:15px; font-size:16px; font-weight:bold;">Aivoma AI Chat</div>
            <div id="preview-body" style="padding:10px; background:#f9f9f9; height:200px;">Preview Area</div>
        </div>
        <div style="margin-top:20px; padding:10px; border:1px solid #ccc; background:#f1f1f1;">
            <strong>Need a custom solution, API integration, or language change? Encounter an issue? Contact <a href="https://aivoma.com" target="_blank">Aivoma.com</a> today!</strong>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function updatePreview() {
            var containerColorInput = document.getElementById('ai_chatbot_container_bg_color');
            var containerTransparent = document.getElementById('ai_chatbot_container_bg_transparent').checked;
            var headerColorInput = document.getElementById('ai_chatbot_header_bg_color');
            var headerTransparent = document.getElementById('ai_chatbot_header_bg_transparent').checked;
            var bodyColorInput = document.getElementById('ai_chatbot_body_bg_color');
            var bodyTransparent = document.getElementById('ai_chatbot_body_bg_transparent') ? document.getElementById('ai_chatbot_body_bg_transparent').checked : false;
            var userMsgColorInput = document.getElementById('ai_chatbot_user_msg_bg_color');
            var userMsgTransparent = document.getElementById('ai_chatbot_user_msg_bg_transparent').checked;
            var botMsgColorInput = document.getElementById('ai_chatbot_bot_msg_bg_color');
            var botMsgTransparent = document.getElementById('ai_chatbot_bot_msg_bg_transparent').checked;
            var headerFontInput = document.getElementById('ai_chatbot_header_font_color');
            var userMsgFontInput = document.getElementById('ai_chatbot_user_msg_font_color');
            var botMsgFontInput = document.getElementById('ai_chatbot_bot_msg_font_color');
            var inputContainerColorInput = document.getElementById('ai_chatbot_input_container_bg_color');
            var inputContainerTransparent = document.getElementById('ai_chatbot_input_container_bg_transparent') ? document.getElementById('ai_chatbot_input_container_bg_transparent').checked : false;
            var inputBgColorInput = document.getElementById('ai_chatbot_input_bg_color');
            var inputBgTransparent = document.getElementById('ai_chatbot_input_bg_transparent') ? document.getElementById('ai_chatbot_input_bg_transparent').checked : false;
            var inputTextColorInput = document.getElementById('ai_chatbot_input_text_color');
            var sendBgColorInput = document.getElementById('ai_chatbot_send_bg_color');
            var sendBgTransparent = document.getElementById('ai_chatbot_send_bg_transparent') ? document.getElementById('ai_chatbot_send_bg_transparent').checked : false;
            var sendTextColorInput = document.getElementById('ai_chatbot_send_text_color');

            var containerColor = containerTransparent ? 'transparent' : containerColorInput.value;
            var headerColor = headerTransparent ? 'transparent' : headerColorInput.value;
            var bodyColor = bodyTransparent ? 'transparent' : bodyColorInput.value;
            var userMsgColor = userMsgTransparent ? 'transparent' : userMsgColorInput.value;
            var botMsgColor = botMsgTransparent ? 'transparent' : botMsgColorInput.value;
            var headerFontColor = headerFontInput.value;
            var userMsgFontColor = userMsgFontInput.value;
            var botMsgFontColor = botMsgFontInput.value;
            var inputContainerColor = inputContainerTransparent ? 'transparent' : inputContainerColorInput.value;
            var inputBgColor = inputBgTransparent ? 'transparent' : inputBgColorInput.value;
            var inputTextColor = inputTextColorInput.value;
            var sendBgColor = sendBgTransparent ? 'transparent' : sendBgColorInput.value;
            var sendTextColor = sendTextColorInput.value;

            var previewContainer = document.getElementById('ai-chatbot-settings-preview');
            var previewHeader = document.getElementById('preview-header');
            previewContainer.style.background = containerColor;
            previewHeader.style.background = headerColor;
            previewHeader.style.color = headerFontColor;
            var previewBody = document.getElementById('preview-body');
            previewBody.innerHTML = '<div style="padding:10px; margin:5px; background:' + userMsgColor + '; border-radius:10px; color:' + userMsgFontColor + ';">User Message</div>' +
                                    '<div style="padding:10px; margin:5px; background:' + botMsgColor + '; border-radius:10px; color:' + botMsgFontColor + ';">Bot Message</div>' +
                                    '<div style="padding:10px; margin:5px; background:' + inputContainerColor + '; border-radius:10px; color:' + inputTextColor + ';">Input Container</div>' +
                                    '<div style="padding:10px; margin:5px; background:' + inputBgColor + '; border-radius:10px; color:' + inputTextColor + ';">Input Field</div>' +
                                    '<div style="padding:10px; margin:5px; background:' + sendBgColor + '; border-radius:10px; color:' + sendTextColor + ';">Send Button</div>';
        }

        var applyButtons = document.querySelectorAll('.apply-color');
        applyButtons.forEach(function(button) {
            button.addEventListener('click', updatePreview);
        });

        var transparentCheckboxes = document.querySelectorAll('input[type="checkbox"]');
        transparentCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', updatePreview);
        });

        var colorInputs = document.querySelectorAll('input[type="color"]');
        colorInputs.forEach(function(input) {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    updatePreview();
                }
            });
        });
    });
    </script>
    <?php
}
// End of file (omit closing PHP tag to prevent accidental whitespace)


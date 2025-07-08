<?php
/**
 * Telegram Bot API Wrapper
 *
 * This class handles communication with the Telegram Bot API.
 *
 * @since      0.1.0
 * @package    TLC
 * @subpackage TLC/includes
 * @author     Your Name <email@example.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class TLC_Telegram_Bot_API {

    /**
     * The Telegram Bot API Token.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $bot_token    Telegram Bot API Token.
     */
    private $bot_token;

    /**
     * The base URL for the Telegram Bot API.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $api_base_url    Telegram API base URL.
     */
    private $api_base_url = 'https://api.telegram.org/bot';

    /**
     * Constructor.
     *
     * @since    0.1.0
     * @param    string    $bot_token    The Telegram Bot API Token.
     */
    public function __construct( $bot_token = null ) {
        if ( $bot_token ) {
            $this->bot_token = $bot_token;
        } else {
            $this->bot_token = get_option( TLC_PLUGIN_PREFIX . 'bot_token' );
        }
    }

    /**
     * Check if the bot token is configured.
     *
     * @since 0.1.0
     * @return bool True if token is set, false otherwise.
     */
    public function is_configured() {
        return ! empty( $this->bot_token );
    }

    /**
     * Send a message to a Telegram chat.
     *
     * @since    0.1.0
     * @param    int|string   $chat_id        Unique identifier for the target chat or username of the target channel (in the format @channelusername).
     * @param    string       $text           Text of the message to be sent.
     * @param    string       $parse_mode     Optional. Mode for parsing entities in the message text. See formatting options for more details. (e.g., 'MarkdownV2', 'HTML').
     * @param    array        $reply_markup   Optional. Additional interface options. A JSON-serialized object for an inline keyboard, custom reply keyboard, instructions to remove reply keyboard or to force a reply from the user.
     * @return   array|WP_Error The response from the Telegram API or WP_Error on failure.
     */
    public function send_message( $chat_id, $text, $parse_mode = null, $reply_markup = null ) {
        if ( ! $this->is_configured() ) {
            // Consider logging this or returning a more specific error
            return new WP_Error( 'not_configured', __( 'Telegram Bot Token is not configured.', 'telegram-live-chat' ) );
        }

        $endpoint = $this->api_base_url . $this->bot_token . '/sendMessage';

        $body = array(
            'chat_id' => $chat_id,
            'text'    => $text,
        );

        if ( $parse_mode ) {
            $body['parse_mode'] = $parse_mode;
        }
        if ( $reply_markup ) {
            $body['reply_markup'] = json_encode( $reply_markup );
        }

        $args = array(
            'body'    => $body,
            'timeout' => '15', // Seconds
        );

        $response = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            // Log error: $response->get_error_message()
            error_log("Telegram API Error (wp_remote_post): " . $response->get_error_message());
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $decoded_response = json_decode( $response_body, true );

        if ( ! $decoded_response || ! isset( $decoded_response['ok'] ) ) {
            // Log error: Invalid response from Telegram
            error_log("Telegram API Error (Invalid Response): " . $response_body);
            return new WP_Error( 'telegram_api_error', __( 'Invalid response from Telegram API.', 'telegram-live-chat' ), $response_body );
        }

        if ( $decoded_response['ok'] === false ) {
            // Log error: Telegram API returned an error
             error_log("Telegram API Error (ok:false): " . $decoded_response['description']);
            return new WP_Error( 'telegram_api_failure', $decoded_response['description'], $decoded_response );
        }

        return $decoded_response;
    }

    /**
     * Get updates from Telegram (basic polling method).
     *
     * @since    0.1.0
     * @param    int      $offset         Optional. Identifier of the first update to be returned.
     * @param    int      $limit          Optional. Limits the number of updates to be retrieved.
     * @param    int      $timeout        Optional. Timeout in seconds for long polling.
     * @return   array|WP_Error The response from the Telegram API or WP_Error on failure.
     */
    public function get_updates( $offset = null, $limit = 100, $timeout = 0 ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'Telegram Bot Token is not configured.', 'telegram-live-chat' ) );
        }

        $endpoint = $this->api_base_url . $this->bot_token . '/getUpdates';
        $body = array(
            'limit'   => $limit,
            'timeout' => $timeout,
        );

        if ( $offset ) {
            $body['offset'] = $offset;
        }

        $args = array(
            'body'    => $body,
            'timeout' => $timeout + 5, // Timeout for wp_remote_get should be slightly more than Telegram's long poll timeout
        );

        $response = wp_remote_get( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            error_log("Telegram API Error (getUpdates wp_remote_get): " . $response->get_error_message());
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $decoded_response = json_decode( $response_body, true );

        if ( ! $decoded_response || ! isset( $decoded_response['ok'] ) || $decoded_response['ok'] === false ) {
            $error_message = isset($decoded_response['description']) ? $decoded_response['description'] : 'Unknown error getting updates.';
            error_log("Telegram API Error (getUpdates): " . $error_message . " | Response: " . $response_body);
            return new WP_Error( 'telegram_api_error', $error_message, $decoded_response );
        }

        return $decoded_response;
    }

    /**
     * A simple helper to test the API connection (e.g., getMe).
     *
     * @since 0.1.0
     * @return array|WP_Error
     */
    public function get_me() {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'Telegram Bot Token is not configured.', 'telegram-live-chat' ) );
        }

        $endpoint = $this->api_base_url . $this->bot_token . '/getMe';
        $response = wp_remote_get( $endpoint, array('timeout' => 10) );

        if ( is_wp_error( $response ) ) {
            error_log("Telegram API Error (getMe wp_remote_get): " . $response->get_error_message());
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $decoded_response = json_decode( $response_body, true );

        if ( ! $decoded_response || ! isset( $decoded_response['ok'] ) || $decoded_response['ok'] === false ) {
             $error_message = isset($decoded_response['description']) ? $decoded_response['description'] : 'Unknown error with getMe.';
             error_log("Telegram API Error (getMe): " . $error_message . " | Response: " . $response_body);
            return new WP_Error( 'telegram_api_error', $error_message, $decoded_response );
        }
        return $decoded_response;
    }
}

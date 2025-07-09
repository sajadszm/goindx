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

    /**
     * Send a document (file).
     *
     * @since 0.4.0
     * @param int|string $chat_id Unique identifier for the target chat.
     * @param string $file_path Absolute path to the file on the server.
     * @param string|null $caption Optional. Document caption (may also be used when resending documents by file_id), 0-1024 characters after entities parsing.
     * @param string|null $original_filename Optional. The original filename to be displayed by Telegram clients.
     * @return array|WP_Error The response from the Telegram API or WP_Error on failure.
     */
    public function send_document( $chat_id, $file_path, $caption = null, $original_filename = null ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'Telegram Bot Token is not configured.', 'telegram-live-chat' ) );
        }
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return new WP_Error( 'file_not_accessible', __( 'File path is not accessible.', 'telegram-live-chat' ), $file_path );
        }

        $endpoint = $this->api_base_url . $this->bot_token . '/sendDocument';

        // Using CURLFile for file uploads with wp_remote_post
        // WordPress HTTP API doesn't directly support multipart/form-data with CURLFile objects in $body like Guzzle.
        // We need to construct the multipart request manually or use a library if wp_remote_request doesn't handle it well.
        // For simplicity, let's try with wp_remote_post and see if it handles CURLFile correctly.
        // If not, a more complex solution using raw cURL or a different HTTP client might be needed.
        // Update: wp_remote_post can handle file uploads if the file path is provided in the body with a key that's a file itself.
        // However, it's often easier if the file is accessible via URL.
        // For local files with Telegram, they expect multipart/form-data.

        $boundary = wp_generate_password( 24 );
        $payload = '';

        // Chat ID
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="chat_id"' . "\r\n\r\n";
        $payload .= $chat_id;
        $payload .= "\r\n";

        // Caption
        if ( $caption !== null ) {
            $payload .= '--' . $boundary;
            $payload .= "\r\n";
            $payload .= 'Content-Disposition: form-data; name="caption"' . "\r\n\r\n";
            $payload .= $caption;
            $payload .= "\r\n";
        }

        // Document
        $file_contents = file_get_contents( $file_path );
        if ($file_contents === false) {
            return new WP_Error('file_read_error', __('Could not read file contents.', 'telegram-live-chat'), $file_path);
        }
        $filename_to_send = $original_filename ? $original_filename : basename( $file_path );

        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="document"; filename="' . esc_attr( $filename_to_send ) . '"' . "\r\n";
        // Content-Type can be determined by WP or set manually if known
        $file_type = mime_content_type($file_path);
        if ($file_type) {
             $payload .= "Content-Type: " . $file_type . "\r\n";
        }
        $payload .= "\r\n";
        $payload .= $file_contents;
        $payload .= "\r\n";

        $payload .= '--' . $boundary . '--';

        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body'    => $payload,
            'timeout' => 60, // Increased timeout for file uploads
        );

        $response = wp_remote_request( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            error_log(TLC_PLUGIN_PREFIX . "Telegram API Error (sendDocument wp_remote_request): " . $response->get_error_message());
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $decoded_response = json_decode( $response_body, true );

        if ( ! $decoded_response || ! isset( $decoded_response['ok'] ) ) {
            error_log(TLC_PLUGIN_PREFIX . "Telegram API Error (sendDocument Invalid Response): " . $response_body);
            return new WP_Error( 'telegram_api_error', __( 'Invalid response from Telegram API sending document.', 'telegram-live-chat' ), $response_body );
        }

        if ( $decoded_response['ok'] === false ) {
            error_log(TLC_PLUGIN_PREFIX . "Telegram API Error (sendDocument ok:false): " . ($decoded_response['description'] ?? 'Unknown error'));
            return new WP_Error( 'telegram_api_failure', ($decoded_response['description'] ?? 'Unknown error sending document.'), $decoded_response );
        }

        return $decoded_response;
    }
}

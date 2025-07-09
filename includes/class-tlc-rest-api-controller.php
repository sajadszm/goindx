<?php
/**
 * TLC REST API Controller
 *
 * Handles REST API endpoints for Telegram Live Chat.
 *
 * @since      0.7.0
 * @package    TLC
 * @subpackage TLC/includes
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class TLC_REST_API_Controller extends WP_REST_Controller {

    /**
     * Namespace for the REST API.
     * @var string
     */
    protected $namespace = 'tlc/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        // Hooks are registered in the main plugin class via rest_api_init
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/sessions', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_sessions' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_collection_params(),
            ),
            'schema' => array( $this, 'get_public_item_schema' ), // Define schema for session item
        ) );

        register_rest_route( $this->namespace, '/sessions/(?P<session_id>\\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_session' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'                => array(
                    'session_id' => array(
                        'description' => __( 'Unique identifier for the session.', 'telegram-live-chat' ),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/sessions/(?P<session_id>\\d+)/messages', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_session_messages' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ), // Same permission as getting a session
                'args'                => array_merge(
                    $this->get_collection_params(), // For pagination of messages
                    array(
                        'session_id' => array(
                            'description' => __( 'Unique identifier for the session.', 'telegram-live-chat' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    )
                ),
            ),
            // Schema for messages can be added here
        ) );
    }

    /**
     * Check if a given request has permission to read items.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) { // Example capability
            return new WP_Error( 'rest_forbidden', esc_html__( 'Sorry, you are not allowed to access this resource.', 'telegram-live-chat' ), array( 'status' => rest_authorization_required_code() ) );
        }
        return true;
    }

    /**
     * Check if a given request has permission to read a specific item.
     * (Same as get_items_permissions_check for this basic implementation)
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
     */
    public function get_item_permissions_check( $request ) {
        return $this->get_items_permissions_check( $request );
    }


    /**
     * Get a collection of sessions.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_sessions( $request ) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';

        $params = $request->get_params();
        $page = isset($params['page']) ? absint($params['page']) : 1;
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 10;
        $offset = ($page - 1) * $per_page;

        // Basic filtering example (can be expanded)
        $where_clauses = array();
        $sql_params = array();

        if (!empty($params['status'])) {
            $where_clauses[] = "status = %s";
            $sql_params[] = sanitize_text_field($params['status']);
        }
        // Add more filters: date_range, search by visitor_name/email etc.

        $sql_where = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

        $total_items_query = "SELECT COUNT(*) FROM $sessions_table $sql_where";
        $total_items = $wpdb->get_var(empty($sql_params) ? $total_items_query : $wpdb->prepare($total_items_query, $sql_params));

        $query = "SELECT * FROM $sessions_table $sql_where ORDER BY session_id DESC LIMIT %d OFFSET %d";
        array_push($sql_params, $per_page, $offset);
        $sessions = $wpdb->get_results( $wpdb->prepare( $query, $sql_params ) );

        $data = array();
        foreach ( $sessions as $session ) {
            $response_item = $this->prepare_item_for_response( $session, $request );
            $data[] = $this->prepare_response_for_collection( $response_item );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', (int) $total_items );
        $response->header( 'X-WP-TotalPages', (int) ceil( $total_items / $per_page ) );

        return $response;
    }

    /**
     * Get one session from the collection.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_session( $request ) {
        global $wpdb;
        $session_id = absint( $request['session_id'] );
        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';

        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $sessions_table WHERE session_id = %d", $session_id ) );

        if ( ! $session ) {
            return new WP_Error( 'rest_not_found', __( 'Session not found.', 'telegram-live-chat' ), array( 'status' => 404 ) );
        }

        $data = $this->prepare_item_for_response( $session, $request );
        return rest_ensure_response( $data );
    }

    /**
     * Get messages for a specific session.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_session_messages( $request ) {
        global $wpdb;
        $session_id = absint( $request['session_id'] );
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        // Check if session exists first
        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $session_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sessions_table WHERE session_id = %d", $session_id ) );
        if (!$session_exists) {
             return new WP_Error( 'rest_session_not_found', __( 'Session not found for these messages.', 'telegram-live-chat' ), array( 'status' => 404 ) );
        }

        $params = $request->get_params();
        $page = isset($params['page']) ? absint($params['page']) : 1;
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 20;
        $offset = ($page - 1) * $per_page;

        $total_items = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $messages_table WHERE session_id = %d", $session_id) );

        $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $messages_table WHERE session_id = %d ORDER BY timestamp ASC LIMIT %d OFFSET %d",
            $session_id, $per_page, $offset
        ) );

        $data = array();
        foreach ( $messages as $message ) {
            // Simple preparation, can be expanded with a prepare_message_for_response method
            $message_data = array(
                'message_id' => $message->message_id,
                'session_id' => $message->session_id,
                'sender_type' => $message->sender_type,
                'telegram_user_id' => $message->telegram_user_id,
                'message_content' => $message->message_content, // Consider encryption if applied
                'timestamp' => $message->timestamp,
                'is_read' => (bool) $message->is_read,
                'page_url' => $message->page_url,
            );
            $data[] = $message_data;
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', (int) $total_items );
        $response->header( 'X-WP-TotalPages', (int) ceil( $total_items / $per_page ) );

        return $response;
    }


    /**
     * Prepare the item for the REST response.
     *
     * @param mixed $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function prepare_item_for_response( $item, $request ) {
        // $item is a DB row object from get_row or get_results
        $schema = $this->get_public_item_schema();
        $data = array();

        // Map DB fields to schema fields
        if ( isset( $schema['properties']['session_id'] ) ) $data['session_id'] = (int) $item->session_id;
        if ( isset( $schema['properties']['visitor_token'] ) ) $data['visitor_token'] = $item->visitor_token;
        if ( isset( $schema['properties']['wp_user_id'] ) ) $data['wp_user_id'] = $item->wp_user_id ? (int) $item->wp_user_id : null;
        if ( isset( $schema['properties']['visitor_name'] ) ) $data['visitor_name'] = $item->visitor_name;
        if ( isset( $schema['properties']['visitor_email'] ) ) $data['visitor_email'] = $item->visitor_email;
        if ( isset( $schema['properties']['start_time'] ) ) $data['start_time'] = mysql_to_rfc3339( $item->start_time );
        if ( isset( $schema['properties']['last_active_time'] ) ) $data['last_active_time'] = mysql_to_rfc3339( $item->last_active_time );
        if ( isset( $schema['properties']['status'] ) ) $data['status'] = $item->status;
        if ( isset( $schema['properties']['visitor_ip'] ) ) $data['visitor_ip'] = $item->visitor_ip;
        // ... and so on for referer, utm fields, rating, rating_comment, initial_page_url

        $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data = $this->add_additional_fields_to_object( $data, $request );
        $data = $this->filter_response_by_context( $data, $context );

        $response = rest_ensure_response( $data );
        $response->add_links( $this->prepare_links( $item ) ); // Add HATEOAS links

        return $response;
    }

    /**
     * Prepare links for the request.
     * @param object $item DB row object for a session.
     * @return array Links for the given item.
     */
    protected function prepare_links( $item ) {
        $base = sprintf( '%s/%s', $this->namespace, 'sessions' );
        $links = array(
            'self' => array(
                'href' => rest_url( trailingslashit( $base ) . $item->session_id ),
            ),
            'collection' => array(
                'href' => rest_url( $base ),
            ),
            'messages' => array(
                'href' => rest_url( trailingslashit( $base ) . $item->session_id . '/messages' ),
                'embeddable' => true, // If we want to allow embedding messages
            ),
        );
        return $links;
    }

    /**
     * Get the query params for collections.
     * @return array
     */
    public function get_collection_params() {
        return array(
            'page'     => array(
                'description'       => 'Current page of the collection.',
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
            ),
            'per_page' => array(
                'description'       => 'Maximum number of items to be returned in result set.',
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
                'maximum'           => 100,
            ),
            'status' => array(
                'description'       => 'Filter by session status.',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
            ),
            // Add other common params like 'search', 'orderby', 'order'
        );
    }

    /**
     * Get the Session item schema, conforming to JSON Schema.
     * @return array
     */
    public function get_public_item_schema() {
        if ( $this->schema ) {
            return $this->schema;
        }
        $this->schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'session',
            'type'       => 'object',
            'properties' => array(
                'session_id' => array(
                    'description' => __( 'Unique identifier for the session.', 'telegram-live-chat' ),
                    'type'        => 'integer',
                    'context'     => array( 'view', 'edit', 'embed' ),
                    'readonly'    => true,
                ),
                'visitor_token' => array(
                    'description' => __( 'Unique token for the visitor.', 'telegram-live-chat' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit', 'embed' ),
                    'readonly'    => true,
                ),
                'wp_user_id' => array(
                    'description' => __( 'WordPress user ID, if logged in.', 'telegram-live-chat' ),
                    'type'        => array('integer', 'null'),
                    'context'     => array( 'view', 'edit', 'embed' ),
                    'readonly'    => true,
                ),
                 'visitor_name' => array(
                    'description' => __( 'Name provided by the visitor.', 'telegram-live-chat' ),
                    'type'        => array('string', 'null'),
                    'context'     => array( 'view', 'edit', 'embed' ),
                ),
                'visitor_email' => array(
                    'description' => __( 'Email provided by the visitor.', 'telegram-live-chat' ),
                    'type'        => array('string', 'null'),
                    'format'      => 'email',
                    'context'     => array( 'view', 'edit', 'embed' ),
                ),
                'start_time' => array(
                    'description' => __( "The date the session was started, in the site's timezone.", 'telegram-live-chat' ),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => array( 'view', 'edit', 'embed' ),
                    'readonly'    => true,
                ),
                // Add all other fields: last_active_time, status, ip, user_agent, initial_page_url, referer, utms, rating, rating_comment
            ),
        );
        return $this->schema;
    }
}

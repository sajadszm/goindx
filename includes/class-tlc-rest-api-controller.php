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
            'schema' => array( $this, 'get_public_item_schema' ),
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
                        'validate_callback' => function($param, $request, $key) { return is_numeric($param) && $param > 0; }
                    ),
                ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/sessions/(?P<session_id>\\d+)/messages', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_session_messages' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'                => array_merge(
                    $this->get_collection_params(),
                    array(
                        'session_id' => array(
                            'description' => __( 'Unique identifier for the session.', 'telegram-live-chat' ),
                            'type'        => 'integer',
                            'required'    => true,
                            'validate_callback' => function($param, $request, $key) { return is_numeric($param) && $param > 0; }
                        ),
                        'since_message_id' => array( // For polling new messages
                            'description' => __('Fetch messages after this ID.', 'telegram-live-chat'),
                            'type'        => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                    )
                ),
            ),
        ) );
    }

    public function get_items_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'read_tlc_chat_sessions' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'Sorry, you are not allowed to access this resource.', 'telegram-live-chat' ), array( 'status' => rest_authorization_required_code() ) );
        }
        return true;
    }

    public function get_item_permissions_check( $request ) {
        // For individual items, check if they can read_tlc_chat_sessions in general.
        // More granular checks could be added if needed (e.g., agent can only see their assigned chats - not in scope yet)
        return $this->get_items_permissions_check( $request );
    }

    public function get_sessions( $request ) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $params = $request->get_params();

        $page = isset($params['page']) ? absint($params['page']) : 1;
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 10; // Default to 10 for API
        $offset = ($page - 1) * $per_page;

        $where_clauses = array("1=1"); // Start with a true condition
        $sql_params = array();

        if (!empty($params['status'])) {
            $statuses = array_map('sanitize_key', explode(',', $params['status']));
            if (!empty($statuses)) {
                $status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
                $where_clauses[] = "status IN ($status_placeholders)";
                $sql_params = array_merge($sql_params, $statuses);
            }
        }

        $sql_where = implode(" AND ", $where_clauses);

        $total_items_query = "SELECT COUNT(*) FROM $sessions_table WHERE $sql_where";
        $total_items = $wpdb->get_var(empty($sql_params) ? $total_items_query : $wpdb->prepare($total_items_query, $sql_params));

        $orderby = isset($params['orderby']) ? sanitize_key($params['orderby']) : 'last_active_time';
        $order = isset($params['order']) && in_array(strtoupper($params['order']), array('ASC', 'DESC')) ? strtoupper($params['order']) : 'DESC';

        $allowed_orderby_columns = array('session_id', 'start_time', 'last_active_time', 'status', 'visitor_name', 'visitor_email', 'rating');
        if (!in_array($orderby, $allowed_orderby_columns)) {
            $orderby = 'last_active_time';
        }

        $query_params_for_main_query = $sql_params;
        array_push($query_params_for_main_query, $per_page, $offset);

        $query = "SELECT * FROM $sessions_table WHERE $sql_where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $sessions_results = $wpdb->get_results( $wpdb->prepare( $query, $query_params_for_main_query ) );

        $data = array();
        foreach ( $sessions_results as $session ) {
            $response_item = $this->prepare_item_for_response( $session, $request );
            $data[] = $this->prepare_response_for_collection( $response_item );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', (int) $total_items );
        $response->header( 'X-WP-TotalPages', (int) ceil( $total_items / $per_page ) );

        return $response;
    }

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

    public function get_session_messages( $request ) {
        global $wpdb;
        $session_id = absint( $request['session_id'] );
        $messages_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_messages';

        $sessions_table = $wpdb->prefix . TLC_PLUGIN_PREFIX . 'chat_sessions';
        $session_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sessions_table WHERE session_id = %d", $session_id ) );
        if (!$session_exists) {
             return new WP_Error( 'rest_session_not_found', __( 'Session not found for these messages.', 'telegram-live-chat' ), array( 'status' => 404 ) );
        }

        $params = $request->get_params();
        $page = isset($params['page']) ? absint($params['page']) : 1;
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 20; // Default for API
        $offset = ($page - 1) * $per_page;
        $since_message_id = isset($params['since_message_id']) ? absint($params['since_message_id']) : 0;

        $where_clauses = array("session_id = %d");
        $sql_params = array($session_id);

        if ($since_message_id > 0) {
            $where_clauses[] = "message_id > %d";
            $sql_params[] = $since_message_id;
            // If using since_message_id, we typically don't paginate in the traditional sense but fetch all new.
            // Or, still limit per_page for safety. For now, let's assume it fetches all new ones.
            $per_page = -1; // No limit if fetching since_message_id, or a high limit
            $offset = 0;
        }

        $sql_where = implode(" AND ", $where_clauses);

        $total_items_query = "SELECT COUNT(*) FROM $messages_table WHERE $sql_where";
        $total_items = $wpdb->get_var( $wpdb->prepare($total_items_query, $sql_params) );

        $main_query_params = $sql_params;
        $limit_clause = "ORDER BY timestamp ASC"; // Always get messages in chronological order
        if ($per_page > 0) {
            $limit_clause .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);
            // array_push($main_query_params, $per_page, $offset); // This is incorrect way to add limit params
        }

        $query = "SELECT * FROM $messages_table WHERE $sql_where $limit_clause";
        $messages = $wpdb->get_results( $wpdb->prepare( $query, $main_query_params ) );

        $data = array();
        foreach ( $messages as $message ) {
            $message_data = array(
                'message_id' => (int) $message->message_id,
                'session_id' => (int) $message->session_id,
                'sender_type' => $message->sender_type,
                'telegram_user_id' => $message->telegram_user_id ? (int) $message->telegram_user_id : null,
                'message_content' => $message->message_content,
                'timestamp' => mysql_to_rfc3339($message->timestamp),
                'is_read' => (bool) $message->is_read,
                'page_url' => $message->page_url,
            );
            $data[] = $message_data;
        }

        $response = rest_ensure_response( $data );
        if ($per_page > 0) { // Only set pagination headers if not fetching all 'since_message_id'
            $response->header( 'X-WP-Total', (int) $total_items );
            $response->header( 'X-WP-TotalPages', (int) ceil( $total_items / $per_page ) );
        }

        return $response;
    }

    public function prepare_item_for_response( $item, $request ) {
        $schema = $this->get_public_item_schema();
        $data = array();
        $fields = $this->get_fields_for_response($request); // Get fields based on ?_fields=...

        // Helper to check if field is requested
        $is_field_requested = function($field_name) use ($fields) {
            return empty($fields) || in_array($field_name, $fields, true);
        };

        if ( $is_field_requested('session_id') && isset( $schema['properties']['session_id'] ) ) $data['session_id'] = (int) $item->session_id;
        if ( $is_field_requested('visitor_token') && isset( $schema['properties']['visitor_token'] ) ) $data['visitor_token'] = $item->visitor_token;
        if ( $is_field_requested('wp_user_id') && isset( $schema['properties']['wp_user_id'] ) ) $data['wp_user_id'] = $item->wp_user_id ? (int) $item->wp_user_id : null;
        if ( $is_field_requested('visitor_name') && isset( $schema['properties']['visitor_name'] ) ) $data['visitor_name'] = $item->visitor_name;
        if ( $is_field_requested('visitor_email') && isset( $schema['properties']['visitor_email'] ) ) $data['visitor_email'] = $item->visitor_email;
        if ( $is_field_requested('start_time') && isset( $schema['properties']['start_time'] ) ) $data['start_time'] = mysql_to_rfc3339( $item->start_time );
        if ( $is_field_requested('last_active_time') && isset( $schema['properties']['last_active_time'] ) ) $data['last_active_time'] = mysql_to_rfc3339( $item->last_active_time );
        if ( $is_field_requested('status') && isset( $schema['properties']['status'] ) ) $data['status'] = $item->status;
        if ( $is_field_requested('visitor_ip') && isset( $schema['properties']['visitor_ip'] ) ) $data['visitor_ip'] = $item->visitor_ip;
        if ( $is_field_requested('initial_page_url') && isset( $schema['properties']['initial_page_url'] ) ) $data['initial_page_url'] = $item->initial_page_url;
        if ( $is_field_requested('referer') && isset( $schema['properties']['referer'] ) ) $data['referer'] = $item->referer;
        if ( $is_field_requested('utm_source') && isset( $schema['properties']['utm_source'] ) ) $data['utm_source'] = $item->utm_source;
        if ( $is_field_requested('utm_medium') && isset( $schema['properties']['utm_medium'] ) ) $data['utm_medium'] = $item->utm_medium;
        if ( $is_field_requested('utm_campaign') && isset( $schema['properties']['utm_campaign'] ) ) $data['utm_campaign'] = $item->utm_campaign;
        if ( $is_field_requested('rating') && isset( $schema['properties']['rating'] ) ) $data['rating'] = $item->rating ? (int) $item->rating : null;
        if ( $is_field_requested('rating_comment') && isset( $schema['properties']['rating_comment'] ) ) $data['rating_comment'] = $item->rating_comment;


        $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
        // $data = $this->add_additional_fields_to_object( $data, $request ); // Not using additional fields yet
        $data = $this->filter_response_by_context( $data, $context );

        $response = rest_ensure_response( $data );
        if ( $is_field_requested('_links') ) { // Only add links if requested or if no specific fields are requested
             $response->add_links( $this->prepare_links( $item ) );
        }
        return $response;
    }

    protected function prepare_links( $item ) {
        $base = sprintf( '%s/%s', $this->namespace, 'sessions' );
        $links = array(
            'self' => array( 'href' => rest_url( trailingslashit( $base ) . $item->session_id ), ),
            'collection' => array( 'href' => rest_url( $base ), ),
            'messages' => array( 'href' => rest_url( trailingslashit( $base ) . $item->session_id . '/messages' ), 'embeddable' => true, ),
        );
        return $links;
    }

    public function get_collection_params() {
        $params = parent::get_collection_params(); // Gets 'context', 'page', 'per_page', 'search', 'after', 'before', 'modified_after', 'modified_before', 'author', 'author_exclude', 'offset', 'order', 'orderby', 'slug', 'status', 'tax_relation', 'categories', 'tags'.

        $params['status'] = array(
            'description'       => __( 'Filter by session status or comma-separated list of statuses.', 'telegram-live-chat' ),
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        );
        $params['orderby']['enum'] = array_merge($params['orderby']['enum'] ?? ['date'], ['session_id', 'start_time', 'last_active_time', 'status', 'visitor_name', 'rating']);

        return $params;
    }

    public function get_public_item_schema() {
        if ( $this->schema ) {
            return $this->schema;
        }
        $this->schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'session', 'type'       => 'object',
            'properties' => array(
                'session_id' => array( 'description' => __( 'Unique identifier for the session.', 'telegram-live-chat' ), 'type' => 'integer', 'context' => array( 'view', 'embed' ), 'readonly' => true, ),
                'visitor_token' => array( 'description' => __( 'Unique token for the visitor.', 'telegram-live-chat' ), 'type' => 'string', 'context' => array( 'view', 'embed' ), 'readonly' => true, ),
                'wp_user_id' => array( 'description' => __( 'WordPress user ID, if logged in.', 'telegram-live-chat' ), 'type' => array('integer', 'null'), 'context' => array( 'view', 'embed' ), 'readonly' => true, ),
                'visitor_name' => array( 'description' => __( 'Name provided by the visitor.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'context' => array( 'view', 'embed' ), ),
                'visitor_email' => array( 'description' => __( 'Email provided by the visitor.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'format' => 'email', 'context' => array( 'view', 'embed' ), ),
                'start_time' => array( 'description' => __( "The date the session was started.", 'telegram-live-chat' ), 'type' => 'string', 'format' => 'date-time', 'context' => array( 'view', 'embed' ), 'readonly' => true, ),
                'last_active_time' => array( 'description' => __( "The date the session was last active.", 'telegram-live-chat' ), 'type' => 'string', 'format' => 'date-time', 'context' => array( 'view', 'embed' ), 'readonly' => true, ),
                'status' => array( 'description' => __( 'Session status.', 'telegram-live-chat' ), 'type' => 'string', 'context' => array( 'view', 'embed' ), ),
                'visitor_ip' => array( 'description' => __( 'Visitor IP address.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'context' => array( 'view' ), ), // Typically not 'embed'
                'initial_page_url' => array( 'description' => __( 'Initial page URL where session started.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'format' => 'uri', 'context' => array( 'view' ), ),
                'referer' => array( 'description' => __( 'Referer URL for the session.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'format' => 'uri', 'context' => array( 'view' ), ),
                'utm_source' => array( 'description' => __( 'UTM source parameter.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'context' => array( 'view' ), ),
                'utm_medium' => array( 'description' => __( 'UTM medium parameter.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'context' => array( 'view' ), ),
                'utm_campaign' => array( 'description' => __( 'UTM campaign parameter.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'context' => array( 'view' ), ),
                'rating' => array( 'description' => __( 'Visitor rating for the session (1-5).', 'telegram-live-chat' ), 'type' => array('integer', 'null'), 'context' => array( 'view', 'embed' ), ),
                'rating_comment' => array( 'description' => __( 'Visitor comment for the rating.', 'telegram-live-chat' ), 'type' => array('string', 'null'), 'context' => array( 'view' ), ),
            ),
        );
        return $this->schema;
    }
}

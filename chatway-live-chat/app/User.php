<?php 
/**
 * Chatway internal user APIs
 *
 * @author  : Chatway
 * @license : GPLv3
 * */

namespace Chatway\App;
/**
 * @since 1.0.0
 * Create an internal API group for users by extending Api class 
 */ 
class User extends Api
{
    use Singleton;
    
    public function config() {
        // this prefix will used in api endpoint - example: /chatway/v1/user
        $this->prefix = 'user';
    }

    /**
     * @method POST
     * @api /chatway/v1/user/save 
     * 
     * Save user current user data. Initiall it receives user identifier and token
     */ 
    public function post_save() {
        $params          = $this->request->get_params();
        $user_identifier = sanitize_text_field( isset( $params['user_identifier'] ) ? $params['user_identifier'] : '' );
        $token           = sanitize_text_field( isset( $params['token'] ) ? $params['token'] : '' );
        
        // clear the cache of the user is new
        if (function_exists('chatway_clear_all_caches')) {
            chatway_clear_all_caches();
        }

        // delete all data
        User::clear_chatway_keys();

        if ( ! empty( $user_identifier ) && ! empty( $token ) ) {
            // save user identifier and token to DB
            add_option( 'chatway_user_identifier', $user_identifier );
            add_option( 'chatway_user_cache_identifier', $user_identifier );
            add_option( 'chatway_token', $token );

            return [
                'code'    => 200,
                'message' => 'success',
            ]; 
        }

        return [
            'code'    => 401,
            'message' => 'error',
        ]; 
    }

    /**
     * @method GET
     * @api /chatway/v1/user/logout 
     * 
     * Remote everything related to the current user from DB
     */ 
    public function get_logout() {
        ExternalApi::sync_wp_plugin_version(\Chatway::is_woocomerce_active(), 0);
        User::clear_chatway_keys();
        if (function_exists('chatway_clear_all_caches')) {
            chatway_clear_all_caches();
        }
        return [
            'code'    => 200,
            'message' => 'success',
        ];
    }

    /**
     * Retrieves the unread messages count from an external API and caches it as a transient.
     *
     * @return array An associative array containing the count of unread messages ('count') and a status code ('code').
     */
    public function get_count() {
        delete_transient( 'chatway_unread_messages_count' );
        $count = ExternalApi::get_unread_messages_count();
        set_transient( 'chatway_unread_messages_count', $count, 5*60 );
        return ['count' => $count, 'code' => 200];
    }

    /**
     * Removes all Chatway-related options from the WordPress options table.
     *
     * @return void
     * Method does not return any value.
     */
    static function clear_chatway_keys() {
        delete_option( 'chatway_redirection' );
        delete_option( 'chatway_user_identifier' );
        delete_option( 'chatway_api_secret_license_key' );
        delete_option( 'chatway_token' );
        delete_option( 'chatway_wp_plugin_version' );
        delete_option( 'chatway_secret_key' );
        delete_option( 'chatway_has_auth_error' );
    }
}
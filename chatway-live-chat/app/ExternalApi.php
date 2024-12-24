<?php 
/**
 * Chatway external/remote APIs
 *
 * @author  : Chatway
 * @license : GPLv3
 * */

namespace Chatway\App;

class ExternalApi {
    use Singleton;
    
    /**
     * @return 'invalid' | 'server-down' | 'valid'
     */ 
    static function get_token_status() {
        $token    = get_option( 'chatway_token', '' );
        $response = wp_remote_get( 
            Url::remote_api( "/market-apps/connected?channel=wordpress" ), 
            [
                'redirect' => 'follow',
                'headers'  => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
            ]
        ); 

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( 200 === $response_code ) return 'valid';
        if ( 521 === $response_code ) return 'server-down';

        return 'invalid';
    }

    /**
     * Send the plugin status to the Chatway server
     * @param string $status install | uninstall
     * @return boolean
     */
    static function update_plugins_status( $status = 'install' ) {
        $token      = get_option( 'chatway_token', '' );
        $user_id    = get_option( 'chatway_user_identifier', '' );
        
        if( empty( $token ) || empty( $user_id ) ) return false;

        $response = wp_remote_post( 
            Url::remote_api( "/wordpress/" . $status ), 
            [
                'redirect' => 'follow',
                'headers'  => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
            ]
        );  

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( 200 === $response_code ) return true;

        return false;
    }

    static function get_chatway_secret_key() {
        $token      = get_option( 'chatway_token', '' );
        $user_id    = get_option( 'chatway_user_identifier', '' );

        if( empty( $token ) || empty( $user_id ) ) {
            return false;
        }

        $secret_key    = get_option( 'chatway_secret_key', '' );
        if(!empty($secret_key)) {
            return $secret_key;
        }

        $response = wp_remote_get(
            Url::remote_api( "/visitor-identity-verification/settings" ),
            [
                'redirect' => 'follow',
                'headers'  => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
            ]
        );

        if(!is_wp_error($response) && 200 === wp_remote_retrieve_response_code( $response )) {
            $response_code = json_decode( wp_remote_retrieve_body( $response ), true );
        }

        if (isset($response_code['secret_key'])) {
            delete_option('chatway_secret_key' );
            add_option( 'chatway_secret_key', $response_code['secret_key'] );
            return $response_code['secret_key'];
        }

        return false;
    }

    static function send_visitor_data($hmac, $client_id, $client_data, $token) {
        $user_id    = get_option( 'chatway_user_identifier', '' );
        if(empty( $user_id ) ) {
            return false;
        }

        $payload = [
            'visitor'   => [
                'hmac'  => $hmac,
                'data'  => $client_data,
            ]
        ];

        $response = wp_remote_post(
            Url::remote_api( "/chat-contacts/{$client_id}/mark-as-verified" ),
            [
                'redirect' => 'follow',
                'headers'  => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
                'body'     => $payload
            ]
        );

        $response_code = [];
        if(!is_wp_error($response) && 200 === wp_remote_retrieve_response_code( $response )) {
            $response_code = json_decode( wp_remote_retrieve_body( $response ), true );
        }

        return $response_code;
    }
}
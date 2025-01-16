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

    /**
     * Checks for the presence of a secret key in the WordPress options.
     * If the secret key is not found, it generates a new one, sends it to a remote API,
     * and saves it in the WordPress options if the remote API call is successful.
     *
     * @return void
     */
    static function check_for_secret_key() {
        $secret_key = get_option( 'chatway_secret_token', '' );
        if(empty($secret_key)) {
            $data = random_bytes(16);
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Set the version to 0100 (binary for v4)
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Set the variant to 10xx (RFC variant)
            $secret_key = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

            $payload = [
                'site_url'  => site_url(),
                'secret_key'  => $secret_key,
            ];
            $token      = get_option( 'chatway_token', '' );

            $response = wp_remote_post(
                Url::remote_api( "/wordpress-proxy-api-secret" ),
                [
                    'redirect' => 'follow',
                    'headers'  => [
                        'Accept'        => 'application/json',
                        'Authorization' => 'Bearer ' . $token
                    ],
                    'body'     => $payload
                ]
            );

            if(!is_wp_error($response) && 200 === wp_remote_retrieve_response_code( $response )) {
                $response = json_decode( wp_remote_retrieve_body( $response ), true );
                if(isset($response['message']) && $response['message'] == 'Success') {
                    add_option( 'chatway_secret_token', $secret_key );
                }
            }
        }
    }

    /**
     * Retrieves the secret key for Chatway.
     *
     * This method retrieves the secret key from the WordPress options or fetches it
     * from the remote API if it is not stored in the local options. If the secret key
     * is fetched successfully from the API, it will be stored as a WordPress option.
     *
     * @return string|false The secret key as a string if found or successfully fetched,
     *                      or false if no secret key is available.
     */

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

    /**
     * Sends visitor data to mark the visitor as verified.
     *
     * @param string $hmac The HMAC string for verification.
     * @param string $client_id The client identifier.
     * @param array $client_data The client data to send.
     * @param string $token The authorization token.
     *
     * @return array|false Returns the response code array if successful, or false if an error occurs.
     */
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

    /**
     * @return int Total count of unread messages or 0 if the request fails or required data is missing
     */
    static function get_unread_messages_count()
    {
        $token = get_option('chatway_token', '');
        $user_id = get_option('chatway_user_identifier', '');
        if( empty( $token ) || empty( $user_id ) ) {
            return 0;
        }

        $response = wp_remote_get(
            Url::remote_api( "/unread-notifications" ),
            [
                'redirect' => 'follow',
                'headers'  => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
            ]
        );

        $response_code = [];
        if(!is_wp_error($response) && 200 === wp_remote_retrieve_response_code( $response )) {
            $response_code = json_decode( wp_remote_retrieve_body( $response ), true );
        }

        if(isset($response_code['total_unread_count'])) {
            return $response_code['total_unread_count'];
        }
        return 0;
    }
}
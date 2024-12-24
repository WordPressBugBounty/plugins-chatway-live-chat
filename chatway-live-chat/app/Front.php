<?php
/**
 * Chatway admin assets enqueue
 *
 * @author  : Chatway
 * @license : GPLv3
 * */

namespace Chatway\App;

class Front
{
    use Singleton;

    public function __construct()
    {
        add_action( 'wp_ajax_sync_chatway_data', [$this, 'check_for_conversation'] );
        add_action( 'wp_ajax_nopriv_sync_chatway_data', [$this, 'check_for_conversation'] );
    }

    /**
     * Checks if there is an active conversation for the current user and sends visitor data to an external API if necessary.
     *
     * @return void
     */
    public function check_for_conversation() {
        $user_id = get_current_user_id();
        if(!empty($user_id)) {
            $token = get_option('chatway_user_identifier', '');
            if (!empty($token)) {
                if ((isset($_COOKIE['ch_cw_contact_id_' . $token]) || isset($_GET['ch_contact_id'])) && isset($_COOKIE['ch_cw_token_' . $token]) && !isset($_COOKIE['ch_cw_user_status_' . $token])) {
                    $contact_id    = esc_attr($_COOKIE['ch_cw_contact_id_' . $token]);
                    if(empty($contact_id)) {
                        $contact_id = esc_attr($_GET['ch_contact_id']);
                    }
                    $contact_token = esc_attr($_COOKIE['ch_cw_token_' . $token]);
                    if (!empty($contact_id) && !empty($contact_token)) {

                        $user_status = get_user_meta($user_id, 'chatway_status_'.esc_attr($contact_id));
                        if($user_status) {
                            setcookie('ch_cw_user_status_' . $token, 'yes', time() + YEAR_IN_SECONDS, "/");
                            return;
                        }

                        $user = get_userdata($user_id);
                        if (!isset($user->data->user_email) || empty($user->data->user_email)) {
                            return;
                        }
                        $email = $user->data->user_email;

                        $secret_key = ExternalApi::get_chatway_secret_key();

                        if (empty($secret_key)) {
                            return;
                        }

                        $first_name = get_user_meta($user_id, 'first_name', true);
                        $last_name = get_user_meta($user_id, 'last_name', true);
                        $name = trim($first_name . ' ' . $last_name);
                        $user_info = [
                            'email' => esc_attr($email),
                            'id' => esc_attr($user_id)
                        ];
                        if (!empty($name)) {
                            $user_info['name'] = $name;
                        }

                        $avatar = get_avatar_url($user_id);
                        if(!empty($avatar)) {
                            $user_info['avatar'] = $avatar;
                        }

                        $hmac = hash_hmac(
                            'sha256',
                            json_encode($user_info),
                            esc_attr($secret_key)
                        );

                        $response_code = ExternalApi::send_visitor_data($hmac, $contact_id, $user_info, $contact_token);
                        if (is_array($response_code) && isset($response_code['message']) && $response_code['message'] == 'Success') {
                            setcookie('ch_cw_user_status_' . $token, 'yes', time() + YEAR_IN_SECONDS, "/");
                            add_user_meta($user_id, 'chatway_status_'.esc_attr($contact_id), $contact_id);
                        }

                        echo json_encode($response_code);
                        exit;
                    }
                }
            }
        }
    }
}
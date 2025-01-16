<?php
/**
 * Chatway view
 *
 * @author  : Chatway
 * @license : GPLv3
 * */

namespace Chatway\App;

class View {
    use Singleton;
    
    public function __construct() {
        add_action( 'admin_menu', [$this, 'dashboard_screen'] );
        add_action( 'admin_head', [$this, 'admin_head'] );
        add_action( 'admin_init', [$this, 'show_unread_messages_count']);
    }

    /**
     * Updates the admin menu to display the count of unread messages for the "Chatway" menu item.
     * If the current page is the "Chatway" page, it clears the transient cache for unread messages count.
     *
     * @return void
     */
    public function show_unread_messages_count() {
        global $menu;
        if((!isset($_GET['page']) || $_GET['page'] != 'chatway') && is_array($menu)) {
            foreach ($menu as $key => $value) {
                if ($value[0] == 'Chatway') {
                    $count = $this->check_for_unread_messages();
                    $menu[$key][0] = $menu[$key][0] . ' <span id="chatway-unread-count" class="update-plugins count-'.esc_attr($count).'"><span class="plugin-count" id="chatway-count">'.esc_attr($count).'</span></span>';
                }
            }
        } else if(isset($_GET['page']) && $_GET['page'] == 'chatway') {
            delete_transient( 'chatway_unread_messages_count' );
        }
    }

    /**
     * Checks for the number of unread messages.
     * Utilizes a transient to cache the count and avoid redundant API calls.
     * If the transient is not set or expired, it fetches the count from an external API.
     *
     * @return int The number of unread messages. Returns 0 if no unread messages exist or data is unavailable.
     */
    public function check_for_unread_messages() {
        $count = get_transient( 'chatway_unread_messages_count' );
        if(!empty($count)) {
            return $count;
        }
        $user_identifier = get_option( 'chatway_user_identifier', '' );
        $count = 0;
        if (!empty( $user_identifier ) ) {
            $count = ExternalApi::get_unread_messages_count();
            if(empty($count)) {
                $count = 0;
            }
        }
        set_transient( 'chatway_unread_messages_count', $count, 5*60 );
        return $count;
    }

    /**
     * apply some css to admin head. These stylesheet will be used through out the application frontend
     * @since 1.0.0 
     */ 
    public function admin_head() {
        ?>
            <style>
                #toplevel_page_chatway .dashicons-before img {
                    opacity: 0 !important;
                }
                
                #toplevel_page_chatway .dashicons-before {
                    background-color: #A0A3A8;
                    -webkit-mask: url( <?php echo esc_url( \Chatway::url( 'assets/images/menu-icon.svg' ) ) ?> ) no-repeat center;
                    mask: url( <?php echo esc_url( \Chatway::url( 'assets/images/menu-icon.svg' ) ) ?> ) no-repeat center;
                }
                #toplevel_page_chatway:hover .dashicons-before {
                    background-color: #00b9eb;
                }

                #toplevel_page_chatway:has(.current) .dashicons-before{
                    background-color: currentColor;
                }

                @media (min-width: 961px) {
                    body:not(.folded) {
                        --wp-sidebar-width: 160px;
                    }

                    body.folded {
                        --wp-sidebar-width: 36px;
                    }
                }

                @media (max-width: 960px) and (min-width: 783px) {
                    body {
                        --wp-sidebar-width: 36px;
                    }
                }

                @media (max-width: 782px ) {
                    body {
                        --wp-sidebar-width: 0px;
                    }
                }
            </style>
        <?php
    }

    public function screen() {
        $status = ExternalApi::get_token_status();

        switch ( $status ) {
            case 'valid':
                \Chatway::include_once( 'views/dashboard.php' );
                break;
            case 'invalid': 
                \Chatway::include_once( 'views/auth.php' );
                break;
            case 'server-down':
                \Chatway::include_once( 'views/error.php' );
                break;
        }
    }

    public function dashboard_screen() {
        add_menu_page(
            esc_html__( "Chatway Dashboard", 'chatway' ), 
            esc_html__( "Chatway", 'chatway' ), 
            'manage_options', 
            'chatway', 
            [$this, 'screen'], 
            esc_url( \Chatway::url( 'assets/images/menu-icon.svg' ) )
        );

        if ( ! empty( get_option( 'chatway_token', '' ) ) ) {
            add_submenu_page(
                'chatway',
                esc_html__( "Chatway Full-Screen View", 'chatway' ), 
                esc_html__( "Full-Screen View", 'chatway' ),
                'manage_options',
                'chatway-full-screen',
                [$this, 'screen']
            );
            
            add_submenu_page(
                'chatway',
                esc_html__( "Chatway Logout", 'chatway' ), 
                esc_html__( "Log Out", 'chatway' ),
                'manage_options',
                'chatway-logout',
                [$this, 'screen']
            );
        }
    }
}
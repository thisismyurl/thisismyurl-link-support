<?php
/**
 * Author:              Christopher Ross
 * Author URI:          https://thisismyurl.com/?source=link-support-thisismyurl
 * Plugin Name:         Link Support by thisismyurl.com
 * Plugin URI:          https://thisismyurl.com/link-support-thisismyurl/?source=link-support-thisismyurl
 * Donate link:         https://thisismyurl.com/link-support-thisismyurl/#register?source=link-support-thisismyurl
 * 
 * Description:         The ultimate suite for link management: Custom Link Post Type with Meta Fields, Force New Tab, SEO support, Internal Link indicators, and Heatmap Analytics.
 * Tags:                links, seo, internal-links, badges, analytics, heatmap, security, exit-monitor, external-links, target-blank
 * 
 * Version: 1.26010222
 * Requires at least:   6.0
 * Requires PHP:        7.4
 * 
 * Update URI:          https://github.com/thisismyurl/link-support-thisismyurl
 * GitHub Plugin URI:   https://github.com/thisismyurl/link-support-thisismyurl
 * Primary Branch:      main
 * Text Domain:         link-support-thisismyurl
 * 
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * 
 * @package TIMU_LINK_Support
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core Library Loader
 */
function timu_link_support_load_core() {
    $core_path = plugin_dir_path( __FILE__ ) . 'core/class-timu-core.php';
    if ( ! class_exists( 'TIMU_Core_v1' ) ) {
        require_once $core_path;
    }
}
timu_link_support_load_core();

if ( class_exists( 'TIMU_Core_v1' ) && ! class_exists( 'TIMU_Link_Support' ) ) {

class TIMU_Link_Support extends TIMU_Core_v1 {

    /**
     * Plugin version for asset cache busting.
     * Static to match parent TIMU_Core_v1 definition.
     */
    public static $version = '1.260101';

    public function __construct() {
        parent::__construct( 
            'link-support-thisismyurl', 
            plugin_dir_url( __FILE__ ), 
            'timu_ls_settings_group', 
            '', 
            'edit.php?post_type=timu_links' 
        );

        // Core Hooks
        add_action( 'init', array( $this, 'register_link_cpt' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_link_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_link_meta_data' ) );
        
        // AJAX Click Tracking
        add_action( 'wp_ajax_timu_track_link_click', array( $this, 'ajax_track_link_click' ) );
        add_action( 'wp_ajax_nopriv_timu_track_link_click', array( $this, 'ajax_track_link_click' ) );

        // List Table Customization
        add_filter( 'manage_timu_links_posts_columns', array( $this, 'set_link_table_columns' ) );
        add_action( 'manage_timu_links_posts_custom_column', array( $this, 'render_link_table_columns' ), 10, 2 );

        // UI and Assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_head', array( $this, 'apply_cpt_custom_css' ) );
        add_action( 'edit_form_top', array( $this, 'render_cpt_banner' ) );
        add_action( 'edit_form_after_title', array( $this, 'render_title_directions' ) );

        // Link logic processing filters
        add_filter( 'the_content', array( $this, 'auto_replace_links_in_content' ), 5 );
        add_filter( 'the_content', array( $this, 'modify_content_links' ), 99 );
        add_filter( 'comment_text', array( $this, 'modify_comment_links' ), 99 );
        add_filter( 'widget_text', array( $this, 'modify_sidebar_links' ), 99 );
        add_filter( 'widget_block_content', array( $this, 'modify_sidebar_links' ), 99 );
        
        // Security Sanitization
        add_filter( 'comment_text', array( $this, 'jailbreak_sanitizer' ), 98 );
        add_filter( 'get_the_author_description', array( $this, 'jailbreak_sanitizer' ), 99 );

        // Frontend Scripts
        add_action( 'wp_footer', array( $this, 'enqueue_link_scripts' ) );

        register_activation_hook( __FILE__, array( $this, 'activate_plugin_defaults' ) );
    }

    /**
     * Records clicks with timestamps.
     */
    public function ajax_track_link_click() {
        $link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
        if ( $link_id ) {
            $clicks = (int) get_post_meta( $link_id, '_timu_link_clicks', true );
            update_post_meta( $link_id, '_timu_link_clicks', ++$clicks );

            $log = get_post_meta( $link_id, '_timu_click_log', true ) ?: array();
            array_unshift( $log, time() );
            update_post_meta( $link_id, '_timu_click_log', array_slice( $log, 0, 50 ) );
        }
        wp_die();
    }

    /**
     * List table customization.
     */
    public function set_link_table_columns( $columns ) {
        return array(
            'cb'            => $columns['cb'],
            'title'         => __( 'Link Name', 'link-support-thisismyurl' ),
            'dest_url'      => __( 'Destination URL', 'link-support-thisismyurl' ),
            'dest_age'      => __( 'Destination Age', 'link-support-thisismyurl' ), // New Column
            'is_active'     => __( 'Active', 'link-support-thisismyurl' ),
            'usage_count'   => __( 'Usage', 'link-support-thisismyurl' ),
            'clicks'        => __( 'Clicks', 'link-support-thisismyurl' ),
            'date'          => $columns['date'],
        );
    }

    public function render_link_table_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'dest_url':
                $url = get_post_meta( $post_id, '_timu_link_url', true );
                echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html($url) . '</a>' : '?';
                break;
            case 'dest_age':
                $url = get_post_meta( $post_id, '_timu_link_url', true );
                echo $url ? esc_html( $this->get_destination_age( $url ) ) : '?';
                break;
            case 'is_active':
                $active = get_post_meta( $post_id, '_timu_auto_replace', true );
                echo ( 1 == $active ) ? '<span style="color:#00a32a; font-weight:bold;">Active</span>' : '<span style="color:#999;">Disabled</span>';
                break;
            case 'usage_count':
                echo '<strong>' . count( $this->get_active_post_list( $post_id ) ) . '</strong> posts';
                break;
            case 'clicks':
                $clicks = get_post_meta( $post_id, '_timu_link_clicks', true ) ?: 0;
                echo '<strong>' . esc_html( $clicks ) . '</strong> clicks';
                break;
        }
    }

    /**
     * Checks the remote destination's last modified date.
     */
    private function get_destination_age( $url ) {
        $cache_key = 'timu_dest_age_' . md5( $url );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;

        $response = wp_remote_head( $url, array( 'timeout' => 5 ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            set_transient( $cache_key, 'Unknown', DAY_IN_SECONDS );
            return 'Unknown';
        }

        $last_modified = wp_remote_retrieve_header( $response, 'last-modified' );
        if ( ! $last_modified ) {
            set_transient( $cache_key, 'No date header', DAY_IN_SECONDS );
            return 'No date header';
        }

        $timestamp = strtotime( $last_modified );
        $age = human_time_diff( $timestamp, time() ) . ' ago';
        set_transient( $cache_key, $age, DAY_IN_SECONDS );
        return $age;
    }

    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        if ( $screen && 'timu_links' === $screen->post_type ) {
            wp_enqueue_style( 'timu-shared-admin', $this->plugin_url . 'core/assets/shared-admin.css', array(), self::$version );
        }
    }
    
    public function apply_cpt_custom_css() {
        $screen = get_current_screen();
        if ( $screen && 'timu_links' === $screen->post_type ) {
            ?>
            <style>
                .post-type-timu_links .wp-heading-inline, .post-type-timu_links .page-title-action, .post-type-timu_links #wpbody-content > h1 { display: none !important; }
                .post-type-timu_links .timu-admin-wrap.timu-banner-area { margin: 10px 0 20px 0; clear: both; display: block; }
                .post-type-timu_links #timu_link_sidebar_tools .inside { margin: 0 !important; padding: 0 !important; }
                .post-type-timu_links #timu_link_sidebar_tools #postbox-container-1 { width: 100% !important; float: none !important; margin: 0 !important; display: block !important; background: transparent !important; border: none !important; box-shadow: none !important; }
                .post-type-timu_links #timu_link_sidebar_tools .timu-admin-wrap { width: 100% !important; float: none !important; display: block !important; }
                .usage-log-container { max-height: 200px; overflow-y: auto; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px; }
                .click-log-box { font-family: monospace; font-size: 11px; background: #1a1a1a; color: #2ecc71; padding: 10px; border-radius: 4px; height: 150px; overflow-y: scroll; border: 1px solid #000; }
            </style>
            <?php
        }
    }

    public function render_cpt_banner( $post ) {
        if ( 'timu_links' === $post->post_type ) {
            echo '<div class="timu-admin-wrap timu-banner-area">';
            $this->render_core_header();
            echo '</div>';
        }
    }

    public function render_title_directions( $post ) {
        if ( 'timu_links' === $post->post_type ) {
            echo '<p class="description" style="margin-top: 5px; margin-bottom: 20px;">' . esc_html__( 'Please enter the name of the website (e.g., Google or Facebook).', 'link-support-thisismyurl' ) . '</p>';
        }
    }

    public function render_cpt_sidebar_box() {
        echo '<div class="timu-admin-wrap">';
        $this->render_core_sidebar();
        echo '</div>';
    }

    public function register_link_cpt() {
        $labels = array(
            'name'               => _x( 'Links', 'post type general name', 'link-support-thisismyurl' ),
            'singular_name'      => _x( 'Link', 'post type singular name', 'link-support-thisismyurl' ),
            'menu_name'          => _x( 'Links', 'admin menu', 'link-support-thisismyurl' ),
            'add_new'            => _x( 'Add New', 'link', 'link-support-thisismyurl' ),
            'add_new_item'       => __( 'Add New Link Entry', 'link-support-thisismyurl' ),
            'edit_item'          => __( 'Edit Link Entry', 'link-support-thisismyurl' ),
            'all_items'          => __( 'All Links', 'link-support-thisismyurl' ),
        );
        register_post_type( 'timu_links', array( 'labels' => $labels, 'public' => true, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 20, 'menu_icon' => 'dashicons-admin-links', 'supports' => array( 'title' ), 'show_in_rest' => true ) );
    }

    public function add_link_meta_boxes() {
        add_meta_box( 'timu_link_details', __( 'Link Configuration', 'link-support-thisismyurl' ), array( $this, 'render_link_meta_box' ), 'timu_links', 'normal', 'high' );
        add_meta_box( 'timu_link_automation', __( 'Link Automation', 'link-support-thisismyurl' ), array( $this, 'render_automation_meta_box' ), 'timu_links', 'normal', 'default' );
        add_meta_box( 'timu_link_usage_list', __( 'Active Locations', 'link-support-thisismyurl' ), array( $this, 'render_usage_meta_box' ), 'timu_links', 'normal', 'default' );
        add_meta_box( 'timu_link_click_log', __( 'Click Log', 'link-support-thisismyurl' ), array( $this, 'render_click_log_meta_box' ), 'timu_links', 'normal', 'default' );
        add_meta_box( 'timu_link_sidebar_tools', __( 'Link Support Tools', 'link-support-thisismyurl' ), array( $this, 'render_cpt_sidebar_box' ), 'timu_links', 'side', 'default' );
    }

    public function render_link_meta_box( $post ) {
        wp_nonce_field( 'timu_save_link_meta', 'timu_link_nonce' );
        $url = get_post_meta( $post->ID, '_timu_link_url', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="timu_link_url"><?php _e( 'Destination URL', 'link-support-thisismyurl' ); ?></label></th>
                <td><input type="url" name="timu_link_url" id="timu_link_url" value="<?php echo esc_url( $url ); ?>" class="large-text" placeholder="https://example.com" required /></td>
            </tr>
        </table>
        <?php
    }

    public function render_automation_meta_box( $post ) {
        $auto_replace   = get_post_meta( $post->ID, '_timu_auto_replace', true );
        if ( '' === $auto_replace ) $auto_replace = 1;
        $case_sensitive = get_post_meta( $post->ID, '_timu_case_sensitive', true );
        $alias          = get_post_meta( $post->ID, '_timu_link_alias', true );
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e( 'Auto-Replace Title', 'link-support-thisismyurl' ); ?></th>
                <td><label class="timu-switch"><input type="checkbox" name="timu_auto_replace" value="1" <?php checked($auto_replace, 1); ?> /><span class="timu-slider"></span></label></td>
            </tr>
            <tr>
                <th><?php _e( 'Case Sensitive', 'link-support-thisismyurl' ); ?></th>
                <td><label class="timu-switch"><input type="checkbox" name="timu_case_sensitive" value="1" <?php checked($case_sensitive, 1); ?> /><span class="timu-slider"></span></label></td>
            </tr>
            <tr>
                <th><label for="timu_link_alias"><?php _e( 'Link Alias', 'link-support-thisismyurl' ); ?></label></th>
                <td><input type="text" name="timu_link_alias" id="timu_link_alias" value="<?php echo esc_attr($alias); ?>" class="large-text" placeholder="Facebook Profile, FB Page" /></td>
            </tr>
        </table>
        <?php
    }

    public function render_usage_meta_box( $post ) {
        $posts = $this->get_active_post_list( $post->ID );
        if ( empty($posts) ) { echo '<p>' . esc_html__( 'No active locations found.', 'link-support-thisismyurl' ) . '</p>'; return; }
        echo '<div class="usage-log-container"><ul>';
        foreach ( $posts as $id => $title ) {
            $link = get_edit_post_link($id);
            printf( '<li>%s <strong>%s</strong> (ID: %d)</li>', $link ? '<a href="'.esc_url($link).'" target="_blank">Edit</a>' : '', esc_html($title), $id );
        }
        echo '</ul></div>';
    }

    public function render_click_log_meta_box( $post ) {
        $log = get_post_meta( $post->ID, '_timu_click_log', true ) ?: array();
        if ( empty($log) ) { echo '<p>' . esc_html__( 'No clicks tracked.', 'link-support-thisismyurl' ) . '</p>'; return; }
        echo '<div class="click-log-box">';
        foreach ( $log as $ts ) { echo '[' . date( 'Y-m-d H:i:s', $ts ) . '] Link Clicked<br>'; }
        echo '</div>';
    }

    private function get_active_post_list( $post_id ) {
        global $wpdb;
        $title = get_the_title( $post_id );
        $alias = get_post_meta( $post_id, '_timu_link_alias', true );
        $terms = array_filter( array_map( 'trim', explode( ',', $alias ) ) );
        $terms[] = $title;
        $results = array();
        foreach ( $terms as $term ) {
            if ( empty($term) ) continue;
            $posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE post_content LIKE %s AND post_status = 'publish' AND post_type != 'timu_links' LIMIT 100", '%' . $wpdb->esc_like($term) . '%' ) );
            foreach($posts as $p) { $results[$p->ID] = $p->post_title; }
        }
        return $results;
    }

    public function save_link_meta_data( $post_id ) {
        if ( ! isset( $_POST['timu_link_nonce'] ) || ! wp_verify_nonce( $_POST['timu_link_nonce'], 'timu_save_link_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( isset( $_POST['timu_link_url'] ) ) update_post_meta( $post_id, '_timu_link_url', esc_url_raw( $_POST['timu_link_url'] ) );
        update_post_meta( $post_id, '_timu_auto_replace', isset( $_POST['timu_auto_replace'] ) ? 1 : 0 );
        update_post_meta( $post_id, '_timu_case_sensitive', isset( $_POST['timu_case_sensitive'] ) ? 1 : 0 );
        if ( isset( $_POST['timu_link_alias'] ) ) update_post_meta( $post_id, '_timu_link_alias', sanitize_text_field( $_POST['timu_link_alias'] ) );
    }

    /**
     * Updated defaults to include link masking.
     */
    public function activate_plugin_defaults() {
        $option_name = $this->plugin_slug . '_options';
        if ( false === get_option( $option_name ) ) {
            update_option( $option_name, array( 
                'new_tab'           => 1, 
                'nofollow'          => 1, 
                'ugc_enabled'       => 1, 
                'force_secure'      => 1, 
                'aria_labels'       => 1, 
                'sponsored_enabled' => 1, 
                'external_icon'     => 1, 
                'download_attr'     => 1, 
                'jailbreak'         => 1,
                'link_masking'      => 0 
            ) );
        }
    }

    public function sanitize_core_options( $input ) {
        $sanitized = array();
        $checkboxes = array('new_tab', 'force_secure', 'nofollow', 'ugc_enabled', 'aria_labels', 'sponsored_enabled', 'external_icon', 'favicon_mode', 'download_attr', 'internal_badges', 'media_indicators', 'post_type_data', 'hreflang_tags', 'heatmap_enabled', 'tracking_enabled', 'exit_monitor', 'jailbreak', 'link_masking');
        foreach ( $checkboxes as $key ) { $sanitized[$key] = isset( $input[$key] ) ? 1 : 0; }
        $sanitized['exit_message']      = sanitize_text_field( $input['exit_message'] ?? '' );
        $sanitized['sponsored_domains'] = sanitize_textarea_field( $input['sponsored_domains'] ?? '' );
        $sanitized['whitelist']         = sanitize_textarea_field( $input['whitelist'] ?? '' );
        return $sanitized;
    }

    public function add_admin_menu() {
        add_submenu_page( 'edit.php?post_type=timu_links', __( 'Link Support Settings', 'link-support-thisismyurl' ), __( 'Settings', 'link-support-thisismyurl' ), 'manage_options', $this->plugin_slug, array( $this, 'render_ui' ) );
    }

    public function render_ui() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $options = $this->get_plugin_option();
        ?>
        <div class="wrap timu-admin-wrap">
            <?php $this->render_core_header(); ?>
            <?php include_once('settings.php');?>
            <?php $this->render_core_footer(); ?>
        </div>
        <?php
    }

    public function jailbreak_sanitizer( $content ) {
        $options = $this->get_plugin_option(); if ( empty( $options['jailbreak'] ) ) return $content;
        return preg_replace( array('/\son\w+=(["\'])(.*?)\1/i', '/href=(["\'])javascript:(.*?)\1/i'), array('', 'href="#"'), $content );
    }

    public function modify_content_links( $content ) { return $this->process_links( $content, 'content' ); }
    public function modify_comment_links( $content ) { return $this->process_links( $content, 'comment' ); }
    public function modify_sidebar_links( $content ) { return $this->process_links( $content, 'sidebar' ); }

    private function process_links( $content, $context = 'content' ) {
        $options = $this->get_plugin_option(); static $link_count = 0;
        return preg_replace_callback( '/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i', function( $matches ) use ( $options, $context, &$link_count ) {
            $link_html = $matches[0]; $url = $matches[1]; $inner_txt = $matches[2];
            $link_count++; $new_inner = $inner_txt;
            if ( $this->is_external( $url ) && strpos( $url, 'http' ) === 0 ) {
                $host = parse_url($url, PHP_URL_HOST);
                if ( ! empty($options['tracking_enabled']) ) $link_html = str_replace('<a ', '<a data-link-id="tracking" ', $link_html);
                $file_ext = strtolower( pathinfo( parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION ) );
                if ( ! empty($options['download_attr']) && in_array($file_ext, array('pdf', 'zip')) ) {
                    $link_html = str_replace('<a ', '<a download ', $link_html);
                    $size = $this->get_remote_file_size($url); if($size) $new_inner .= ' ('.$size.')';
                }
                if ( ! empty($options['favicon_mode']) && $host ) $new_inner = '<img src="https://www.google.com/s2/favicons?domain='.$host.'" style="width:16px; margin-right:5px; vertical-align:middle;">' . $new_inner;
                if ( ! empty($options['external_icon']) ) $new_inner .= ' ?';
                $link_html = str_replace('>'.$inner_txt.'</a>', '>'.$new_inner.'</a>', $link_html);
                if ( ! empty($options['new_tab']) ) { $link_html = str_replace('<a ', '<a target="_blank" ', $link_html); }
            } else {
                $post_id = url_to_postid($url);
                if ( $post_id && ! empty($options['internal_badges']) ) {
                    $mod = get_post_modified_time('U', true, $post_id);
                    if ( (time() - $mod) < (30 * DAY_IN_SECONDS ) ) $link_html = str_replace($inner_txt.'</a>', $inner_txt.' <span class="badge-fresh" style="background:#00a32a; color:#fff; font-size:9px; padding:2px 4px; border-radius:3px;">NEW</span></a>', $link_html);
                }
            }
            return $link_html;
        }, $content );
    }

    private function is_external( $url ) {
        $site_host = parse_url( get_site_url(), PHP_URL_HOST ); $link_host = parse_url( $url, PHP_URL_HOST );
        return ( $link_host && strpos( $link_host, $site_host ) === false );
    }

    private function get_remote_file_size( $url ) {
        $key = 'timu_ls_size_' . md5( $url ); $cached = get_transient( $key ); if ( false !== $cached ) return $cached;
        $res = wp_remote_head( $url, array( 'timeout' => 5 ) ); 
        if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) return '';
        $fmt = size_format( wp_remote_retrieve_header( $res, 'content-length' ) );
        set_transient( $key, $fmt, DAY_IN_SECONDS ); return $fmt;
    }

    public function auto_replace_links_in_content( $content ) {
        $links = get_posts( array( 'post_type' => 'timu_links', 'meta_key' => '_timu_auto_replace', 'meta_value' => '1', 'posts_per_page' => -1 ) );
        foreach ( $links as $l ) {
            $url   = get_post_meta( $l->ID, '_timu_link_url', true );
            $alias = get_post_meta( $l->ID, '_timu_link_alias', true );
            $case  = get_post_meta( $l->ID, '_timu_case_sensitive', true );
            $terms = array_filter( array_map( 'trim', explode( ',', $alias ) ) );
            $terms[] = $l->post_title;
            foreach ( $terms as $term ) {
                if ( empty($term) ) continue;
                $regex = '/(?!(?:[^<]*<a[^>]*>))(?<!\w)' . preg_quote( $term, '/' ) . '(?!\w)(?![^<]*<\/a>)/';
                if ( ! $case ) $regex .= 'i';
                $content = preg_replace( $regex, sprintf( '<a href="%s" class="timu-managed-link" data-link-id="%d">%s</a>', esc_url($url), $l->ID, '$0' ), $content, 1 ); 
            }
        }
        return $content;
    }

    public function enqueue_link_scripts() {
        ?>
        <script type="text/javascript">
            document.addEventListener('click', function(e) {
                var t = e.target.closest('a[data-link-id]'); 
                if (t && t.getAttribute('data-link-id') !== 'tracking') {
                    var data = new FormData();
                    data.append('action', 'timu_track_link_click');
                    data.append('link_id', t.getAttribute('data-link-id'));
                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: data });
                }
            });
        </script>
        <?php
    }
}
new TIMU_Link_Support();
}

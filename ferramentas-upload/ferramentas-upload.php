<?php
/**
 * Plugin Name:       Ferramentas Upload Refatorado
 * Plugin URI:        https://github.com/cagezinho/ferramentas-upload
 * Description:       Permite atualizações massivas via CSV: Texto Alternativo (Alt Text) de Imagens e SERPs utilizando o Yoast.
 * Version:           1.1.0
 * Author:            Cage
 * Author URI:        https://github.com/cagezinho
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ferramentas-upload
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FU_VERSION', '1.1.0');
define('FU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FU_TEXT_DOMAIN', 'ferramentas-upload');
define('FU_PAGE_SLUG', 'ferramentas-upload-main');
define('FU_CAPABILITY', 'manage_options');

require_once FU_PLUGIN_DIR . 'includes/class-fu-admin.php';
require_once FU_PLUGIN_DIR . 'includes/class-fu-alt-text-handler.php';
require_once FU_PLUGIN_DIR . 'includes/class-fu-serp-handler.php';

class Ferramentas_Upload_Main {

    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_textdomain();
        $this->init_hooks();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            FU_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    private function init_hooks() {
        add_action('admin_menu', [FU_Admin::class, 'add_admin_menu']);
        add_action('admin_post_fu_handle_alt_text_upload', [FU_Alt_Text_Handler::class, 'handle_upload']);
        add_action('admin_post_fu_handle_serp_upload', [FU_SERP_Handler::class, 'handle_upload']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('admin_init', [$this, 'check_dependencies']);
    }

    public function display_admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== FU_PAGE_SLUG) {
            return;
        }

        $notice_types = ['success', 'error', 'warning', 'info'];
        foreach ($notice_types as $type) {
            $transient_name = 'fu_admin_notice_' . $type;
            $message = get_transient($transient_name);

            if ($message) {
                printf(
                    '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                    esc_attr($type),
                    wp_kses_post($message)
                );
                delete_transient($transient_name);
            }
        }
    }

    public function check_dependencies() {
        if (!is_plugin_active('wordpress-seo/wp-seo.php') && !is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
            add_action('admin_notices', function() {
                if (isset($_GET['page']) && $_GET['page'] === FU_PAGE_SLUG && isset($_GET['tab']) && $_GET['tab'] === 'serp') {
                    echo '<div class="notice notice-error"><p>' .
                         sprintf(
                             esc_html__('Aviso: O plugin %s precisa estar ativo para usar a funcionalidade de atualização de SERP.', FU_TEXT_DOMAIN),
                             '<strong>Yoast SEO</strong>'
                         ) .
                         '</p></div>';
                }
            });
        }
    }
}

Ferramentas_Upload_Main::get_instance();

?>
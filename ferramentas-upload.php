<?php
/**
 * Plugin Name:       Ferramentas Upload
 * Plugin URI:        https://github.com/cagezinho?tab=repositories
 * Description:       Permite atualizações em massa via CSV: Texto Alternativo (Alt Text) de Imagens e Títulos/Descrições SEO do Yoast.
 * Version:           1.0.1
 * Author:            Nicolas Cage
 * Author URI:        https://nicolascage.dev.br
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ferramentas-upload
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FU_PREFIX', 'fu_');
define('FU_TEXT_DOMAIN', 'ferramentas-upload');
define('FU_PAGE_SLUG', 'ferramentas-upload-main');
define('FU_ALT_NONCE_ACTION', FU_PREFIX . 'alt_text_nonce_action');
define('FU_ALT_NONCE_FIELD', FU_PREFIX . 'alt_text_nonce_field');
define('FU_SERP_NONCE_ACTION', FU_PREFIX . 'serp_nonce_action');
define('FU_SERP_NONCE_FIELD', FU_PREFIX . 'serp_nonce_field');

add_action('admin_menu', FU_PREFIX . 'add_admin_menu');
add_action('plugins_loaded', FU_PREFIX . 'load_textdomain');

function fu_load_textdomain() {
    load_plugin_textdomain(FU_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function fu_add_admin_menu() {
    add_menu_page(
        __('Ferramentas Upload', FU_TEXT_DOMAIN),
        __('Ferramentas Upload', FU_TEXT_DOMAIN),
        'manage_options',
        FU_PAGE_SLUG,
        FU_PREFIX . 'render_admin_page',
        'dashicons-upload',
        25
    );
}

function fu_render_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Você não tem permissão para acessar esta página.', FU_TEXT_DOMAIN));
    }

    if (isset($_POST[FU_PREFIX . 'action'])) {
        $action = sanitize_key($_POST[FU_PREFIX . 'action']);
        if ($action === 'update_alt_text' && isset($_POST[FU_ALT_NONCE_FIELD])) {
            check_admin_referer(FU_ALT_NONCE_ACTION, FU_ALT_NONCE_FIELD);
            fu_handle_alt_text_upload();
        } elseif ($action === 'update_serp' && isset($_POST[FU_SERP_NONCE_FIELD])) {
             check_admin_referer(FU_SERP_NONCE_ACTION, FU_SERP_NONCE_FIELD);
             fu_handle_serp_upload();
        }
    }

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'alt_text';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=<?php echo esc_attr(FU_PAGE_SLUG); ?>&tab=alt_text" class="nav-tab <?php echo $active_tab == 'alt_text' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Atualizar Texto Alt', FU_TEXT_DOMAIN); ?>
            </a>
            <a href="?page=<?php echo esc_attr(FU_PAGE_SLUG); ?>&tab=serp" class="nav-tab <?php echo $active_tab == 'serp' ? 'nav-tab-active' : ''; ?>">
                 <?php esc_html_e('Atualizar SERP Yoast', FU_TEXT_DOMAIN); ?>
            </a>
        </h2>

        <?php if ($active_tab == 'alt_text') : ?>
            <div id="alt-text-updater" class="tab-content">
                 <h3><?php esc_html_e('Atualizar Texto Alternativo (Alt Text) de Imagens', FU_TEXT_DOMAIN); ?></h3>
                 <p><?php esc_html_e('Faça o upload de um arquivo CSV para atualizar o texto alternativo das imagens em massa.', FU_TEXT_DOMAIN); ?></p>
                 <p><?php esc_html_e('O CSV deve ter duas colunas: ', FU_TEXT_DOMAIN); ?><strong><?php esc_html_e('Image URL', FU_TEXT_DOMAIN); ?></strong> <?php esc_html_e('e', FU_TEXT_DOMAIN); ?> <strong><?php esc_html_e('Alt Text', FU_TEXT_DOMAIN); ?></strong>. <?php esc_html_e('A primeira linha (cabeçalho) será ignorada.', FU_TEXT_DOMAIN); ?></p>
                 <p><strong><?php esc_html_e('Importante:', FU_TEXT_DOMAIN); ?></strong> <?php esc_html_e('Use a URL completa da imagem como ela aparece na Biblioteca de Mídia.', FU_TEXT_DOMAIN); ?></p>

                 <form method="post" enctype="multipart/form-data">
                     <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="update_alt_text">
                     <?php wp_nonce_field(FU_ALT_NONCE_ACTION, FU_ALT_NONCE_FIELD); ?>
                     <table class="form-table">
                         <tr valign="top">
                            <th scope="row">
                                <label for="fu_alt_csv_file"><?php esc_html_e('Arquivo CSV (Alt Text)', FU_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <input type="file" id="fu_alt_csv_file" name="fu_alt_csv_file" accept=".csv" required>
                                <p class="description"><?php esc_html_e('Selecione o arquivo CSV com as URLs das imagens e os textos alternativos.', FU_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                     </table>
                     <?php submit_button(__('Processar CSV de Alt Text', FU_TEXT_DOMAIN), 'primary', 'fu_submit_alt'); ?>
                 </form>
            </div>
        <?php elseif ($active_tab == 'serp') : ?>
             <div id="serp-updater" class="tab-content">
                 <h3><?php esc_html_e('Atualizar Título e Descrição SEO (Yoast)', FU_TEXT_DOMAIN); ?></h3>

                 <?php
                 $yoast_active = is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');
                 if (!$yoast_active) {
                     echo '<div class="notice notice-error"><p>' .
                          sprintf(
                              esc_html__('Erro: O plugin %s precisa estar ativo para usar esta funcionalidade.', FU_TEXT_DOMAIN),
                              '<strong>Yoast SEO</strong>'
                          ) .
                          '</p></div>';
                 } else {
                     ?>
                     <p><?php esc_html_e('Faça upload de um arquivo CSV para atualizar os Títulos e Meta Descrições gerenciados pelo Yoast SEO.', FU_TEXT_DOMAIN); ?></p>
                     <p><?php esc_html_e('Este plugin foi desenvolvido para sobrescrever os metadados de SERP definidos pelo Yoast SEO.', FU_TEXT_DOMAIN); ?></p>
                     <p><?php esc_html_e('O CSV deve ter 3 colunas, nesta ordem:', FU_TEXT_DOMAIN); ?> <strong><?php esc_html_e('URL', FU_TEXT_DOMAIN); ?></strong>, <strong><?php esc_html_e('Novo Título', FU_TEXT_DOMAIN); ?></strong>, <strong><?php esc_html_e('Nova Descrição', FU_TEXT_DOMAIN); ?></strong>.</p>
                     <p><strong><?php esc_html_e('Importante:', FU_TEXT_DOMAIN); ?></strong> <?php esc_html_e('A primeira linha do CSV será ignorada (cabeçalho).', FU_TEXT_DOMAIN); ?></p>
                     <p><strong><?php esc_html_e('Atenção:', FU_TEXT_DOMAIN); ?></strong> <?php esc_html_e('Faça um backup do seu banco de dados antes de executar atualizações em massa.', FU_TEXT_DOMAIN); ?></p>

                     <form method="post" enctype="multipart/form-data">
                         <input type="hidden" name="<?php echo esc_attr(FU_PREFIX . 'action'); ?>" value="update_serp">
                         <?php wp_nonce_field(FU_SERP_NONCE_ACTION, FU_SERP_NONCE_FIELD); ?>
                         <table class="form-table">
                             <tr valign="top">
                                <th scope="row">
                                    <label for="fu_serp_csv_file"><?php esc_html_e('Arquivo CSV (SERP)', FU_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="fu_serp_csv_file" name="fu_serp_csv_file" accept=".csv" required>
                                    <p class="description"><?php esc_html_e('Selecione o arquivo CSV com as URLs, Títulos e Descrições.', FU_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                         </table>
                         <?php submit_button(__('Processar CSV de SERP', FU_TEXT_DOMAIN), 'primary', 'fu_submit_serp'); ?>
                     </form>
                     <?php
                 }
                 ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function fu_handle_alt_text_upload() {
    if (!isset($_FILES['fu_alt_csv_file']) || $_FILES['fu_alt_csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Erro no upload do arquivo CSV de Alt Text. Verifique as permissões ou o tamanho do arquivo.', FU_TEXT_DOMAIN) . '</p></div>';
        return;
    }

    $file = $_FILES['fu_alt_csv_file'];
    $file_path = $file['tmp_name'];
    $file_type = $file['type'];
    $allowed_mime_types = ['text/csv', 'application/vnd.ms-excel', 'text/plain', 'application/csv'];

    if (empty($file_type)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_type, $allowed_mime_types) && $file_ext !== 'csv') {
         echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Tipo de arquivo inválido ou extensão não permitida. Por favor, envie um arquivo .csv.', FU_TEXT_DOMAIN) . '</p></div>';
        return;
    }

    @set_time_limit(300);

    $updated_count = 0;
    $skipped_count = 0;
    $not_found_count = 0;
    $errors = [];
    $row_number = 0;

    setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil', 'Portuguese');

    if (($handle = fopen($file_path, 'r')) !== FALSE) {

        fgetcsv($handle, 0, ",");
        $row_number++;

        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            $row_number++;

            $original_data_string = implode(',', $data);
             if (!mb_check_encoding($original_data_string, 'UTF-8')) {
                 $data = array_map(function($item) {
                     $detected_encoding = mb_detect_encoding($item, 'UTF-8, ISO-8859-1', true);
                     if ($detected_encoding && $detected_encoding !== 'UTF-8') {
                         return mb_convert_encoding($item, 'UTF-8', $detected_encoding);
                     }
                     return $item; // Assume UTF-8 or return as is if detection fails
                 }, $data);
             }

            if (count($data) < 2) {
                $errors[] = sprintf(__('Linha %d: Número insuficiente de colunas. Verifique se o CSV está separado por vírgulas.', FU_TEXT_DOMAIN), $row_number);
                $skipped_count++;
                continue;
            }

            $image_url = isset($data[0]) ? trim($data[0]) : '';
            $alt_text = isset($data[1]) ? trim($data[1]) : '';

            if (empty($image_url)) {
                $errors[] = sprintf(__('Linha %d: URL da imagem está vazia.', FU_TEXT_DOMAIN), $row_number);
                $skipped_count++;
                continue;
            }

            $attachment_id = attachment_url_to_postid($image_url);

            if ($attachment_id) {
                if (get_post_type($attachment_id) === 'attachment') {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', wp_kses_post($alt_text)); // Sanitize alt text
                    $updated_count++;
                } else {
                    $errors[] = sprintf(__('Linha %d: URL encontrada (%s), mas não é um anexo da biblioteca de mídia.', FU_TEXT_DOMAIN), $row_number, esc_url($image_url));
                    $not_found_count++;
                }
            } else {
                $errors[] = sprintf(__('Linha %d: Imagem não encontrada na biblioteca de mídia para a URL: %s', FU_TEXT_DOMAIN), $row_number, esc_url($image_url));
                $not_found_count++;
            }
        }
        fclose($handle);

        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            esc_html(_n(
                'Processamento concluído! %d imagem atualizada.',
                'Processamento concluído! %d imagens atualizadas.',
                $updated_count,
                FU_TEXT_DOMAIN
            )) . ' ',
            $updated_count
        );
        printf(
             esc_html(_n(
                '%d URL não encontrada na biblioteca.',
                '%d URLs não encontradas na biblioteca.',
                $not_found_count,
                FU_TEXT_DOMAIN
            )) . ' ',
            $not_found_count
        );
         printf(
             esc_html(_n(
                '%d linha pulada (vazia ou formato incorreto).',
                '%d linhas puladas (vazias ou formato incorreto).',
                $skipped_count,
                FU_TEXT_DOMAIN
            )),
            $skipped_count
        );
        echo '</p></div>';

        if (!empty($errors)) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('Detalhes dos erros/avisos encontrados:', FU_TEXT_DOMAIN) . '</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . wp_kses_post($error) . '</li>'; // Use wp_kses_post for safer output
            }
            echo '</ul></div>';
        }

    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Não foi possível abrir o arquivo CSV para leitura.', FU_TEXT_DOMAIN) . '</p></div>';
    }
    @unlink($file_path);
}

function fu_handle_serp_upload() {
    if (!is_plugin_active('wordpress-seo/wp-seo.php') && !is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
         echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(esc_html__('Erro: O plugin %s precisa estar ativo para executar esta ação.', FU_TEXT_DOMAIN), '<strong>Yoast SEO</strong>') . '</p></div>';
        return;
    }

    if (!isset($_FILES['fu_serp_csv_file']) || $_FILES['fu_serp_csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Erro no upload do arquivo CSV de SERP. Código:', FU_TEXT_DOMAIN) . ' ' . esc_html($_FILES['fu_serp_csv_file']['error']) . '</p></div>';
        return;
    }

    $file = $_FILES['fu_serp_csv_file'];
    $file_path = $file['tmp_name'];
    $file_info = wp_check_filetype($file['name']);

    if (strtolower($file_info['ext']) !== 'csv') {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Tipo de arquivo inválido. Por favor, envie um arquivo .csv.', FU_TEXT_DOMAIN) . '</p></div>';
        return;
    }

    @set_time_limit(300);

    $success_count = 0;
    $error_log = [];
    $warning_log = [];
    $row_num = 0;

    setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil', 'Portuguese');

    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ',')) !== FALSE) {
            $row_num++;

            if ($row_num == 1) {
                continue;
            }

             $original_data_string = implode(',', $data);
             if (!mb_check_encoding($original_data_string, 'UTF-8')) {
                 $data = array_map(function($item) {
                     $detected_encoding = mb_detect_encoding($item, 'UTF-8, ISO-8859-1', true);
                      if ($detected_encoding && $detected_encoding !== 'UTF-8') {
                          return mb_convert_encoding($item, 'UTF-8', $detected_encoding);
                      }
                      return $item;
                 }, $data);
             }

            if (count($data) < 3) {
                $error_log[] = sprintf(__('Linha %d: Formato inválido (esperado: URL, Título, Descrição). Linha ignorada.', FU_TEXT_DOMAIN), $row_num);
                continue;
            }

            $url         = isset($data[0]) ? trim($data[0]) : '';
            $new_title   = isset($data[1]) ? trim($data[1]) : null;
            $new_desc    = isset($data[2]) ? trim($data[2]) : null;

            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                 $error_log[] = sprintf(__('Linha %d: URL inválida ou vazia (\'%s\'). Linha ignorada.', FU_TEXT_DOMAIN), $row_num, esc_html($url));
                 continue;
            }

            $post_id = url_to_postid($url);

            if ($post_id > 0) {

                $yoast_title_key = '_yoast_wpseo_title';
                $yoast_desc_key = '_yoast_wpseo_metadesc';
                $updated = false;

                if ($new_title !== null) {
                    update_post_meta($post_id, $yoast_title_key, sanitize_text_field($new_title));
                    $updated = true;
                }

                if ($new_desc !== null) {
                    update_post_meta($post_id, $yoast_desc_key, sanitize_textarea_field($new_desc));
                     $updated = true;
                }

                if($updated) {
                    $success_count++;
                } else {
                     $warning_log[] = sprintf(__('Linha %d: URL \'%s\' (ID: %d) encontrada, mas nenhum título ou descrição foi fornecido para atualização.', FU_TEXT_DOMAIN), $row_num, esc_url($url), $post_id);
                }

            } else {
                $error_log[] = sprintf(__('Linha %d: Post/Página não encontrado para a URL \'%s\'. Verifique se a URL está correta e existe no site.', FU_TEXT_DOMAIN), $row_num, esc_url($url));
            }
        }
        fclose($handle);

        if ($success_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d registro(s) de SERP atualizado(s) com sucesso.', FU_TEXT_DOMAIN), $success_count) . '</p></div>';
        } else {
            if (empty($error_log) && empty($warning_log)) {
                 echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Nenhum registro de SERP foi atualizado (nenhum dado válido encontrado no CSV ou posts correspondentes).', FU_TEXT_DOMAIN) . '</p></div>';
            }
        }

        if (!empty($warning_log)) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('Avisos encontrados:', FU_TEXT_DOMAIN) . '</strong></p><ul>';
            foreach ($warning_log as $warning) {
                echo '<li>' . wp_kses_post($warning) . '</li>';
            }
            echo '</ul></div>';
        }

        if (!empty($error_log)) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__('Erros encontrados durante o processamento:', FU_TEXT_DOMAIN) . '</strong></p><ul>';
            foreach ($error_log as $error) {
                echo '<li>' . wp_kses_post($error) . '</li>';
            }
            echo '</ul></div>';
        }

    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Não foi possível abrir o arquivo CSV para leitura.', FU_TEXT_DOMAIN) . '</p></div>';
    }
     @unlink($file_path);
}

?>
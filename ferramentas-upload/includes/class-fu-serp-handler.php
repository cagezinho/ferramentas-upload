<?php
if (!defined('ABSPATH')) {
    exit;
}

class FU_SERP_Handler {

    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const TRANSIENT_PREFIX = 'fu_admin_notice_';

    public static function handle_upload() {
        if (!isset($_POST['fu_serp_nonce_field']) || !wp_verify_nonce(sanitize_key($_POST['fu_serp_nonce_field']), 'fu_serp_nonce_action')) {
            self::set_admin_notice('error', __('Falha na verificação de segurança (Nonce inválido).', FU_TEXT_DOMAIN));
            self::redirect_back();
            return;
        }

        if (!current_user_can(FU_CAPABILITY)) {
            self::set_admin_notice('error', __('Você não tem permissão para executar esta ação.', FU_TEXT_DOMAIN));
            self::redirect_back();
            return;
        }

        if (!is_plugin_active('wordpress-seo/wp-seo.php') && !is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
             self::set_admin_notice('error', sprintf(esc_html__('Erro: O plugin %s precisa estar ativo para executar esta ação.', FU_TEXT_DOMAIN), '<strong>Yoast SEO</strong>'));
             self::redirect_back();
             return;
        }

        if (!isset($_FILES['fu_serp_csv_file']) || !is_uploaded_file($_FILES['fu_serp_csv_file']['tmp_name'])) {
            self::set_admin_notice('error', __('Nenhum arquivo foi enviado ou ocorreu um erro no upload.', FU_TEXT_DOMAIN));
            self::redirect_back();
            return;
        }

        $file = $_FILES['fu_serp_csv_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            self::set_admin_notice('error', sprintf(__('Erro no upload: Código %d.', FU_TEXT_DOMAIN), $file['error']));
            self::redirect_back();
            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            self::set_admin_notice('error', sprintf(__('Erro: O arquivo excede o tamanho máximo de %s.', FU_TEXT_DOMAIN), size_format(self::MAX_FILE_SIZE)));
            self::redirect_back();
            return;
        }

        $file_info = wp_check_filetype($file['name']);
        $allowed_exts = ['csv'];
        $allowed_mime_types = ['text/csv', 'application/csv', 'text/plain'];

        if (empty($file_info['ext']) || !in_array(strtolower($file_info['ext']), $allowed_exts, true)) {
             self::set_admin_notice('error', __('Erro: Tipo de arquivo inválido. Use apenas arquivos .csv.', FU_TEXT_DOMAIN));
             self::redirect_back();
             return;
        }
        if (!in_array($file['type'], $allowed_mime_types, true)) {
             self::set_admin_notice('warning', __('Aviso: O tipo MIME do arquivo (%s) não é o esperado para CSV. Tentando processar mesmo assim.', FU_TEXT_DOMAIN), esc_html($file['type']));
        }

        $file_path = $file['tmp_name'];
        $results = self::process_csv($file_path);

        $message = self::format_results_message($results);
        $notice_type = !empty($results['errors']) ? 'error' : (!empty($results['warnings']) ? 'warning' : 'success');
         if ($results['success_count'] == 0 && empty($results['errors']) && empty($results['warnings'])) {
             $notice_type = 'warning'; // Arquivo vazio ou sem dados válidos
             $message = __('Nenhum registro SERP foi atualizado. Verifique o conteúdo do arquivo CSV e se as URLs existem.', FU_TEXT_DOMAIN);
         }

        self::set_admin_notice($notice_type, $message);

        self::redirect_back('serp');
    }

    /**
     * Processa as linhas do arquivo CSV de SERP.
     *
     * @param string $file_path Caminho para o arquivo CSV temporário.
     * @return array Resultados do processamento.
     */
    private static function process_csv($file_path) {
        @set_time_limit(300);

        $results = [
            'success_count' => 0,
            'errors' => [],
            'warnings' => [],
            'processed_rows' => 0,
        ];
        $row_num = 0;

        $locales = ['pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil', 'Portuguese', 'en_US.UTF-8', 'en_US'];
        setlocale(LC_ALL, $locales);

        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            $header_line = fgets($handle);
            rewind($handle);
            $delimiter = strpos($header_line, ';') !== false ? ';' : ',';

            fgetcsv($handle, 0, $delimiter);
            $row_num++;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                $row_num++;
                $results['processed_rows']++;

                $data = FU_Alt_Text_Handler::ensure_utf8($data); // Reutiliza a função da outra classe

                if (count($data) < 3) {
                    $results['errors'][] = sprintf(__('Linha %d: Formato inválido (esperado: URL, Título, Descrição). Ignorada.', FU_TEXT_DOMAIN), $row_num);
                    continue;
                }

                $url         = isset($data[0]) ? trim($data[0]) : '';
                $new_title   = isset($data[1]) ? trim($data[1]) : null; // null se vazio
                $new_desc    = isset($data[2]) ? trim($data[2]) : null; // null se vazio

                if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    $results['errors'][] = sprintf(__('Linha %d: URL inválida ou vazia (\'%s\'). Ignorada.', FU_TEXT_DOMAIN), $row_num, esc_html(substr($url, 0, 50)).'...'); // Limita tamanho da URL no erro
                    continue;
                }

                $post_id = url_to_postid($url);

                if ($post_id > 0) {
                    $yoast_title_key = '_yoast_wpseo_title';
                    $yoast_desc_key = '_yoast_wpseo_metadesc';
                    $updated = false;

                    // Atualiza apenas se um novo valor foi fornecido (não nulo)
                    if ($new_title !== null) {
                        update_post_meta($post_id, $yoast_title_key, sanitize_text_field($new_title));
                        $updated = true;
                    }

                    if ($new_desc !== null) {
                        update_post_meta($post_id, $yoast_desc_key, sanitize_textarea_field($new_desc));
                        $updated = true;
                    }

                    if ($updated) {
                        $results['success_count']++;
                    } else {
                        $results['warnings'][] = sprintf(__('Linha %d: URL encontrada (%s, ID: %d), mas nenhum título ou descrição válido fornecido para atualizar.', FU_TEXT_DOMAIN), $row_num, esc_url($url), $post_id);
                    }

                } else {
                    $results['errors'][] = sprintf(__('Linha %d: Post/Página não encontrado para a URL \'%s\'. Verifique se a URL está correta.', FU_TEXT_DOMAIN), $row_num, esc_url($url));
                }
            }

            fclose($handle);

        } else {
             $results['errors'][] = __('Não foi possível abrir o arquivo CSV para leitura.', FU_TEXT_DOMAIN);
        }

        return $results;
    }

    /**
     * Formata a mensagem de resultados para o notice.
     * @param array $results Resultados do processamento.
     * @return string Mensagem formatada.
     */
    private static function format_results_message(array $results): string {
         $message = sprintf(
             esc_html(_n(
                 '%d registro SERP atualizado.',
                 '%d registros SERP atualizados.',
                 $results['success_count'],
                 FU_TEXT_DOMAIN
             )),
             $results['success_count']
         );

         if (!empty($results['warnings'])) {
            $message .= '<br><strong>' . esc_html__('Avisos:', FU_TEXT_DOMAIN) . '</strong><ul>';
            $warning_limit = 5;
            $warning_count = 0;
            foreach ($results['warnings'] as $warning) {
                 if ($warning_count >= $warning_limit) {
                    $message .= '<li>' . esc_html__('Mais avisos omitidos...', FU_TEXT_DOMAIN) . '</li>';
                    break;
                }
                $message .= '<li>' . wp_kses_post($warning) . '</li>';
                $warning_count++;
            }
            $message .= '</ul>';
         }

         if (!empty($results['errors'])) {
            $message .= '<br><strong>' . esc_html__('Erros:', FU_TEXT_DOMAIN) . '</strong><ul>';
            $error_limit = 5;
            $error_count = 0;
            foreach ($results['errors'] as $error) {
                 if ($error_count >= $error_limit) {
                    $message .= '<li>' . esc_html__('Mais erros omitidos...', FU_TEXT_DOMAIN) . '</li>';
                    break;
                }
                $message .= '<li>' . wp_kses_post($error) . '</li>'; // wp_kses_post para segurança
                $error_count++;
            }
            $message .= '</ul>';
         }

        return $message;
    }

     /**
     * Define um admin notice usando transientes.
     * @param string $type Tipo do notice (success, error, warning, info).
     * @param string $message Mensagem a ser exibida.
     */
    private static function set_admin_notice(string $type, string $message) {
        set_transient(self::TRANSIENT_PREFIX . $type, $message, 60); // expira em 60 segundos
    }


    /**
     * Redireciona o usuário de volta para a página do plugin.
     * @param string $active_tab Aba que deve ficar ativa após o redirecionamento.
     */
    private static function redirect_back(string $active_tab = 'serp') {
         $redirect_url = add_query_arg(
            [
                'page' => FU_PAGE_SLUG,
                'tab' => $active_tab,
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }
}
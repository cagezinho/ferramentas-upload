<?php
if (!defined('ABSPATH')) {
    exit;
}

class FU_Alt_Text_Handler {

    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const TRANSIENT_PREFIX = 'fu_admin_notice_';

    public static function handle_upload() {

        if (!isset($_POST['fu_alt_text_nonce_field']) || !wp_verify_nonce(sanitize_key($_POST['fu_alt_text_nonce_field']), 'fu_alt_text_nonce_action')) {
            self::set_admin_notice('error', __('Falha na verificação de segurança (Nonce inválido).', FU_TEXT_DOMAIN));
            self::redirect_back();
            return;
        }

        if (!current_user_can(FU_CAPABILITY)) {
            self::set_admin_notice('error', __('Você não tem permissão para executar esta ação.', FU_TEXT_DOMAIN));
            self::redirect_back();
            return;
        }

        if (!isset($_FILES['fu_alt_csv_file']) || !is_uploaded_file($_FILES['fu_alt_csv_file']['tmp_name'])) {
            self::set_admin_notice('error', __('Nenhum arquivo foi enviado ou ocorreu um erro no upload.', FU_TEXT_DOMAIN));
            self::redirect_back();
            return;
        }

        $file = $_FILES['fu_alt_csv_file'];

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
        $notice_type = ($results['errors'] || $results['not_found']) ? 'warning' : 'success';
        if ($results['updated_count'] == 0 && !$results['errors'] && !$results['not_found'] && $results['skipped_count'] > 0) {
             $notice_type = 'info';
        } elseif ($results['updated_count'] == 0 && !$results['errors'] && !$results['not_found'] && $results['skipped_count'] == 0) {
             $notice_type = 'warning'; 
             $message = __('Nenhuma imagem foi atualizada. Verifique o conteúdo do arquivo CSV.', FU_TEXT_DOMAIN);
        }

        self::set_admin_notice($notice_type, $message);
        self::redirect_back('alt_text');
    }

    /**
     * Processa as linhas do arquivo CSV.
     *
     * @param string $file_path Caminho para o arquivo CSV temporário.
     * @return array Resultados do processamento.
     */
    private static function process_csv($file_path) {
        @set_time_limit(300); // Tenta aumentar o limite de tempo

        $results = [
            'updated_count' => 0,
            'skipped_count' => 0,
            'not_found_count' => 0,
            'errors' => [],
            'processed_rows' => 0,
        ];
        $row_number = 0;

        $locales = ['pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil', 'Portuguese', 'en_US.UTF-8', 'en_US'];
        setlocale(LC_ALL, $locales); // Define o locale para fgetcsv

        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            $header_line = fgets($handle);
            rewind($handle);
            $delimiter = strpos($header_line, ';') !== false ? ';' : ',';
            fgetcsv($handle, 0, $delimiter);
            $row_number++;

            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                $row_number++;
                $results['processed_rows']++;

                $data = self::ensure_utf8($data);

                if (count($data) < 2 || empty(trim($data[0])) || $data[0] === null) {
                    $results['errors'][] = sprintf(__('Linha %d: Formato inválido ou URL da imagem vazia. Ignorada.', FU_TEXT_DOMAIN), $row_number);
                    $results['skipped_count']++;
                    continue;
                }

                $image_url = trim($data[0]);
                $alt_text = isset($data[1]) ? trim($data[1]) : '';

                if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                     $results['errors'][] = sprintf(__('Linha %d: URL da imagem inválida (%s). Ignorada.', FU_TEXT_DOMAIN), $row_number, esc_html($image_url));
                     $results['skipped_count']++;
                     continue;
                }

                $attachment_id = attachment_url_to_postid($image_url);

                if ($attachment_id && get_post_type($attachment_id) === 'attachment') {
                    $sanitized_alt = wp_kses_post($alt_text);

                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $sanitized_alt);
                    $results['updated_count']++;
                } else {
                    $results['errors'][] = sprintf(__('Linha %d: Imagem não encontrada ou inválida para a URL: %s', FU_TEXT_DOMAIN), $row_number, esc_url($image_url));
                    $results['not_found_count']++;
                }
            }

            fclose($handle);

        } else {
            $results['errors'][] = __('Não foi possível abrir o arquivo CSV para leitura.', FU_TEXT_DOMAIN);
        }

        return $results;
    }

     /**
     * Garante que os dados estejam em UTF-8.
     * @param array $data Array de strings.
     * @return array Array de strings em UTF-8.
     */
    private static function ensure_utf8(array $data): array {
        return array_map(function($item) {
            if (is_string($item)) {
                $encoding = mb_detect_encoding($item, mb_detect_order(), true);
                if ($encoding && $encoding !== 'UTF-8') {
                    return mb_convert_encoding($item, 'UTF-8', $encoding);
                } elseif (!$encoding && !mb_check_encoding($item, 'UTF-8')) {
                     return mb_convert_encoding($item, 'UTF-8', 'ISO-8859-1');
                }
            }
            return $item;
        }, $data);
    }

    /**
     * Formata a mensagem de resultados para o notice.
     * @param array $results Resultados do processamento.
     * @return string Mensagem formatada.
     */
    private static function format_results_message(array $results): string {
         $message = sprintf(
             esc_html(_n(
                 '%d imagem atualizada.',
                 '%d imagens atualizadas.',
                 $results['updated_count'],
                 FU_TEXT_DOMAIN
             )),
             $results['updated_count']
         );

        if ($results['not_found_count'] > 0) {
             $message .= ' ' . sprintf(
                 esc_html(_n(
                     '%d URL não encontrada.',
                     '%d URLs não encontradas.',
                     $results['not_found_count'],
                     FU_TEXT_DOMAIN
                 )),
                 $results['not_found_count']
             );
         }
         if ($results['skipped_count'] > 0) {
             $message .= ' ' . sprintf(
                 esc_html(_n(
                     '%d linha ignorada (formato inválido/URL vazia).',
                     '%d linhas ignoradas (formato inválido/URL vazia).',
                     $results['skipped_count'],
                     FU_TEXT_DOMAIN
                 )),
                 $results['skipped_count']
             );
         }

         if (!empty($results['errors'])) {
            $message .= '<br><strong>' . esc_html__('Detalhes dos erros/avisos:', FU_TEXT_DOMAIN) . '</strong><ul>';
            $error_limit = 5;
            $error_count = 0;
            foreach ($results['errors'] as $error) {
                if ($error_count >= $error_limit) {
                    $message .= '<li>' . esc_html__('Mais erros/avisos omitidos...', FU_TEXT_DOMAIN) . '</li>';
                    break;
                }
                $message .= '<li>' . wp_kses_post($error) . '</li>';
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
        set_transient(self::TRANSIENT_PREFIX . $type, $message, 60); // Expira em 60 segundos
    }

    /**
     * Redireciona o usuário de volta para a página do plugin.
     * @param string $active_tab Aba que deve ficar ativa após o redirecionamento.
     */
    private static function redirect_back(string $active_tab = 'alt_text') {
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
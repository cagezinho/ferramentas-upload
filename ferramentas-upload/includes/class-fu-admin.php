<?php
if (!defined('ABSPATH')) {
    exit;
}

class FU_Admin {

    /**
     * Registra a página do menu administrativo.
     */
    public static function add_admin_menu() {
        add_menu_page(
            __('Ferramentas Upload', FU_TEXT_DOMAIN),
            __('Ferramentas Upload', FU_TEXT_DOMAIN),
            FU_CAPABILITY, // Usa a constante de capacidade
            FU_PAGE_SLUG,
            [self::class, 'render_admin_page'], // Chama o método de renderização
            'dashicons-upload',
            25
        );
    }

    /**
     * Renderiza o conteúdo da página administrativa.
     */
    public static function render_admin_page() {
        if (!current_user_can(FU_CAPABILITY)) {
            wp_die(esc_html__('Você não tem permissão para acessar esta página.', FU_TEXT_DOMAIN));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'alt_text';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('fu_notices'); // Exibe notices registrados com add_settings_error (alternativa a transientes) ?>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . FU_PAGE_SLUG . '&tab=alt_text')); ?>"
                   class="nav-tab <?php echo $active_tab == 'alt_text' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Atualizar Texto Alt', FU_TEXT_DOMAIN); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . FU_PAGE_SLUG . '&tab=serp')); ?>"
                   class="nav-tab <?php echo $active_tab == 'serp' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Atualizar SERP Yoast', FU_TEXT_DOMAIN); ?>
                </a>
            </h2>

            <?php
            if ($active_tab == 'alt_text') {
                self::render_alt_text_form();
            } elseif ($active_tab == 'serp') {
                self::render_serp_form();
            }
            ?>

        </div>
        <?php
    }

    /**
     * Renderiza o formulário para upload de Alt Text.
     */
    private static function render_alt_text_form() {
        ?>
        <div id="alt-text-updater" class="tab-content">
            <h3><?php esc_html_e('Atualizar Texto Alternativo (Alt Text) de Imagens', FU_TEXT_DOMAIN); ?></h3>
            <p><?php esc_html_e('Faça o upload de um arquivo CSV para atualizar o texto alternativo das imagens em massa.', FU_TEXT_DOMAIN); ?></p>
            <p><?php echo wp_kses_post(__('O CSV deve ter duas colunas: <strong>Image URL</strong> e <strong>Alt Text</strong>. A primeira linha (cabeçalho) será ignorada.', FU_TEXT_DOMAIN)); ?></p>
            <p><strong><?php esc_html_e('Importante:', FU_TEXT_DOMAIN); ?></strong> <?php esc_html_e('Use a URL completa da imagem como ela aparece na Biblioteca de Mídia.', FU_TEXT_DOMAIN); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="fu_handle_alt_text_upload">
                <?php wp_nonce_field('fu_alt_text_nonce_action', 'fu_alt_text_nonce_field'); // Nonce para segurança ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="fu_alt_csv_file"><?php esc_html_e('Arquivo CSV (Alt Text)', FU_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input type="file" id="fu_alt_csv_file" name="fu_alt_csv_file" accept=".csv, text/csv" required>
                             <p class="description"><?php esc_html_e('Selecione o arquivo CSV (formato .csv, codificado em UTF-8). Máx 2MB.', FU_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Processar CSV de Alt Text', FU_TEXT_DOMAIN), 'primary', 'fu_submit_alt'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderiza o formulário para upload de SERP.
     */
    private static function render_serp_form() {
         $yoast_active = is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');

         ?>
        <div id="serp-updater" class="tab-content">
            <h3><?php esc_html_e('Atualizar Título e Descrição SEO (Yoast)', FU_TEXT_DOMAIN); ?></h3>

            <?php if (!$yoast_active) : ?>
                 <p><em><?php
                    printf(
                         esc_html__('Funcionalidade desativada. O plugin %s precisa estar ativo.', FU_TEXT_DOMAIN),
                         '<strong>Yoast SEO</strong>'
                    );
                 ?></em></p>
            <?php else : ?>
                <p><?php esc_html_e('Faça upload de um arquivo CSV para atualizar os Títulos e Meta Descrições gerenciados pelo Yoast SEO.', FU_TEXT_DOMAIN); ?></p>
                <p><?php esc_html_e('Este plugin foi desenvolvido para sobrescrever os metadados de SERP definidos pelo Yoast SEO.', FU_TEXT_DOMAIN); ?></p>
                <p><?php echo wp_kses_post(__('O CSV deve ter 3 colunas, nesta ordem: <strong>URL</strong>, <strong>Novo Título</strong>, <strong>Nova Descrição</strong>. A primeira linha (cabeçalho) será ignorada.', FU_TEXT_DOMAIN)); ?></p>
                <p><strong><?php esc_html_e('Atenção:', FU_TEXT_DOMAIN); ?></strong> <?php esc_html_e('Faça um backup do seu banco de dados antes de executar atualizações em massa.', FU_TEXT_DOMAIN); ?></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="fu_handle_serp_upload">
                    <?php wp_nonce_field('fu_serp_nonce_action', 'fu_serp_nonce_field'); // Nonce para segurança ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">
                                <label for="fu_serp_csv_file"><?php esc_html_e('Arquivo CSV (SERP)', FU_TEXT_DOMAIN); ?></label>
                            </th>
                            <td>
                                <input type="file" id="fu_serp_csv_file" name="fu_serp_csv_file" accept=".csv, text/csv" required>
                                <p class="description"><?php esc_html_e('Selecione o arquivo CSV (formato .csv, codificado em UTF-8). Máx 2MB.', FU_TEXT_DOMAIN); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Processar CSV de SERP', FU_TEXT_DOMAIN), 'primary', 'fu_submit_serp'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
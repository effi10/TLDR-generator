<?php
class TLDR_Settings_Page {
    public function add_options_page() {
        add_options_page(
            'TLDR Generator',
            'TLDR Generator',
            'manage_options',
            'tldr-generator',
            [ $this, 'create_admin_page' ]
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'tldr_options_group' );
                do_settings_sections( 'tldr-generator-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting(
            'tldr_options_group',
            'tldr_settings',
            [ $this, 'sanitize_settings' ]
        );

        add_settings_section(
            'tldr_api_settings_section',
            'Configuration de l\'API',
            null,
            'tldr-generator-admin'
        );

        add_settings_section(
            'tldr_bulk_settings_section',
            'Génération en lot',
            [ $this, 'render_bulk_section_description' ],
            'tldr-generator-admin'
        );
        
        add_settings_field(
            'provider',
            'Fournisseur LLM',
            [ $this, 'render_provider_field' ],
            'tldr-generator-admin',
            'tldr_api_settings_section'
        );
        
        add_settings_field(
            'api_key',
            'Clé API',
            [ $this, 'render_api_key_field' ],
            'tldr-generator-admin',
            'tldr_api_settings_section'
        );
        
        add_settings_field(
            'model',
            'Modèle',
            [ $this, 'render_model_field' ],
            'tldr-generator-admin',
            'tldr_api_settings_section'
        );
        
         add_settings_field(
            'prompt_template',
            'Modèle de Prompt',
            [ $this, 'render_prompt_template_field' ],
            'tldr-generator-admin',
            'tldr_api_settings_section'
        );

        add_settings_field(
            'bulk_generation',
            'Génération automatique',
            [ $this, 'render_bulk_generation_field' ],
            'tldr-generator-admin',
            'tldr_bulk_settings_section'
        );
    }
    
    public function render_provider_field() {
        $options = get_option( 'tldr_settings' );
        $provider = isset( $options['provider'] ) ? $options['provider'] : 'openai';
        ?>
        <select name="tldr_settings[provider]" id="tldr_provider">
            <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
            <option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Gemini</option>
        </select>
        <?php
    }
    
    public function render_model_field() {
        $options = get_option( 'tldr_settings' );
        $provider = isset( $options['provider'] ) ? $options['provider'] : 'openai';
        $model = isset( $options['model'] ) ? $options['model'] : '';
        $available_models = isset( $options['available_models'][$provider] ) ? $options['available_models'][$provider] : [];
        
        // Si le modèle sélectionné n'est pas dans la liste, l'ajouter temporairement
        if ( ! empty( $model ) && ! in_array( $model, $available_models ) ) {
            $available_models[] = $model;
        }
        
        ?>
        <select name="tldr_settings[model]" id="tldr_model">
            <?php foreach ( $available_models as $m ) : 
                $is_temp = ( $m === $model && ! isset( $options['available_models'][$provider] ) || ! in_array( $m, $options['available_models'][$provider] ) );
            ?>
                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $model, $m ); ?>><?php echo esc_html( $m ); ?><?php echo $is_temp ? ' (Rafraîchissez pour confirmer)' : ''; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="tldr_refresh_models" class="button">Rafraîchir la liste</button>
        <button type="button" id="tldr_test_api" class="button">Tester la connexion API</button>
        <p id="tldr_test_result"></p>
        <?php
    }
    
    // TODO: Créer les fonctions de rendu pour chaque champ.
    public function render_api_key_field() {
        $options = get_option( 'tldr_settings' );
        printf(
            '<input type="password" id="tldr_settings_api_key" name="tldr_settings[api_key]" value="%s" class="regular-text" />',
            isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : ''
        );
    }

    public function render_prompt_template_field() {
         $options = get_option( 'tldr_settings' );
         $prompt = isset( $options['prompt_template'] ) ? $options['prompt_template'] : "À partir de ce contenu, génère moi un TLDR de 4 à 6 points clés permettant de comprendre les idées principales évoquées. N'explique ce que tu fais, je veux directement le résultat sous forme de liste.\nTitre : %%titre%%.\nContenu :\n%%contenu%%.";
         ?>
         <textarea name="tldr_settings[prompt_template]" rows="8" cols="50" class="large-text"><?php echo esc_textarea( $prompt ); ?></textarea>
         <p class="description">Utilisez les variables <code>%%titre%%</code> et <code>%%contenu%%</code>.</p>
         <?php
    }
    
    /**
     * TODO: Nettoyer les données avant de les sauvegarder.
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = [];
        if ( isset( $input['provider'] ) ) {
            $sanitized_input['provider'] = in_array( $input['provider'], [ 'openai', 'gemini' ] ) ? $input['provider'] : 'openai';
        }
        if ( isset( $input['api_key'] ) ) {
            $sanitized_input['api_key'] = sanitize_text_field( $input['api_key'] );
        }
        if ( isset( $input['model'] ) ) {
            $sanitized_input['model'] = sanitize_text_field( $input['model'] );
        }
        if ( isset( $input['prompt_template'] ) ) {
            $sanitized_input['prompt_template'] = sanitize_textarea_field( $input['prompt_template'] );
        }
        // Pour available_models, on le gérera via AJAX, pas dans sanitize
        return $sanitized_input;
    }

    public function render_bulk_section_description() {
        echo '<p>Générez automatiquement les résumés TLDR manquants pour tous vos articles existants.</p>';
    }

    public function render_bulk_generation_field() {
        // Compter les articles sans TLDR
        global $wpdb;
        $posts_without_tldr = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ", '_tldr_summary_text' ) );

        ?>
        <div id="tldr-bulk-generation">
            <p><strong><?php echo $posts_without_tldr; ?></strong> articles sans résumé TLDR trouvés.</p>
            <button type="button" id="tldr_bulk_generate" class="button button-primary" <?php echo $posts_without_tldr == 0 ? 'disabled' : ''; ?>>
                Générer tous les TLDR manquants
            </button>
            <div id="tldr-bulk-progress" style="display: none; margin-top: 15px;">
                <div style="background: #f1f1f1; border-radius: 3px; padding: 3px;">
                    <div id="tldr-progress-bar" style="background: #0073aa; height: 20px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p id="tldr-progress-text">Préparation...</p>
            </div>
            <div id="tldr-bulk-results" style="margin-top: 15px;"></div>
        </div>
        <?php
    }

    public function enqueue_scripts( $hook ) {
        // Charger le JS/CSS uniquement sur la page de réglages
        if ( 'settings_page_tldr-generator' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'tldr-admin-js', TLDR_GENERATOR_URL . 'includes/admin/js/tldr-settings.js', [ 'jquery' ], TLDR_GENERATOR_VERSION, true );
        wp_localize_script( 'tldr-admin-js', 'tldr_ajax', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'tldr_ajax_nonce' ),
        ] );
    }
}
<?php
class TLDR_Post_Handler {

    private $meta_key = '_tldr_summary_text';

    /**
     * Ajoute la Meta Box dans l'éditeur d'articles.
     */
    public function add_tldr_meta_box() {
        $options = get_option('tldr_settings');
        // Ne rien afficher si l'API n'est pas configurée.
        //if ( empty($options['api_key']) ) {
          //  return;
        //}

        add_meta_box(
            'tldr_summary_meta_box',
            __( 'Résumé TLDR', 'tldr-generator' ),
            [ $this, 'render_meta_box_content' ],
            get_post_types_by_support( 'editor' ), // S'affiche sur tous les types de contenu compatibles Gutenberg
            'normal',
            'high'
        );
    }

    /**
     * Affiche le contenu de la Meta Box.
     */
    public function render_meta_box_content( $post ) {
        wp_nonce_field( 'tldr_meta_box_save', 'tldr_meta_box_nonce' );
        $tldr_text = get_post_meta( $post->ID, $this->meta_key, true );
        ?>
        <p><?php _e( 'Ce résumé sera généré ou mis à jour automatiquement à la sauvegarde si le titre ou le contenu a changé.', 'tldr-generator' ); ?></p>
        <textarea name="tldr_summary_field" id="tldr_summary_field" rows="6" style="width:100%;"><?php echo esc_textarea( $tldr_text ); ?></textarea>
        <p>
            <input type="checkbox" name="tldr_force_regenerate" id="tldr_force_regenerate" value="1" />
            <label for="tldr_force_regenerate"><?php _e( 'Forcer la régénération à la sauvegarde', 'tldr-generator' ); ?></label>
        </p>
        <?php
    }

    /**
     * Gère la sauvegarde du post et la génération du TLDR.
     */
    public function handle_post_save( $post_id, $post ) {
        // 1. Vérifications de sécurité
        if ( ! isset( $_POST['tldr_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['tldr_meta_box_nonce'], 'tldr_meta_box_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Récupérer $old_post tôt pour éviter les undefined
        $old_post = get_post( $post_id );

        // Débogage : Log entrée dans handle_post_save
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $old_status = $old_post ? $old_post->post_status : 'null';
            error_log( "[TLDR Debug] handle_post_save - Post ID: $post_id, Status: {$post->post_status}, Old Status: $old_status" );
        }

        // Garde si $old_post est null (rare, mais possible en initial insert)
        if ( ! $old_post ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "[TLDR Debug] handle_post_save - Old Post is null, skipping generation" );
            }
            return;
        }

        // 2. Sauvegarde du champ manuel
        if ( isset( $_POST['tldr_summary_field'] ) ) {
            $raw_value = $_POST['tldr_summary_field'];
            $sanitized_value = sanitize_textarea_field( $raw_value );
            
            // Skip update si valeur vide ET meta existant non vide (pour préserver TLDR auto)
            $existing_tldr = get_post_meta( $post_id, $this->meta_key, true );
            if ( empty( $sanitized_value ) && ! empty( $existing_tldr ) ) {
                // Débogage : Log skip overwrite
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "[TLDR Debug] handle_post_save Manual Save - Skipped overwrite of non-empty TLDR with empty value" );
                }
            } else {
                update_post_meta( $post_id, $this->meta_key, $sanitized_value );

                // Débogage : Log sauvegarde manuelle
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "[TLDR Debug] handle_post_save Manual Save - Post ID: $post_id, Raw Value Length: " . strlen( $raw_value ) . ", Sanitized Value Length: " . strlen( $sanitized_value ) );
                    $verified_value = get_post_meta( $post_id, $this->meta_key, true );
                    error_log( "[TLDR Debug] handle_post_save Manual Save - Verified After Update: " . ( empty( $verified_value ) ? 'Empty' : 'Length ' . strlen( $verified_value ) ) );
                }
            }
        }

        // 3. Condition de génération
        $content_changed = $post->post_content !== $old_post->post_content;
        $title_changed = $post->post_title !== $old_post->post_title;
        $force_regenerate = isset( $_POST['tldr_force_regenerate'] ) && $_POST['tldr_force_regenerate'] === '1';
        $is_new_post = in_array( $old_post->post_status, [ 'new', 'auto-draft' ] ) && empty( get_post_meta( $post_id, $this->meta_key, true ) ) && ( ! empty( $post->post_title ) || ! empty( $post->post_content ) );

        // Débogage : Log conditions
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] handle_post_save Conditions - New Post: " . var_export( $is_new_post, true ) . ", Force: " . var_export( $force_regenerate, true ) . ", Changed (Title/Content): " . var_export( $title_changed || $content_changed, true ) . ", TLDR Empty: " . var_export( empty( get_post_meta( $post_id, $this->meta_key, true ) ), true ) );
        }

        if ( $is_new_post || $force_regenerate || ( empty( get_post_meta( $post_id, $this->meta_key, true ) ) && ( $content_changed || $title_changed ) ) ) {
            $this->trigger_tldr_generation( $post );
        }
    }

    /**
     * Génère automatiquement le TLDR lors de la publication initiale.
     */
    public function on_post_publish( $new_status, $old_status, $post ) {
        if ( $new_status === 'publish' && $old_status !== 'publish' && post_type_supports( $post->post_type, 'editor' ) ) {
            // Débogage : Log entrée dans on_post_publish
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "[TLDR Debug] on_post_publish - Post ID: {$post->ID}, Old Status: $old_status, New Status: $new_status, TLDR Empty: " . var_export( empty( get_post_meta( $post->ID, $this->meta_key, true ) ), true ) );
            }

            if ( empty( get_post_meta( $post->ID, $this->meta_key, true ) ) ) {
                $this->trigger_tldr_generation( $post );
            }
        }
    }

    /**
     * Déclenche la génération du TLDR.
     */
    private function trigger_tldr_generation( $post ) {
        $api_handler = new TLDR_API_Handler();

        // Débogage : Log entrée dans trigger_tldr_generation
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] trigger_tldr_generation - Post ID: {$post->ID}, Title: {$post->post_title}, Content Length: " . strlen( $post->post_content ) );
        }

        // 1. Nettoyer le contenu
        $content = apply_filters( 'the_content', $post->post_content );
        $cleaned_content = wp_strip_all_tags( $content );

        // Débogage : Log contenu nettoyé
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] trigger_tldr_generation - Cleaned Content Length: " . strlen( $cleaned_content ) );
        }

        // 2. Appeler l'API
        $generated_tldr = $api_handler->generate_summary( $post->post_title, $cleaned_content );

        // Débogage : Log résultat API
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_wp_error( $generated_tldr ) ) {
                error_log( "[TLDR Debug] trigger_tldr_generation - API Error: " . $generated_tldr->get_error_message() );
            } else {
                error_log( "[TLDR Debug] trigger_tldr_generation - Generated TLDR Length: " . strlen( $generated_tldr ) );
            }
        }

        // 3. Mettre à jour le post meta
        if ( ! is_wp_error( $generated_tldr ) && ! empty( $generated_tldr ) ) {
            update_post_meta( $post->ID, $this->meta_key, $generated_tldr );
            error_log( "[TLDR Debug] trigger_tldr_generation - Sauvegarde du TLDR: " . strlen( $generated_tldr ) );
            // Forcer rafraîchissement du cache meta
            wp_cache_delete( $post->ID, 'post_meta' );

            // Débogage : Log mise à jour meta et vérification post-update
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $saved_tldr = get_post_meta( $post->ID, $this->meta_key, true );
                error_log( "[TLDR Debug] trigger_tldr_generation - Meta Updated, Verified Value Length: " . strlen( $saved_tldr ) . ", Empty After Update: " . var_export( empty( $saved_tldr ), true ) );
            }
        }
    }

    /**
     * Version publique de trigger_tldr_generation pour l'usage en lot
     */
    public function trigger_tldr_generation_public( $post ) {
        $this->trigger_tldr_generation( $post );
    }
}
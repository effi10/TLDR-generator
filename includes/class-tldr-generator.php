<?php
class TLDR_Generator {

    public function run() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_block_hooks();
    }

    private function load_dependencies() {
        require_once TLDR_GENERATOR_PATH . 'includes/admin/class-tldr-settings-page.php';
        require_once TLDR_GENERATOR_PATH . 'includes/class-tldr-api-handler.php';
        require_once TLDR_GENERATOR_PATH . 'includes/class-tldr-post-handler.php';
    }

    private function define_admin_hooks() {
        $settings_page = new TLDR_Settings_Page();
        $post_handler = new TLDR_Post_Handler();

        add_action( 'admin_menu', [ $settings_page, 'add_options_page' ] );
        add_action( 'admin_init', [ $settings_page, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $settings_page, 'enqueue_scripts' ] );

        add_action( 'add_meta_boxes', [ $post_handler, 'add_tldr_meta_box' ] );
        add_action( 'save_post', [ $post_handler, 'handle_post_save' ], 10, 2 );

        // Détection de la publication initiale pour génération automatique du TLDR
        add_action( 'transition_post_status', [ $post_handler, 'on_post_publish' ], 10, 3 );

        // AJAX pour rafraîchir modèles et test API
        add_action( 'wp_ajax_tldr_refresh_models', [ $this, 'ajax_refresh_models' ] );
        add_action( 'wp_ajax_tldr_test_api', [ $this, 'ajax_test_api' ] );
        add_action( 'wp_ajax_tldr_bulk_generate', [ $this, 'ajax_bulk_generate' ] );
    }

    private function define_public_hooks() {
        // Hooks pour le côté public (si nécessaire)
    }
    
    private function define_block_hooks() {
        add_action( 'init', [ $this, 'register_tldr_block' ] );
    }

    public function register_tldr_block() {
        // Enregistrer le script éditeur (ES5, sans build)
        wp_register_script(
            'tldr-generator-summary-editor',
            TLDR_GENERATOR_URL . 'includes/admin/blocks/tldr-summary/index.js',
            [ 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ],
            TLDR_GENERATOR_VERSION,
            true
        );

        // Enregistrer le bloc à partir des métadonnées en liant le handle du script éditeur
        register_block_type(
            TLDR_GENERATOR_PATH . 'includes/admin/blocks/tldr-summary',
            [ 'editor_script' => 'tldr-generator-summary-editor' ]
        );
    }
    
    public function ajax_refresh_models() {
        check_ajax_referer( 'tldr_ajax_nonce', 'nonce' );
        $provider = sanitize_text_field( $_POST['provider'] );
        $api_key = sanitize_text_field( $_POST['api_key'] );
        
        // Débogage : Logger les paramètres
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[TLDR Debug] Refresh Models - Provider: ' . $provider . ', API Key: [masked]' );
        }
        
        $models = [];
        if ( $provider === 'openai' ) {
            $response = wp_remote_get( 'https://api.openai.com/v1/models', [
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ]
            ] );
            
            // Débogage : Logger la réponse
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[TLDR Debug] OpenAI Models - Response Code: ' . wp_remote_retrieve_response_code( $response ) );
                error_log( '[TLDR Debug] OpenAI Models - Response Body: ' . print_r( wp_remote_retrieve_body( $response ), true ) );
                if ( is_wp_error( $response ) ) {
                    error_log( '[TLDR Debug] OpenAI Models - Error: ' . print_r( $response->get_error_messages(), true ) );
                }
            }
            
            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['data'] ) ) {
                    foreach ( $body['data'] as $model ) {
                        $models[] = $model['id'];
                    }
                } else {
                    wp_send_json_error( 'Format de réponse inattendu depuis OpenAI.' );
                }
            } else {
                $error_msg = is_wp_error( $response ) ? implode( '; ', $response->get_error_messages() ) : 'Code ' . wp_remote_retrieve_response_code( $response );
                wp_send_json_error( 'Échec récupération modèles OpenAI : ' . $error_msg );
            }
        } elseif ( $provider === 'gemini' ) {
            $response = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key );
            
            // Débogage similaire pour Gemini
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[TLDR Debug] Gemini Models - Response Code: ' . wp_remote_retrieve_response_code( $response ) );
                error_log( '[TLDR Debug] Gemini Models - Response Body: ' . print_r( wp_remote_retrieve_body( $response ), true ) );
                if ( is_wp_error( $response ) ) {
                    error_log( '[TLDR Debug] Gemini Models - Error: ' . print_r( $response->get_error_messages(), true ) );
                }
            }
            
            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['models'] ) ) {
                    foreach ( $body['models'] as $model ) {
                        $models[] = $model['name'];
                    }
                } else {
                    wp_send_json_error( 'Format de réponse inattendu depuis Gemini.' );
                }
            } else {
                $error_msg = is_wp_error( $response ) ? implode( '; ', $response->get_error_messages() ) : 'Code ' . wp_remote_retrieve_response_code( $response );
                wp_send_json_error( 'Échec récupération modèles Gemini : ' . $error_msg );
            }
        }
        
        $options = get_option( 'tldr_settings' );
        if ( ! isset( $options['available_models'] ) ) {
            $options['available_models'] = [];
        }
        $options['available_models'][$provider] = $models;
        update_option( 'tldr_settings', $options );
        
        wp_send_json_success( $models );
    }
    
    public function ajax_test_api() {
        check_ajax_referer( 'tldr_ajax_nonce', 'nonce' );
        $provider = sanitize_text_field( $_POST['provider'] );
        $api_key = sanitize_text_field( $_POST['api_key'] );
        
        $api_handler = new TLDR_API_Handler();
        $result = $api_handler->test_connection( $provider, $api_key );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( 'Connexion OK' );
        }
    }

    public function ajax_bulk_generate() {
        check_ajax_referer( 'tldr_ajax_nonce', 'nonce' );
        
        $batch_size = 5; // Traiter 5 articles par lot
        $offset = intval( $_POST['offset'] ?? 0 );
        
        // Récupérer les articles sans TLDR
        global $wpdb;
        $posts = $wpdb->get_results( $wpdb->prepare( "
            SELECT p.ID, p.post_title, p.post_content 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ORDER BY p.ID ASC
            LIMIT %d OFFSET %d
        ", '_tldr_summary_text', $batch_size, $offset ) );

        $total_posts = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ", '_tldr_summary_text' ) );

        $results = [];
        $post_handler = new TLDR_Post_Handler();
        
        foreach ( $posts as $post_data ) {
            $post = get_post( $post_data->ID );
            if ( $post ) {
                // Utiliser la méthode existante pour générer le TLDR
                $post_handler->trigger_tldr_generation_public( $post );
                $results[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'success'
                ];
            }
        }

        wp_send_json_success( [
            'processed' => count( $results ),
            'total' => $total_posts,
            'offset' => $offset + $batch_size,
            'results' => $results,
            'completed' => ( $offset + $batch_size ) >= $total_posts
        ] );
    }
}
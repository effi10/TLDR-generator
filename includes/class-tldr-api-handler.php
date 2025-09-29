<?php
class TLDR_API_Handler {

    /**
     * Génère le résumé en appelant l'API configurée.
     * @return string|WP_Error
     */
    public function generate_summary( $title, $content ) {
        $options = get_option( 'tldr_settings' );

        // Débogage : Log configuration
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] generate_summary - Provider: {$options['provider']}, Model: {$options['model']}, Has Key: " . ( ! empty( $options['api_key'] ) ? 'Yes' : 'No' ) . ", Has Prompt: " . ( ! empty( $options['prompt_template'] ) ? 'Yes' : 'No' ) );
        }

        if ( empty( $options['api_key'] ) || empty( $options['prompt_template'] ) || empty( $options['provider'] ) || empty( $options['model'] ) ) {
            return new WP_Error( 'config_error', __( 'Configuration incomplète.', 'tldr-generator' ) );
        }

        $prompt = str_replace(
            [ '%%titre%%', '%%contenu%%' ],
            [ $title, $content ],
            $options['prompt_template']
        );

        // Débogage : Log prompt généré
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] generate_summary - Generated Prompt Length: " . strlen( $prompt ) );
        }

        if ( $options['provider'] === 'openai' ) {
            return $this->call_openai_api( $prompt, $options );
        } elseif ( $options['provider'] === 'gemini' ) {
            return $this->call_gemini_api( $prompt, $options );
        }

        return new WP_Error( 'provider_error', __( 'Fournisseur non supporté.', 'tldr-generator' ) );
    }

    private function call_openai_api( $prompt, $options ) {
        $api_key = $options['api_key'];
        $model = $options['model'];
        $api_url = 'https://api.openai.com/v1/chat/completions';

        // Débogage : Log appel API
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] call_openai_api - Model: $model, URL: $api_url" );
        }

        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode( [
                'model'    => $model,
                'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
            'timeout' => 60,
        ] );

        // Débogage : Log réponse
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] call_openai_api - Response Code: " . wp_remote_retrieve_response_code( $response ) );
            error_log( "[TLDR Debug] call_openai_api - Response Body: " . print_r( wp_remote_retrieve_body( $response ), true ) );
            if ( is_wp_error( $response ) ) {
                error_log( "[TLDR Debug] call_openai_api - Error: " . print_r( $response->get_error_messages(), true ) );
            }
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['choices'][0]['message']['content'] ?? '';
    }

    private function call_gemini_api( $prompt, $options ) {
        $api_key = $options['api_key'];
        $model = $options['model'];
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        // Débogage : Log appel API
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] call_gemini_api - Model: $model, URL: $api_url" );
        }

        $response = wp_remote_post( $api_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [ 'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ] ] ),
            'timeout' => 60,
        ] );

        // Débogage : Log réponse
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[TLDR Debug] call_gemini_api - Response Code: " . wp_remote_retrieve_response_code( $response ) );
            error_log( "[TLDR Debug] call_gemini_api - Response Body: " . print_r( wp_remote_retrieve_body( $response ), true ) );
            if ( is_wp_error( $response ) ) {
                error_log( "[TLDR Debug] call_gemini_api - Error: " . print_r( $response->get_error_messages(), true ) );
            }
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    public function test_connection( $provider, $api_key ) {
        if ( $provider === 'openai' ) {
            $response = wp_remote_get( 'https://api.openai.com/v1/models', [
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key ]
            ] );
            
            // Débogage : Logger la réponse complète
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[TLDR Debug] OpenAI Test Connection - Response Code: ' . wp_remote_retrieve_response_code( $response ) );
                error_log( '[TLDR Debug] OpenAI Test Connection - Response Body: ' . print_r( wp_remote_retrieve_body( $response ), true ) );
                if ( is_wp_error( $response ) ) {
                    error_log( '[TLDR Debug] OpenAI Test Connection - Error: ' . print_r( $response->get_error_messages(), true ) );
                }
            }
            
            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'api_error', __( 'Échec de la connexion à OpenAI : ' . implode( '; ', $response->get_error_messages() ), 'tldr-generator' ) );
            } elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Réponse inattendue de l\'API.', 'tldr-generator' );
                return new WP_Error( 'api_error', __( 'Échec de la connexion à OpenAI (code ' . wp_remote_retrieve_response_code( $response ) . ') : ' . $error_msg, 'tldr-generator' ) );
            }
        } elseif ( $provider === 'gemini' ) {
            // ... (similaire pour Gemini, mais on se concentre sur OpenAI pour l'instant)
            $response = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[TLDR Debug] Gemini Test Connection - Response Code: ' . wp_remote_retrieve_response_code( $response ) );
                error_log( '[TLDR Debug] Gemini Test Connection - Response Body: ' . print_r( wp_remote_retrieve_body( $response ), true ) );
                if ( is_wp_error( $response ) ) {
                    error_log( '[TLDR Debug] Gemini Test Connection - Error: ' . print_r( $response->get_error_messages(), true ) );
                }
            }
            
            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'api_error', __( 'Échec de la connexion à Gemini : ' . implode( '; ', $response->get_error_messages() ), 'tldr-generator' ) );
            } elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Réponse inattendue de l\'API.', 'tldr-generator' );
                return new WP_Error( 'api_error', __( 'Échec de la connexion à Gemini (code ' . wp_remote_retrieve_response_code( $response ) . ') : ' . $error_msg, 'tldr-generator' ) );
            }
        } else {
            return new WP_Error( 'provider_error', __( 'Fournisseur non supporté.', 'tldr-generator' ) );
        }
        return true;
    }
}
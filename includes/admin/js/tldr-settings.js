jQuery( document ).ready( function( $ ) {
    // Rafraîchissement automatique au changement de provider
    $( '#tldr_provider' ).on( 'change', function() {
        $( '#tldr_refresh_models' ).trigger( 'click' );
    } );

    // Rafraîchir modèles
    $( '#tldr_refresh_models' ).on( 'click', function() {
        var provider = $( '#tldr_provider' ).val();
        var api_key = $( '#tldr_settings_api_key' ).val(); // Assurer l'ID correct
        
        $.ajax( {
            url: tldr_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'tldr_refresh_models',
                nonce: tldr_ajax.nonce,
                provider: provider,
                api_key: api_key
            },
            success: function( response ) {
                console.log( '[TLDR Debug] Refresh Models Response:', response ); // Log pour console navigateur
                if ( response.success ) {
                    var select = $( '#tldr_model' );
                    select.empty();
                    $.each( response.data, function( i, model ) {
                        select.append( '<option value="' + model + '">' + model + '</option>' );
                    } );
                    alert( 'Liste des modèles mise à jour !' );
                } else {
                    alert( 'Erreur lors du rafraîchissement : ' + ( response.data || 'Inconnue' ) );
                }
            }
        } );
    } );
    
    // Test API
    $( '#tldr_test_api' ).on( 'click', function() {
        var provider = $( '#tldr_provider' ).val();
        var api_key = $( '#tldr_settings_api_key' ).val(); // Assurer l'ID correct
        
        $.ajax( {
            url: tldr_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'tldr_test_api',
                nonce: tldr_ajax.nonce,
                provider: provider,
                api_key: api_key
            },
            success: function( response ) {
                if ( response.success ) {
                    $( '#tldr_test_result' ).text( response.data ).css( 'color', 'green' );
                } else {
                    $( '#tldr_test_result' ).text( 'Erreur : ' + ( response.data || 'Inconnue' ) ).css( 'color', 'red' );
                }
            }
        } );
    } );

    // Génération en lot
    $( '#tldr_bulk_generate' ).on( 'click', function() {
        var button = $( this );
        var progressDiv = $( '#tldr-bulk-progress' );
        var progressBar = $( '#tldr-progress-bar' );
        var progressText = $( '#tldr-progress-text' );
        var resultsDiv = $( '#tldr-bulk-results' );
        
        button.prop( 'disabled', true ).text( 'Génération en cours...' );
        progressDiv.show();
        resultsDiv.empty();
        
        var offset = 0;
        var totalProcessed = 0;
        var totalPosts = 0;
        
        function processBatch() {
            $.ajax( {
                url: tldr_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tldr_bulk_generate',
                    nonce: tldr_ajax.nonce,
                    offset: offset
                },
                success: function( response ) {
                    if ( response.success ) {
                        var data = response.data;
                        totalProcessed += data.processed;
                        totalPosts = data.total;
                        offset = data.offset;
                        
                        // Mettre à jour la barre de progression
                        var percentage = totalPosts > 0 ? Math.round( ( totalProcessed / totalPosts ) * 100 ) : 100;
                        progressBar.css( 'width', percentage + '%' );
                        progressText.text( totalProcessed + ' / ' + totalPosts + ' articles traités (' + percentage + '%)' );
                        
                        // Afficher les résultats
                        data.results.forEach( function( result ) {
                            resultsDiv.append( '<div style="color: green;">✓ ' + result.title + '</div>' );
                        } );
                        
                        if ( data.completed ) {
                            button.prop( 'disabled', false ).text( 'Générer tous les TLDR manquants' );
                            progressText.text( 'Terminé ! ' + totalProcessed + ' articles traités.' );
                            setTimeout( function() {
                                location.reload(); // Recharger pour mettre à jour le compteur
                            }, 2000 );
                        } else {
                            // Continuer avec le lot suivant
                            setTimeout( processBatch, 1000 ); // Pause de 1s entre les lots
                        }
                    } else {
                        button.prop( 'disabled', false ).text( 'Générer tous les TLDR manquants' );
                        progressText.text( 'Erreur : ' + ( response.data || 'Inconnue' ) );
                    }
                },
                error: function() {
                    button.prop( 'disabled', false ).text( 'Générer tous les TLDR manquants' );
                    progressText.text( 'Erreur de connexion' );
                }
            } );
        }
        
        processBatch();
    } );
} );

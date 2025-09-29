( function( wp ) {
    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var ServerSideRender = wp.serverSideRender;
    var useBlockProps = wp.blockEditor.useBlockProps;

    registerBlockType( 'tldr-generator/summary', {
        // Les propriétés telles que title, icon, category, et supports sont héritées de block.json.
        // Seules les fonctions de comportement côté client (edit, save) sont définies ici.
        edit: function( props ) {
            var blockProps = useBlockProps();
            return el( 'div', blockProps, 
                el( ServerSideRender, { 
                    block: 'tldr-generator/summary', 
                    attributes: props.attributes,
                    EmptyResponsePlaceholder: function() {
                        return el( 'p', {}, __( 'Le résumé TLDR s\'affichera ici. Sauvegardez l\'article pour le générer.', 'tldr-generator' ) );
                    }
                } ) 
            );
        },
        save: function() {
            return null; // rendu côté serveur via render.php
        }
    } );
} )( window.wp );



import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, Text } from '@wordpress/components';
import './editor.scss';

export default function Edit( { attributes, setAttributes, name } ) {
    const blockProps = useBlockProps();

    return (
        <>
            {/* Les contrôles dans la barre latérale sont gérés par "supports" dans block.json */}
            <InspectorControls>
                <PanelBody title={ __( 'Information', 'tldr-generator' ) }>
                    <p>{ __( 'Les options de style (couleurs, typographie, etc.) sont disponibles dans l\'onglet "Styles" de la barre latérale.', 'tldr-generator' ) }</p>
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <ServerSideRender
                    block={ name }
                    attributes={ attributes }
                    EmptyResponsePlaceholder={ () => (
                        <p>{ __( 'Le résumé TLDR s\'affichera ici. Sauvegardez l\'article pour le générer.', 'tldr-generator' ) }</p>
                    ) }
                />
            </div>
        </>
    );
}
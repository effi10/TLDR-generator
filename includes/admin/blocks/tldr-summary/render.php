<?php
$tldr_text = get_post_meta( get_the_ID(), '_tldr_summary_text', true );

if ( empty( $tldr_text ) ) {
    // Ne rien afficher si le résumé est vide.
    return '';
}

$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
    <?php echo wp_kses_post( nl2br( $tldr_text ) ); ?>
</div>
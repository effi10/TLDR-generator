<?php
/**
 * Plugin Name:       TLDR Generator
 * Description:       Génère des résumés TLDR pour les articles via une API LLM et les affiche avec un bloc Gutenberg.
 * Version:           1.0.0
 * Author:            Cédric et Ares en live
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tldr-generator
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'TLDR_GENERATOR_VERSION', '1.0.0' );
define( 'TLDR_GENERATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'TLDR_GENERATOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Chargement de la classe principale du plugin.
 */
require_once TLDR_GENERATOR_PATH . 'includes/class-tldr-generator.php';

/**
 * Initialise le plugin.
 */
function tldr_generator_run() {
    $plugin = new TLDR_Generator();
    $plugin->run();
}
tldr_generator_run();
<?php
/**
 * Plugin Name:  CF7 Abholtermin-Picker
 * Description:  Datepicker für Contact Form 7 mit Abhollogik (24h Vorlaufzeit).
 * Version:      1.0.0
 * Author:       Enes Saran
 * Text Domain:  cf7-abholtermin-picker
 */

defined( 'ABSPATH' ) || exit;

// Abhol-Logik laden
require_once plugin_dir_path( __FILE__ ) . 'includes/pickup-logic.php';

/**
 * Assets (JS + CSS) nur laden, wenn CF7 aktiv ist.
 */
add_action( 'wp_enqueue_scripts', function () {

    if ( ! function_exists( 'wpcf7' ) ) {
        return;
    }

    // Flatpickr (Datepicker-Library) aus CDN laden
    wp_enqueue_style(
        'flatpickr-css',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        '4.6.13'
    );
    wp_enqueue_script(
        'flatpickr-js',
        'https://cdn.jsdelivr.net/npm/flatpickr',
        [],
        '4.6.13',
        true
    );

    // Flatpickr Deutsch-Locale
    wp_enqueue_script(
        'flatpickr-de',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/de.js',
        [ 'flatpickr-js' ],
        '4.6.13',
        true
    );

    // Plugin-eigenes JS
    wp_enqueue_script(
        'cf7-abholpicker-js',
        plugin_dir_url( __FILE__ ) . 'assets/datepicker.js',
        [ 'flatpickr-de' ],
        '1.0.0',
        true
    );

    // Plugin-eigenes CSS
    wp_enqueue_style(
        'cf7-abholpicker-css',
        plugin_dir_url( __FILE__ ) . 'assets/datepicker.css',
        [],
        '1.0.0'
    );

    // PHP-Daten an JS übergeben
    $pickup_data = cf7ap_get_pickup_config();

    wp_localize_script( 'cf7-abholpicker-js', 'CF7AbholPicker', $pickup_data );
} );
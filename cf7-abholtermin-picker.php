<?php
/**
 * Plugin Name:  CF7 Abholtermin-Picker
 * Description:  Datepicker für Contact Form 7 mit Abhollogik (24h Vorlaufzeit).
 * Version:      1.1.0
 * Author:       Enes Saran
 * Text Domain:  cf7-abholtermin-picker
 */

defined( 'ABSPATH' ) || exit;

// Konstanten
define( 'CF7AP_VERSION', '1.1.0' );
define( 'CF7AP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CF7AP_URL', plugin_dir_url( __FILE__ ) );

// Include-Dateien laden
require_once CF7AP_PATH . 'includes/pickup-logic.php';
require_once CF7AP_PATH . 'includes/admin.php';

/**
 * Frontend-Assets (JS + CSS) laden — nur wenn CF7 aktiv ist.
 */
add_action( 'wp_enqueue_scripts', function () {

    if ( ! function_exists( 'wpcf7' ) ) {
        return;
    }

    // Flatpickr (Datepicker-Library) aus CDN
    wp_enqueue_style(
        'flatpickr-css',
        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
        [],
        '4.6.13'
    );
    wp_enqueue_script(
        'flatpickr-js',
        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
        [],
        '4.6.13',
        true
    );
    wp_enqueue_script(
        'flatpickr-de',
        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/de.js',
        [ 'flatpickr-js' ],
        '4.6.13',
        true
    );

    // Plugin-eigene Assets
    wp_enqueue_script(
        'cf7ap-js',
        CF7AP_URL . 'assets/datepicker.js',
        [ 'flatpickr-de' ],
        CF7AP_VERSION,
        true
    );
    wp_enqueue_style(
        'cf7ap-css',
        CF7AP_URL . 'assets/datepicker.css',
        [],
        CF7AP_VERSION
    );

    // Konfiguration an JavaScript übergeben
    wp_localize_script( 'cf7ap-js', 'CF7AbholPicker', cf7ap_get_pickup_config() );
} );

/**
 * E-Mail-Versand: ISO-Datum (YYYY-MM-DD) in deutsches Format umwandeln (DD.MM.YYYY).
 * Wirkt auf alle Felder mit ISO-Datum als Wert.
 */
add_filter( 'wpcf7_posted_data', function ( $posted_data ) {

    if ( ! is_array( $posted_data ) ) {
        return $posted_data;
    }

    foreach ( $posted_data as $key => $value ) {
        if ( is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            $d = DateTime::createFromFormat( 'Y-m-d', $value );
            if ( $d && $d->format( 'Y-m-d' ) === $value ) {
                $posted_data[ $key ] = $d->format( 'd.m.Y' );
            }
        }
    }

    return $posted_data;
} );

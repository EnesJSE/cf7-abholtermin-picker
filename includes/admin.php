<?php
/**
 * Admin-UI: Verwaltung von Feiertagen und Betriebsferien.
 * Erreichbar unter: WP-Admin → Einstellungen → CF7 Abholtermin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menüpunkt registrieren
 */
add_action( 'admin_menu', function () {
    add_options_page(
        'CF7 Abholtermin',
        'CF7 Abholtermin',
        'manage_options',
        'cf7-abholtermin',
        'cf7ap_render_admin_page'
    );
} );

/**
 * Settings registrieren
 */
add_action( 'admin_init', function () {

    register_setting( 'cf7ap_settings_group', 'cf7ap_feiertage', [
        'type'              => 'array',
        'sanitize_callback' => 'cf7ap_sanitize_feiertage',
        'default'           => [],
    ] );

    register_setting( 'cf7ap_settings_group', 'cf7ap_betriebsferien', [
        'type'              => 'array',
        'sanitize_callback' => 'cf7ap_sanitize_betriebsferien',
        'default'           => [],
    ] );
} );

/**
 * Sanitizer für Feiertage
 */
function cf7ap_sanitize_feiertage( $input ): array {

    if ( ! is_array( $input ) ) {
        return [];
    }

    $clean = [];
    foreach ( $input as $date ) {
        $date = trim( (string) $date );
        if ( '' === $date ) {
            continue;
        }
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $d = DateTime::createFromFormat( 'Y-m-d', $date );
            if ( $d && $d->format( 'Y-m-d' ) === $date ) {
                $clean[] = $date;
            }
        }
    }

    sort( $clean );
    return array_values( array_unique( $clean ) );
}

/**
 * Sanitizer für Betriebsferien
 */
function cf7ap_sanitize_betriebsferien( $input ): array {

    if ( ! is_array( $input ) ) {
        return [];
    }

    $clean = [];
    foreach ( $input as $range ) {
        if ( ! is_array( $range ) ) {
            continue;
        }
        $von = isset( $range['von'] ) ? trim( (string) $range['von'] ) : '';
        $bis = isset( $range['bis'] ) ? trim( (string) $range['bis'] ) : '';

        if ( '' === $von || '' === $bis ) {
            continue;
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $von )
          || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bis )
        ) {
            continue;
        }
        if ( $von > $bis ) {
            // Falsche Reihenfolge → tauschen
            [ $von, $bis ] = [ $bis, $von ];
        }

        $clean[] = [ 'von' => $von, 'bis' => $bis ];
    }

    // Nach Startdatum sortieren
    usort( $clean, function ( $a, $b ) {
        return strcmp( $a['von'], $b['von'] );
    } );

    return $clean;
}

/**
 * Admin-Seite rendern
 */
function cf7ap_render_admin_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $feiertage = cf7ap_get_feiertage();
    $ferien    = cf7ap_get_betriebsferien();

    ?>
    <div class="wrap cf7ap-admin">
        <h1>CF7 Abholtermin – Einstellungen</h1>

        <p>Hier können Feiertage und Betriebsferien gepflegt werden.
            An diesen Tagen ist im Frontend keine Abholung wählbar.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'cf7ap_settings_group' ); ?>

            <h2>Feiertage</h2>
            <p class="description">Einzelne Tage, an denen keine Abholung möglich ist.</p>

            <div id="cf7ap-feiertage-rows" class="cf7ap-rows">
                <?php if ( empty( $feiertage ) ) : ?>
                    <p class="cf7ap-empty"><em>Noch keine Feiertage angelegt.</em></p>
                <?php else : ?>
                    <?php foreach ( $feiertage as $f ) : ?>
                        <div class="cf7ap-row">
                            <input type="date" name="cf7ap_feiertage[]"
                                   value="<?php echo esc_attr( $f ); ?>" required>
                            <button type="button" class="button cf7ap-remove">Entfernen</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p>
                <button type="button" class="button button-secondary" id="cf7ap-add-feiertag">
                    + Feiertag hinzufügen
                </button>
            </p>

            <hr style="margin: 2em 0;">

            <h2>Betriebsferien</h2>
            <p class="description">Zeiträume, in denen das gesamte Abholfenster gesperrt ist.</p>

            <div id="cf7ap-ferien-rows" class="cf7ap-rows">
                <?php if ( empty( $ferien ) ) : ?>
                    <p class="cf7ap-empty"><em>Noch keine Betriebsferien angelegt.</em></p>
                <?php else : ?>
                    <?php foreach ( $ferien as $i => $f ) : ?>
                        <div class="cf7ap-row">
                            <label>Von
                                <input type="date"
                                       name="cf7ap_betriebsferien[<?php echo (int) $i; ?>][von]"
                                       value="<?php echo esc_attr( $f['von'] ); ?>" required>
                            </label>
                            <label>Bis
                                <input type="date"
                                       name="cf7ap_betriebsferien[<?php echo (int) $i; ?>][bis]"
                                       value="<?php echo esc_attr( $f['bis'] ); ?>" required>
                            </label>
                            <button type="button" class="button cf7ap-remove">Entfernen</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p>
                <button type="button" class="button button-secondary" id="cf7ap-add-ferien">
                    + Zeitraum hinzufügen
                </button>
            </p>

            <?php submit_button( 'Änderungen speichern' ); ?>
        </form>

        <hr style="margin: 2em 0;">

        <h2>Aktuelle Berechnung (Vorschau)</h2>
        <?php $preview = cf7ap_get_pickup_config(); ?>
        <table class="form-table">
            <tr>
                <th>Aktuell früheste Abholung</th>
                <td>
                    <code><?php echo esc_html(
                        DateTime::createFromFormat( 'Y-m-d', $preview['earliest_date'] )->format( 'd.m.Y' )
                    ); ?> ab <?php echo esc_html( $preview['earliest_time'] ); ?> Uhr</code>
                </td>
            </tr>
            <tr>
                <th>Spätestmögliche Abholung</th>
                <td>
                    <code><?php echo esc_html(
                        DateTime::createFromFormat( 'Y-m-d', $preview['max_date'] )->format( 'd.m.Y' )
                    ); ?></code>
                    <span class="description">(<?php echo (int) CF7AP_MAX_DAYS_AHEAD; ?> Tage im Voraus)</span>
                </td>
            </tr>
            <tr>
                <th>Anzahl gesperrte Tage im Zeitraum</th>
                <td><code><?php echo count( $preview['disabled_dates'] ); ?></code></td>
            </tr>
        </table>
    </div>
    <?php
}

/**
 * Admin-Assets nur auf der eigenen Settings-Seite laden
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {

    if ( 'settings_page_cf7-abholtermin' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'cf7ap-admin-js',
        CF7AP_URL . 'assets/admin.js',
        [],
        CF7AP_VERSION,
        true
    );
    wp_enqueue_style(
        'cf7ap-admin-css',
        CF7AP_URL . 'assets/admin.css',
        [],
        CF7AP_VERSION
    );
} );

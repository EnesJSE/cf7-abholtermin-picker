<?php
/**
 * Admin-UI: Zusaetzliche Sperrtage und Betriebsferien verwalten.
 * Bundesweite Feiertage werden automatisch berechnet.
 * Erreichbar unter: WP-Admin -> Einstellungen -> CF7 Abholtermin
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_options_page(
            'CF7 Abholtermin',
            'CF7 Abholtermin',
            'manage_options',
            'cf7-abholtermin',
            'cf7ap_render_admin_page'
    );
} );

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

function cf7ap_sanitize_feiertage( $input ): array {
    if ( ! is_array( $input ) ) {
        return [];
    }
    $clean = [];
    foreach ( $input as $date ) {
        $date = trim( (string) $date );
        if ( '' === $date ) continue;
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

function cf7ap_sanitize_betriebsferien( $input ): array {
    if ( ! is_array( $input ) ) return [];
    $clean = [];
    foreach ( $input as $range ) {
        if ( ! is_array( $range ) ) continue;
        $von = isset( $range['von'] ) ? trim( (string) $range['von'] ) : '';
        $bis = isset( $range['bis'] ) ? trim( (string) $range['bis'] ) : '';
        if ( '' === $von || '' === $bis ) continue;
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $von )
                || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bis ) ) continue;
        if ( $von > $bis ) [ $von, $bis ] = [ $bis, $von ];
        $clean[] = [ 'von' => $von, 'bis' => $bis ];
    }

    // Nach Startdatum sortieren
    usort( $clean, function ( $a, $b ) {
        return strcmp( $a['von'], $b['von'] );
    } );

    return $clean;
}

function cf7ap_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $custom_feiertage = get_option( 'cf7ap_feiertage', [] );
    if ( ! is_array( $custom_feiertage ) ) $custom_feiertage = [];
    $custom_feiertage = array_values( array_filter( $custom_feiertage, 'is_string' ) );

    $ferien = cf7ap_get_betriebsferien();

    $tz           = new DateTimeZone( CF7AP_TIMEZONE );
    $current_year = (int) ( new DateTime( 'now', $tz ) )->format( 'Y' );

    $national_by_year = [];
    for ( $y = $current_year; $y <= $current_year + 1; $y++ ) {
        $easter = cf7ap_easter_date( $y );
        $national_by_year[ $y ] = [
                'Neujahr'                   => sprintf( '%d-01-01', $y ),
                'Karfreitag'                => ( clone $easter )->modify( '-2 days' )->format( 'Y-m-d' ),
                'Ostermontag'               => ( clone $easter )->modify( '+1 day' )->format( 'Y-m-d' ),
                'Tag der Arbeit'            => sprintf( '%d-05-01', $y ),
                'Christi Himmelfahrt'       => ( clone $easter )->modify( '+39 days' )->format( 'Y-m-d' ),
                'Pfingstmontag'             => ( clone $easter )->modify( '+50 days' )->format( 'Y-m-d' ),
                'Tag der Deutschen Einheit' => sprintf( '%d-10-03', $y ),
                '1. Weihnachtstag'          => sprintf( '%d-12-25', $y ),
                '2. Weihnachtstag'          => sprintf( '%d-12-26', $y ),
        ];
    }
    ?>
    <div class="wrap cf7ap-admin">
        <h1>CF7 Abholtermin &ndash; Einstellungen</h1>
        <p>Bundesweite Feiertage werden <strong>automatisch</strong> gesperrt.
            Hier nur regionale Sperrtage und Betriebsferien eintragen.</p>

        <h2>Bundesweite Feiertage <span class="cf7ap-badge">automatisch</span></h2>
        <p class="description">Immer gesperrt &mdash; aktuelles und naechstes Jahr.</p>
        <div class="cf7ap-national-grid">
            <?php foreach ( $national_by_year as $year => $holidays ) : ?>
                <div class="cf7ap-national-year">
                    <strong><?php echo (int) $year; ?></strong>
                    <ul>
                        <?php foreach ( $holidays as $name => $date ) : ?>
                            <li>
                        <span class="cf7ap-date"><?php echo esc_html(
                                    DateTime::createFromFormat( 'Y-m-d', $date )->format( 'd.m.Y' )
                            ); ?></span>
                                <?php echo esc_html( $name ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <hr style="margin:2em 0">

        <form method="post" action="options.php">
            <?php settings_fields( 'cf7ap_settings_group' ); ?>

            <h2>Zusaetzliche Sperrtage <span class="cf7ap-badge cf7ap-badge--custom">manuell</span></h2>
            <p class="description">Regionale Feiertage, Brueckentage o.ae.</p>
            <div id="cf7ap-feiertage-rows" class="cf7ap-rows">
                <?php if ( empty( $custom_feiertage ) ) : ?>
                    <p class="cf7ap-empty"><em>Noch keine zusaetzlichen Sperrtage eingetragen.</em></p>
                <?php else : ?>
                    <?php foreach ( $custom_feiertage as $f ) : ?>
                        <div class="cf7ap-row">
                            <input type="date" name="cf7ap_feiertage[]"
                                   value="<?php echo esc_attr( $f ); ?>" required>
                            <button type="button" class="button cf7ap-remove">Entfernen</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p><button type="button" class="button button-secondary"
                       id="cf7ap-add-feiertag">+ Sperrtag hinzufuegen</button></p>

            <hr style="margin:2em 0">

            <h2>Betriebsferien <span class="cf7ap-badge cf7ap-badge--custom">manuell</span></h2>
            <p class="description">Zeitraeume ohne Abholung.</p>
            <div id="cf7ap-ferien-rows" class="cf7ap-rows">
                <?php if ( empty( $ferien ) ) : ?>
                    <p class="cf7ap-empty"><em>Noch keine Betriebsferien eingetragen.</em></p>
                <?php else : ?>
                    <?php foreach ( $ferien as $i => $f ) : ?>
                        <div class="cf7ap-row">
                            <label>Von <input type="date"
                                              name="cf7ap_betriebsferien[<?php echo (int) $i; ?>][von]"
                                              value="<?php echo esc_attr( $f['von'] ); ?>" required></label>
                            <label>Bis <input type="date"
                                              name="cf7ap_betriebsferien[<?php echo (int) $i; ?>][bis]"
                                              value="<?php echo esc_attr( $f['bis'] ); ?>" required></label>
                            <button type="button" class="button cf7ap-remove">Entfernen</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p><button type="button" class="button button-secondary"
                       id="cf7ap-add-ferien">+ Zeitraum hinzufuegen</button></p>

            <?php submit_button( 'Aenderungen speichern' ); ?>
        </form>

        <hr style="margin:2em 0">

        <h2>Aktuelle Berechnung (Vorschau)</h2>
        <?php $preview = cf7ap_get_pickup_config(); ?>
        <table class="form-table">
            <tr>
                <th>Frueheste Abholung</th>
                <td><code><?php echo esc_html(
                                DateTime::createFromFormat( 'Y-m-d', $preview['earliest_date'] )->format( 'd.m.Y' )
                        ); ?> ab <?php echo esc_html( $preview['earliest_time'] ); ?> Uhr</code></td>
            </tr>
            <tr>
                <th>Spaeteste Abholung</th>
                <td><code><?php echo esc_html(
                                DateTime::createFromFormat( 'Y-m-d', $preview['max_date'] )->format( 'd.m.Y' )
                        ); ?></code>
                    <span class="description">(<?php echo (int) CF7AP_MAX_DAYS_AHEAD; ?> Tage im Voraus)</span></td>
            </tr>
            <tr>
                <th>Gesperrte Tage im Zeitraum</th>
                <td><code><?php echo count( $preview['disabled_dates'] ); ?></code>
                    <span class="description">(bundesweit + manuell + Sonntage)</span></td>
            </tr>
        </table>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'settings_page_cf7-abholtermin' !== $hook ) return;
    wp_enqueue_script( 'cf7ap-admin-js', CF7AP_URL . 'assets/admin.js', [], CF7AP_VERSION, true );
    wp_enqueue_style( 'cf7ap-admin-css', CF7AP_URL . 'assets/admin.css', [], CF7AP_VERSION );
} );

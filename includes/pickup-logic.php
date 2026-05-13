<?php
defined( 'ABSPATH' ) || exit;

/**
 * ============================================================
 * KONFIGURATION – Hier alle Parameter anpassen
 * ============================================================
 */

// Feiertage (Format: 'YYYY-MM-DD') – an diesen Tagen keine Abholung
$CF7AP_FEIERTAGE = [
    '2025-01-01', // Neujahr
    '2025-04-18', // Karfreitag
    '2025-04-21', // Ostermontag
    '2025-05-01', // Tag der Arbeit
    '2025-05-29', // Christi Himmelfahrt
    '2025-06-09', // Pfingstmontag
    '2025-10-03', // Tag der Deutschen Einheit
    '2025-12-25', // 1. Weihnachtstag
    '2025-12-26', // 2. Weihnachtstag
    // Weitere Daten hier ergänzen...
];

// Betriebsferien (Format: ['von' => 'YYYY-MM-DD', 'bis' => 'YYYY-MM-DD'])
$CF7AP_BETRIEBSFERIEN = [
    [ 'von' => '2025-08-04', 'bis' => '2025-08-15' ],
    // Weitere Zeiträume hier ergänzen...
];

/**
 * ============================================================
 * LOGIK-FUNKTIONEN (nicht ändern)
 * ============================================================
 */

/**
 * Berechnet den frühestmöglichen Abholtermin als DateTime-Objekt.
 * Gibt ein associative Array zurück mit:
 *   - earliest_date (YYYY-MM-DD)
 *   - disabled_dates (Array von YYYY-MM-DD Strings)
 *   - max_date (YYYY-MM-DD, 30 Tage ab heute)
 */
function cf7ap_get_pickup_config(): array {

    global $CF7AP_FEIERTAGE, $CF7AP_BETRIEBSFERIEN;

    $now = new DateTime( 'now', new DateTimeZone( 'Europe/Berlin' ) );

    // Schritt 1: Bearbeitungsbeginn ermitteln
    $processing_start = cf7ap_next_processing_start( $now );

    // Schritt 2: 24h Vorlaufzeit addieren
    $lead_end = clone $processing_start;
    $lead_end->modify( '+24 hours' );

    // Schritt 3: Ersten verfügbaren Abholtag finden
    $earliest = cf7ap_next_pickup_day( $lead_end );

    // Gesperrte Einzeltage sammeln (Feiertage + Betriebsferien + Wochentage ohne Abholung)
    $disabled = cf7ap_build_disabled_dates( $now );

    return [
        'earliest_date'  => $earliest->format( 'Y-m-d' ),
        'disabled_dates' => $disabled,
        'max_date'       => ( clone $now )->modify( '+30 days' )->format( 'Y-m-d' ),
        'pickup_min_time' => '09:30',
        'pickup_max_time' => '12:30',
    ];
}

/**
 * Findet den nächsten Bearbeitungsbeginn ab $from.
 * Mo–Do: 08:00–16:00, Fr: 08:00–12:00, Sa/So: kein Bearbeitungsbeginn.
 */
function cf7ap_next_processing_start( DateTime $from ): DateTime {

    $dt = clone $from;

    // Bearbeitungsfenster je Wochentag (1=Mo ... 7=So)
    $processing_windows = [
        1 => [ 'start' => '08:00', 'end' => '16:00' ], // Montag
        2 => [ 'start' => '08:00', 'end' => '16:00' ], // Dienstag
        3 => [ 'start' => '08:00', 'end' => '16:00' ], // Mittwoch
        4 => [ 'start' => '08:00', 'end' => '16:00' ], // Donnerstag
        5 => [ 'start' => '08:00', 'end' => '12:00' ], // Freitag
        // 6 = Samstag, 7 = Sonntag → kein Eintrag
    ];

    for ( $i = 0; $i < 14; $i++ ) { // max. 2 Wochen iterieren (Sicherheits-Limit)

        $dow = (int) $dt->format( 'N' ); // 1=Mo, 7=So

        if ( isset( $processing_windows[ $dow ] ) ) {
            $window = $processing_windows[ $dow ];

            // Ist die aktuelle Uhrzeit noch innerhalb des Fensters?
            $window_start = ( clone $dt )->setTime( ...array_map( 'intval', explode( ':', $window['start'] ) ) );
            $window_end   = ( clone $dt )->setTime( ...array_map( 'intval', explode( ':', $window['end'] ) ) );

            if ( $dt < $window_end ) {
                // Bestellung kommt noch heute an – Bearbeitungsbeginn ist jetzt oder um Öffnung
                if ( $dt >= $window_start ) {
                    return $dt; // Mitten im Fenster → sofort
                } else {
                    return $window_start; // Vor Öffnung → warte auf Öffnung
                }
            }
        }

        // Tag überspringen → nächsten Tag, ab 00:00
        $dt->modify( '+1 day' )->setTime( 0, 0, 0 );
    }

    return $dt; // Fallback
}

/**
 * Findet den ersten Abholtag (Mo–Sa) nach $after, der kein gesperrter Tag ist.
 */
function cf7ap_next_pickup_day( DateTime $after ): DateTime {

    global $CF7AP_FEIERTAGE, $CF7AP_BETRIEBSFERIEN;

    $dt = clone $after;

    // Wenn Vorlaufzeit mitten im Tag endet, gilt der Tag nur, wenn Abholung noch möglich (ab 09:30)
    $dt_time = (int) $dt->format( 'H' ) * 60 + (int) $dt->format( 'i' );
    if ( $dt_time >= 12 * 60 + 30 ) {
        // Abholzeit vorbei → nächsten Tag
        $dt->modify( '+1 day' )->setTime( 9, 30, 0 );
    } else {
        // Abholung heute noch möglich → frühestens 09:30
        if ( $dt_time < 9 * 60 + 30 ) {
            $dt->setTime( 9, 30, 0 );
        }
    }

    for ( $i = 0; $i < 60; $i++ ) { // max. 60 Tage

        $dow        = (int) $dt->format( 'N' );
        $date_str   = $dt->format( 'Y-m-d' );
        $is_holiday = in_array( $date_str, $CF7AP_FEIERTAGE, true );
        $is_vacation = cf7ap_in_betriebsferien( $dt, $CF7AP_BETRIEBSFERIEN );

        // Abholung: Mo(1)–Sa(6), kein Feiertag, keine Betriebsferien
        if ( $dow <= 6 && ! $is_holiday && ! $is_vacation ) {
            return $dt;
        }

        $dt->modify( '+1 day' )->setTime( 9, 30, 0 );
    }

    return $dt; // Fallback
}

/**
 * Prüft, ob ein Datum innerhalb der Betriebsferien liegt.
 */
function cf7ap_in_betriebsferien( DateTime $dt, array $ferien ): bool {

    foreach ( $ferien as $zeitraum ) {
        $von = new DateTime( $zeitraum['von'] );
        $bis = new DateTime( $zeitraum['bis'] );
        $bis->setTime( 23, 59, 59 );

        if ( $dt >= $von && $dt <= $bis ) {
            return true;
        }
    }

    return false;
}

/**
 * Erstellt eine Liste aller gesperrten Datumsstrings für Flatpickr.
 * Enthält: Sonntage, Feiertage, Betriebsferien (als Einzeltage).
 */
function cf7ap_build_disabled_dates( DateTime $from ): array {

    global $CF7AP_FEIERTAGE, $CF7AP_BETRIEBSFERIEN;

    $disabled = [];
    $dt       = clone $from;
    $dt->setTime( 0, 0, 0 );
    $end = ( clone $from )->modify( '+31 days' );

    while ( $dt <= $end ) {
        $dow      = (int) $dt->format( 'N' );
        $date_str = $dt->format( 'Y-m-d' );

        // Sonntag (7) immer sperren
        if ( $dow === 7 ) {
            $disabled[] = $date_str;
        }
        // Feiertage sperren
        elseif ( in_array( $date_str, $CF7AP_FEIERTAGE, true ) ) {
            $disabled[] = $date_str;
        }
        // Betriebsferien sperren
        elseif ( cf7ap_in_betriebsferien( $dt, $CF7AP_BETRIEBSFERIEN ) ) {
            $disabled[] = $date_str;
        }

        $dt->modify( '+1 day' );
    }

    return $disabled;
}
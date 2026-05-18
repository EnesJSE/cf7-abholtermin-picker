<?php
/**
 * Abhol-Logik: Berechnet den frühestmöglichen Abholtermin und liefert
 * alle für den Datepicker benötigten Daten.
 */

defined( 'ABSPATH' ) || exit;

/**
 * ============================================================
 * KONFIGURATION (nur Öffnungszeiten — Rest kommt aus dem Admin-UI)
 * ============================================================
 */

// Bearbeitungsfenster intern (1 = Montag ... 7 = Sonntag)
function cf7ap_processing_windows(): array {
    return [
        1 => [ '08:00', '16:00' ], // Montag
        2 => [ '08:00', '16:00' ], // Dienstag
        3 => [ '08:00', '16:00' ], // Mittwoch
        4 => [ '08:00', '16:00' ], // Donnerstag
        5 => [ '08:00', '12:00' ], // Freitag
        // Samstag/Sonntag: keine Bearbeitung
    ];
}

// Abholfenster
const CF7AP_PICKUP_START_H = 9;
const CF7AP_PICKUP_START_M = 30;
const CF7AP_PICKUP_END_H   = 12;
const CF7AP_PICKUP_END_M   = 30;

// Slots im Abholfenster (alle 30 Minuten)
function cf7ap_time_slots(): array {
    return [ '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30' ];
}

// Vorlaufzeit in Stunden
const CF7AP_LEAD_HOURS = 24;

// Maximaler Vorausbuchungszeitraum in Tagen
const CF7AP_MAX_DAYS_AHEAD = 30;

// Zeitzone
const CF7AP_TIMEZONE = 'Europe/Berlin';


/**
 * ============================================================
 * ÖFFENTLICHE GETTER FÜR FEIERTAGE/BETRIEBSFERIEN (aus DB)
 * ============================================================
 */

function cf7ap_get_feiertage(): array {
    $raw = get_option( 'cf7ap_feiertage', [] );
    if ( ! is_array( $raw ) ) {
        return [];
    }
    return array_values( array_filter( $raw, 'is_string' ) );
}

function cf7ap_get_betriebsferien(): array {
    $raw = get_option( 'cf7ap_betriebsferien', [] );
    if ( ! is_array( $raw ) ) {
        return [];
    }
    return array_values( array_filter( $raw, function ( $r ) {
        return is_array( $r ) && ! empty( $r['von'] ) && ! empty( $r['bis'] );
    } ) );
}


/**
 * ============================================================
 * HAUPTFUNKTION: Konfiguration für den Datepicker erzeugen
 * ============================================================
 */

function cf7ap_get_pickup_config(): array {

    $now              = new DateTime( 'now', new DateTimeZone( CF7AP_TIMEZONE ) );
    $processing_start = cf7ap_next_processing_start( $now );

    $lead_end = clone $processing_start;
    $lead_end->modify( '+' . CF7AP_LEAD_HOURS . ' hours' );

    $earliest = cf7ap_next_pickup_slot( $lead_end );

    $disabled = cf7ap_build_disabled_dates( $now );

    $max = ( clone $now )->modify( '+' . CF7AP_MAX_DAYS_AHEAD . ' days' );

    return [
        'earliest_date'  => $earliest->format( 'Y-m-d' ),
        'earliest_time'  => $earliest->format( 'H:i' ),
        'max_date'       => $max->format( 'Y-m-d' ),
        'disabled_dates' => $disabled,
        'time_slots'     => cf7ap_time_slots(),
    ];
}


/**
 * Nächster Bearbeitungsbeginn ab $from.
 * Berücksichtigt Wochenenden und Öffnungszeiten — Feiertage NICHT
 * (die wirken nur auf die Abholung, nicht auf die Bearbeitung).
 */
function cf7ap_next_processing_start( DateTime $from ): DateTime {

    $dt      = clone $from;
    $windows = cf7ap_processing_windows();

    // Safety-Limit: max. 2 Wochen iterieren
    for ( $i = 0; $i < 14; $i++ ) {

        $dow = (int) $dt->format( 'N' );

        if ( isset( $windows[ $dow ] ) ) {

            [ $s, $e ] = $windows[ $dow ];

            $start = ( clone $dt )->setTime(
                ...array_map( 'intval', explode( ':', $s ) )
            );
            $end = ( clone $dt )->setTime(
                ...array_map( 'intval', explode( ':', $e ) )
            );

            if ( $dt < $end ) {
                return ( $dt >= $start ) ? $dt : $start;
            }
        }

        $dt->modify( '+1 day' )->setTime( 0, 0, 0 );
    }

    return $dt; // Fallback
}


/**
 * Findet den ersten gültigen Abhol-Slot ab $after.
 * Berücksichtigt: Abholfenster (09:30–12:30), Sonntag, Feiertage, Betriebsferien.
 * Zeit wird auf den nächsten 30-Minuten-Slot aufgerundet.
 */
function cf7ap_next_pickup_slot( DateTime $after ): DateTime {

    $feiertage = cf7ap_get_feiertage();
    $ferien    = cf7ap_get_betriebsferien();

    $dt           = clone $after;
    $minutes_now  = (int) $dt->format( 'H' ) * 60 + (int) $dt->format( 'i' );
    $window_start = CF7AP_PICKUP_START_H * 60 + CF7AP_PICKUP_START_M;
    $window_end   = CF7AP_PICKUP_END_H * 60 + CF7AP_PICKUP_END_M;

    if ( $minutes_now > $window_end ) {
        // Nach Abholfenster → nächster Tag, 09:30
        $dt->modify( '+1 day' )->setTime( CF7AP_PICKUP_START_H, CF7AP_PICKUP_START_M, 0 );
    } elseif ( $minutes_now < $window_start ) {
        // Vor Abholfenster → heute 09:30
        $dt->setTime( CF7AP_PICKUP_START_H, CF7AP_PICKUP_START_M, 0 );
    } else {
        // Innerhalb Abholfenster → auf nächsten 30-Min-Slot aufrunden
        $dt = cf7ap_round_up_to_slot( $dt );
    }

    // Iteriere bis zu einem zulässigen Tag (max. 90 Tage Safety)
    for ( $i = 0; $i < 90; $i++ ) {

        $dow      = (int) $dt->format( 'N' );
        $date_str = $dt->format( 'Y-m-d' );

        $is_sunday   = ( $dow === 7 );
        $is_holiday  = in_array( $date_str, $feiertage, true );
        $is_vacation = cf7ap_in_betriebsferien( $dt, $ferien );

        if ( ! $is_sunday && ! $is_holiday && ! $is_vacation ) {
            return $dt;
        }

        $dt->modify( '+1 day' )->setTime( CF7AP_PICKUP_START_H, CF7AP_PICKUP_START_M, 0 );
    }

    return $dt; // Fallback
}


/**
 * Rundet eine Uhrzeit auf den nächsten 30-Minuten-Slot auf.
 */
function cf7ap_round_up_to_slot( DateTime $dt ): DateTime {

    $minutes = (int) $dt->format( 'H' ) * 60 + (int) $dt->format( 'i' );
    $rounded = (int) ceil( $minutes / 30 ) * 30;

    if ( $rounded === $minutes && (int) $dt->format( 's' ) === 0 ) {
        return $dt;
    }

    $result = clone $dt;
    $h      = intdiv( $rounded, 60 );
    $m      = $rounded % 60;

    if ( $h >= 24 ) {
        $result->modify( '+1 day' );
        $h -= 24;
    }

    $result->setTime( $h, $m, 0 );
    return $result;
}


/**
 * Prüft, ob ein Datum innerhalb der Betriebsferien liegt.
 */
function cf7ap_in_betriebsferien( DateTime $dt, array $ferien ): bool {

    $check_date = $dt->format( 'Y-m-d' );

    foreach ( $ferien as $r ) {
        try {
            $von = $r['von'];
            $bis = $r['bis'];

            if ( $check_date >= $von && $check_date <= $bis ) {
                return true;
            }
        } catch ( Exception $e ) {
            continue;
        }
    }

    return false;
}


/**
 * Liefert eine Liste aller im Vorausbuchungszeitraum gesperrten Tage.
 * Enthält: alle Sonntage, alle Feiertage, alle Tage in Betriebsferien.
 */
function cf7ap_build_disabled_dates( DateTime $from ): array {

    $feiertage = cf7ap_get_feiertage();
    $ferien    = cf7ap_get_betriebsferien();

    $disabled = [];
    $dt       = ( clone $from )->setTime( 0, 0, 0 );
    $end      = ( clone $from )->modify( '+' . ( CF7AP_MAX_DAYS_AHEAD + 1 ) . ' days' );

    while ( $dt <= $end ) {

        $dow      = (int) $dt->format( 'N' );
        $date_str = $dt->format( 'Y-m-d' );

        if ( $dow === 7
            || in_array( $date_str, $feiertage, true )
            || cf7ap_in_betriebsferien( $dt, $ferien )
        ) {
            $disabled[] = $date_str;
        }

        $dt->modify( '+1 day' );
    }

    return $disabled;
}
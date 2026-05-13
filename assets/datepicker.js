document.addEventListener('DOMContentLoaded', function () {

    // Alle CF7-Datumsfelder mit der Klasse "cf7-abholpicker" initialisieren
    const inputs = document.querySelectorAll('.cf7-abholpicker');
    if (!inputs.length || typeof flatpickr === 'undefined' || typeof CF7AbholPicker === 'undefined') {
        return;
    }

    const config = CF7AbholPicker;

    inputs.forEach(function (input) {
        flatpickr(input, {
            locale: 'de',
            dateFormat: 'd.m.Y',
            minDate: config.earliest_date,
            maxDate: config.max_date,
            disable: config.disabled_dates,
            disableMobile: false,
            allowInput: false,
        });
    });

    // Zeitauswahl: Nur Optionen zwischen 09:30 und 12:30
    const timeSelects = document.querySelectorAll('.cf7-abholzeit');
    timeSelects.forEach(function (select) {
        // Optionen dynamisch aufbauen (alle 30 Minuten)
        const slots = ['09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30'];
        select.innerHTML = '<option value="">Uhrzeit wählen …</option>';
        slots.forEach(function (slot) {
            const opt = document.createElement('option');
            opt.value = slot;
            opt.textContent = slot + ' Uhr';
            select.appendChild(opt);
        });
    });
});
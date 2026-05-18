/**
 * CF7 Abholtermin-Picker — Frontend-Logik
 *
 * Initialisiert Flatpickr auf .cf7-abholpicker und füllt .cf7-abholzeit
 * dynamisch mit gültigen Zeit-Slots.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        if (typeof flatpickr === 'undefined' || typeof CF7AbholPicker === 'undefined') {
            console.warn('CF7 Abholtermin-Picker: flatpickr oder Config nicht geladen.');
            return;
        }

        var config = CF7AbholPicker;
        var dateInputs = document.querySelectorAll('.cf7-abholpicker');
        var timeSelects = document.querySelectorAll('.cf7-abholzeit');

        if (!dateInputs.length) {
            return;
        }

        /**
         * Baut die Optionen im Zeit-Select neu auf.
         * Wenn das gewählte Datum der frühestmögliche Abholtag ist,
         * werden Slots vor der frühestmöglichen Uhrzeit ausgeblendet.
         */
        function rebuildTimeOptions(selectedDateStr) {

            timeSelects.forEach(function (select) {

                var currentValue = select.value;
                var isEarliestDay = (selectedDateStr === config.earliest_date);

                // Select leeren und Placeholder neu setzen
                select.innerHTML = '';
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Uhrzeit wählen …';
                select.appendChild(placeholder);

                config.time_slots.forEach(function (slot) {
                    // Auf dem frühestmöglichen Tag nur Slots ab earliest_time anbieten
                    if (isEarliestDay && slot < config.earliest_time) {
                        return;
                    }

                    var opt = document.createElement('option');
                    opt.value = slot;
                    opt.textContent = slot + ' Uhr';
                    if (slot === currentValue) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });
            });
        }

        // Zeit-Optionen initial befüllen — für den frühestmöglichen Tag
        // (da Flatpickr diesen unten als defaultDate setzt)
        rebuildTimeOptions(config.earliest_date);

        // Flatpickr auf allen Datumsfeldern initialisieren
        dateInputs.forEach(function (input) {

            flatpickr(input, {
                locale: 'de',
                dateFormat: 'Y-m-d',         // intern/Submit: ISO-Format
                altInput: true,              // sichtbares Feld trennen
                altFormat: 'd.m.Y',          // sichtbar: deutsches Format
                minDate: config.earliest_date,
                maxDate: config.max_date,
                disable: config.disabled_dates,
                defaultDate: config.earliest_date,
                disableMobile: false,        // Flatpickr auch auf Mobile
                allowInput: false,           // kein Freitext
                onChange: function (selectedDates, dateStr) {
                    rebuildTimeOptions(dateStr);
                }
            });
        });
    });
})();
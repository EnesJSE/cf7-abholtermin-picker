/**
 * CF7 Abholtermin — Admin-UI Logik
 * Add/Remove für Feiertage und Betriebsferien.
 */
(function () {
    'use strict';

    var ferienCounter = 0;

    function addFeiertagRow() {

        var wrap = document.getElementById('cf7ap-feiertage-rows');
        if (!wrap) return;

        // "Noch keine ..." Hinweis entfernen, falls vorhanden
        var empty = wrap.querySelector('.cf7ap-empty');
        if (empty) empty.remove();

        var row = document.createElement('div');
        row.className = 'cf7ap-row';
        row.innerHTML =
            '<input type="date" name="cf7ap_feiertage[]" value="" required> ' +
            '<button type="button" class="button cf7ap-remove">Entfernen</button>';
        wrap.appendChild(row);
        row.querySelector('input').focus();
    }

    function addFerienRow() {

        var wrap = document.getElementById('cf7ap-ferien-rows');
        if (!wrap) return;

        var empty = wrap.querySelector('.cf7ap-empty');
        if (empty) empty.remove();

        // Eindeutigen Index ermitteln
        var existing = wrap.querySelectorAll('.cf7ap-row').length;
        var i = existing + ferienCounter;
        ferienCounter++;

        var row = document.createElement('div');
        row.className = 'cf7ap-row';
        row.innerHTML =
            '<label>Von ' +
            '<input type="date" name="cf7ap_betriebsferien[' + i + '][von]" value="" required>' +
            '</label> ' +
            '<label>Bis ' +
            '<input type="date" name="cf7ap_betriebsferien[' + i + '][bis]" value="" required>' +
            '</label> ' +
            '<button type="button" class="button cf7ap-remove">Entfernen</button>';
        wrap.appendChild(row);
        row.querySelector('input').focus();
    }

    document.addEventListener('DOMContentLoaded', function () {

        var addF = document.getElementById('cf7ap-add-feiertag');
        if (addF) addF.addEventListener('click', addFeiertagRow);

        var addV = document.getElementById('cf7ap-add-ferien');
        if (addV) addV.addEventListener('click', addFerienRow);

        // Remove-Buttons (Event-Delegation)
        document.addEventListener('click', function (e) {
            if (e.target.classList && e.target.classList.contains('cf7ap-remove')) {
                var row = e.target.closest('.cf7ap-row');
                if (row) row.remove();
            }
        });
    });
})();

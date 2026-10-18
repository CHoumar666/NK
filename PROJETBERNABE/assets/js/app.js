/* ============================================================
   BERNABE DEPANNAGE - Comportements d'interface
   ============================================================ */
(function () {
    'use strict';

    // Menu lateral sur mobile
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            document.body.classList.remove('sidebar-open');
        });
    });

    // Confirmation avant suppression
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Filtrage instantane d'un tableau : <input data-filter="#idTable">
    document.querySelectorAll('[data-filter]').forEach(function (input) {
        var table = document.querySelector(input.getAttribute('data-filter'));
        if (!table) { return; }
        input.addEventListener('input', function () {
            var term = input.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(term) === -1 ? 'none' : '';
            });
        });
    });

    // Soumission automatique des filtres
    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });

    // Disparition des messages de succes
    document.querySelectorAll('.alert-success[data-dismissible]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 4000);
    });
})();

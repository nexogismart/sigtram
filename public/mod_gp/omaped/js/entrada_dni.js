document.addEventListener('DOMContentLoaded', function() {
    var dniInput = document.getElementById('dni');

    // Ocultar el logo si no se carga (equivalente al onerror inline).
    var logo = document.getElementById('omapedLogo');
    if (logo) {
        logo.addEventListener('error', function() {
            logo.style.display = 'none';
        });
    }

    if (!dniInput) return;

    function soloNumeros(val) {
        var n = (val || '').replace(/\D/g, '');
        return n.length > 8 ? n.substring(0, 8) : n;
    }

    dniInput.addEventListener('keypress', function(e) {
        if (e.key && e.key.length === 1 && !/\d/.test(e.key)) {
            e.preventDefault();
        }
    });

    dniInput.addEventListener('input', function(e) {
        e.target.value = soloNumeros(e.target.value);
    });

    dniInput.addEventListener('paste', function(e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text');
        var start = dniInput.selectionStart, end = dniInput.selectionEnd;
        var val = dniInput.value.substring(0, start) + soloNumeros(text) + dniInput.value.substring(end);
        dniInput.value = soloNumeros(val);
    });
});


/**
 * Painel admin — interações mínimas.
 * Confirmação antes de submeter formulários marcados com data-confirm.
 */

function initConfirms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Tem certeza?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConfirms);
} else {
    initConfirms();
}

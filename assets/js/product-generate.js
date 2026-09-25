document.addEventListener('DOMContentLoaded', function () {
    const openButton = document.getElementById('detit-open-generator');
    const modal = document.getElementById('detit-generator-modal');

    if (! openButton || ! modal) {
        return;
    }

    const closeButtons = modal.querySelectorAll('[data-detit-close="true"]');

    function openModal() {
        modal.removeAttribute('hidden');
        document.body.classList.add('detit-modal-open');

        const closeButton = modal.querySelector('.detit-modal__close');

        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.setAttribute('hidden', '');
        document.body.classList.remove('detit-modal-open');

        openButton.focus();
    }

    openButton.addEventListener('click', openModal);

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            ! modal.hasAttribute('hidden')
        ) {
            closeModal();
        }
    });
});
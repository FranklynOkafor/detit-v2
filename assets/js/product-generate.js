document.addEventListener('DOMContentLoaded', function () {
    const openButton = document.getElementById(
        'detit-open-generator'
    );

    const modal = document.getElementById(
        'detit-generator-modal'
    );

    /*
     * Stage 41 foundation.
     */
    if (! openButton || ! modal) {
        return;
    }

    const controls = document.getElementById(
        'detit-generation-controls'
    );

    const generateButton = document.getElementById(
        'detit-prepare-generation'
    );

    const errorMessage = document.getElementById(
        'detit-generation-error'
    );

    const statusMessage = document.getElementById(
        'detit-generation-status'
    );

    const modalBody = modal.querySelector(
        '.detit-modal__body'
    );

    const closeButtons = modal.querySelectorAll(
        '[data-detit-close="true"]'
    );

    function clearMessages() {
        if (errorMessage) {
            errorMessage.textContent = '';
            errorMessage.setAttribute('hidden', '');
        }

        if (statusMessage) {
            statusMessage.textContent = '';
            statusMessage.setAttribute('hidden', '');
        }
    }

    function openModal() {
        clearMessages();

        modal.removeAttribute('hidden');

        document.body.classList.add(
            'detit-modal-open'
        );

        /*
         * Always start the modal at the top.
         */
        if (modalBody) {
            modalBody.scrollTop = 0;
        }

        const closeButton = modal.querySelector(
            '.detit-modal__close'
        );

        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.setAttribute('hidden', '');

        document.body.classList.remove(
            'detit-modal-open'
        );

        openButton.focus();
    }

    /*
     * Stage 41 behaviour must work regardless
     * of Stage 42 controls.
     */
    openButton.addEventListener(
        'click',
        openModal
    );

    closeButtons.forEach(function (button) {
        button.addEventListener(
            'click',
            closeModal
        );
    });

    document.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key === 'Escape' &&
                ! modal.hasAttribute('hidden')
            ) {
                closeModal();
            }
        }
    );

    /*
     * Stage 42 controls.
     */
    if (! controls || ! generateButton) {
        console.warn(
            'DetIt: Stage 42 generation controls were not found.'
        );

        return;
    }

    function getSelectedFields() {
        return Array.from(
            controls.querySelectorAll(
                '.detit-output-field:checked'
            )
        ).map(function (checkbox) {
            return checkbox.value;
        });
    }

    function getValue(id) {
        const field = document.getElementById(id);

        if (! field) {
            return '';
        }

        return field.value.trim();
    }

    function buildPayload() {
        return {
            product_id: Number.parseInt(
                openButton.getAttribute(
                    'data-product-id'
                ) || '',
                10
            ),

            selected_fields:
                getSelectedFields(),

            template:
                getValue('detit-template'),

            language:
                getValue('detit-language'),

            tone:
                getValue('detit-tone'),

            additional_instructions:
                getValue('detit-instructions')
        };
    }

    function showError(message) {
        if (! errorMessage) {
            return;
        }

        errorMessage.textContent = message;

        errorMessage.removeAttribute(
            'hidden'
        );
    }

    function showStatus(message) {
        if (! statusMessage) {
            return;
        }

        statusMessage.textContent = message;

        statusMessage.removeAttribute(
            'hidden'
        );
    }

    generateButton.addEventListener(
        'click',
        function () {
            clearMessages();

            const payload = buildPayload();

            if (
                ! Number.isInteger(
                    payload.product_id
                ) ||
                payload.product_id < 1
            ) {
                showError(
                    'The product ID is invalid.'
                );

                return;
            }

            /*
             * This validation deliberately comes
             * before language validation.
             */
            if (
                payload.selected_fields.length === 0
            ) {
                showError(
                    'Select at least one field to generate.'
                );

                return;
            }

            if (payload.language === '') {
                showError(
                    'Enter the language DetIt should use.'
                );

                return;
            }

            /*
             * Stage 42 stops here.
             */
            modal.dispatchEvent(
                new CustomEvent(
                    'detit:generation-request-ready',
                    {
                        detail: payload
                    }
                )
            );

            showStatus(
                'Generation request prepared successfully.'
            );

            console.log(
                'DetIt Stage 42 payload:',
                payload
            );
        }
    );

    /*
     * Prevent Enter inside the modal's single-line
     * controls from submitting WordPress's outer
     * product-edit form.
     */
    controls.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key === 'Enter' &&
                event.target instanceof HTMLElement &&
                event.target.matches(
                    'input, select'
                )
            ) {
                event.preventDefault();
            }
        }
    );
});
document.addEventListener('DOMContentLoaded', function () {
    /*
     * ---------------------------------------------------------
     * Stage 41: Base modal elements.
     * ---------------------------------------------------------
     */

    const openButton = document.getElementById(
        'detit-open-generator'
    );

    const modal = document.getElementById(
        'detit-generator-modal'
    );

    /*
     * Stage 41 must continue to work even if later-stage
     * controls are unavailable.
     */
    if (! openButton || ! modal) {
        return;
    }

    const closeButtons = modal.querySelectorAll(
        '[data-detit-close="true"]'
    );

    /*
     * ---------------------------------------------------------
     * Stage 42: Generation form elements.
     * ---------------------------------------------------------
     */

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

    /*
     * ---------------------------------------------------------
     * Stage 43: Preview elements.
     * ---------------------------------------------------------
     */

    const preview = document.getElementById(
        'detit-generation-preview'
    );

    const previewFields = document.getElementById(
        'detit-preview-fields'
    );

    const backButton = document.getElementById(
        'detit-back-to-controls'
    );

    /*
    * ---------------------------------------------------------
    * Stage 45: Apply generated content.
    * ---------------------------------------------------------
    */

    const applyButton = document.getElementById(
        'detit-apply-generation'
    );

    const previewError = document.getElementById(
        'detit-preview-error'
    );

    const previewStatus = document.getElementById(
        'detit-preview-status'
    );

    /*
     * There are now two .detit-modal__body elements:
     * one for generation controls and one for preview.
     *
     * We only need the first one here so that opening
     * the modal starts at the top.
     */
    const controlsBody = controls
        ? controls.querySelector('.detit-modal__body')
        : null;

    /*
     * ---------------------------------------------------------
     * Human-readable field labels.
     * ---------------------------------------------------------
     */

    const fieldLabels = {
        title: 'Product title',
        short_description: 'Short description',
        description: 'Description',
        meta_description: 'Meta description',
        tags: 'Tags',
    };

    /*
     * ---------------------------------------------------------
     * Message helpers.
     * ---------------------------------------------------------
     */

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

    function clearPreviewMessages() {
        if (previewError) {
            previewError.textContent = '';
            previewError.setAttribute('hidden', '');
        }

        if (previewStatus) {
            previewStatus.textContent = '';
            previewStatus.setAttribute('hidden', '');
        }
    }

    function showError(message) {
        if (! errorMessage) {
            return;
        }

        errorMessage.textContent = message;
        errorMessage.removeAttribute('hidden');
    }

    function showStatus(message) {
        if (! statusMessage) {
            return;
        }

        statusMessage.textContent = message;
        statusMessage.removeAttribute('hidden');
    }

    function showPreviewError(message) {
        if (! previewError) {
            return;
        }

        previewError.textContent = message;
        previewError.removeAttribute('hidden');
    }

    function showPreviewStatus(message) {
        if (! previewStatus) {
            return;
        }

        previewStatus.textContent = message;
        previewStatus.removeAttribute('hidden');
    }

    /*
     * ---------------------------------------------------------
     * Screen switching.
     * ---------------------------------------------------------
     */

    function showPreviewScreen() {
        if (! controls || ! preview) {
            return;
        }

        controls.hidden = true;
        preview.hidden = false;
    }

    function showControlsScreen() {
        if (! controls || ! preview) {
            return;
        }

        preview.hidden = true;
        controls.hidden = false;
    }

    /*
     * Reset preview state without resetting the merchant's
     * generation choices.
     *
     * This means field selections, language, tone, etc.
     * stay intact while stale generated content disappears.
     */
    function resetPreview() {
        latestGeneratedContent = null;
        showControlsScreen();

        if (previewFields) {
            previewFields.replaceChildren();
        }

        clearPreviewMessages();
    }

    /*
     * ---------------------------------------------------------
     * Stage 41 modal behaviour.
     * ---------------------------------------------------------
     */

    function openModal() {
        clearMessages();
        resetPreview();

        modal.removeAttribute('hidden');

        document.body.classList.add(
            'detit-modal-open'
        );

        /*
         * Always begin at the top of the generation form.
         */
        if (controlsBody) {
            controlsBody.scrollTop = 0;
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

        clearMessages();
        resetPreview();

        openButton.focus();
    }

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
                event.key === 'Escape'
                && ! modal.hasAttribute('hidden')
            ) {
                closeModal();
            }
        }
    );

    /*
     * ---------------------------------------------------------
     * Stage 42 controls must exist before generation logic
     * can continue.
     * ---------------------------------------------------------
     */

    if (! controls || ! generateButton) {
        console.warn(
            'DetIt: Stage 42 generation controls were not found.'
        );

        return;
    }

    /*
     * ---------------------------------------------------------
     * Stage 43 preview elements must also exist.
     * ---------------------------------------------------------
     */

    if (
        ! preview
        || ! previewFields
        || ! backButton
        || ! previewError
        || ! previewStatus
        || ! applyButton
    ) {
        console.warn(
            'DetIt: Stage 43 preview elements were not found.'
        );

        return;
    }

    /*
     * Confirm that the PHP -> JavaScript bridge created
     * during Stage 43 exists.
     */
    if (
        typeof detitProductGenerate === 'undefined'
        || ! detitProductGenerate.restUrl
        || ! detitProductGenerate.applyRestUrl
        || ! detitProductGenerate.nonce
    ) {
        console.warn(
            'DetIt: Generation configuration is unavailable.'
        );

        return;
    }


    /*
    * The most recent successful generation result.
    *
    * Stage 45 uses this when the merchant chooses which
    * generated fields should be written to WooCommerce.
    */
    let latestGeneratedContent = null;


    /*
     * ---------------------------------------------------------
     * Back button.
     * ---------------------------------------------------------
     */

    backButton.addEventListener(
        'click',
        function () {
            clearMessages();
            showControlsScreen();

            /*
             * Keep the generated preview in memory for now.
             * It will be replaced when another generation runs
             * or cleared when the modal closes.
             */
        }
    );

    /*
     * ---------------------------------------------------------
     * Stage 42: Read generation selections.
     * ---------------------------------------------------------
     */

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

    /*
     * Build the exact request shape expected by:
     *
     * POST /detit/v2/generate
     */
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
                getValue('detit-instructions'),
        };
    }

    /*
     * ---------------------------------------------------------
     * Stage 43: Send the generation request.
     * ---------------------------------------------------------
     */

    async function requestGeneration(payload) {
        const response = await fetch(
            detitProductGenerate.restUrl,
            {
                method: 'POST',

                credentials: 'same-origin',

                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':
                        detitProductGenerate.nonce,
                },

                body: JSON.stringify(payload),
            }
        );

        let data;

        try {
            data = await response.json();
        } catch (error) {
            console.error(
                'DetIt received a non-JSON generation response.',
                {
                    status: response.status,
                    statusText: response.statusText,
                }
            );

            if (! response.ok) {
                throw new Error(
                    'DetIt generation failed with HTTP '
                    + response.status
                    + '.'
                );
            }

            throw new Error(
                'DetIt received an invalid response from the server.'
            );
        }

        if (! response.ok) {
            console.error(
                'DetIt generation request failed:',
                {
                    status: response.status,
                    statusText: response.statusText,
                    response: data,
                }
            );

            throw new Error(
                data && data.message
                    ? data.message
                    : (
                        'DetIt could not generate product content. '
                        + 'HTTP '
                        + response.status
                        + '.'
                    )
            );
        }

        return data;
    }

    /*
     * ---------------------------------------------------------
     * Preview value formatting.
     * ---------------------------------------------------------
     */

    function normalizePreviewValue(value) {
        /*
         * Tags may arrive as an array.
         */
        if (Array.isArray(value)) {
            return value.join(', ');
        }

        if (
            value === null
            || value === undefined
        ) {
            return '';
        }

        /*
         * Product descriptions may contain HTML.
         *
         * Parse it in an isolated document and extract only
         * readable text for this first preview implementation.
         */
        const parsedDocument =
            new DOMParser().parseFromString(
                String(value),
                'text/html'
            );

        return parsedDocument.body.textContent
            || '';
    }

    /*
     * ---------------------------------------------------------
     * Build one Existing / Generated column.
     * ---------------------------------------------------------
     */

    function createPreviewColumn(
        labelText,
        value
    ) {
        const column =
            document.createElement('div');

        column.className =
            'detit-preview-column';

        const label =
            document.createElement('span');

        label.className =
            'detit-preview-column-label';

        label.textContent =
            labelText;

        const content =
            document.createElement('div');

        content.className =
            'detit-preview-content';

        const normalized =
            normalizePreviewValue(value);

        content.textContent =
            normalized.trim()
                ? normalized
                : 'Not set';

        column.append(
            label,
            content
        );

        return column;
    }

    /*
     * ---------------------------------------------------------
     * Build one complete preview field.
     *
     * Example:
     *
     * Short description    [x] Use this field later
     *
     * Existing             Generated
     * --------             ---------
     * Old text             New AI text
     * ---------------------------------------------------------
     */

    function createPreviewField(
        field,
        existingValue,
        generatedValue
    ) {
        const section =
            document.createElement('section');

        section.className =
            'detit-preview-field';

        section.dataset.field =
            field;

        /*
         * Field heading.
         */
        const header =
            document.createElement('div');

        header.className =
            'detit-preview-field-header';

        const heading =
            document.createElement('strong');

        heading.textContent =
            fieldLabels[field] || field;

        /*
         * Future apply-selection checkbox.
         *
         * Stage 43 does NOT apply anything yet.
         */
        const selection =
            document.createElement('label');

        selection.className =
            'detit-preview-selection';

        const checkbox =
            document.createElement('input');

        checkbox.type =
            'checkbox';

        checkbox.checked =
            field !== 'meta_description';

        checkbox.disabled =
            field === 'meta_description';
                checkbox.dataset.previewApplyField =
                    field;

        const checkboxText =
            document.createElement('span');

        checkboxText.textContent =
            field === 'meta_description'
                ? 'Preview only'
                : 'Apply this field';

        selection.append(
            checkbox,
            checkboxText
        );

        header.append(
            heading,
            selection
        );

        /*
         * Existing vs Generated comparison.
         */
        const comparison =
            document.createElement('div');

        comparison.className =
            'detit-preview-comparison';

        comparison.append(
            createPreviewColumn(
                'Existing',
                existingValue
            ),

            createPreviewColumn(
                'Generated',
                generatedValue
            )
        );

        section.append(
            header,
            comparison
        );

        return section;
    }

    /*
     * ---------------------------------------------------------
     * Render the complete preview.
     * ---------------------------------------------------------
     */

    function renderPreview(
        selectedFields,
        generated
    ) {
        previewFields.replaceChildren();

        const existing =
            detitProductGenerate.existing || {};

        selectedFields.forEach(
            function (field) {
                const previewField =
                    createPreviewField(
                        field,
                        existing[field] ?? '',
                        generated[field] ?? ''
                    );

                previewFields.appendChild(
                    previewField
                );
            }
        );

        showPreviewScreen();

        /*
         * Start the preview at the top.
         */
        const previewBody =
            preview.querySelector(
                '.detit-modal__body'
            );

        if (previewBody) {
            previewBody.scrollTop = 0;
        }
    }




    /*
    * ---------------------------------------------------------
    * Stage 45: Build the Apply request.
    * ---------------------------------------------------------
    */

    function buildApplyPayload() {
        if (! latestGeneratedContent) {
            return null;
        }

        const fields = {};

        previewFields.querySelectorAll(
            '[data-preview-apply-field]:checked'
        ).forEach(function (checkbox) {
            const field =
                checkbox.dataset.previewApplyField;

            if (
                ! field
                || field === 'meta_description'
            ) {
                return;
            }

            if (
                Object.prototype.hasOwnProperty.call(
                    latestGeneratedContent,
                    field
                )
            ) {
                fields[field] =
                    latestGeneratedContent[field];
            }
        });

        return {
            product_id: Number.parseInt(
                openButton.getAttribute(
                    'data-product-id'
                ) || '',
                10
            ),
            fields: fields,
        };
    }







    async function requestApply(payload) {
        const response = await fetch(
            detitProductGenerate.applyRestUrl,
            {
                method: 'POST',

                credentials: 'same-origin',

                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':
                        detitProductGenerate.nonce,
                },

                body: JSON.stringify(payload),
            }
        );

        let data;

        try {
            data = await response.json();
        } catch (error) {
            throw new Error(
                'DetIt received an invalid apply response.'
            );
        }

        if (! response.ok) {
            throw new Error(
                data && data.message
                    ? data.message
                    : (
                        'DetIt could not apply the selected '
                        + 'product content.'
                    )
            );
        }

        return data;
    }




    applyButton.addEventListener(
        'click',
        async function () {
            clearPreviewMessages();

            const payload = buildApplyPayload();

            if (! payload) {
                showPreviewError(
                    'There is no generated content to apply.'
                );

                return;
            }

            if (
                ! Number.isInteger(payload.product_id)
                || payload.product_id < 1
            ) {
                showPreviewError(
                    'The product ID is invalid.'
                );

                return;
            }

            if (
                Object.keys(payload.fields).length === 0
            ) {
                showPreviewError(
                    'Select at least one field to apply.'
                );

                return;
            }

            applyButton.disabled = true;

            const originalButtonText =
                applyButton.textContent;

            applyButton.textContent = 'Applying…';

            showPreviewStatus(
                'DetIt is applying the selected content…'
            );

            try {
                const result =
                    await requestApply(payload);

                console.log(
                    'DetIt apply result:',
                    result
                );

                clearPreviewMessages();

                showPreviewStatus(
                    'Content applied successfully. '
                    + 'Refreshing the product editor…'
                );

                /*
                * Reload the product editor from WooCommerce after
                * ProductWriter has successfully saved the content.
                *
                * This prevents stale values in the already-open
                * WordPress form from overwriting DetIt's changes
                * if the merchant later clicks Update.
                */
                window.setTimeout(
                    function () {
                        window.location.reload();
                    },
                    1000
                );
            } catch (error) {
                clearPreviewMessages();

                showPreviewError(
                    error instanceof Error
                        ? error.message
                        : (
                            'DetIt could not apply the '
                            + 'selected product content.'
                        )
                );
            } finally {
                applyButton.disabled = false;

                applyButton.textContent =
                    originalButtonText;
            }
        }
    );






    /*
     * ---------------------------------------------------------
     * Generate button.
     * ---------------------------------------------------------
     */

    generateButton.addEventListener(
        'click',
        async function () {
            /*
             * Clear anything left from the previous attempt.
             */
            clearMessages();
            clearPreviewMessages();

            previewFields.replaceChildren();

            const payload =
                buildPayload();

            /*
             * -------------------------------------------------
             * Validate product ID.
             * -------------------------------------------------
             */
            if (
                ! Number.isInteger(
                    payload.product_id
                )
                || payload.product_id < 1
            ) {
                showError(
                    'The product ID is invalid.'
                );

                return;
            }

            /*
             * -------------------------------------------------
             * Validate selected fields.
             *
             * Keep this before language validation so the
             * merchant receives the most useful first error.
             * -------------------------------------------------
             */
            if (
                payload.selected_fields.length === 0
            ) {
                showError(
                    'Select at least one field to generate.'
                );

                return;
            }

            /*
             * -------------------------------------------------
             * Validate language.
             * -------------------------------------------------
             */
            if (payload.language === '') {
                showError(
                    'Enter the language DetIt should use.'
                );

                return;
            }

            /*
             * -------------------------------------------------
             * Preserve Stage 42's diagnostic event.
             * -------------------------------------------------
             */
            modal.dispatchEvent(
                new CustomEvent(
                    'detit:generation-request-ready',
                    {
                        detail: payload,
                    }
                )
            );

            console.log(
                'DetIt Stage 42 payload:',
                payload
            );

            /*
             * -------------------------------------------------
             * Prevent duplicate generation requests.
             * -------------------------------------------------
             */
            generateButton.disabled = true;

            const originalButtonText =
                generateButton.textContent;

            generateButton.textContent =
                'Generating…';

            showStatus(
                'DetIt is generating your content…'
            );

            /*
             * -------------------------------------------------
             * Call the real generation endpoint.
             * -------------------------------------------------
             */
            try {
                const generated =
                    await requestGeneration(
                        payload
                    );

                latestGeneratedContent = generated;

                console.log(
                    'DetIt generation result:',
                    generated
                );

                /*
                 * Remove the loading message before switching
                 * to Preview.
                 */
                clearMessages();

                /*
                 * Build Stage 43's Existing vs Generated view.
                 */
                renderPreview(
                    payload.selected_fields,
                    generated
                );

                showPreviewStatus(
                    'Generation completed successfully. '
                    + 'Nothing has been saved.'
                );
            } catch (error) {
                /*
                 * Stay on the generation screen when the
                 * provider/server fails.
                 */
                clearMessages();

                showControlsScreen();

                showError(
                    error instanceof Error
                        ? error.message
                        : (
                            'DetIt could not generate '
                            + 'product content.'
                        )
                );
            } finally {
                /*
                 * Always restore the Generate button.
                 */
                generateButton.disabled = false;

                generateButton.textContent =
                    originalButtonText;
            }
        }
    );

    /*
     * ---------------------------------------------------------
     * Prevent Enter inside single-line controls from
     * submitting WordPress's outer product-edit form.
     * ---------------------------------------------------------
     */

    controls.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key === 'Enter'
                && event.target
                    instanceof HTMLElement
                && event.target.matches(
                    'input, select'
                )
            ) {
                event.preventDefault();
            }
        }
    );
});
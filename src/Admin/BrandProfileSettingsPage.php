<?php

namespace DetIt\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class BrandProfileSettingsPage
{
    private const OPTION_NAME = 'detit_brand_profile';

    private const OPTION_GROUP = 'detit_brand_profile_group';

    private const PAGE_SLUG = 'detit-brand-profile';

    private const SECTION_ID = 'detit_brand_profile_main';

    /**
     * Register WordPress hooks.
     */
    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu']
        );

        add_action(
            'admin_init',
            [$this, 'registerSettings']
        );
    }

    /**
     * Add the Brand Profile page to:
     *
     * Settings -> DetIt Brand Profile
     */
    public function registerMenu(): void
    {
        add_options_page(
            __(
                'DetIt Brand Profile',
                'detit-product-content-generator-for-woocommerce'
            ),
            __(
                'DetIt Brand Profile',
                'detit-product-content-generator-for-woocommerce'
            ),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Register the option, section and fields.
     */
    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'default'           => self::defaults(),
                'sanitize_callback' => [$this, 'sanitize_brand_profile'],
            ]
        );

        add_settings_section(
            self::SECTION_ID,
            __(
                'Brand Profile',
                'detit-product-content-generator-for-woocommerce'
            ),
            [$this, 'renderSectionDescription'],
            self::PAGE_SLUG
        );

        foreach ($this->fields() as $key => $field) {
            $fieldId = 'detit_brand_profile_' . $key;

            add_settings_field(
                $fieldId,
                $field['label'],
                [$this, 'renderField'],
                self::PAGE_SLUG,
                self::SECTION_ID,
                [
                    'key'         => $key,
                    'type'        => $field['type'],
                    'description' => $field['description'],
                    'label_for'   => $fieldId,
                ]
            );
        }
    }

    /**
     * Description displayed above the Brand Profile fields.
     */
    public function renderSectionDescription(): void
    {
        echo '<p>';

        echo esc_html__(
            'Tell DetIt how your store communicates. These settings will later guide AI-generated product content.',
            'detit-product-content-generator-for-woocommerce'
        );

        echo '</p>';
    }

    /**
     * Render one Brand Profile field.
     *
     * @param array<string, mixed> $args
     */
    public function renderField(array $args): void
    {
        $key = (string) $args['key'];

        $type = (string) $args['type'];

        $description = (string) $args['description'];

        $fieldId = 'detit_brand_profile_' . $key;

        $fieldName = self::OPTION_NAME
            . '['
            . $key
            . ']';

        $values = $this->values();

        $value = isset($values[$key])
            ? (string) $values[$key]
            : '';

        if ($type === 'textarea') {

            printf(
                '<textarea
                    id="%1$s"
                    name="%2$s"
                    rows="4"
                    class="large-text"
                >%3$s</textarea>',
                esc_attr($fieldId),
                esc_attr($fieldName),
                esc_textarea($value)
            );
        } else {

            printf(
                '<input
                    type="text"
                    id="%1$s"
                    name="%2$s"
                    value="%3$s"
                    class="regular-text"
                />',
                esc_attr($fieldId),
                esc_attr($fieldName),
                esc_attr($value)
            );
        }

        if ($description !== '') {
            printf(
                '<p class="description">%s</p>',
                esc_html($description)
            );
        }
    }

    /**
     * Render the complete settings page.
     */
    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

?>
        <div class="wrap">

            <h1>
                <?php
                echo esc_html__(
                    'DetIt Brand Profile',
                    'detit-product-content-generator-for-woocommerce'
                );
                ?>
            </h1>


            <form method="post" action="options.php">

                <?php

                settings_fields(
                    self::OPTION_GROUP
                );

                do_settings_sections(
                    self::PAGE_SLUG
                );

                submit_button();

                ?>

            </form>

        </div>
<?php
    }


    /**
     * Sanitize the DetIt Brand Profile before saving.
     *
     * @param mixed $input Submitted Brand Profile data.
     *
     * @return array<string, string>
     */
    public function sanitize_brand_profile($input): array
    {
        if (! is_array($input)) {
            return self::defaults();
        }

        $fields = [
            'store_name' => [
                'type' => 'text',
                'max'  => 120,
            ],
            'industry' => [
                'type' => 'text',
                'max'  => 100,
            ],
            'target_audience' => [
                'type' => 'textarea',
                'max'  => 500,
            ],
            'market' => [
                'type' => 'text',
                'max'  => 150,
            ],
            'language_variant' => [
                'type' => 'text',
                'max'  => 80,
            ],
            'tone' => [
                'type' => 'text',
                'max'  => 100,
            ],
            'style' => [
                'type' => 'textarea',
                'max'  => 500,
            ],
            'preferred_words' => [
                'type' => 'textarea',
                'max'  => 500,
            ],
            'avoided_words' => [
                'type' => 'textarea',
                'max'  => 500,
            ],
            'cta_style' => [
                'type' => 'textarea',
                'max'  => 300,
            ],
            'additional_instructions' => [
                'type' => 'textarea',
                'max'  => 1500,
            ],
        ];

        $sanitized = self::defaults();

        foreach ($fields as $key => $rules) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (! is_scalar($value)) {
                $sanitized[$key] = '';
                continue;
            }

            $value = (string) $value;

            if ('textarea' === $rules['type']) {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }

            $sanitized[$key] = $this->limit_string_length(
                $value,
                (int) $rules['max']
            );
        }

        return $sanitized;
    }



    /**
 * Limit a string to a maximum number of characters.
 */
private function limit_string_length( string $value, int $max_length ): string {
    if ( $max_length < 1 ) {
        return '';
    }

    if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
        if ( mb_strlen( $value, 'UTF-8' ) <= $max_length ) {
            return $value;
        }

        return mb_substr(
            $value,
            0,
            $max_length,
            'UTF-8'
        );
    }

    if ( strlen( $value ) <= $max_length ) {
        return $value;
    }

    return substr( $value, 0, $max_length );
}



    /**
     * Retrieve saved Brand Profile values.
     *
     * @return array<string, string>
     */
    private function values(): array
    {
        $saved = get_option(
            self::OPTION_NAME,
            self::defaults()
        );

        if (! is_array($saved)) {
            return self::defaults();
        }

        return array_merge(
            self::defaults(),
            $saved
        );
    }

    /**
     * Default Brand Profile values.
     *
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return [
            'store_name'              => '',
            'industry'                => '',
            'target_audience'         => '',
            'market'                  => '',
            'language_variant'        => '',
            'tone'                    => '',
            'style'                   => '',
            'preferred_words'         => '',
            'avoided_words'           => '',
            'cta_style'               => '',
            'additional_instructions' => '',
        ];
    }

    /**
     * Brand Profile field definitions.
     *
     * @return array<string, array{
     *     label:string,
     *     type:string,
     *     description:string
     * }>
     */
    private function fields(): array
    {
        return [

            'store_name' => [
                'label' => __(
                    'Store name',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'The name customers know your store by.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'industry' => [
                'label' => __(
                    'Industry',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'For example: fashion, electronics, home and kitchen, beauty or food.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'target_audience' => [
                'label' => __(
                    'Target audience',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'textarea',
                'description' => __(
                    'Describe the people your store mainly sells to.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'market' => [
                'label' => __(
                    'Market',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'The main country, region or market your store serves.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'language_variant' => [
                'label' => __(
                    'Language / variant',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'For example: English (Nigeria), English (UK), English (US) or French.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'tone' => [
                'label' => __(
                    'Tone',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'For example: friendly, confident, professional or conversational.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'style' => [
                'label' => __(
                    'Style',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'Describe how product content should generally be written.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'preferred_words' => [
                'label' => __(
                    'Preferred words',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'textarea',
                'description' => __(
                    'Words or phrases DetIt should prefer when appropriate.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'avoided_words' => [
                'label' => __(
                    'Avoided words',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'textarea',
                'description' => __(
                    'Words or phrases DetIt should avoid using.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'cta_style' => [
                'label' => __(
                    'CTA style',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'text',
                'description' => __(
                    'Describe the preferred style of calls to action.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],

            'additional_instructions' => [
                'label' => __(
                    'Additional instructions',
                    'detit-product-content-generator-for-woocommerce'
                ),
                'type' => 'textarea',
                'description' => __(
                    'Any additional brand-writing guidance DetIt should follow.',
                    'detit-product-content-generator-for-woocommerce'
                ),
            ],
        ];
    }
}

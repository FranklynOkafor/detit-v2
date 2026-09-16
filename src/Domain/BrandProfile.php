<?php

namespace DetIt\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Represents the DetIt Brand Profile.
 */
final class BrandProfile {

    private string $store_name;

    private string $industry;

    private string $target_audience;

    private string $market;

    private string $language_variant;

    private string $tone;

    private string $style;

    private string $preferred_words;

    private string $avoided_words;

    private string $cta_style;

    private string $additional_instructions;

    /**
     * @param array<string, mixed> $data Brand Profile data.
     */
    public function __construct( array $data = [] ) {
        $this->store_name              = $this->string_value( $data, 'store_name' );
        $this->industry                = $this->string_value( $data, 'industry' );
        $this->target_audience         = $this->string_value( $data, 'target_audience' );
        $this->market                  = $this->string_value( $data, 'market' );
        $this->language_variant        = $this->string_value( $data, 'language_variant' );
        $this->tone                    = $this->string_value( $data, 'tone' );
        $this->style                   = $this->string_value( $data, 'style' );
        $this->preferred_words         = $this->string_value( $data, 'preferred_words' );
        $this->avoided_words           = $this->string_value( $data, 'avoided_words' );
        $this->cta_style               = $this->string_value( $data, 'cta_style' );
        $this->additional_instructions = $this->string_value( $data, 'additional_instructions' );
    }

    public function get_store_name(): string {
        return $this->store_name;
    }

    public function get_industry(): string {
        return $this->industry;
    }

    public function get_target_audience(): string {
        return $this->target_audience;
    }

    public function get_market(): string {
        return $this->market;
    }

    public function get_language_variant(): string {
        return $this->language_variant;
    }

    public function get_tone(): string {
        return $this->tone;
    }

    public function get_style(): string {
        return $this->style;
    }

    public function get_preferred_words(): string {
        return $this->preferred_words;
    }

    public function get_avoided_words(): string {
        return $this->avoided_words;
    }

    public function get_cta_style(): string {
        return $this->cta_style;
    }

    public function get_additional_instructions(): string {
        return $this->additional_instructions;
    }

    /**
     * Convert the Brand Profile back into an array.
     *
     * @return array<string, string>
     */
    public function to_array(): array {
        return [
            'store_name'              => $this->store_name,
            'industry'                => $this->industry,
            'target_audience'         => $this->target_audience,
            'market'                  => $this->market,
            'language_variant'        => $this->language_variant,
            'tone'                    => $this->tone,
            'style'                   => $this->style,
            'preferred_words'         => $this->preferred_words,
            'avoided_words'           => $this->avoided_words,
            'cta_style'               => $this->cta_style,
            'additional_instructions' => $this->additional_instructions,
        ];
    }

    /**
     * Check whether the profile contains any meaningful data.
     */
    public function is_empty(): bool {
        foreach ( $this->to_array() as $value ) {
            if ( '' !== trim( $value ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Safely extract a string value from profile data.
     *
     * @param array<string, mixed> $data Profile data.
     */
    private function string_value( array $data, string $key ): string {
        if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
            return '';
        }

        return (string) $data[ $key ];
    }
}
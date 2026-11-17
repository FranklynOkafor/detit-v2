<?php

declare(strict_types=1);

namespace DetIt\Catalog;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stage 58: transparent catalog completeness scoring.
 *
 * This is NOT a prediction of SEO ranking, sales, writing quality, or
 * regulatory compliance. It scores whether key WooCommerce catalog
 * fields are populated, using the deterministic Stage 57 issue list.
 *
 * Informational issues are never deducted. An unknown issue code
 * never causes an undocumented penalty.
 */
final class ProductHealthScorer
{
    public const RULES_VERSION = '1.0.0';

    /**
     * Deductions for catalog completeness gaps, not AI-estimated quality.
     * A variable product missing configured attributes is more important
     * than a simple product without optional attributes.
     *
     * @var array<string, int>
     */
    private const DEDUCTIONS = [
        'missing_title'             => 30,
        'missing_featured_image'    => 25,
        'missing_description'       => 20,
        'missing_short_description' => 10,
        'missing_categories'        => 10,
        'missing_attributes'        => 15,
    ];

    /**
     * @param array<int, array<string, mixed>> $issues Stage 57 issue objects.
     * @return array{
     *     score: int,
     *     rating: string,
     *     rules_version: string,
     *     deductions: array<int, array{code:string,points:int}>
     * }
     */
    public function score(array $issues): array
    {
        $pointsLost = 0;
        $deductions = [];
        $seen = [];

        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $code = $issue['code'] ?? null;
            $severity = $issue['severity'] ?? null;

            if (
                ! is_string($code)
                || ! is_string($severity)
                || isset($seen[$code])
            ) {
                continue;
            }

            $seen[$code] = true;

            // The issue detector marks optional fields as informational.
            // A missing simple-product attribute MUST NOT lower the score.
            if ($severity === 'info') {
                continue;
            }

            $points = self::DEDUCTIONS[$code] ?? 0;

            if ($points <= 0) {
                continue;
            }

            $pointsLost += $points;
            $deductions[] = [
                'code' => $code,
                'points' => $points,
            ];
        }

        $score = max(0, 100 - $pointsLost);

        return [
            'score' => $score,
            'rating' => $this->rating($score),
            'rules_version' => self::RULES_VERSION,
            'deductions' => $deductions,
        ];
    }

    private function rating(int $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        }

        if ($score >= 70) {
            return 'good';
        }

        if ($score >= 40) {
            return 'needs_attention';
        }

        return 'critical';
    }
}

<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * ConsentService
 *
 * Service to read and parse cookie consent status from Orestbida Cookie Consent.
 * Provides consent information for GDPR-compliant tracking.
 */
class ConsentService
{
    private const COOKIE_NAME = 'wsc_cookie_consent';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get consent status for all categories
     *
     * @return array{
     *     necessary: bool,
     *     analytics: bool,
     *     marketing: bool,
     *     personalization: bool
     * }
     */
    public function getConsentStatus(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->cookies->has(self::COOKIE_NAME)) {
            // No consent cookie found - default to denied
            return [
                'necessary' => true, // Always true
                'analytics' => false,
                'marketing' => false,
                'personalization' => false,
            ];
        }

        try {
            $cookieValue = $request->cookies->get(self::COOKIE_NAME);
            $consentData = json_decode($cookieValue, true);

            if (!is_array($consentData)) {
                $this->logger->warning('[Consent Service] Cookie is not valid JSON', [
                    'cookieValue' => substr($cookieValue, 0, 100) . '...',
                ]);

                return [
                    'necessary' => true,
                    'analytics' => false,
                    'marketing' => false,
                    'personalization' => false,
                ];
            }

            // Orestbida CookieConsent stores categories as array: ["necessary", "analytics"]
            // OR as object: {"necessary": true, "analytics": true}
            $categories = $consentData['categories'] ?? [];

            // Check if categories is an array of strings (Orestbida v3 format)
            if (is_array($categories) && isset($categories[0]) && is_string($categories[0])) {
                // Array format: ["necessary", "analytics", "marketing"]
                return [
                    'necessary' => true, // Always true
                    'analytics' => in_array('analytics', $categories, true),
                    'marketing' => in_array('marketing', $categories, true),
                    'personalization' => in_array('personalization', $categories, true),
                ];
            }

            // Object format: {"necessary": true, "analytics": true}
            return [
                'necessary' => true, // Always true
                'analytics' => !empty($categories['analytics']),
                'marketing' => !empty($categories['marketing']),
                'personalization' => !empty($categories['personalization']),
            ];

        } catch (\Exception $e) {
            $this->logger->error('[Consent Service] Failed to parse cookie consent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return conservative defaults on error
            return [
                'necessary' => true,
                'analytics' => false,
                'marketing' => false,
                'personalization' => false,
            ];
        }
    }

    /**
     * Check if analytics consent is given
     * (Includes: GA4, Matomo, Umami, Rybbit)
     */
    public function hasAnalyticsConsent(): bool
    {
        return $this->getConsentStatus()['analytics'];
    }

    /**
     * Check if marketing consent is given
     * (Includes: Google Ads, Facebook, Instagram, Pinterest)
     */
    public function hasMarketingConsent(): bool
    {
        return $this->getConsentStatus()['marketing'];
    }

    /**
     * Check if personalization consent is given
     */
    public function hasPersonalizationConsent(): bool
    {
        return $this->getConsentStatus()['personalization'];
    }

    /**
     * Get consent data formatted for Jitsu
     *
     * @return array{
     *     consent_analytics: bool,
     *     consent_marketing: bool,
     *     consent_personalization: bool,
     *     consent_mode: string
     * }
     */
    public function getConsentDataForJitsu(): array
    {
        $consent = $this->getConsentStatus();

        return [
            'consent_analytics' => $consent['analytics'],
            'consent_marketing' => $consent['marketing'],
            'consent_personalization' => $consent['personalization'],
            'consent_mode' => $this->determineConsentMode($consent),
        ];
    }

    /**
     * Determine consent mode based on consent status
     *
     * @param array $consent
     * @return string 'full' | 'partial' | 'denied'
     */
    private function determineConsentMode(array $consent): string
    {
        $analyticsConsent = $consent['analytics'] ?? false;
        $marketingConsent = $consent['marketing'] ?? false;

        if ($analyticsConsent && $marketingConsent) {
            return 'full';
        }

        if ($analyticsConsent || $marketingConsent) {
            return 'partial';
        }

        return 'denied';
    }

    /**
     * Check if consent cookie exists
     */
    public function hasConsentCookie(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request && $request->cookies->has(self::COOKIE_NAME);
    }
}

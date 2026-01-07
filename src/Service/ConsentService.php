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

            if (!is_array($consentData) || !isset($consentData['categories'])) {
                $this->logger->warning('[Consent Service] Invalid cookie consent data format', [
                    'cookieValue' => $cookieValue,
                ]);

                return [
                    'necessary' => true,
                    'analytics' => false,
                    'marketing' => false,
                    'personalization' => false,
                ];
            }

            $categories = $consentData['categories'];

            return [
                'necessary' => true, // Always true
                'analytics' => !empty($categories['analytics']),
                'marketing' => !empty($categories['marketing']),
                'personalization' => !empty($categories['personalization']),
            ];

        } catch (\Exception $e) {
            $this->logger->error('[Consent Service] Failed to parse cookie consent', [
                'error' => $e->getMessage(),
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

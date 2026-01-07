<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JitsuClient
{
    private const API_ENDPOINT = '/api/s/s2s/track';
    private const REQUEST_TIMEOUT = 0.5; // 500ms timeout

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Track an event to Jitsu server-side
     *
     * @param string $eventName Event name (e.g., 'view_item', 'purchase')
     * @param array $properties Event properties (GA4-compatible items array, etc.)
     * @param CustomerEntity|null $customer Customer entity (null for guests)
     * @param string $sessionId Session ID for anonymousId
     * @param string|null $salesChannelId Sales channel ID for config lookup
     */
    public function track(
        string $eventName,
        array $properties,
        ?CustomerEntity $customer,
        string $sessionId,
        ?string $salesChannelId = null
    ): void {
        // Check if Jitsu tracking is enabled
        if (!$this->isEnabled($salesChannelId)) {
            return;
        }

        // Get configuration
        $jitsuUrl = $this->getJitsuUrl($salesChannelId);
        $writeKey = $this->getWriteKey($salesChannelId);

        if (empty($jitsuUrl) || empty($writeKey)) {
            $this->logError('Jitsu URL or Write-Key not configured');
            return;
        }

        // Build event payload
        $payload = $this->buildPayload($eventName, $properties, $customer, $sessionId);

        // Send event asynchronously
        $this->sendEvent($jitsuUrl, $writeKey, $payload, $salesChannelId);
    }

    /**
     * Build Jitsu event payload
     */
    private function buildPayload(
        string $eventName,
        array $properties,
        ?CustomerEntity $customer,
        string $sessionId
    ): array {
        try {
            $request = $this->requestStack->getCurrentRequest();

            $payload = [
                'event' => $eventName,
                'properties' => $properties,
                'context' => [
                    'ip' => $request?->getClientIp() ?? 'unknown',
                    'userAgent' => $request?->headers->get('User-Agent') ?? 'unknown',
                    'page' => [
                        'url' => $request?->getUri() ?? '',
                        'referrer' => $request?->headers->get('referer') ?? '',
                    ],
                ],
                'timestamp' => (new \DateTime())->format('c'),
            ];

            // ALWAYS set anonymousId (required for GA4 client_id)
            $payload['anonymousId'] = $sessionId;

            // Add userId and traits if customer is logged in
            if ($customer !== null) {
                try {
                    $payload['userId'] = $customer->getId();
                    $payload['traits'] = [
                        'email' => $customer->getEmail() ?? '',
                        'firstName' => $customer->getFirstName() ?? '',
                        'lastName' => $customer->getLastName() ?? '',
                        'customerNumber' => $customer->getCustomerNumber() ?? '',
                    ];
                } catch (\Exception $e) {
                    // Log error but keep anonymousId
                    $this->logError('Failed to extract customer data for Jitsu payload', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $payload;

        } catch (\Exception $e) {
            // Return minimal payload on error
            $this->logError('Failed to build Jitsu payload', [
                'error' => $e->getMessage(),
                'event' => $eventName,
            ]);

            return [
                'event' => $eventName,
                'properties' => $properties,
                'anonymousId' => $sessionId,
                'timestamp' => (new \DateTime())->format('c'),
            ];
        }
    }

    /**
     * Send event to Jitsu API
     */
    private function sendEvent(string $jitsuUrl, string $writeKey, array $payload, ?string $salesChannelId): void
    {
        $endpoint = rtrim($jitsuUrl, '/') . self::API_ENDPOINT;
        $isDebug = $this->isDebugMode($salesChannelId);

        try {
            $startTime = microtime(true);

            if ($isDebug) {
                $this->logger->info('[Jitsu] Sending event', [
                    'endpoint' => $endpoint,
                    'event' => $payload['event'],
                    'payload' => $payload,
                ]);
            }

            $options = [
                'headers' => [
                    'X-Write-Key' => $writeKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::REQUEST_TIMEOUT,
            ];

            // Check if SSL verification should be disabled (for self-signed certificates)
            if (!$this->shouldVerifySsl($salesChannelId)) {
                $options['verify_peer'] = false;
                $options['verify_host'] = false;

                if ($isDebug) {
                    $this->logger->warning('[Jitsu] SSL verification disabled - only use in dev/test environments!');
                }
            }

            $response = $this->httpClient->request('POST', $endpoint, $options);

            $statusCode = $response->getStatusCode();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($isDebug) {
                $this->logger->info('[Jitsu] Event sent successfully', [
                    'statusCode' => $statusCode,
                    'duration' => $duration . 'ms',
                    'event' => $payload['event'],
                ]);
            }

            if ($statusCode >= 400) {
                $this->logError('Jitsu API returned error status', [
                    'statusCode' => $statusCode,
                    'event' => $payload['event'],
                    'response' => $response->getContent(false),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logError('Failed to send event to Jitsu', [
                'event' => $payload['event'],
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            if ($isDebug) {
                $this->logger->error('[Jitsu] Exception details', [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Check if Jitsu tracking is enabled
     */
    private function isEnabled(?string $salesChannelId): bool
    {
        return (bool) $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerJitsu',
            $salesChannelId
        );
    }

    /**
     * Get Jitsu server URL from config
     */
    private function getJitsuUrl(?string $salesChannelId): ?string
    {
        return $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerJitsuUrl',
            $salesChannelId
        );
    }

    /**
     * Get Jitsu Write-Key from config
     */
    private function getWriteKey(?string $salesChannelId): ?string
    {
        return $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerJitsuWriteKey',
            $salesChannelId
        );
    }

    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode(?string $salesChannelId): bool
    {
        return (bool) $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerJitsuDebug',
            $salesChannelId
        );
    }

    /**
     * Check if SSL verification should be enabled
     */
    private function shouldVerifySsl(?string $salesChannelId): bool
    {
        $verifySsl = $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerJitsuVerifySsl',
            $salesChannelId
        );

        // Default to true (secure) if not set
        return $verifySsl !== false;
    }

    /**
     * Log error message
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger->error('[Jitsu] ' . $message, $context);
    }
}

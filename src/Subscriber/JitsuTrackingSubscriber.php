<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WSC\SWCookieDataLayer\Service\JitsuClient;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * JitsuTrackingSubscriber
 *
 * Handles additional Jitsu server-side tracking events:
 * - login (CustomerLoginEvent)
 * - sign_up (CustomerRegisterEvent via CustomerEvents::CUSTOMER_WRITTEN)
 * - search (SearchPageLoadedEvent)
 * - add_to_cart (AfterLineItemAddedEvent)
 * - remove_from_cart (AfterLineItemRemovedEvent)
 */
class JitsuTrackingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly JitsuClient $jitsuClient,
        private readonly LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack,
        private readonly EntityRepository $productRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
            CustomerRegisterEvent::class => 'onCustomerRegister',
            SearchPageLoadedEvent::class => 'onSearchPageLoaded',
            AfterLineItemAddedEvent::class => 'onLineItemAdded',
            AfterLineItemRemovedEvent::class => 'onLineItemRemoved',
        ];
    }

    /**
     * Handle customer login (login event)
     */
    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        try {
            $customer = $event->getCustomer();
            $request = $this->requestStack->getCurrentRequest();
            $sessionId = $request?->getSession()?->getId() ?? 'unknown';

            $properties = [
                'method' => 'standard', // or 'social', 'guest' depending on context
            ];

            $this->jitsuClient->track(
                'login',
                $properties,
                $customer,
                $sessionId,
                $event->getSalesChannelContext()->getSalesChannelId()
            );

            $this->logDebug('Customer login tracked to Jitsu', [
                'customerId' => $customer->getId(),
                'email' => $customer->getEmail(),
            ], $event->getSalesChannelContext()->getSalesChannelId());

        } catch (\Throwable $e) {
            $this->logger->error('[Jitsu Tracking] Failed to track login event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle customer registration (sign_up event)
     */
    public function onCustomerRegister(CustomerRegisterEvent $event): void
    {
        try {
            $customer = $event->getCustomer();
            $request = $this->requestStack->getCurrentRequest();
            $sessionId = $request?->getSession()?->getId() ?? 'unknown';

            $properties = [
                'method' => 'standard', // or 'guest' depending on context
            ];

            $this->jitsuClient->track(
                'sign_up',
                $properties,
                $customer,
                $sessionId,
                $event->getSalesChannelContext()->getSalesChannelId()
            );

            $this->logDebug('Customer registration tracked to Jitsu', [
                'customerId' => $customer->getId(),
                'email' => $customer->getEmail(),
            ], $event->getSalesChannelContext()->getSalesChannelId());

        } catch (\Throwable $e) {
            $this->logger->error('[Jitsu Tracking] Failed to track sign_up event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle search (search event)
     */
    public function onSearchPageLoaded(SearchPageLoadedEvent $event): void
    {
        try {
            $page = $event->getPage();
            $request = $event->getRequest();
            $searchTerm = $request->query->get('search', '');
            $listing = $page->getListing();

            $customer = $event->getSalesChannelContext()->getCustomer();
            $sessionId = $request->getSession()?->getId() ?? 'unknown';

            $properties = [
                'search_term' => $searchTerm,
                'results' => $listing->getTotal(),
            ];

            $this->jitsuClient->track(
                'search',
                $properties,
                $customer,
                $sessionId,
                $event->getSalesChannelContext()->getSalesChannelId()
            );

            $this->logDebug('Search tracked to Jitsu', [
                'searchTerm' => $searchTerm,
                'results' => $listing->getTotal(),
            ], $event->getSalesChannelContext()->getSalesChannelId());

        } catch (\Throwable $e) {
            $this->logger->error('[Jitsu Tracking] Failed to track search event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle add to cart (add_to_cart event)
     */
    public function onLineItemAdded(AfterLineItemAddedEvent $event): void
    {
        try {
            $lineItems = $event->getLineItems();
            $context = $event->getContext();
            $request = $this->requestStack->getCurrentRequest();
            $sessionId = $request?->getSession()?->getId() ?? 'unknown';

            // Get customer from context if available
            $customer = $context->getCustomer() ?? null;

            foreach ($lineItems as $lineItem) {
                // Build GA4-compatible item data
                $itemData = [
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'value' => $lineItem->getPrice()?->getTotalPrice() ?? 0,
                    'items' => [
                        [
                            'item_id' => $lineItem->getReferencedId() ?? $lineItem->getId(),
                            'item_name' => $lineItem->getLabel(),
                            'quantity' => $lineItem->getQuantity(),
                            'price' => $lineItem->getPrice()?->getUnitPrice() ?? 0,
                        ]
                    ],
                ];

                $this->jitsuClient->track(
                    'add_to_cart',
                    $itemData,
                    $customer,
                    $sessionId,
                    null // No sales channel context in cart events
                );

                $this->logDebug('Add to cart tracked to Jitsu', [
                    'itemId' => $lineItem->getId(),
                    'label' => $lineItem->getLabel(),
                    'quantity' => $lineItem->getQuantity(),
                ], null);
            }

        } catch (\Throwable $e) {
            $this->logger->error('[Jitsu Tracking] Failed to track add_to_cart event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle remove from cart (remove_from_cart event)
     */
    public function onLineItemRemoved(AfterLineItemRemovedEvent $event): void
    {
        try {
            $lineItems = $event->getLineItems();
            $context = $event->getContext();
            $request = $this->requestStack->getCurrentRequest();
            $sessionId = $request?->getSession()?->getId() ?? 'unknown';

            // Get customer from context if available
            $customer = $context->getCustomer() ?? null;

            foreach ($lineItems as $lineItem) {
                // Build GA4-compatible item data
                $itemData = [
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'value' => $lineItem->getPrice()?->getTotalPrice() ?? 0,
                    'items' => [
                        [
                            'item_id' => $lineItem->getReferencedId() ?? $lineItem->getId(),
                            'item_name' => $lineItem->getLabel(),
                            'quantity' => $lineItem->getQuantity(),
                            'price' => $lineItem->getPrice()?->getUnitPrice() ?? 0,
                        ]
                    ],
                ];

                $this->jitsuClient->track(
                    'remove_from_cart',
                    $itemData,
                    $customer,
                    $sessionId,
                    null // No sales channel context in cart events
                );

                $this->logDebug('Remove from cart tracked to Jitsu', [
                    'itemId' => $lineItem->getId(),
                    'label' => $lineItem->getLabel(),
                    'quantity' => $lineItem->getQuantity(),
                ], null);
            }

        } catch (\Throwable $e) {
            $this->logger->error('[Jitsu Tracking] Failed to track remove_from_cart event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if Jitsu debug mode is enabled
     */
    private function isDebugMode(?string $salesChannelId): bool
    {
        return (bool) $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerJitsuDebug',
            $salesChannelId
        );
    }

    /**
     * Log debug message if debug mode is enabled
     */
    private function logDebug(string $message, array $context, ?string $salesChannelId): void
    {
        if ($this->isDebugMode($salesChannelId)) {
            $this->logger->info('[Jitsu Tracking] ' . $message, $context);
        }
    }
}

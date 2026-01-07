<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use WSC\SWCookieDataLayer\Service\DataLayerBuilder;
use WSC\SWCookieDataLayer\Service\JitsuClient;
use WSC\SWCookieDataLayer\Struct\DataLayerStruct;
use WSC\SWCookieDataLayer\Extension\DataLayerPageExtension;
use Psr\Log\LoggerInterface;

/**
 * DataLayerSubscriber
 *
 * Subscribes to Shopware page events and builds DataLayer events for Google Analytics 4, Matomo, and Jitsu.
 * Implements Issue #22: Event Subscriber für E-Commerce-Events
 * Issue #1: Added PSR-3 Logger and Debug Mode support
 * Added: Jitsu server-side tracking integration
 */
class DataLayerSubscriber implements EventSubscriberInterface
{
    private DataLayerBuilder $dataLayerBuilder;
    private JitsuClient $jitsuClient;
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;

    public function __construct(
        DataLayerBuilder $dataLayerBuilder,
        JitsuClient $jitsuClient,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
        $this->dataLayerBuilder = $dataLayerBuilder;
        $this->jitsuClient = $jitsuClient;
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode(?string $salesChannelId = null): bool
    {
        return (bool) $this->systemConfigService->get(
            'WscSwCookieDataLayer.config.wscTagManagerDataLayerDebug',
            $salesChannelId
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
            ProductListingResultEvent::class => 'onProductListingResult',
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
            CheckoutCartPageLoadedEvent::class => 'onCheckoutCartPageLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishPageLoaded',
        ];
    }

    /**
     * Handle product detail page (view_item event)
     */
    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $debugMode = $this->isDebugMode($event->getSalesChannelContext()->getSalesChannelId());

        try {
            $page = $event->getPage();
            $product = $page->getProduct();

            if (!$product) {
                if ($debugMode) {
                    $this->logger->warning('WSC DataLayer Subscriber: No product found on ProductPageLoadedEvent');
                }
                return;
            }

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: ProductPageLoadedEvent triggered', [
                    'productId' => $product->getId(),
                    'route' => $event->getRequest()->attributes->get('_route'),
                ]);
            }

            $dataLayerEvent = $this->dataLayerBuilder->buildViewItemData(
                $product,
                $event->getSalesChannelContext(),
                $event->getRequest()
            );

            // Use PageExtension instead of assign() to fix PHP 8.2+ deprecation warning
            $dataLayerStruct = new DataLayerStruct(
                $dataLayerEvent,
                $event->getRequest()->attributes->get('_route')
            );
            $page->addExtension('wscDataLayer', new DataLayerPageExtension($dataLayerStruct));

            // Send event to Jitsu
            $this->trackToJitsu(
                'view_item',
                $dataLayerEvent['ecommerce'] ?? [],
                $event->getSalesChannelContext(),
                $event->getRequest()
            );

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: DataLayer extension added to page', [
                    'hasExtension' => $page->hasExtension('wscDataLayer'),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer Subscriber: Exception in onProductPageLoaded', [
                'error' => $e->getMessage(),
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);
        }
    }

    /**
     * Handle product listing result (prepare view_item_list event data)
     * This event fires when products are loaded in listings (categories, search, etc.)
     */
    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $debugMode = $this->isDebugMode($event->getSalesChannelContext()->getSalesChannelId());

        try {
            $result = $event->getResult();
            $request = $event->getRequest();
            $context = $event->getSalesChannelContext();

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: ProductListingResultEvent triggered', [
                    'productsCount' => $result->getEntities()->count(),
                    'route' => $request->attributes->get('_route'),
                ]);
            }

            // Validate result
            if (!$result->getEntities()) {
                if ($debugMode) {
                    $this->logger->warning('WSC DataLayer Subscriber: No entities in ProductListingResultEvent');
                }
                return;
            }

            // Get category information from request
            $navigationId = $request->attributes->get('navigationId');

            // Build view_item_list data
            $dataLayerEvent = $this->dataLayerBuilder->buildViewItemListData(
                $result->getEntities(),
                $context,
                $navigationId ?? 'unknown',
                $request->attributes->get('_route_params')['navigationId'] ?? 'Product Listing'
            );

            // Store in request attributes for later use in NavigationPageLoadedEvent
            $request->attributes->set('wscDataLayerEvent', $dataLayerEvent);

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: ProductListingResult data prepared', [
                    'navigationId' => $navigationId,
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer Subscriber: Exception in onProductListingResult', [
                'error' => $e->getMessage(),
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);
        }
    }

    /**
     * Handle navigation/category pages (view_item_list event)
     * Retrieves data prepared by onProductListingResult
     */
    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $request = $event->getRequest();

        // Check if ProductListingResultEvent has prepared data
        $dataLayerEvent = $request->attributes->get('wscDataLayerEvent');

        if ($dataLayerEvent) {
            // Get category information to update the list name
            $category = $page->getCategory();
            if ($category && isset($dataLayerEvent['ecommerce'])) {
                $dataLayerEvent['ecommerce']['item_list_id'] = $category->getId();
                $dataLayerEvent['ecommerce']['item_list_name'] = $category->getTranslated()['name'] ?? 'Product Listing';
            }

            // Use prepared listing data with PageExtension
            $dataLayerStruct = new DataLayerStruct(
                $dataLayerEvent,
                $request->attributes->get('_route')
            );
            $page->addExtension('wscDataLayer', new DataLayerPageExtension($dataLayerStruct));

            // Send event to Jitsu
            $this->trackToJitsu(
                'view_item_list',
                $dataLayerEvent['ecommerce'] ?? [],
                $event->getSalesChannelContext(),
                $request
            );
        } else {
            // No listing data available (e.g., custom page without product listing)
            // Don't add extension - template will show warning in debug mode
        }
    }

    /**
     * Handle cart page (view_cart event)
     */
    public function onCheckoutCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $debugMode = $this->isDebugMode($event->getSalesChannelContext()->getSalesChannelId());

        try {
            $page = $event->getPage();
            $cart = $page->getCart();

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: CheckoutCartPageLoadedEvent triggered', [
                    'cartToken' => $cart->getToken(),
                    'route' => $event->getRequest()->attributes->get('_route'),
                ]);
            }

            $dataLayerEvent = $this->dataLayerBuilder->buildViewCartData(
                $cart,
                $event->getSalesChannelContext()
            );

            $dataLayerStruct = new DataLayerStruct(
                $dataLayerEvent,
                $event->getRequest()->attributes->get('_route')
            );
            $page->addExtension('wscDataLayer', new DataLayerPageExtension($dataLayerStruct));

            // Send event to Jitsu
            $this->trackToJitsu(
                'view_cart',
                $dataLayerEvent['ecommerce'] ?? [],
                $event->getSalesChannelContext(),
                $event->getRequest()
            );

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: DataLayer extension added to page (view_cart)');
            }

        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer Subscriber: Exception in onCheckoutCartPageLoaded', [
                'error' => $e->getMessage(),
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);
        }
    }

    /**
     * Handle checkout confirm page (begin_checkout event)
     */
    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $debugMode = $this->isDebugMode($event->getSalesChannelContext()->getSalesChannelId());

        try {
            $page = $event->getPage();
            $cart = $page->getCart();

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: CheckoutConfirmPageLoadedEvent triggered', [
                    'cartToken' => $cart->getToken(),
                    'route' => $event->getRequest()->attributes->get('_route'),
                ]);
            }

            $dataLayerEvent = $this->dataLayerBuilder->buildBeginCheckoutData(
                $cart,
                $event->getSalesChannelContext()
            );

            $dataLayerStruct = new DataLayerStruct(
                $dataLayerEvent,
                $event->getRequest()->attributes->get('_route')
            );
            $page->addExtension('wscDataLayer', new DataLayerPageExtension($dataLayerStruct));

            // Send event to Jitsu
            $this->trackToJitsu(
                'begin_checkout',
                $dataLayerEvent['ecommerce'] ?? [],
                $event->getSalesChannelContext(),
                $event->getRequest()
            );

            // Track shipping info if available
            $this->trackShippingInfo($cart, $event->getSalesChannelContext(), $event->getRequest());

            // Track payment info if available
            $this->trackPaymentInfo($cart, $event->getSalesChannelContext(), $event->getRequest());

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: DataLayer extension added to page (begin_checkout)');
            }

        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer Subscriber: Exception in onCheckoutConfirmPageLoaded', [
                'error' => $e->getMessage(),
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);
        }
    }

    /**
     * Handle checkout finish page (purchase event)
     */
    public function onCheckoutFinishPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $debugMode = $this->isDebugMode($event->getSalesChannelContext()->getSalesChannelId());

        try {
            $page = $event->getPage();
            $order = $page->getOrder();

            if (!$order) {
                $this->logger->error('WSC DataLayer Subscriber: CRITICAL - No order found on CheckoutFinishPageLoadedEvent');
                return;
            }

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: CheckoutFinishPageLoadedEvent triggered (PURCHASE)', [
                    'orderId' => $order->getId(),
                    'orderNumber' => $order->getOrderNumber(),
                    'route' => $event->getRequest()->attributes->get('_route'),
                ]);
            }

            $dataLayerEvent = $this->dataLayerBuilder->buildPurchaseData(
                $order,
                $event->getSalesChannelContext()
            );

            $dataLayerStruct = new DataLayerStruct(
                $dataLayerEvent,
                $event->getRequest()->attributes->get('_route')
            );
            $page->addExtension('wscDataLayer', new DataLayerPageExtension($dataLayerStruct));

            // Send event to Jitsu
            $this->trackToJitsu(
                'purchase',
                $dataLayerEvent['ecommerce'] ?? [],
                $event->getSalesChannelContext(),
                $event->getRequest()
            );

            if ($debugMode) {
                $this->logger->info('WSC DataLayer Subscriber: DataLayer extension added to page (PURCHASE)', [
                    'orderNumber' => $order->getOrderNumber(),
                    'hasError' => isset($dataLayerEvent['_wsc_error']),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->critical('WSC DataLayer Subscriber: CRITICAL Exception in onCheckoutFinishPageLoaded (PURCHASE)', [
                'error' => $e->getMessage(),
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);
        }
    }

    /**
     * Helper method to send event to Jitsu
     */
    private function trackToJitsu(
        string $eventName,
        array $properties,
        $salesChannelContext,
        $request
    ): void {
        try {
            // Get session ID from request (fallback to 'unknown' if not available)
            $session = $request->hasSession() ? $request->getSession() : null;
            $sessionId = $session?->getId() ?? 'unknown';

            // Get customer from context (null for guests)
            $customer = $salesChannelContext->getCustomer();

            // Get sales channel ID for config lookup
            $salesChannelId = $salesChannelContext->getSalesChannelId();

            // Track event to Jitsu
            $this->jitsuClient->track(
                $eventName,
                $properties,
                $customer,
                $sessionId,
                $salesChannelId
            );
        } catch (\Throwable $e) {
            // Don't let Jitsu tracking errors break the page
            $this->logger->error('WSC DataLayer Subscriber: Failed to track to Jitsu', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track shipping info if available in cart
     */
    private function trackShippingInfo($cart, $salesChannelContext, $request): void
    {
        try {
            $deliveries = $cart->getDeliveries();

            if ($deliveries && $deliveries->count() > 0) {
                $delivery = $deliveries->first();
                $shippingMethod = $delivery?->getShippingMethod();

                if ($shippingMethod) {
                    // Extract coupon codes from cart
                    $couponCodes = $this->extractCouponCodes($cart);

                    $properties = [
                        'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
                        'value' => $cart->getPrice()->getTotalPrice(),
                        'coupon' => !empty($couponCodes) ? implode(',', $couponCodes) : '',
                        'shipping_tier' => $shippingMethod->getName(),
                    ];

                    $this->trackToJitsu(
                        'add_shipping_info',
                        $properties,
                        $salesChannelContext,
                        $request
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('WSC DataLayer Subscriber: Failed to track shipping info', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track payment info if available in cart
     */
    private function trackPaymentInfo($cart, $salesChannelContext, $request): void
    {
        try {
            $transactions = $cart->getTransactions();

            if ($transactions && $transactions->count() > 0) {
                $transaction = $transactions->first();
                $paymentMethod = $transaction?->getPaymentMethod();

                if ($paymentMethod) {
                    // Extract coupon codes from cart
                    $couponCodes = $this->extractCouponCodes($cart);

                    $properties = [
                        'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
                        'value' => $cart->getPrice()->getTotalPrice(),
                        'coupon' => !empty($couponCodes) ? implode(',', $couponCodes) : '',
                        'payment_type' => $paymentMethod->getName(),
                    ];

                    $this->trackToJitsu(
                        'add_payment_info',
                        $properties,
                        $salesChannelContext,
                        $request
                    );
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('WSC DataLayer Subscriber: Failed to track payment info', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract coupon/promotion codes from cart
     */
    private function extractCouponCodes($cart): array
    {
        $couponCodes = [];

        try {
            foreach ($cart->getLineItems() as $lineItem) {
                // Shopware Promotion Line Items have type = 'promotion'
                if ($lineItem->getType() === 'promotion' && $lineItem->getPayload()) {
                    $payload = $lineItem->getPayload();
                    if (isset($payload['code'])) {
                        $couponCodes[] = $payload['code'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer Subscriber: Failed to extract coupon codes', [
                'error' => $e->getMessage(),
            ]);
        }

        return $couponCodes;
    }
}

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
use WSC\SWCookieDataLayer\Struct\DataLayerStruct;
use WSC\SWCookieDataLayer\Extension\DataLayerPageExtension;
use Psr\Log\LoggerInterface;

/**
 * DataLayerSubscriber
 *
 * Subscribes to Shopware page events and builds DataLayer events for Google Analytics 4 and Matomo.
 * Implements Issue #22: Event Subscriber für E-Commerce-Events
 * Issue #1: Added PSR-3 Logger and Debug Mode support
 */
class DataLayerSubscriber implements EventSubscriberInterface
{
    private DataLayerBuilder $dataLayerBuilder;
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;

    public function __construct(
        DataLayerBuilder $dataLayerBuilder,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
        $this->dataLayerBuilder = $dataLayerBuilder;
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
        $result = $event->getResult();
        $request = $event->getRequest();
        $context = $event->getSalesChannelContext();

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
}

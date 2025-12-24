<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use WSC\SWCookieDataLayer\Service\DataLayerBuilder;

/**
 * DataLayerSubscriber
 *
 * Subscribes to Shopware page events and builds DataLayer events for Google Analytics 4 and Matomo.
 * Implements Issue #22: Event Subscriber für E-Commerce-Events
 */
class DataLayerSubscriber implements EventSubscriberInterface
{
    private DataLayerBuilder $dataLayerBuilder;

    public function __construct(DataLayerBuilder $dataLayerBuilder)
    {
        $this->dataLayerBuilder = $dataLayerBuilder;
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
        $page = $event->getPage();
        $product = $page->getProduct();

        if (!$product) {
            return;
        }

        $dataLayerEvent = $this->dataLayerBuilder->buildViewItemData(
            $product,
            $event->getSalesChannelContext(),
            $event->getRequest()
        );

        $page->assign([
            'wscDataLayerEvent' => $dataLayerEvent,
            'activeRoute' => $event->getRequest()->attributes->get('_route'),
        ]);
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

            // Use prepared listing data
            $page->assign([
                'wscDataLayerEvent' => $dataLayerEvent,
                'activeRoute' => $request->attributes->get('_route'),
            ]);
        } else {
            // No listing data available (e.g., custom page without product listing)
            $page->assign([
                'activeRoute' => $request->attributes->get('_route'),
            ]);
        }
    }

    /**
     * Handle cart page (view_cart event)
     */
    public function onCheckoutCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $cart = $page->getCart();

        $dataLayerEvent = $this->dataLayerBuilder->buildViewCartData(
            $cart,
            $event->getSalesChannelContext()
        );

        $page->assign([
            'wscDataLayerEvent' => $dataLayerEvent,
            'activeRoute' => $event->getRequest()->attributes->get('_route'),
        ]);
    }

    /**
     * Handle checkout confirm page (begin_checkout event)
     */
    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $cart = $page->getCart();

        $dataLayerEvent = $this->dataLayerBuilder->buildBeginCheckoutData(
            $cart,
            $event->getSalesChannelContext()
        );

        $page->assign([
            'wscDataLayerEvent' => $dataLayerEvent,
            'activeRoute' => $event->getRequest()->attributes->get('_route'),
        ]);
    }

    /**
     * Handle checkout finish page (purchase event)
     */
    public function onCheckoutFinishPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $order = $page->getOrder();

        if (!$order) {
            return;
        }

        $dataLayerEvent = $this->dataLayerBuilder->buildPurchaseData(
            $order,
            $event->getSalesChannelContext()
        );

        $page->assign([
            'wscDataLayerEvent' => $dataLayerEvent,
            'activeRoute' => $event->getRequest()->attributes->get('_route'),
        ]);
    }
}

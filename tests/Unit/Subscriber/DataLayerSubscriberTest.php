<?php

declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;
use WSC\SWCookieDataLayer\Subscriber\DataLayerSubscriber;
use WSC\SWCookieDataLayer\Service\DataLayerBuilder;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Unit Tests for DataLayerSubscriber
 *
 * Tests that the subscriber correctly listens to events and calls the DataLayerBuilder
 *
 * @covers \WSC\SWCookieDataLayer\Subscriber\DataLayerSubscriber
 */
class DataLayerSubscriberTest extends TestCase
{
    private DataLayerSubscriber $subscriber;
    private DataLayerBuilder $dataLayerBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock DataLayerBuilder
        $this->dataLayerBuilder = $this->createMock(DataLayerBuilder::class);

        $this->subscriber = new DataLayerSubscriber($this->dataLayerBuilder);
    }

    /**
     * Test: Subscriber subscribes to correct events
     */
    public function testGetSubscribedEvents(): void
    {
        $events = DataLayerSubscriber::getSubscribedEvents();

        $this->assertIsArray($events);

        // Assert key events are subscribed
        $this->assertArrayHasKey(ProductPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(CheckoutFinishPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(CheckoutConfirmPageLoadedEvent::class, $events);

        // Assert correct method names
        $this->assertEquals('onProductPageLoaded', $events[ProductPageLoadedEvent::class]);
        $this->assertEquals('onCheckoutFinishPageLoaded', $events[CheckoutFinishPageLoadedEvent::class]);
        $this->assertEquals('onCheckoutConfirmPageLoaded', $events[CheckoutConfirmPageLoadedEvent::class]);
    }

    /**
     * Test: onProductPageLoaded calls DataLayerBuilder
     */
    public function testOnProductPageLoadedCallsBuilder(): void
    {
        // Mock product page event
        $event = $this->createProductPageLoadedEvent();

        // Expect builder to be called with product and context
        $this->dataLayerBuilder
            ->expects($this->once())
            ->method('buildViewItemData')
            ->with(
                $this->anything(), // product
                $this->anything()  // context
            )
            ->willReturn([
                'dataLayerEvent' => ['event' => 'view_item'],
                'activeRoute' => 'product.detail'
            ]);

        // Execute
        $this->subscriber->onProductPageLoaded($event);

        // Assert page extension was set (we can't directly check this without accessing page)
        $this->assertTrue(true); // If no exception thrown, test passes
    }

    /**
     * Test: onCheckoutFinishPageLoaded calls DataLayerBuilder
     */
    public function testOnCheckoutFinishPageLoadedCallsBuilder(): void
    {
        // Mock checkout finish page event
        $event = $this->createCheckoutFinishPageLoadedEvent();

        // Expect builder to be called
        $this->dataLayerBuilder
            ->expects($this->once())
            ->method('buildPurchaseData')
            ->with(
                $this->anything(), // order
                $this->anything()  // context
            )
            ->willReturn([
                'dataLayerEvent' => ['event' => 'purchase'],
                'activeRoute' => 'checkout.finish'
            ]);

        // Execute
        $this->subscriber->onCheckoutFinishPageLoaded($event);

        $this->assertTrue(true); // If no exception thrown, test passes
    }

    /**
     * Test: onCheckoutConfirmPageLoaded calls DataLayerBuilder
     */
    public function testOnCheckoutConfirmPageLoadedCallsBuilder(): void
    {
        // Mock checkout confirm page event
        $event = $this->createCheckoutConfirmPageLoadedEvent();

        // Expect builder to be called
        $this->dataLayerBuilder
            ->expects($this->once())
            ->method('buildBeginCheckoutData')
            ->with(
                $this->anything(), // cart
                $this->anything()  // context
            )
            ->willReturn([
                'dataLayerEvent' => ['event' => 'begin_checkout'],
                'activeRoute' => 'checkout.confirm'
            ]);

        // Execute
        $this->subscriber->onCheckoutConfirmPageLoaded($event);

        $this->assertTrue(true); // If no exception thrown, test passes
    }

    /**
     * Test: Subscriber handles missing product gracefully
     */
    public function testHandlesMissingProductGracefully(): void
    {
        // Mock event with null product
        $event = $this->createProductPageLoadedEvent(null);

        // Builder should not be called
        $this->dataLayerBuilder
            ->expects($this->never())
            ->method('buildViewItemData');

        // Should not throw exception
        $this->expectNotToPerformAssertions();
        $this->subscriber->onProductPageLoaded($event);
    }

    // ==================== Helper Methods ====================

    /**
     * Create a mock ProductPageLoadedEvent
     */
    private function createProductPageLoadedEvent($product = 'mock'): ProductPageLoadedEvent
    {
        $page = $this->createMock(ProductPage::class);

        if ($product === null) {
            $page->method('getProduct')->willReturn(null);
        } else {
            $mockProduct = $this->createMock(\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::class);
            $mockProduct->method('getId')->willReturn('product-123');
            $page->method('getProduct')->willReturn($mockProduct);
        }

        $context = $this->createMock(SalesChannelContext::class);
        $request = new Request();

        return new ProductPageLoadedEvent($page, $context, $request);
    }

    /**
     * Create a mock CheckoutFinishPageLoadedEvent
     */
    private function createCheckoutFinishPageLoadedEvent(): CheckoutFinishPageLoadedEvent
    {
        $page = $this->createMock(CheckoutFinishPage::class);

        // Mock order
        $order = $this->createMock(\Shopware\Core\Checkout\Order\OrderEntity::class);
        $order->method('getId')->willReturn('order-123');
        $page->method('getOrder')->willReturn($order);

        $context = $this->createMock(SalesChannelContext::class);
        $request = new Request();

        return new CheckoutFinishPageLoadedEvent($page, $context, $request);
    }

    /**
     * Create a mock CheckoutConfirmPageLoadedEvent
     */
    private function createCheckoutConfirmPageLoadedEvent(): CheckoutConfirmPageLoadedEvent
    {
        $page = $this->createMock(CheckoutConfirmPage::class);

        // Mock cart
        $cart = $this->createMock(\Shopware\Core\Checkout\Cart\Cart::class);
        $page->method('getCart')->willReturn($cart);

        $context = $this->createMock(SalesChannelContext::class);
        $request = new Request();

        return new CheckoutConfirmPageLoadedEvent($page, $context, $request);
    }
}

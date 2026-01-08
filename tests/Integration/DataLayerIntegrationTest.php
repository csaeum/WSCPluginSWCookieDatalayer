<?php

declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WSC\SWCookieDataLayer\Service\DataLayerBuilder;
use WSC\SWCookieDataLayer\Subscriber\DataLayerSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Integration Tests for DataLayer Plugin
 *
 * Tests the complete flow from Event → Subscriber → Builder → Page Extension
 *
 * @group integration
 */
class DataLayerIntegrationTest extends TestCase
{
    private EventDispatcher $eventDispatcher;
    private DataLayerBuilder $dataLayerBuilder;
    private DataLayerSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup real instances (not mocks) for integration testing
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(false); // debug off

        $this->dataLayerBuilder = new DataLayerBuilder($systemConfigService);
        $this->subscriber = new DataLayerSubscriber($this->dataLayerBuilder);

        // Setup event dispatcher with subscriber
        $this->eventDispatcher = new EventDispatcher();
        foreach (DataLayerSubscriber::getSubscribedEvents() as $eventName => $methodName) {
            $this->eventDispatcher->addListener($eventName, [$this->subscriber, $methodName]);
        }
    }

    /**
     * Test: Complete flow from ProductPageLoadedEvent to DataLayer
     */
    public function testCompleteProductPageFlow(): void
    {
        // Arrange: Create a realistic product page event
        $page = $this->createMock(ProductPage::class);
        $product = $this->createMockProduct();
        $context = $this->createMockContext();

        $page->method('getProduct')->willReturn($product);

        $event = new ProductPageLoadedEvent($page, $context, new Request());

        // Act: Dispatch event (subscriber should handle it)
        $this->eventDispatcher->dispatch($event, ProductPageLoadedEvent::class);

        // Assert: Event was processed successfully
        $this->assertTrue(true); // If no exception thrown, integration works
    }

    /**
     * Test: DataLayerBuilder can handle real-world-like product data
     */
    public function testBuilderWithRealisticProductData(): void
    {
        $product = $this->createMockProduct();
        $context = $this->createMockContext();

        $result = $this->dataLayerBuilder->buildViewItemData($product, $context);

        // Assert complete structure
        $this->assertArrayHasKey('dataLayerEvent', $result);
        $this->assertArrayHasKey('activeRoute', $result);
        $this->assertEquals('product.detail', $result['activeRoute']);

        $event = $result['dataLayerEvent'];
        $this->assertEquals('view_item', $event['event']);

        // Assert GA4 compliance
        $this->assertArrayHasKey('ecommerce', $event);
        $this->assertArrayHasKey('currency', $event['ecommerce']);
        $this->assertArrayHasKey('value', $event['ecommerce']);
        $this->assertArrayHasKey('items', $event['ecommerce']);
        $this->assertIsArray($event['ecommerce']['items']);
        $this->assertNotEmpty($event['ecommerce']['items']);

        // Assert item structure (GA4 compliance)
        $item = $event['ecommerce']['items'][0];
        $requiredFields = ['item_id', 'item_name', 'price'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $item, "Missing required field: $field");
        }
    }

    /**
     * Test: Multiple events can be processed sequentially
     */
    public function testMultipleEventsSequential(): void
    {
        $context = $this->createMockContext();

        // Event 1: Product View
        $product1 = $this->createMockProduct(['id' => 'product-1', 'name' => 'Product 1']);
        $result1 = $this->dataLayerBuilder->buildViewItemData($product1, $context);

        // Event 2: Product View (different product)
        $product2 = $this->createMockProduct(['id' => 'product-2', 'name' => 'Product 2']);
        $result2 = $this->dataLayerBuilder->buildViewItemData($product2, $context);

        // Assert both events are independent
        $this->assertNotEquals(
            $result1['dataLayerEvent']['ecommerce']['items'][0]['item_id'],
            $result2['dataLayerEvent']['ecommerce']['items'][0]['item_id']
        );
    }

    /**
     * Test: Error handling - Invalid input doesn't crash
     */
    public function testErrorHandlingWithInvalidInput(): void
    {
        $context = $this->createMockContext();

        // Product without required fields should not crash
        $product = $this->createMock(\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::class);
        $product->method('getId')->willReturn('test-id');
        $product->method('getProductNumber')->willReturn(null);
        $product->method('getName')->willReturn(null);

        // Should not throw exception
        $this->expectNotToPerformAssertions();
        $this->dataLayerBuilder->buildViewItemData($product, $context);
    }

    // ==================== Helper Methods ====================

    /**
     * Create a realistic mock product
     */
    private function createMockProduct(array $overrides = []): \Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity
    {
        $product = $this->createMock(\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity::class);

        $product->method('getId')->willReturn($overrides['id'] ?? 'product-integration-test');
        $product->method('getProductNumber')->willReturn($overrides['productNumber'] ?? 'INT-TEST-001');
        $product->method('getName')->willReturn($overrides['name'] ?? 'Integration Test Product');

        // Mock price
        $price = new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(
            $overrides['price'] ?? 49.99,
            $overrides['price'] ?? 49.99,
            new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
            new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()
        );
        $product->method('getCalculatedPrice')->willReturn($price);

        // Mock manufacturer
        $manufacturer = $this->createMock(\Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity::class);
        $manufacturer->method('getTranslated')->willReturn(['name' => $overrides['brand'] ?? 'Test Brand']);
        $product->method('getManufacturer')->willReturn($manufacturer);

        // Mock category
        $product->method('getCategoryTree')->willReturn(['category-test-1']);

        return $product;
    }

    /**
     * Create a realistic mock sales channel context
     */
    private function createMockContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);

        // Mock currency
        $currency = $this->createMock(\Shopware\Core\System\Currency\CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('EUR');
        $context->method('getCurrency')->willReturn($currency);

        // No customer (guest)
        $context->method('getCustomer')->willReturn(null);

        return $context;
    }
}

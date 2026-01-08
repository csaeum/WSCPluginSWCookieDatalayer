<?php

declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use WSC\SWCookieDataLayer\Service\DataLayerBuilder;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;

/**
 * Unit Tests for DataLayerBuilder Service
 *
 * Tests the core functionality of building GA4/Matomo-compatible data structures
 *
 * @covers \WSC\SWCookieDataLayer\Service\DataLayerBuilder
 */
class DataLayerBuilderTest extends TestCase
{
    private DataLayerBuilder $dataLayerBuilder;
    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock SystemConfigService
        $this->systemConfigService = $this->createMock(SystemConfigService::class);

        // Default config: debug mode off, tracking enabled
        $this->systemConfigService->method('get')
            ->willReturnCallback(function ($key) {
                $config = [
                    'WscSwCookieDataLayer.config.wscTagManagerDataLayerDebug' => false,
                    'WscSwCookieDataLayer.config.wscTagManagerDataLayer' => true,
                ];
                return $config[$key] ?? null;
            });

        $this->dataLayerBuilder = new DataLayerBuilder($this->systemConfigService);
    }

    /**
     * Test: buildViewItemData() with valid product
     */
    public function testBuildViewItemDataWithValidProduct(): void
    {
        $product = $this->createMockProduct();
        $context = $this->createMockSalesChannelContext();

        $result = $this->dataLayerBuilder->buildViewItemData($product, $context);

        // Assert structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('dataLayerEvent', $result);

        $event = $result['dataLayerEvent'];
        $this->assertEquals('view_item', $event['event']);
        $this->assertArrayHasKey('ecommerce', $event);

        // Assert ecommerce data
        $ecommerce = $event['ecommerce'];
        $this->assertEquals('EUR', $ecommerce['currency']);
        $this->assertEquals(99.99, $ecommerce['value']);
        $this->assertArrayHasKey('items', $ecommerce);
        $this->assertCount(1, $ecommerce['items']);

        // Assert item data
        $item = $ecommerce['items'][0];
        $this->assertEquals('TEST-PRODUCT-123', $item['item_id']);
        $this->assertEquals('Test Product', $item['item_name']);
        $this->assertEquals(99.99, $item['price']);
        $this->assertEquals('Test Brand', $item['item_brand']);
        $this->assertEquals('Test Category', $item['item_category']);
    }

    /**
     * Test: buildViewItemData() with product without price
     */
    public function testBuildViewItemDataWithoutPrice(): void
    {
        $product = $this->createMockProduct([
            'calculatedPrice' => null
        ]);
        $context = $this->createMockSalesChannelContext();

        $result = $this->dataLayerBuilder->buildViewItemData($product, $context);

        $event = $result['dataLayerEvent'];
        $this->assertEquals(0.0, $event['ecommerce']['value']);
        $this->assertEquals(0.0, $event['ecommerce']['items'][0]['price']);
    }

    /**
     * Test: buildViewCartData() with empty cart
     */
    public function testBuildViewCartDataWithEmptyCart(): void
    {
        $cart = $this->createMockCart([]);
        $context = $this->createMockSalesChannelContext();

        $result = $this->dataLayerBuilder->buildViewCartData($cart, $context);

        $event = $result['dataLayerEvent'];
        $this->assertEquals('view_cart', $event['event']);
        $this->assertEquals(0.0, $event['ecommerce']['value']);
        $this->assertEmpty($event['ecommerce']['items']);
    }

    /**
     * Test: buildViewCartData() with items
     */
    public function testBuildViewCartDataWithItems(): void
    {
        $cart = $this->createMockCart([
            $this->createMockLineItem('PRODUCT-1', 'Product 1', 10.00, 2),
            $this->createMockLineItem('PRODUCT-2', 'Product 2', 20.00, 1),
        ]);
        $context = $this->createMockSalesChannelContext();

        $result = $this->dataLayerBuilder->buildViewCartData($cart, $context);

        $event = $result['dataLayerEvent'];
        $this->assertEquals('view_cart', $event['event']);
        $this->assertEquals(40.0, $event['ecommerce']['value']); // 10*2 + 20*1
        $this->assertCount(2, $event['ecommerce']['items']);
    }

    /**
     * Test: buildBeginCheckoutData()
     */
    public function testBuildBeginCheckoutData(): void
    {
        $cart = $this->createMockCart([
            $this->createMockLineItem('PRODUCT-1', 'Product 1', 50.00, 1),
        ]);
        $context = $this->createMockSalesChannelContext();

        $result = $this->dataLayerBuilder->buildBeginCheckoutData($cart, $context);

        $event = $result['dataLayerEvent'];
        $this->assertEquals('begin_checkout', $event['event']);
        $this->assertEquals(50.0, $event['ecommerce']['value']);
        $this->assertCount(1, $event['ecommerce']['items']);
    }

    /**
     * Test: User data is included when customer is logged in
     */
    public function testUserDataIncludedWhenCustomerLoggedIn(): void
    {
        $product = $this->createMockProduct();
        $context = $this->createMockSalesChannelContext(true); // with customer

        $result = $this->dataLayerBuilder->buildViewItemData($product, $context);

        $event = $result['dataLayerEvent'];
        $this->assertArrayHasKey('user', $event);
        $this->assertEquals('test@example.com', $event['user']['user_email']);
        $this->assertEquals('Germany', $event['user']['user_country']);
        $this->assertEquals('Berlin', $event['user']['user_city']);
    }

    /**
     * Test: User data is empty for guest users
     */
    public function testUserDataEmptyForGuests(): void
    {
        $product = $this->createMockProduct();
        $context = $this->createMockSalesChannelContext(false); // without customer

        $result = $this->dataLayerBuilder->buildViewItemData($product, $context);

        $event = $result['dataLayerEvent'];
        $this->assertArrayHasKey('user', $event);
        $this->assertEmpty($event['user']['user_email']);
    }

    // ==================== Helper Methods ====================

    /**
     * Create a mock product
     */
    private function createMockProduct(array $overrides = []): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product-id-123');
        $product->setProductNumber($overrides['productNumber'] ?? 'TEST-PRODUCT-123');
        $product->setName($overrides['name'] ?? 'Test Product');

        // Mock calculated price
        if (!isset($overrides['calculatedPrice']) || $overrides['calculatedPrice'] !== null) {
            $price = new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(
                99.99,
                99.99,
                new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
                new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()
            );
            $product->setCalculatedPrice($price);
        }

        // Mock manufacturer
        if (!isset($overrides['manufacturer'])) {
            $manufacturer = $this->createMock(\Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity::class);
            $manufacturer->method('getTranslated')->willReturn(['name' => 'Test Brand']);
            $product->setManufacturer($manufacturer);
        }

        // Mock category
        $product->setCategoryTree(['category-id-1']);

        return $product;
    }

    /**
     * Create a mock sales channel context
     */
    private function createMockSalesChannelContext(bool $withCustomer = false): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);

        // Mock currency
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $context->method('getCurrency')->willReturn($currency);

        // Mock customer if requested
        if ($withCustomer) {
            $customer = $this->createMockCustomer();
            $context->method('getCustomer')->willReturn($customer);
        } else {
            $context->method('getCustomer')->willReturn(null);
        }

        return $context;
    }

    /**
     * Create a mock customer
     */
    private function createMockCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setEmail('test@example.com');

        // Mock billing address
        $address = new CustomerAddressEntity();
        $address->setCity('Berlin');

        // Mock country
        $country = new CountryEntity();
        $country->setTranslated(['name' => 'Germany']);
        $address->setCountry($country);

        $customer->setDefaultBillingAddress($address);

        return $customer;
    }

    /**
     * Create a mock cart
     */
    private function createMockCart(array $lineItems = []): Cart
    {
        $cart = new Cart('test-cart');

        foreach ($lineItems as $lineItem) {
            $cart->add($lineItem);
        }

        return $cart;
    }

    /**
     * Create a mock line item
     */
    private function createMockLineItem(string $id, string $label, float $price, int $quantity): \Shopware\Core\Checkout\Cart\LineItem\LineItem
    {
        $lineItem = new \Shopware\Core\Checkout\Cart\LineItem\LineItem($id, 'product', null, $quantity);
        $lineItem->setLabel($label);

        $calculatedPrice = new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(
            $price,
            $price * $quantity,
            new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(),
            new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()
        );
        $lineItem->setPrice($calculatedPrice);

        return $lineItem;
    }
}

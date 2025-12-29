<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

/**
 * DataLayerBuilder
 *
 * Service to build DataLayer data for Google Analytics 4 and Matomo.
 * Replaces Twig logic with clean PHP code (Issue #22).
 *
 * Issue #1: Added PSR-3 Logger and Debug Mode support
 */
class DataLayerBuilder
{
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
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
    /**
     * Build view_item event data for product detail page
     */
    public function buildViewItemData(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
        Request $request
    ): array {
        $debugMode = $this->isDebugMode($context->getSalesChannelId());

        try {
            if ($debugMode) {
                $this->logger->info('WSC DataLayer: Building view_item event', [
                    'productId' => $product->getId(),
                    'productNumber' => $product->getProductNumber(),
                ]);
            }

            // Validate required data
            if (!$product->getProductNumber()) {
                $this->logger->warning('WSC DataLayer: Product has no product number', [
                    'productId' => $product->getId(),
                ]);
            }

            $items = [
                [
                    'item_id' => $product->getProductNumber() ?? '',
                    'item_name' => $product->getTranslated()['name'] ?? 'Unknown Product',
                    'affiliation' => $request->cookies->get('partner', 'kein_Partner'),
                    'currency' => $context->getCurrency()->getIsoCode(),
                ]
            ];

            // Add discount if available
            $calculatedPrice = $product->getCalculatedPrice();
            if ($calculatedPrice && $calculatedPrice->getListPrice() && $calculatedPrice->getListPrice()->getPercentage() > 0) {
                $items[0]['discount'] = $calculatedPrice->getListPrice()->getPercentage();
            }

            // Add brand/manufacturer
            if ($product->getManufacturer()) {
                $items[0]['item_brand'] = $product->getManufacturer()->getTranslated()['name'] ?? '';
            }

            // Add categories
            if ($product->getSeoCategory() && $product->getSeoCategory()->getTranslated()['breadcrumb']) {
                $breadcrumbs = $product->getSeoCategory()->getTranslated()['breadcrumb'];
                $catIndex = 0;
                foreach ($breadcrumbs as $breadcrumb) {
                    $key = $catIndex === 0 ? 'item_category' : 'item_category' . ($catIndex + 1);
                    $items[0][$key] = $breadcrumb;
                    $catIndex++;
                }
            }

            // Add variant information
            if ($product->getVariation() && count($product->getVariation()) > 0) {
                $variants = [];
                foreach ($product->getVariation() as $variation) {
                    $variants[] = ($variation['group'] ?? '') . ':' . ($variation['option'] ?? '');
                }
                $items[0]['item_variant'] = implode('|', $variants);
            }

            // Add price
            $productPrice = 0;
            if ($product->getPrice()) {
                foreach ($product->getPrice() as $priceItem) {
                    $productPrice = $priceItem->getNet();
                    break;
                }
            }
            $items[0]['price'] = $productPrice;
            $items[0]['quantity'] = $product->getMinPurchase() ?? 1;

            $result = [
                'event' => 'view_item',
                'ecommerce' => [
                    'items' => $items,
                ],
                'user' => $this->buildUserData($context),
            ];

            // Add debug info if debug mode is enabled
            if ($debugMode) {
                $result['_wsc_debug'] = [
                    'event_type' => 'view_item',
                    'productNumber' => $product->getProductNumber() ?? 'NULL',
                    'productId' => $product->getId() ?? 'NULL',
                    'timestamp' => date('Y-m-d H:i:s'),
                ];

                $this->logger->info('WSC DataLayer: view_item event built successfully', [
                    'items_count' => count($items),
                    'product' => $product->getProductNumber(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer: Failed to build view_item event', [
                'error' => $e->getMessage(),
                'productId' => $product->getId() ?? 'NULL',
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);

            // Return minimal event data on error
            return [
                'event' => 'view_item',
                '_wsc_error' => 'Failed to build complete event data',
                'ecommerce' => [
                    'items' => [],
                ],
            ];
        }
    }

    /**
     * Build view_item_list event data for category/navigation pages
     */
    public function buildViewItemListData(
        ProductCollection $products,
        SalesChannelContext $context,
        string $listId,
        string $listName
    ): array {
        $items = [];
        $index = 1;

        foreach ($products as $product) {
            $item = [
                'item_id' => $product->getProductNumber() ?? '',
                'item_name' => $product->getTranslated()['name'] ?? '',
                'affiliation' => 'kein_Partner',
                'currency' => $context->getCurrency()->getIsoCode(),
                'index' => $index++,
            ];

            // Add brand
            if ($product->getManufacturer()) {
                $item['item_brand'] = $product->getManufacturer()->getTranslated()['name'] ?? '';
            }

            // Add categories
            if ($product->getSeoCategory() && $product->getSeoCategory()->getTranslated()['breadcrumb']) {
                $breadcrumbs = $product->getSeoCategory()->getTranslated()['breadcrumb'];
                $catIndex = 0;
                foreach ($breadcrumbs as $breadcrumb) {
                    $key = $catIndex === 0 ? 'item_category' : 'item_category' . ($catIndex + 1);
                    $item[$key] = $breadcrumb;
                    $catIndex++;
                }
            }

            // Add variant
            if ($product->getVariation() && count($product->getVariation()) > 0) {
                $variants = [];
                foreach ($product->getVariation() as $variation) {
                    $variants[] = ($variation['group'] ?? '') . ':' . ($variation['option'] ?? '');
                }
                $item['item_variant'] = implode('|', $variants);
            }

            // Add price
            $calculatedPrice = $product->getCalculatedPrice();
            if ($calculatedPrice) {
                $item['price'] = $calculatedPrice->getUnitPrice();
                $item['price_net'] = $calculatedPrice->getUnitPrice();
                $item['price_gross'] = $calculatedPrice->getTotalPrice();
            }

            $item['quantity'] = 1;

            $items[] = $item;
        }

        return [
            'event' => 'view_item_list',
            'ecommerce' => [
                'item_list_id' => $listId,
                'item_list_name' => $listName,
                'items' => $items,
            ],
            'user' => $this->buildUserData($context),
        ];
    }

    /**
     * Build view_cart event data
     */
    public function buildViewCartData(Cart $cart, SalesChannelContext $context): array
    {
        $debugMode = $this->isDebugMode($context->getSalesChannelId());

        try {
            if ($debugMode) {
                $this->logger->info('WSC DataLayer: Building view_cart event', [
                    'cartToken' => $cart->getToken(),
                    'lineItemsCount' => $cart->getLineItems()->count(),
                ]);
            }

            $items = [];

            foreach ($cart->getLineItems() as $lineItem) {
                $items[] = [
                    'item_id' => $lineItem->getReferencedId() ?? '',
                    'item_name' => $lineItem->getLabel() ?? '',
                    'affiliation' => 'kein_Partner',
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'price' => $lineItem->getPrice() ? $lineItem->getPrice()->getTotalPrice() : 0,
                    'quantity' => $lineItem->getQuantity(),
                ];
            }

            $result = [
                'event' => 'view_cart',
                'ecommerce' => [
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'value' => $cart->getPrice()->getTotalPrice(),
                    'items' => $items,
                ],
                'user' => $this->buildUserData($context),
            ];

            if ($debugMode) {
                $result['_wsc_debug'] = [
                    'event_type' => 'view_cart',
                    'items_count' => count($items),
                    'cart_value' => $cart->getPrice()->getTotalPrice(),
                    'timestamp' => date('Y-m-d H:i:s'),
                ];

                $this->logger->info('WSC DataLayer: view_cart event built successfully', [
                    'items_count' => count($items),
                    'total_value' => $cart->getPrice()->getTotalPrice(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('WSC DataLayer: Failed to build view_cart event', [
                'error' => $e->getMessage(),
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);

            return [
                'event' => 'view_cart',
                '_wsc_error' => 'Failed to build complete event data',
                'ecommerce' => [
                    'items' => [],
                ],
            ];
        }
    }

    /**
     * Build begin_checkout event data
     */
    public function buildBeginCheckoutData(Cart $cart, SalesChannelContext $context): array
    {
        $items = [];

        foreach ($cart->getLineItems() as $lineItem) {
            $items[] = [
                'item_id' => $lineItem->getReferencedId() ?? '',
                'item_name' => $lineItem->getLabel() ?? '',
                'affiliation' => 'kein_Partner',
                'currency' => $context->getCurrency()->getIsoCode(),
                'price' => $lineItem->getPrice()->getTotalPrice(),
                'quantity' => $lineItem->getQuantity(),
            ];
        }

        return [
            'event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $cart->getPrice()->getTotalPrice(),
                'items' => $items,
            ],
            'user' => $this->buildUserData($context),
        ];
    }

    /**
     * Build purchase event data
     */
    public function buildPurchaseData(OrderEntity $order, SalesChannelContext $context): array
    {
        $debugMode = $this->isDebugMode($context->getSalesChannelId());

        try {
            if ($debugMode) {
                $this->logger->info('WSC DataLayer: Building purchase event', [
                    'orderId' => $order->getId(),
                    'orderNumber' => $order->getOrderNumber(),
                    'lineItemsCount' => $order->getLineItems() ? $order->getLineItems()->count() : 0,
                ]);
            }

            // Validate critical data
            if (!$order->getOrderNumber()) {
                $this->logger->error('WSC DataLayer: Order has no order number', [
                    'orderId' => $order->getId(),
                ]);
                throw new \RuntimeException('Order number is missing');
            }

            $items = [];

            foreach ($order->getLineItems() as $lineItem) {
                $items[] = [
                    'item_id' => $lineItem->getReferencedId() ?? '',
                    'item_name' => $lineItem->getLabel() ?? '',
                    'affiliation' => 'kein_Partner',
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'price' => $lineItem->getTotalPrice(),
                    'quantity' => $lineItem->getQuantity(),
                ];
            }

            $result = [
                'event' => 'purchase',
                'ecommerce' => [
                    'transaction_id' => $order->getOrderNumber(),
                    'affiliation' => 'kein_Partner',
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'value' => $order->getPrice()->getTotalPrice(),
                    'tax' => $order->getPrice()->getCalculatedTaxes()->getAmount(),
                    'shipping' => $order->getShippingCosts()->getTotalPrice(),
                    'items' => $items,
                ],
                'user' => $this->buildUserData($context),
            ];

            if ($debugMode) {
                $result['_wsc_debug'] = [
                    'event_type' => 'purchase',
                    'transaction_id' => $order->getOrderNumber(),
                    'items_count' => count($items),
                    'order_value' => $order->getPrice()->getTotalPrice(),
                    'timestamp' => date('Y-m-d H:i:s'),
                ];

                $this->logger->info('WSC DataLayer: purchase event built successfully', [
                    'order_number' => $order->getOrderNumber(),
                    'items_count' => count($items),
                    'total_value' => $order->getPrice()->getTotalPrice(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->critical('WSC DataLayer: CRITICAL - Failed to build purchase event', [
                'error' => $e->getMessage(),
                'orderId' => $order->getId() ?? 'NULL',
                'orderNumber' => $order->getOrderNumber() ?? 'NULL',
                'trace' => $debugMode ? $e->getTraceAsString() : 'Enable debug mode for stack trace',
            ]);

            // Return minimal event data on error - purchase events are critical!
            return [
                'event' => 'purchase',
                '_wsc_error' => 'CRITICAL: Failed to build complete purchase event data',
                'ecommerce' => [
                    'transaction_id' => $order->getOrderNumber() ?? 'ERROR',
                    'items' => [],
                ],
            ];
        }
    }

    /**
     * Build user data
     */
    private function buildUserData(SalesChannelContext $context): array
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            return [
                'user_email' => '',
                'user_country' => '',
                'user_city' => '',
            ];
        }

        $billingAddress = $customer->getDefaultBillingAddress();

        return [
            'user_email' => $customer->getEmail() ?? '',
            'user_country' => $billingAddress && $billingAddress->getCountry()
                ? $billingAddress->getCountry()->getTranslated()['name'] ?? ''
                : '',
            'user_city' => $billingAddress ? $billingAddress->getCity() ?? '' : '',
        ];
    }
}

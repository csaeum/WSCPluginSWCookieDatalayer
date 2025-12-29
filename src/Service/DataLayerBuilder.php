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

            // Add price (Issue #2: Fixed price calculation - Brutto/Netto/Tax)
            // NOTE: price_net and price_gross are NOT GA4 standard, but useful for GTM calculations
            $calculatedPrice = $product->getCalculatedPrice();
            if ($calculatedPrice) {
                // GA4 Standard: price = Brutto (with tax)
                $items[0]['price'] = $calculatedPrice->getUnitPrice(); // Brutto per unit

                // Extra fields for GTM (not GA4 standard):
                $items[0]['price_net'] = $calculatedPrice->getUnitPrice() - ($calculatedPrice->getCalculatedTaxes()->getAmount() / $calculatedPrice->getQuantity());
                $items[0]['price_gross'] = $calculatedPrice->getUnitPrice(); // Same as price

                // Tax per unit (optional, for GTM)
                $taxAmount = $calculatedPrice->getCalculatedTaxes()->getAmount() / $calculatedPrice->getQuantity();
                if ($taxAmount > 0) {
                    $items[0]['tax'] = round($taxAmount, 2);
                }
            } else {
                // Fallback if no calculated price
                $items[0]['price'] = 0;
                $items[0]['price_net'] = 0;
                $items[0]['price_gross'] = 0;
            }

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

            // Add price (Issue #2: Fixed price calculation)
            // NOTE: price_net and price_gross are NOT GA4 standard, but useful for GTM
            $calculatedPrice = $product->getCalculatedPrice();
            if ($calculatedPrice) {
                // GA4 Standard: price = Brutto per unit
                $item['price'] = $calculatedPrice->getUnitPrice(); // Brutto per unit

                // Extra fields for GTM (not GA4 standard):
                $netPrice = $calculatedPrice->getUnitPrice() - ($calculatedPrice->getCalculatedTaxes()->getAmount() / $calculatedPrice->getQuantity());
                $item['price_net'] = round($netPrice, 2);
                $item['price_gross'] = $calculatedPrice->getUnitPrice(); // Same as price

                // Tax per unit (optional)
                $taxAmount = $calculatedPrice->getCalculatedTaxes()->getAmount() / $calculatedPrice->getQuantity();
                if ($taxAmount > 0) {
                    $item['tax'] = round($taxAmount, 2);
                }
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
                $itemData = [
                    'item_id' => $lineItem->getReferencedId() ?? '',
                    'item_name' => $lineItem->getLabel() ?? '',
                    'affiliation' => 'kein_Partner',
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'quantity' => $lineItem->getQuantity(),
                ];

                // Add price (Issue #2: Fixed price calculation)
                // NOTE: price_net and price_gross are NOT GA4 standard
                if ($lineItem->getPrice()) {
                    $itemPrice = $lineItem->getPrice();
                    // GA4 Standard: price = Brutto per unit
                    $itemData['price'] = $itemPrice->getUnitPrice(); // Brutto per unit

                    // Extra fields for GTM:
                    $netPrice = $itemPrice->getUnitPrice() - ($itemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity());
                    $itemData['price_net'] = round($netPrice, 2);
                    $itemData['price_gross'] = $itemPrice->getUnitPrice();

                    // Tax per unit
                    $taxAmount = $itemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity();
                    if ($taxAmount > 0) {
                        $itemData['tax'] = round($taxAmount, 2);
                    }
                } else {
                    $itemData['price'] = 0;
                    $itemData['price_net'] = 0;
                    $itemData['price_gross'] = 0;
                }

                $items[] = $itemData;
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
            $itemData = [
                'item_id' => $lineItem->getReferencedId() ?? '',
                'item_name' => $lineItem->getLabel() ?? '',
                'affiliation' => 'kein_Partner',
                'currency' => $context->getCurrency()->getIsoCode(),
                'quantity' => $lineItem->getQuantity(),
            ];

            // Add price (Issue #2: Fixed price calculation)
            if ($lineItem->getPrice()) {
                $itemPrice = $lineItem->getPrice();
                $itemData['price'] = $itemPrice->getUnitPrice();

                // Extra fields for GTM (not GA4 standard):
                $netPrice = $itemPrice->getUnitPrice() - ($itemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity());
                $itemData['price_net'] = round($netPrice, 2);
                $itemData['price_gross'] = $itemPrice->getUnitPrice();

                $taxAmount = $itemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity();
                if ($taxAmount > 0) {
                    $itemData['tax'] = round($taxAmount, 2);
                }
            } else {
                $itemData['price'] = 0;
                $itemData['price_net'] = 0;
                $itemData['price_gross'] = 0;
            }

            $items[] = $itemData;
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
                $itemData = [
                    'item_id' => $lineItem->getReferencedId() ?? '',
                    'item_name' => $lineItem->getLabel() ?? '',
                    'affiliation' => 'kein_Partner',
                    'currency' => $context->getCurrency()->getIsoCode(),
                    'quantity' => $lineItem->getQuantity(),
                ];

                // Add price (Issue #2: Fixed price calculation)
                // NOTE: price should be price per unit, NOT total price!
                if ($lineItem->getPrice()) {
                    $itemPrice = $lineItem->getPrice();
                    // GA4: price per unit (Brutto)
                    $itemData['price'] = $itemPrice->getUnitPrice();

                    // Extra fields for GTM (not GA4 standard):
                    $netPrice = $itemPrice->getUnitPrice() - ($itemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity());
                    $itemData['price_net'] = round($netPrice, 2);
                    $itemData['price_gross'] = $itemPrice->getUnitPrice();

                    $taxAmount = $itemPrice->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity();
                    if ($taxAmount > 0) {
                        $itemData['tax'] = round($taxAmount, 2);
                    }
                } else {
                    // Fallback: use getTotalPrice() and divide by quantity
                    $unitPrice = $lineItem->getTotalPrice() / max($lineItem->getQuantity(), 1);
                    $itemData['price'] = round($unitPrice, 2);
                    $itemData['price_net'] = round($unitPrice, 2); // Fallback
                    $itemData['price_gross'] = round($unitPrice, 2);
                }

                $items[] = $itemData;
            }

            // Extract coupon/promotion codes (Issue #2: Gutscheincode)
            $couponCodes = [];
            foreach ($order->getLineItems() as $lineItem) {
                // Shopware Promotion Line Items haben type = 'promotion'
                if ($lineItem->getType() === 'promotion' && $lineItem->getPayload()) {
                    $payload = $lineItem->getPayload();
                    if (isset($payload['code'])) {
                        $couponCodes[] = $payload['code'];
                    }
                }
            }

            $ecommerceData = [
                'transaction_id' => $order->getOrderNumber(),
                'affiliation' => 'kein_Partner',
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $order->getPrice()->getTotalPrice(),
                'tax' => $order->getPrice()->getCalculatedTaxes()->getAmount(),
                'shipping' => $order->getShippingCosts()->getTotalPrice(),
                'items' => $items,
            ];

            // Add coupon code if used (GA4 Standard)
            if (!empty($couponCodes)) {
                $ecommerceData['coupon'] = implode(',', $couponCodes);
            }

            $result = [
                'event' => 'purchase',
                'ecommerce' => $ecommerceData,
                'user' => $this->buildUserData($context, true), // Extended user data for purchase!
            ];

            if ($debugMode) {
                $debugInfo = [
                    'event_type' => 'purchase',
                    'transaction_id' => $order->getOrderNumber(),
                    'items_count' => count($items),
                    'order_value' => $order->getPrice()->getTotalPrice(),
                    'timestamp' => date('Y-m-d H:i:s'),
                ];

                // Add coupon info to debug
                if (!empty($couponCodes)) {
                    $debugInfo['coupon_used'] = true;
                    $debugInfo['coupon_codes'] = $couponCodes;
                }

                $result['_wsc_debug'] = $debugInfo;

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
    private function buildUserData(SalesChannelContext $context, bool $includeExtendedData = false): array
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

        $userData = [
            'user_email' => $customer->getEmail() ?? '',
            'user_country' => $billingAddress && $billingAddress->getCountry()
                ? $billingAddress->getCountry()->getTranslated()['name'] ?? ''
                : '',
            'user_city' => $billingAddress ? $billingAddress->getCity() ?? '' : '',
        ];

        // Extended user data (Issue #2: For purchase event)
        if ($includeExtendedData && $customer) {
            // Name
            $userData['user_first_name'] = $customer->getFirstName() ?? '';
            $userData['user_last_name'] = $customer->getLastName() ?? '';

            // Birthday (if available)
            if ($customer->getBirthday()) {
                $userData['user_birthday'] = $customer->getBirthday()->format('Y-m-d');
            }

            // Customer Number
            $userData['user_customer_number'] = $customer->getCustomerNumber() ?? '';

            // Billing Address (extended)
            if ($billingAddress) {
                $userData['user_street'] = $billingAddress->getStreet() ?? '';
                $userData['user_zipcode'] = $billingAddress->getZipcode() ?? '';
                $userData['user_phone'] = $billingAddress->getPhoneNumber() ?? '';
                $userData['user_company'] = $billingAddress->getCompany() ?? '';

                // Country ISO Code
                if ($billingAddress->getCountry()) {
                    $userData['user_country_iso'] = $billingAddress->getCountry()->getIso() ?? '';
                }
            }

            // Customer Group
            if ($customer->getGroup()) {
                $userData['user_customer_group'] = $customer->getGroup()->getTranslated()['name'] ?? '';
            }
        }

        return $userData;
    }
}

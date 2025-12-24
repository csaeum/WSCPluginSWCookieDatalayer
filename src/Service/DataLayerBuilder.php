<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Content\Product\ProductCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * DataLayerBuilder
 *
 * Service to build DataLayer data for Google Analytics 4 and Matomo.
 * Replaces Twig logic with clean PHP code (Issue #22).
 */
class DataLayerBuilder
{
    /**
     * Build view_item event data for product detail page
     */
    public function buildViewItemData(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
        Request $request
    ): array {
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

        return [
            'event' => 'view_item',
            '_wsc_debug' => [
                'productNumber' => $product->getProductNumber() ?? 'NULL',
                'productId' => $product->getId() ?? 'NULL',
            ],
            'ecommerce' => [
                'items' => $items,
            ],
            'user' => $this->buildUserData($context),
        ];
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
            'event' => 'view_cart',
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $cart->getPrice()->getTotalPrice(),
                'items' => $items,
            ],
            'user' => $this->buildUserData($context),
        ];
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

        return [
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

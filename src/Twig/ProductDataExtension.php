<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Twig;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ProductDataExtension
 *
 * Twig extension to generate product data JSON for JavaScript tracking (add_to_cart, etc.)
 * Provides complete GA4-compliant product data as data-attributes
 */
class ProductDataExtension extends AbstractExtension
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wsc_product_data', [$this, 'getProductData'], ['needs_context' => true]),
        ];
    }

    /**
     * Generate product data JSON for data-product-info attribute
     *
     * @param array $twigContext
     * @param SalesChannelProductEntity $product
     * @param int|null $index Position in list (0-based)
     * @param string|null $listId List ID (e.g., "category_listing", "search_results")
     * @param string|null $listName List Name (e.g., "Category Listing", "Search Results")
     * @return string JSON string with product data
     */
    public function getProductData(
        array $twigContext,
        SalesChannelProductEntity $product,
        ?int $index = null,
        ?string $listId = null,
        ?string $listName = null
    ): string
    {
        // Try to get context from Twig context first, then from request
        $context = $twigContext['context'] ?? null;

        if (!$context) {
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
            }
        }

        if (!$context instanceof SalesChannelContext) {
            // Fallback: return minimal data without context-dependent info
            return json_encode([
                'item_id' => $product->getProductNumber() ?? '',
                'item_name' => $product->getTranslated()['name'] ?? '',
            ], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
        }
        $data = [
            'item_id' => $product->getProductNumber() ?? '',
            'item_name' => $product->getTranslated()['name'] ?? '',
            'affiliation' => $context->getSalesChannel()->getTranslated()['name'] ?? '',
            'currency' => $context->getCurrency()->getIsoCode(),
        ];

        // Add index if provided (position in list, 0-based)
        if ($index !== null) {
            $data['index'] = $index;
        }

        // Add list information if provided
        if ($listId !== null) {
            $data['item_list_id'] = $listId;
        }
        if ($listName !== null) {
            $data['item_list_name'] = $listName;
        }

        // Add price
        $calculatedPrice = $product->getCalculatedPrice();
        if ($calculatedPrice) {
            $data['price'] = $calculatedPrice->getUnitPrice();

            // Calculate net price
            $taxAmount = $calculatedPrice->getCalculatedTaxes()->getAmount() / max($calculatedPrice->getQuantity(), 1);
            $data['price_net'] = round($calculatedPrice->getUnitPrice() - $taxAmount, 2);
            $data['price_gross'] = $calculatedPrice->getUnitPrice();

            if ($taxAmount > 0) {
                $data['tax'] = round($taxAmount, 2);
            }
        }

        // Add brand/manufacturer
        if ($product->getManufacturer()) {
            $data['item_brand'] = $product->getManufacturer()->getTranslated()['name'] ?? '';
        }

        // Add categories (breadcrumb)
        if ($product->getSeoCategory() && $product->getSeoCategory()->getTranslated()['breadcrumb']) {
            $breadcrumbs = $product->getSeoCategory()->getTranslated()['breadcrumb'];
            $catIndex = 0;
            foreach ($breadcrumbs as $breadcrumb) {
                $key = $catIndex === 0 ? 'item_category' : 'item_category' . ($catIndex + 1);
                $data[$key] = $breadcrumb;
                $catIndex++;
                if ($catIndex >= 5) break; // Max 5 categories (GA4 limit)
            }
        }

        // Add variant information
        if ($product->getVariation() && count($product->getVariation()) > 0) {
            $variants = [];
            foreach ($product->getVariation() as $variation) {
                $variants[] = ($variation['group'] ?? '') . ':' . ($variation['option'] ?? '');
            }
            $data['item_variant'] = implode('|', $variants);
        }

        // Add discount if available
        if ($calculatedPrice && $calculatedPrice->getListPrice() && $calculatedPrice->getListPrice()->getPercentage() > 0) {
            $data['discount'] = $calculatedPrice->getListPrice()->getPercentage();
        }

        // Add Google business vertical (always retail for e-commerce)
        $data['google_business_vertical'] = 'retail';

        return json_encode($data, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    }
}

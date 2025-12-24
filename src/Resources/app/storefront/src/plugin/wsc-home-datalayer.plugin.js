import Plugin from 'src/plugin-system/plugin.class';

export default class WscHomeDataLayer extends Plugin {
    init() {
        this.dataLayer = window.dataLayer || [];
        this.mtmLayer = window._mtm || [];

        this._registerProductClickListener();
        this._registerPromoClickListener();
    }

    _registerProductClickListener() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }

            const productLink = target.closest('.cms-element-product-slider a, .cms-element-product-listing a');
            if (!productLink) {
                return;
            }

            const productBox = productLink.closest('.product-box, .cms-product-box, .search-suggest-product');
            if (!productBox) {
                return;
            }

            const item = this._buildItemFromElement(productBox);
            if (!item.item_id && !item.item_name) {
                return;
            }

            const listName = this._resolveSliderName(productBox) || 'home_product_slider';
            item.item_list_name = listName;
            item.item_list_id = listName;

            this._pushEvent('select_item', {
                ecommerce: {
                    item_list_id: listName,
                    item_list_name: listName,
                    items: [item],
                },
            });
        });
    }

    _registerPromoClickListener() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }

            const promoLink = target.closest(
                '.cms-element-image-slider a, .cms-element-image a, .cms-element-text a'
            );
            if (!promoLink) {
                return;
            }

            const promoName = this._resolvePromoName(promoLink);
            if (!promoName) {
                return;
            }

            this._pushEvent('select_promotion', {
                promotion_name: promoName,
                promotion_id: promoLink.getAttribute('href') || '',
                creative_name: this._resolvePromoCreative(target),
            });
        });
    }

    _buildItemFromElement(container) {
        const itemId = container?.dataset?.productId || container?.dataset?.productNumber || '';
        const nameEl = container.querySelector(
            '.product-name, .product-box-title, .product-detail-name, .search-suggest-product-name'
        );
        const itemName = nameEl ? (nameEl.textContent || '').trim() : '';

        return {
            item_id: itemId,
            item_name: itemName,
            quantity: 1,
        };
    }

    _resolveSliderName(element) {
        const slider = element.closest('.cms-element-product-slider, .cms-element-product-listing');
        if (!slider) {
            return '';
        }

        const title = slider.querySelector('.cms-element-title, .cms-block-title, .element-title');
        if (title) {
            return (title.textContent || '').trim();
        }

        return '';
    }

    _resolvePromoName(element) {
        if (element.getAttribute) {
            const title = element.getAttribute('title');
            if (title) {
                return title.trim();
            }
        }

        const img = element.querySelector('img');
        if (img) {
            const alt = img.getAttribute('alt');
            if (alt) {
                return alt.trim();
            }
        }

        const text = element.textContent || '';
        return text.trim();
    }

    _resolvePromoCreative(element) {
        const slider = element.closest('.cms-element-image-slider');
        if (!slider) {
            return '';
        }

        const title = slider.querySelector('.cms-element-title, .cms-block-title, .element-title');
        if (title) {
            return (title.textContent || '').trim();
        }

        return '';
    }

    _pushEvent(eventName, payload) {
        const eventPayload = Object.assign({ event: eventName }, payload);

        if (Array.isArray(this.dataLayer)) {
            this.dataLayer.push({ ecommerce: null });
            this.dataLayer.push(eventPayload);
        }

        if (Array.isArray(this.mtmLayer)) {
            this.mtmLayer.push({ ecommerce: null });
            this.mtmLayer.push(eventPayload);
        }
    }
}

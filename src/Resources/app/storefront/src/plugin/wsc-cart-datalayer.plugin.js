import Plugin from 'src/plugin-system/plugin.class';

/**
 * WscCartDataLayer Plugin
 * Simplified version using backend-provided product data from data-product-info attributes
 */
export default class WscCartDataLayer extends Plugin {
    init() {
        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Initializing...');

        this.dataLayer = window.dataLayer || [];
        this.mtmLayer = window._mtm || [];
        this.lastClickedProduct = null;

        this._registerAddToCartListener();
        this._installAjaxInterceptor();
        this._registerRemoveFromCartListener();
        this._registerWishlistListener();

        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Initialized successfully');
    }

    /**
     * Get product data from data-product-info attribute
     */
    _getProductDataFromElement(element) {
        const container = element.closest('[data-product-info], .product-box, .product-detail, .buy-widget');

        let dataEl = null;

        // Try to find data-product-info in the container or its children
        if (container) {
            dataEl = container.querySelector('[data-product-info]') ||
                     (container.hasAttribute('data-product-info') ? container : null);
        }

        // Fallback: Search globally for .wsc-product-data-container (for product detail page)
        if (!dataEl) {
            dataEl = document.querySelector('.wsc-product-data-container[data-product-info]');
        }

        // Fallback: Search globally for any [data-product-info] (last resort)
        if (!dataEl) {
            dataEl = document.querySelector('[data-product-info]');
        }

        if (!dataEl) return null;

        const dataJson = dataEl.getAttribute('data-product-info');
        if (!dataJson) return null;

        try {
            return JSON.parse(dataJson);
        } catch (e) {
            if (window.__wscDebugMode) console.warn('WSC DataLayer Plugin: Failed to parse product data', e);
            return null;
        }
    }

    /**
     * Get currency from dataLayer
     */
    _getCurrency() {
        for (let i = this.dataLayer.length - 1; i >= 0; i -= 1) {
            const entry = this.dataLayer[i];
            if (entry && entry.ecommerce && entry.ecommerce.currency) {
                return entry.ecommerce.currency;
            }
        }
        return 'EUR'; // Fallback
    }

    /**
     * Calculate value (price * quantity)
     */
    _calculateValue(productData, quantity) {
        if (!productData.price) return 0;
        return productData.price * quantity;
    }

    /**
     * Push event to dataLayer and mtmLayer
     */
    _pushEvent(eventName, ecommerceData) {
        const eventData = {
            event: eventName,
            ecommerce: ecommerceData
        };

        if (Array.isArray(this.dataLayer)) {
            this.dataLayer.push({ ecommerce: null }); // Clear previous ecommerce data
            this.dataLayer.push(eventData);
        }

        if (Array.isArray(this.mtmLayer)) {
            this.mtmLayer.push({ ecommerce: null });
            this.mtmLayer.push(eventData);
        }
    }

    /**
     * Register click listener for add-to-cart buttons
     */
    _registerAddToCartListener() {
        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Registering add-to-cart listener');

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const button = target.closest('.btn-buy, [data-add-to-cart], button[data-product-id]');
            if (!button) return;

            if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Add-to-cart button clicked', button);

            const productData = this._getProductDataFromElement(button);
            if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Product data from element:', productData);

            if (!productData || !productData.item_id) {
                if (window.__wscDebugMode) console.warn('WSC Cart DataLayer Plugin: No valid product data found');
                return;
            }

            // Store for AJAX fallback
            this.lastClickedProduct = productData;
            if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Stored product data for AJAX:', productData);
        }, true);
    }

    /**
     * Extract quantity from FormData or request body
     */
    _extractQuantityFromRequest(requestBody) {
        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Extracting quantity from request body:', requestBody);

        try {
            // If it's FormData, iterate through it
            if (requestBody instanceof FormData) {
                for (const [key, value] of requestBody.entries()) {
                    if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: FormData entry:', key, '=', value);
                    if (key.includes('[quantity]')) {
                        const qty = parseInt(value, 10);
                        if (!isNaN(qty) && qty > 0) {
                            if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Found quantity in FormData:', qty);
                            return qty;
                        }
                    }
                }
            }

            // If it's a string (URL-encoded), parse it
            if (typeof requestBody === 'string') {
                const params = new URLSearchParams(requestBody);
                for (const [key, value] of params.entries()) {
                    if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: URLSearchParams entry:', key, '=', value);
                    if (key.includes('[quantity]')) {
                        const qty = parseInt(value, 10);
                        if (!isNaN(qty) && qty > 0) {
                            if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Found quantity in URLSearchParams:', qty);
                            return qty;
                        }
                    }
                }
            }
        } catch (e) {
            if (window.__wscDebugMode) console.warn('WSC Cart DataLayer Plugin: Error extracting quantity:', e);
        }

        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: No quantity found, defaulting to 1');
        return 1; // Default quantity
    }

    /**
     * Install AJAX interceptor
     */
    _installAjaxInterceptor() {
        if (window.__wscCartInterceptorInstalled) return;
        window.__wscCartInterceptorInstalled = true;

        // Store request body for fetch requests
        let lastRequestBody = null;

        // Intercept fetch()
        const originalFetch = window.fetch;
        const self = this;

        if (typeof originalFetch === 'function') {
            window.fetch = async (...args) => {
                const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url) || '';

                // Store request body if it's an add-to-cart request
                if (url.includes('checkout/line-item/add') && args[1] && args[1].body) {
                    lastRequestBody = args[1].body;
                    if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Stored fetch request body:', lastRequestBody);
                }

                const response = await originalFetch(...args);

                if (url.includes('checkout/line-item/add') && response && response.ok) {
                    if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: AJAX add-to-cart detected (fetch)');

                    if (self.lastClickedProduct) {
                        // Extract quantity from request body
                        const quantity = self._extractQuantityFromRequest(lastRequestBody);

                        const item = Object.assign({}, self.lastClickedProduct, { quantity: quantity });

                        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Pushing add_to_cart event:', item);

                        self._pushEvent('add_to_cart', {
                            currency: item.currency || self._getCurrency(),
                            value: self._calculateValue(item, quantity),
                            items: [item]
                        });

                        // Reset
                        lastRequestBody = null;
                    } else {
                        if (window.__wscDebugMode) console.warn('WSC Cart DataLayer Plugin: No last clicked product stored');
                    }
                }

                return response;
            };
        }

        // Intercept XMLHttpRequest
        const originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__wscUrl = url;
            return originalOpen.call(this, method, url, ...rest);
        };

        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function (body) {
            // Store request body for add-to-cart requests
            const url = this.__wscUrl || '';
            if (url.includes('checkout/line-item/add')) {
                this.__wscRequestBody = body;
                if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Stored XMLHttpRequest body:', body);
            }

            this.addEventListener('load', () => {
                const url = this.__wscUrl || '';
                if (url.includes('checkout/line-item/add') && this.status >= 200 && this.status < 300) {
                    if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: AJAX add-to-cart detected (XMLHttpRequest)');

                    if (self.lastClickedProduct) {
                        // Extract quantity from request body
                        const quantity = self._extractQuantityFromRequest(this.__wscRequestBody);

                        const item = Object.assign({}, self.lastClickedProduct, { quantity: quantity });

                        if (window.__wscDebugMode) console.log('WSC Cart DataLayer Plugin: Pushing add_to_cart event:', item);

                        self._pushEvent('add_to_cart', {
                            currency: item.currency || self._getCurrency(),
                            value: self._calculateValue(item, quantity),
                            items: [item]
                        });
                    } else {
                        if (window.__wscDebugMode) console.warn('WSC Cart DataLayer Plugin: No last clicked product stored');
                    }
                }
            });
            return originalSend.call(this, body);
        };
    }

    /**
     * Register remove-from-cart listener
     */
    _registerRemoveFromCartListener() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            const action = form.getAttribute('action') || '';
            if (!action.includes('checkout/line-item/delete') && !action.includes('line-item/remove')) return;

            const productData = this._getProductDataFromElement(form);
            if (!productData || !productData.item_id) return;

            // Get quantity from form
            const formData = new FormData(form);
            let quantity = 1;
            for (const [key, value] of formData.entries()) {
                if (key.endsWith('[quantity]')) {
                    const qty = parseFloat(String(value));
                    if (!Number.isNaN(qty)) {
                        quantity = qty;
                    }
                }
            }

            const item = Object.assign({}, productData, { quantity });

            this._pushEvent('remove_from_cart', {
                currency: item.currency || this._getCurrency(),
                value: this._calculateValue(item, quantity),
                items: [item]
            });
        }, true);
    }

    /**
     * Register wishlist listener
     */
    _registerWishlistListener() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const trigger = target.closest(
                '[data-wishlist-add], [data-wishlist-add-id], [data-add-to-wishlist], .product-wishlist-action, .wishlist-add'
            );
            if (!trigger) return;

            const productData = this._getProductDataFromElement(trigger);
            if (!productData || !productData.item_id) return;

            const item = Object.assign({}, productData, { quantity: 1 });

            this._pushEvent('add_to_wishlist', {
                currency: item.currency || this._getCurrency(),
                value: this._calculateValue(item, 1),
                items: [item]
            });
        });
    }
}

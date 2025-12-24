import Plugin from 'src/plugin-system/plugin.class';

export default class WscCartDataLayer extends Plugin {
    init() {
        this.dataLayer = window.dataLayer || [];
        this.mtmLayer = window._mtm || [];
        this._initProductContextStore();

        this._registerAddToCartListener();
        this._registerRemoveFromCartListener();
        this._registerWishlistListener();
    }

    _registerAddToCartListener() {
        this._collectProductContexts();
        this._registerAddToCartContextListener();
        this._installCartRequestInterceptor();
    }

    _registerRemoveFromCartListener() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const action = form.getAttribute('action') || '';
            if (!action.includes('checkout/line-item/delete')) {
                return;
            }

            const item = this._buildItemFromForm(form);
            if (!item.item_id && !item.item_name) {
                return;
            }

            this._pushEvent('remove_from_cart', {
                ecommerce: {
                    currency: this._resolveCurrency(),
                    items: [item],
                },
            });
        }, true);
    }

    _registerWishlistListener() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }

            const trigger = target.closest(
                '[data-wishlist-add], [data-wishlist-add-id], [data-add-to-wishlist], .product-wishlist-action, .wishlist-add'
            );
            if (!trigger) {
                return;
            }

            const item = this._buildItemFromElement(trigger);
            if (!item.item_id && !item.item_name) {
                return;
            }

            this._pushEvent('add_to_wishlist', {
                ecommerce: {
                    currency: this._resolveCurrency(),
                    items: [item],
                },
            });
        });
    }

    _buildItemFromForm(form) {
        const formData = new FormData(form);
        let itemId = '';
        let quantity = 1;

        for (const [key, value] of formData.entries()) {
            if (!itemId && key.endsWith('[id]')) {
                itemId = String(value);
            }

            if (key.endsWith('[quantity]')) {
                const qty = parseFloat(String(value));
                if (!Number.isNaN(qty)) {
                    quantity = qty;
                }
            }
        }

        const container = form.closest('[data-product-id], [data-product-number], .product-box, .product-detail-main, .line-item, .cart-item');

        // Fallback: Extract SKU/Product Number from container or DOM
        if (!itemId && container) {
            // Try data-product-number attribute first
            itemId = container.dataset?.productNumber || '';

            // Try to extract from .line-item-product-number element
            if (!itemId) {
                const numberEl = container.querySelector('.line-item-product-number');
                if (numberEl) {
                    const text = (numberEl.textContent || '').trim();
                    // Extract SKU from "Produkt-Nr.: SWDEMO10001" or similar
                    const match = text.match(/:\s*([A-Z0-9.-]+)/);
                    if (match) {
                        itemId = match[1];
                    }
                }
            }

            // Try to extract from link href
            if (!itemId) {
                const link = container.querySelector('.line-item-label, .line-item-img-link');
                if (link) {
                    const href = link.getAttribute('href') || '';
                    const urlParts = href.split('/');
                    const lastPart = urlParts[urlParts.length - 1];
                    // SKU is usually in format like SWDEMO10001
                    if (lastPart && /^[A-Z0-9.-]+$/.test(lastPart)) {
                        itemId = lastPart;
                    }
                }
            }
        }

        const itemName = this._resolveItemName(container, form);

        return {
            item_id: itemId,
            item_name: itemName,
            quantity,
        };
    }

    _buildItemFromElement(element) {
        const container = element.closest('[data-product-id], [data-product-number], .product-box, .product-detail-main, .search-suggest-product');
        const itemId = container?.dataset?.productId || container?.dataset?.productNumber || '';
        const itemName = this._resolveItemName(container, element);

        return {
            item_id: itemId,
            item_name: itemName,
            quantity: 1,
        };
    }

    _resolveItemName(container, fallbackElement) {
        if (container) {
            const nameEl = container.querySelector(
                '.product-name, .product-box-title, .product-detail-name, .search-suggest-product-name, .line-item-label'
            );
            if (nameEl) {
                return (nameEl.textContent || '').trim();
            }
        }

        if (fallbackElement) {
            const text = fallbackElement.textContent || '';
            return text.trim();
        }

        return '';
    }

    _resolveCurrency() {
        const entry = this._findLatestDataLayerEntry();
        return entry?.ecommerce?.currency || '';
    }

    _findLatestDataLayerEntry() {
        for (let i = this.dataLayer.length - 1; i >= 0; i -= 1) {
            const entry = this.dataLayer[i];
            if (entry && entry.ecommerce && entry.ecommerce.currency) {
                return entry;
            }
        }

        return null;
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

    _installCartRequestInterceptor() {
        if (window.__wscCartInterceptorInstalled) {
            return;
        }
        window.__wscCartInterceptorInstalled = true;

        const originalFetch = window.fetch;
        if (typeof originalFetch === 'function') {
            window.fetch = async (...args) => {
                const response = await originalFetch(...args);
                this._handleCartRequest(args[0], response, args[1]);
                return response;
            };
        }

        const originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__wscUrl = url;
            return originalOpen.call(this, method, url, ...rest);
        };

        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function (body) {
            this.addEventListener('load', () => {
                const url = this.__wscUrl || '';
                if (url && url.includes('checkout/line-item/add') && this.status >= 200 && this.status < 300) {
                    const payload = WscCartDataLayer._extractItemFromRequest(body, url);
                    if (payload) {
                        WscCartDataLayer._pushAddToCart(payload);
                    }
                }
            });
            return originalSend.call(this, body);
        };
    }

    _handleCartRequest(request, response, init = {}) {
        const url = typeof request === 'string' ? request : (request && request.url) || '';
        if (!url || !url.includes('checkout/line-item/add')) {
            return;
        }

        if (!response || !response.ok) {
            return;
        }

        const payload = WscCartDataLayer._extractItemFromRequest(init.body, url);
        if (!payload) {
            return;
        }

        WscCartDataLayer._pushAddToCart(payload);
    }

    static _extractItemFromRequest(body, url) {
        let itemId = '';
        let quantity = 1;

        if (body instanceof FormData) {
            for (const [key, value] of body.entries()) {
                if (!itemId && key.endsWith('[id]')) {
                    itemId = String(value);
                }
                if (key.endsWith('[quantity]')) {
                    const qty = parseFloat(String(value));
                    if (!Number.isNaN(qty)) {
                        quantity = qty;
                    }
                }
            }
        } else if (typeof body === 'string') {
            const params = new URLSearchParams(body);
            for (const [key, value] of params.entries()) {
                if (!itemId && key.endsWith('[id]')) {
                    itemId = String(value);
                }
                if (key.endsWith('[quantity]')) {
                    const qty = parseFloat(String(value));
                    if (!Number.isNaN(qty)) {
                        quantity = qty;
                    }
                }
            }
        }

        if (!itemId && url.includes('?')) {
            const params = new URLSearchParams(url.split('?')[1]);
            for (const [key, value] of params.entries()) {
                if (!itemId && key.endsWith('[id]')) {
                    itemId = String(value);
                }
                if (key.endsWith('[quantity]')) {
                    const qty = parseFloat(String(value));
                    if (!Number.isNaN(qty)) {
                        quantity = qty;
                    }
                }
            }
        }

        return {
            item_id: itemId,
            item_name: '',
            quantity,
        };
    }

    static _pushAddToCart(item) {
        const dataLayer = window.dataLayer || [];
        const mtmLayer = window._mtm || [];
        const context = window.__wscProductContext || {};
        const byId = context.byId || {};
        const byNumber = context.byNumber || {};
        const last = context.last || null;

        if (!item.item_id && last && last.item_id) {
            item.item_id = last.item_id;
        }

        if (!item.item_name) {
            item.item_name = byId[item.item_id] || byNumber[item.item_id] || (last ? last.item_name : '') || '';
        }

        if (!item.item_id && !item.item_name) {
            return;
        }

        const currency = WscCartDataLayer._resolveCurrencyStatic(dataLayer);

        const payload = {
            event: 'add_to_cart',
            ecommerce: {
                currency,
                items: [item],
            },
        };

        if (Array.isArray(dataLayer)) {
            dataLayer.push({ ecommerce: null });
            dataLayer.push(payload);
        }

        if (Array.isArray(mtmLayer)) {
            mtmLayer.push({ ecommerce: null });
            mtmLayer.push(payload);
        }
    }

    static _resolveCurrencyStatic(dataLayer) {
        if (!Array.isArray(dataLayer)) {
            return '';
        }

        for (let i = dataLayer.length - 1; i >= 0; i -= 1) {
            const entry = dataLayer[i];
            if (entry && entry.ecommerce && entry.ecommerce.currency) {
                return entry.ecommerce.currency;
            }
        }

        return '';
    }

    _initProductContextStore() {
        if (!window.__wscProductContext) {
            window.__wscProductContext = {
                byId: {},
                byNumber: {},
                last: null,
            };
        }
    }

    _collectProductContexts() {
        const elements = document.querySelectorAll('[data-product-id], [data-product-number]');
        elements.forEach((element) => {
            const item = this._buildItemFromElement(element);
            if (!item.item_name) {
                return;
            }

            const context = window.__wscProductContext;
            if (item.item_id) {
                context.byId[item.item_id] = item.item_name;
            }

            const number = element.dataset?.productNumber || '';
            if (number) {
                context.byNumber[number] = item.item_name;
            }
        });
    }

    _registerAddToCartContextListener() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }

            const button = target.closest('.btn-buy, [data-add-to-cart], form[action*="checkout/line-item/add"] button');
            if (!button) {
                return;
            }

            const item = this._buildItemFromElement(button);
            if (!item.item_id && !item.item_name) {
                return;
            }

            window.__wscProductContext.last = item;
            if (item.item_id) {
                window.__wscProductContext.byId[item.item_id] = item.item_name || '';
            }
        }, true);
    }
}

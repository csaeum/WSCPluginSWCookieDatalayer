import Plugin from 'src/plugin-system/plugin.class';

export default class WscSearchDataLayer extends Plugin {
    init() {
        this.dataLayer = window.dataLayer || [];
        this.mtmLayer = window._mtm || [];
        this.input = this._resolveSearchInput();
        this.lastTerm = '';
        this.debounceTimer = null;

        this._registerLiveSearchListener();
        this._registerSelectItemListener();
    }

    _resolveSearchInput() {
        return document.querySelector(
            'input[name="search"], input[type="search"], .js-search-field'
        );
    }

    _registerLiveSearchListener() {
        if (!this.input) {
            return;
        }

        this.input.addEventListener('input', () => {
            const term = (this.input.value || '').trim();
            if (term.length < 2 || term === this.lastTerm) {
                return;
            }

            window.clearTimeout(this.debounceTimer);
            this.debounceTimer = window.setTimeout(() => {
                this.lastTerm = term;
                this._pushEvent('search', {
                    search_term: term,
                    ecommerce: {
                        item_list_id: 'search_suggest',
                        item_list_name: 'search_suggest',
                        items: this._collectOverlayItems(),
                    },
                });
            }, 250);
        });
    }

    _registerSelectItemListener() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }

            const link = target.closest('a');
            if (!link) {
                return;
            }

            const isOverlayClick = !!target.closest('.search-suggest, .search-suggest-container');
            const isSearchPageClick = this._isSearchPage() && !!target.closest('.product-box, .cms-element-product-listing, .product-listing');

            if (!isOverlayClick && !isSearchPageClick) {
                return;
            }

            const item = this._buildItemFromElement(link);
            if (!item.item_id && !item.item_name) {
                return;
            }

            const listName = isOverlayClick ? 'search_suggest' : 'view_search_results';
            const term = this._resolveSearchTerm();

            this._pushEvent('select_item', {
                search_term: term,
                ecommerce: {
                    item_list_id: listName,
                    item_list_name: listName,
                    items: [item],
                },
            });
        });
    }

    _resolveSearchTerm() {
        if (this.input && this.input.value) {
            return this.input.value.trim();
        }

        const params = new URLSearchParams(window.location.search);
        return params.get('search') || '';
    }

    _collectOverlayItems() {
        const items = [];
        const overlay = document.querySelector('.search-suggest, .search-suggest-container');
        if (!overlay) {
            return items;
        }

        const links = overlay.querySelectorAll('a');
        links.forEach((link, index) => {
            const item = this._buildItemFromElement(link, index + 1);
            if (item.item_id || item.item_name) {
                items.push(item);
            }
        });

        return items;
    }

    _buildItemFromElement(element, index = null) {
        const container = element.closest('[data-product-id], [data-product-number], .search-suggest-product, .product-box');
        const itemId = container?.dataset?.productId || container?.dataset?.productNumber || '';
        const nameEl = container?.querySelector('.product-name, .search-suggest-product-name, .product-box-title, .product-name') || element;
        const itemName = nameEl ? (nameEl.textContent || '').trim() : '';

        const item = {
            item_id: itemId,
            item_name: itemName,
        };

        if (index !== null) {
            item.index = index;
        }

        return item;
    }

    _isSearchPage() {
        if (window.location.pathname.includes('/search')) {
            return true;
        }

        const params = new URLSearchParams(window.location.search);
        return params.has('search');
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

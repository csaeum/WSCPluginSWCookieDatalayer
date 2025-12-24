import Plugin from 'src/plugin-system/plugin.class';

export default class WscCheckoutDataLayer extends Plugin {
    init() {
        this.dataLayer = window.dataLayer || [];
        this.mtmLayer = window._mtm || [];
        this.basePayload = this._getBasePayload();

        if (!this.basePayload) {
            return;
        }

        this._registerShippingListener();
        this._registerPaymentListener();
    }

    _getBasePayload() {
        for (let i = this.dataLayer.length - 1; i >= 0; i -= 1) {
            const entry = this.dataLayer[i];
            if (!entry || entry.event !== 'begin_checkout') {
                continue;
            }

            const ecommerce = entry.ecommerce || {};
            const items = Array.isArray(ecommerce.items) ? ecommerce.items : [];
            if (items.length === 0) {
                continue;
            }

            return {
                ecommerce,
                user: entry.user || {},
            };
        }

        return null;
    }

    _registerShippingListener() {
        const inputs = document.querySelectorAll('input[name="shippingMethodId"]');
        if (!inputs.length) {
            return;
        }

        inputs.forEach((input) => {
            input.addEventListener('change', () => {
                const tier = this._resolveInputLabel(input) || input.value || '';
                this._pushEvent('add_shipping_info', { shipping_tier: tier });
            });
        });
    }

    _registerPaymentListener() {
        const inputs = document.querySelectorAll('input[name="paymentMethodId"]');
        if (!inputs.length) {
            return;
        }

        inputs.forEach((input) => {
            input.addEventListener('change', () => {
                const paymentType = this._resolveInputLabel(input) || input.value || '';
                this._pushEvent('add_payment_info', { payment_type: paymentType });
            });
        });
    }

    _resolveInputLabel(input) {
        if (!input) {
            return '';
        }

        if (input.id) {
            const label = document.querySelector(`label[for="${input.id}"]`);
            if (label) {
                return label.textContent.trim();
            }
        }

        const container = input.closest('.checkout-shipping-method, .shipping-method, .checkout-payment-method, .payment-method');
        if (container) {
            const name = container.querySelector('.shipping-method-name, .payment-method-name, .method-name');
            if (name) {
                return name.textContent.trim();
            }
        }

        return '';
    }

    _pushEvent(eventName, extraEcommerce) {
        const ecommerce = Object.assign({}, this.basePayload.ecommerce, extraEcommerce);
        const payload = {
            event: eventName,
            ecommerce,
            user: this.basePayload.user,
        };

        if (Array.isArray(this.dataLayer)) {
            this.dataLayer.push({ ecommerce: null });
            this.dataLayer.push(payload);
        }

        if (Array.isArray(this.mtmLayer)) {
            this.mtmLayer.push({ ecommerce: null });
            this.mtmLayer.push(payload);
        }
    }
}

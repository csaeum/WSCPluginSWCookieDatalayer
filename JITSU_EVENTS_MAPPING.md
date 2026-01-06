# Jitsu Server-Side Events - Shopware Event Mapping

## Implementierte Events (✓ Done)

| GA4 Event | Shopware Event | Status | Handler |
|-----------|----------------|--------|---------|
| view_item | ProductPageLoadedEvent | ✓ | DataLayerSubscriber::onProductPageLoaded |
| view_item_list | NavigationPageLoadedEvent | ✓ | DataLayerSubscriber::onNavigationPageLoaded |
| view_cart | CheckoutCartPageLoadedEvent | ✓ | DataLayerSubscriber::onCheckoutCartPageLoaded |
| begin_checkout | CheckoutConfirmPageLoadedEvent | ✓ | DataLayerSubscriber::onCheckoutConfirmPageLoaded |
| purchase | CheckoutFinishPageLoadedEvent | ✓ | DataLayerSubscriber::onCheckoutFinishPageLoaded |

## Noch zu implementieren

| GA4 Event | Shopware Event | Namespace |
|-----------|----------------|-----------|
| add_to_cart | AfterLineItemAddedEvent | Shopware\Core\Checkout\Cart\Event |
| remove_from_cart | AfterLineItemRemovedEvent | Shopware\Core\Checkout\Cart\Event |
| login | CustomerLoginEvent | Shopware\Core\Checkout\Customer\Event |
| sign_up | CustomerRegisterEvent | Shopware\Core\Checkout\Customer\Event |
| search | ProductSearchResultEvent | Shopware\Storefront\Page\Search\SearchPageLoadedEvent |

### Shipping & Payment Info

**Problem:** Shopware hat keine dedizierten Events für "Shipping Method Selected" oder "Payment Method Selected".

**Lösungen:**
1. **add_shipping_info**: Wird getriggert wenn Checkout-Confirm-Seite geladen wird UND shipping method im Cart vorhanden ist
2. **add_payment_info**: Wird getriggert wenn Checkout-Confirm-Seite geladen wird UND payment method im Cart vorhanden ist

Alternative: Wir tracken diese Events nur, wenn sie sich ändern (durch Vergleich mit vorherigem Wert in Session).

## Implementation Plan

### 1. add_to_cart / remove_from_cart
- Event: `AfterLineItemAddedEvent` / `AfterLineItemRemovedEvent`
- Challenge: Event enthält nur LineItem, keine vollständigen Produktdaten
- Lösung: LineItem ID nutzen um Produktdaten zu laden

### 2. login
- Event: `CustomerLoginEvent`
- Simple - Customer-Daten sind verfügbar

### 3. sign_up
- Event: `CustomerRegisterEvent`
- Simple - Customer-Daten sind verfügbar

### 4. search
- Event: `SearchPageLoadedEvent`
- Enthält Suchergebnisse und Query

### 5. add_shipping_info / add_payment_info
- Implementierung über CheckoutConfirmPageLoadedEvent
- Tracking nur wenn Methode gesetzt ist

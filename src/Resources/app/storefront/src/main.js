import WscCheckoutDataLayer from './plugin/wsc-checkout-datalayer.plugin';
import WscSearchDataLayer from './plugin/wsc-search-datalayer.plugin';
import WscCartDataLayer from './plugin/wsc-cart-datalayer.plugin';
import WscHomeDataLayer from './plugin/wsc-home-datalayer.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('WscCheckoutDataLayer', WscCheckoutDataLayer, 'body');
PluginManager.register('WscSearchDataLayer', WscSearchDataLayer, 'body');
PluginManager.register('WscCartDataLayer', WscCartDataLayer, 'body');
PluginManager.register('WscHomeDataLayer', WscHomeDataLayer, 'body');

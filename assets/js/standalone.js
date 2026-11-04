// Components only, for a site that already loads Alpine.js with a <script> tag
// (public/astra-checkout-components.js). Load this file BEFORE Alpine.
import { registerAstraCheckout } from './components.js';

if (window.Alpine) {
  registerAstraCheckout(window.Alpine);
  if (document.querySelector('[x-data]')?._x_dataStack) {
    console.warn('AstraCheckout: Alpine was started before astra-checkout-components.js; load the shop script before Alpine.');
  }
} else {
  document.addEventListener('alpine:init', () => registerAstraCheckout(window.Alpine));
}

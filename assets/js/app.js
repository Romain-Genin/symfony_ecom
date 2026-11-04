// Bundled build: Alpine.js + AstraCheckout components (public/astra-checkout.js).
import Alpine from 'alpinejs';
import { registerAstraCheckout } from './components.js';

window.Alpine = Alpine;
registerAstraCheckout(Alpine);
Alpine.start();

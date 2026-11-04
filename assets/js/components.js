/*
 * AstraCheckout front-end: Alpine.js components and stores, without Alpine itself.
 *
 * - Bundled build (public/astra-checkout.js): Alpine + these components, nothing to do.
 * - Your own Alpine (Vite, AssetMapper…):
 *     import Alpine from 'alpinejs';
 *     import { registerAstraCheckout } from '@studioastra/astra-checkout/js/components.js';
 *     registerAstraCheckout(Alpine);
 *     Alpine.start();
 *
 * Every feature is a progressive enhancement: without JavaScript, forms POST and links navigate,
 * so the shop stays usable (and robust on flaky mobile networks).
 */

const readConfig = () => {
  try {
    return JSON.parse(document.getElementById('astra-config')?.textContent || '{}');
  } catch {
    return {};
  }
};
let configCache = null;
const config = () => (configCache ??= readConfig());
const t = (key, params = {}) => Object.entries(params).reduce((s, [k, v]) => s.replaceAll(`%${k}%`, v), (config().i18n || {})[key] || key);

const postForm = async (url, body) => {
  const response = await fetch(url, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  let data = {};
  try {
    data = await response.json();
  } catch {
    data = { ok: false, message: t('error') };
  }
  return { ok: response.ok && data.ok !== false, ...data };
};

export function registerAstraCheckout(Alpine) {
  if (Alpine.store('cart')) return; // already registered

  /* Screen reader announcements (one polite live region in the layout). */
  Alpine.store('announcer', {
    message: '',
    say(message) {
      this.message = '';
      // Re-set on the next frame so the same message is announced twice in a row.
      requestAnimationFrame(() => { this.message = message; });
    },
  });

  /* Cart badge, mini-cart and customer first name, loaded once per page (catalog HTML stays cacheable). */
  Alpine.store('cart', {
    count: 0,
    subtotal: '',
    discounts: [],
    items: [],
    freeShipping: null,
    customer: null,
    loaded: false,
    async load() {
      if (!config().summaryUrl) return;
      try {
        const response = await fetch(config().summaryUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (response.ok) this.apply(await response.json());
      } catch { /* offline: keep server-rendered fallbacks */ }
    },
    apply(data) {
      if (!data) return;
      Object.assign(this, {
        count: data.count || 0,
        subtotal: data.subtotal || '',
        discounts: data.discounts || [],
        items: data.items || [],
        freeShipping: data.freeShipping || null,
        customer: data.customer || null,
        loaded: true,
      });
    },
    label() {
      return this.count === 0 ? t('cart.empty_label') : t(this.count > 1 ? 'cart.count_label_plural' : 'cart.count_label', { count: this.count });
    },
    async remove(item) {
      const result = await postForm(item.removeUrl, new FormData());
      this.apply(result.cart);
      Alpine.store('announcer').say(result.message || '');
    },
  });

  /* Native <dialog>: focus trap, Escape, inert background and focus return come for free. */
  Alpine.data('dialog', (id) => ({
    open() {
      const el = document.getElementById(id);
      if (el && !el.open) el.showModal();
    },
    close() {
      document.getElementById(id)?.close();
    },
  }));

  /* "Add to cart" without page reload; falls back to a normal POST. */
  Alpine.data('addToCart', () => ({
    busy: false,
    error: '',
    async submit(event) {
      if (this.busy) return;
      this.busy = true;
      this.error = '';
      try {
        const result = await postForm(event.target.action, new FormData(event.target));
        Alpine.store('cart').apply(result.cart);
        Alpine.store('announcer').say(result.message || '');
        if (result.ok) {
          document.getElementById('astra-minicart')?.showModal();
        } else {
          this.error = result.message || t('error');
        }
      } catch {
        event.target.submit();
      } finally {
        this.busy = false;
      }
    },
  }));

  /*
   * Variant picker: one radio group per option (Couleur, Taille…). Unavailable combinations stay
   * selectable (so the visitor learns they are sold out) but cannot be added to the cart.
   */
  Alpine.data('variantPicker', (variants, initialId) => ({
    variants,
    selected: {},
    init() {
      const initial = variants.find((v) => v.id === initialId) || variants[0];
      this.selected = { ...(initial?.options || {}) };
    },
    get variant() {
      const names = Object.keys(this.selected);
      return this.variants.find((v) => names.every((n) => v.options[n] === this.selected[n])) || null;
    },
    get available() {
      return Boolean(this.variant && this.variant.available);
    },
    get stockLabel() {
      const v = this.variant;
      if (!v) return t('variant.unavailable');
      if (!v.available) return t('stock.out');
      if (v.low) return t('stock.low', { count: v.stock });
      return t('stock.in');
    },
    isPossible(name, value) {
      return this.variants.some((v) => v.options[name] === value && v.available
        && Object.entries(this.selected).every(([n, val]) => n === name || v.options[n] === val));
    },
    choose(name, value) {
      this.selected = { ...this.selected, [name]: value };
      const v = this.variant;
      if (v) {
        const url = new URL(window.location.href);
        url.searchParams.set('variant', v.id);
        window.history.replaceState({}, '', url);
      }
      Alpine.store('announcer').say(v ? `${v.label} — ${v.price} — ${this.stockLabel}` : t('variant.unavailable'));
    },
  }));

  Alpine.data('quantity', (min = 1, max = 99) => ({
    value: min,
    min,
    max,
    init() {
      this.value = Number(this.$refs.input?.value || min);
    },
    dec() { this.set(this.value - 1); },
    inc() { this.set(this.value + 1); },
    set(v) {
      this.value = Math.max(this.min, Math.min(this.max, Number(v) || this.min));
      if (this.$refs.input) this.$refs.input.value = this.value;
    },
  }));

  /* Product gallery: thumbnails are buttons; arrow keys move between them (roving tabindex). */
  Alpine.data('gallery', (count) => ({
    current: 0,
    count,
    show(i) {
      this.current = (i + this.count) % this.count;
      Alpine.store('announcer').say(t('gallery.position', { index: this.current + 1, count: this.count }));
    },
    key(event) {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const next = { ArrowLeft: this.current - 1, ArrowRight: this.current + 1, Home: 0, End: this.count - 1 }[event.key];
      this.show(next);
      this.$nextTick(() => this.$root.querySelectorAll('[data-thumb]')[this.current]?.focus());
    },
  }));

  /* Disclosure menus of the main navigation (button + list, Escape closes). */
  Alpine.data('disclosure', () => ({
    expanded: false,
    toggle() { this.expanded = !this.expanded; },
    close(focusButton = false) {
      if (!this.expanded) return;
      this.expanded = false;
      if (focusButton) this.$refs.button?.focus();
    },
  }));

  /* Saved addresses at checkout: fills the form fields of a section. */
  Alpine.data('addressBook', (addresses, prefix) => ({
    addresses,
    chosen: '',
    fill() {
      const address = this.addresses.find((a) => String(a.id) === String(this.chosen));
      if (!address) return;
      Object.entries(address.data).forEach(([field, value]) => {
        const input = document.getElementById(`${prefix}_${field}`);
        if (input) {
          input.value = value ?? '';
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
      Alpine.store('announcer').say(t('address.filled'));
    },
  }));

  Alpine.data('passwordToggle', () => ({
    visible: false,
    toggle() {
      this.visible = !this.visible;
      const input = this.$root.querySelector('input');
      if (input) input.type = this.visible ? 'text' : 'password';
    },
  }));

  /*
   * Top-bar announcements: rotation with pause (WCAG 2.2.2), paused while hovered or focused,
   * never automatic for "prefers-reduced-motion" users; dismissals remembered per browser.
   */
  Alpine.data('announcementBar', (keys, interval) => ({
    keys,
    visible: [],
    dismissible: [],
    current: null,
    paused: false,
    hover: false,
    focus: false,
    timer: null,
    canRotate: interval > 0 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    get playing() { return this.canRotate && !this.paused && !this.hover && !this.focus && this.visible.length > 1; },
    init() {
      let dismissed = [];
      try { dismissed = JSON.parse(localStorage.getItem('astra_dismissed_announcements') || '[]'); } catch { /* private mode */ }
      try { this.dismissible = Object.values(JSON.parse(this.$root.querySelector('[data-dismissible]')?.textContent || '[]')); } catch { this.dismissible = []; }
      this.visible = this.keys.filter((k) => !dismissed.includes(k));
      this.show(this.visible[0] ?? null);
      if (this.canRotate) {
        this.timer = setInterval(() => { if (this.playing) this.step(1); }, interval * 1000);
      }
    },
    destroy() { clearInterval(this.timer); },
    show(key) {
      this.current = key;
      const slides = [...this.$root.querySelectorAll('[data-tone]')];
      const index = this.keys.indexOf(key);
      const tone = slides[index]?.dataset.tone;
      slides.forEach((s) => s.dataset.tone.split(' ').forEach((c) => this.$root.classList.remove(c)));
      if (tone) tone.split(' ').forEach((c) => this.$root.classList.add(c));
    },
    step(delta) {
      if (this.visible.length === 0) return;
      const i = this.visible.indexOf(this.current);
      this.show(this.visible[(i + delta + this.visible.length) % this.visible.length]);
    },
    next() { this.paused = true; this.step(1); },
    previous() { this.paused = true; this.step(-1); },
    togglePause() { this.paused = !this.paused; },
    dismiss() {
      const key = this.current;
      this.step(1);
      this.visible = this.visible.filter((k) => k !== key);
      if (this.visible.length === 0) this.current = null;
      try {
        const stored = JSON.parse(localStorage.getItem('astra_dismissed_announcements') || '[]');
        localStorage.setItem('astra_dismissed_announcements', JSON.stringify([...stored, key].slice(-50)));
      } catch { /* private mode */ }
      Alpine.store('announcer').say(t('announcement.dismissed'));
    },
  }));

  /*
   * Search-as-you-type: ARIA 1.2 combobox + listbox. Arrow keys move in the list, Enter opens
   * the highlighted result (or submits the search), Escape closes then clears. Requests are
   * debounced and cancelled when outdated; results are cached by the HTTP layer.
   */
  Alpine.data('searchSuggest', (url, minLength = 2) => ({
    query: '',
    open: false,
    loading: false,
    active: -1,
    categories: [],
    products: [],
    searchUrl: '',
    status: '',
    controller: null,
    timeout: null,
    init() { this.query = this.$refs.input?.value || ''; },
    get options() { return [...this.categories.map((c) => ({ ...c, kind: 'category' })), ...this.products.map((p) => ({ ...p, kind: 'product' }))]; },
    input() {
      clearTimeout(this.timeout);
      if (this.query.trim().length < minLength) {
        this.close();
        return;
      }
      this.timeout = setTimeout(() => this.fetch(), 180);
    },
    async fetch() {
      this.controller?.abort();
      this.controller = new AbortController();
      this.loading = true;
      try {
        const response = await fetch(`${url}?q=${encodeURIComponent(this.query.trim())}`, { signal: this.controller.signal, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (data.query !== this.query.trim()) return;
        this.categories = data.categories || [];
        this.products = data.products || [];
        this.searchUrl = data.searchUrl;
        this.active = -1;
        this.open = true;
        const count = this.options.length;
        this.status = count === 0 ? t('search.no_suggestion') : t(count > 1 ? 'search.suggestions' : 'search.suggestion', { count });
      } catch { /* aborted or offline */ } finally {
        this.loading = false;
      }
    },
    move(delta) {
      if (!this.open) { this.input(); return; }
      const last = this.options.length; // index of "see all results"
      let next = this.active + delta;
      if (next < -1) next = last;
      if (next > last) next = -1;
      this.active = next;
    },
    optionId(i) { return `${this.$id('astra-suggest')}-${i}`; },
    enter(event) {
      if (!this.open || this.active < 0) return; // normal form submit
      event.preventDefault();
      window.location = this.active < this.options.length ? this.options[this.active].url : this.searchUrl;
    },
    escape() {
      if (this.open) this.close();
      else this.query = '';
    },
    close() {
      this.open = false;
      this.active = -1;
    },
  }));

  /* Moves focus to the error summary of a form re-displayed with errors (WCAG 3.3.1). */
  Alpine.data('errorSummary', () => ({
    init() {
      this.$nextTick(() => this.$el.focus());
    },
  }));

  const load = () => Alpine.store('cart').load();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', load, { once: true });
  else load();
}

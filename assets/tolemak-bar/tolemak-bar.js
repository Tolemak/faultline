const MARK =
  '<svg viewBox="0 0 16 16" aria-hidden="true"><rect x="1" y="1" width="14" height="14" rx="3.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4.6 5h6.8M8 5v6.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

const LABELS = {
  pl: { light: "jasny", dark: "ciemny", toLight: "Włącz jasny motyw", toDark: "Włącz ciemny motyw", home: "Strona główna", lang: "Zmień język" },
  en: { light: "light", dark: "dark", toLight: "Switch to light theme", toDark: "Switch to dark theme", home: "Home", lang: "Change language" },
};

const BAR_CSS = `
:host {
  position: fixed; inset: auto 0 0 0; z-index: 50;
  display: flex; align-items: stretch; height: 30px; box-sizing: content-box; overflow: hidden;
  padding-bottom: env(safe-area-inset-bottom, 0px);
  background: var(--tb-bg, #15171a); color: var(--tb-fg, #e4e7eb);
  border-top: 1px solid var(--tb-line, #2c3036);
  font: 500 12px/1 var(--tb-font, "IBM Plex Mono", ui-monospace, monospace);
  font-variant-numeric: tabular-nums;
}
:host([static]) { position: static; }
.mark { display: grid; place-items: center; width: 34px; flex: none; color: inherit; }
.mark svg { width: 14px; height: 14px; }
.app { display: flex; align-items: center; padding-right: 12px; flex: none; font-weight: 600; }
.fields { display: flex; flex: 1; min-width: 0; overflow-x: auto; scrollbar-width: none; }
.fields::-webkit-scrollbar { display: none; }
button {
  flex: none; padding: 0 12px; border: 0; border-left: 1px solid var(--tb-line, #2c3036);
  font: inherit; color: inherit; background: transparent; cursor: pointer;
}
button:hover { color: var(--tb-accent, currentColor); }
.mark:focus-visible, button:focus-visible { outline: 2px solid var(--tb-accent, currentColor); outline-offset: -2px; }
[hidden] { display: none !important; }
`;

const FIELD_CSS = `
:host {
  display: flex; align-items: center; gap: 6px; padding: 0 12px; white-space: nowrap;
  border-left: 1px solid var(--tb-line, #2c3036); color: var(--tb-muted, #8e97a2);
}
.value { color: var(--tb-fg, #e4e7eb); font-weight: 600; }
:host([tone="accent"]) .value { color: var(--tb-accent, inherit); }
:host([tone="warn"]) .value { color: var(--tb-warn, #ffb52e); }
:host([tone="error"]) .value { color: var(--tb-error, #ff5d5d); }
`;

function sheet(css) {
  const result = new CSSStyleSheet();
  result.replaceSync(css);
  return result;
}

const BAR_SHEET = sheet(BAR_CSS);
const FIELD_SHEET = sheet(FIELD_CSS);

function rootTheme() {
  return document.documentElement.dataset.theme === "light" ? "light" : "dark";
}

function pageLang() {
  return document.documentElement.lang.toLowerCase().startsWith("en") ? "en" : "pl";
}

function storeTheme(key, theme) {
  try {
    localStorage.setItem(key, theme);
    return true;
  } catch {
    return false;
  }
}

class TolemakBar extends HTMLElement {
  static observedAttributes = ["app", "home", "langs"];

  constructor() {
    super();
    const root = this.attachShadow({ mode: "open" });
    root.adoptedStyleSheets = [BAR_SHEET];
    root.innerHTML = `<a class="mark" part="mark">${MARK}</a>
      <span class="app" part="app"></span>
      <div class="fields"><slot></slot></div>
      <button type="button" class="lang" part="lang" hidden></button>
      <button type="button" class="theme" part="theme"></button>`;
    this.markEl = root.querySelector(".mark");
    this.appEl = root.querySelector(".app");
    this.langEl = root.querySelector(".lang");
    this.themeEl = root.querySelector(".theme");
    this.themeEl.addEventListener("click", () => this.toggleTheme());
    this.langEl.addEventListener("click", () => this.nextLang());
    this.observer = new MutationObserver(() => this.render());
  }

  connectedCallback() {
    this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme", "lang"] });
    this.render();
  }

  disconnectedCallback() {
    this.observer.disconnect();
  }

  attributeChangedCallback() {
    this.render();
  }

  langList() {
    return (this.getAttribute("langs") ?? "").split(",").map((l) => l.trim()).filter(Boolean);
  }

  render() {
    const text = LABELS[pageLang()];
    const next = rootTheme() === "dark" ? "light" : "dark";
    this.markEl.href = this.getAttribute("home") ?? "/";
    this.markEl.setAttribute("aria-label", text.home);
    this.appEl.textContent = this.getAttribute("app") ?? "";
    this.themeEl.textContent = text[next];
    this.themeEl.setAttribute("aria-label", next === "light" ? text.toLight : text.toDark);
    this.langEl.hidden = this.langList().length < 2;
    this.langEl.textContent = pageLang().toUpperCase();
    this.langEl.setAttribute("aria-label", text.lang);
  }

  toggleTheme() {
    const theme = rootTheme() === "dark" ? "light" : "dark";
    const event = new CustomEvent("tolemak-theme", { detail: { theme }, bubbles: true, cancelable: true });
    if (!this.dispatchEvent(event)) return;
    document.documentElement.dataset.theme = theme;
    storeTheme(this.getAttribute("theme-key") ?? "theme", theme);
  }

  nextLang() {
    const langs = this.langList();
    const lang = langs[(langs.indexOf(pageLang()) + 1) % langs.length];
    this.dispatchEvent(new CustomEvent("tolemak-lang", { detail: { lang }, bubbles: true }));
  }
}

class TolemakField extends HTMLElement {
  static observedAttributes = ["label"];

  constructor() {
    super();
    const root = this.attachShadow({ mode: "open" });
    root.adoptedStyleSheets = [FIELD_SHEET];
    root.innerHTML = `<span class="label" part="label"></span><span class="value" part="value"><slot></slot></span>`;
    this.labelEl = root.querySelector(".label");
  }

  connectedCallback() {
    this.attributeChangedCallback();
  }

  attributeChangedCallback() {
    this.labelEl.textContent = this.getAttribute("label") ?? "";
  }
}

if (!customElements.get("tolemak-bar")) customElements.define("tolemak-bar", TolemakBar);
if (!customElements.get("tolemak-field")) customElements.define("tolemak-field", TolemakField);

import { inject, type InjectionKey } from "vue";

// ---------------------------------------------------------------------
// Texts (from the ILIAS language files, keys without "ui_")
// ---------------------------------------------------------------------

export interface I18n {
  /** text with %s placeholders filled in order */
  t: (key: string, ...args: (string | number)[]) => string;
  /** text for a number: key_one / key_other, %s = the number */
  tn: (key: string, n: number) => string;
  lang: string;
}

export const I18N: InjectionKey<I18n> = Symbol("i18n");

export function createI18n(texts: Record<string, string>, lang: string): I18n {
  const fmt = (n: number | string): string => (typeof n === "number" ? n.toLocaleString(lang === "de" ? "de-CH" : lang) : n);
  const t = (key: string, ...args: (string | number)[]): string => {
    let text = texts[key] ?? key;
    for (const arg of args) {
      text = text.replace("%s", fmt(arg));
    }
    return text;
  };
  return { t, tn: (key, n) => t(`${key}_${n === 1 ? "one" : "other"}`, n), lang };
}

export function useI18n(): I18n {
  const i18n = inject(I18N);
  if (!i18n) {
    throw new Error("i18n missing");
  }
  return i18n;
}

// ---------------------------------------------------------------------
// Server
// ---------------------------------------------------------------------

export class ServerError extends Error {}

export async function postJson<T>(url: string, body: unknown): Promise<T> {
  const response = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  const type = response.headers.get("Content-Type") ?? "";
  if (!type.includes("application/json")) {
    // e.g. the login page of ILIAS after the session expired
    throw new ServerError(`HTTP ${response.status}`);
  }
  return (await response.json()) as T;
}

/**
 * Calls fn again and again: every `interval` ms while the page is visible,
 * more slowly in the background, with growing pauses after errors.
 */
export class Poller {
  private timer: number | undefined;
  private stopped = true;
  private failures = 0;

  constructor(
    private readonly fn: () => Promise<void>,
    private readonly interval: number,
    private readonly onError: (failing: boolean) => void = () => {},
  ) {}

  start(): void {
    this.stopped = false;
    document.addEventListener("visibilitychange", this.onVisibility);
    void this.tick();
  }

  stop(): void {
    this.stopped = true;
    window.clearTimeout(this.timer);
    document.removeEventListener("visibilitychange", this.onVisibility);
  }

  /** run now (e.g. after an own action) */
  now(): void {
    window.clearTimeout(this.timer);
    void this.tick();
  }

  private onVisibility = (): void => {
    if (document.visibilityState === "visible" && !this.stopped) {
      this.now();
    }
  };

  private async tick(): Promise<void> {
    if (this.stopped) {
      return;
    }
    try {
      await this.fn();
      this.failures = 0;
      this.onError(false);
    } catch {
      this.failures++;
      this.onError(this.failures >= 2);
    }
    if (this.stopped) {
      return;
    }
    const hidden = document.visibilityState === "hidden";
    const delay = Math.min(15000, this.interval * (hidden ? 5 : 1) * (1 + this.failures));
    window.clearTimeout(this.timer);
    this.timer = window.setTimeout(() => void this.tick(), delay);
  }
}

// ---------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------

/** palette as CSS variables --uda-p0 … --uda-p3 */
export function paletteStyle(palette: string[]): Record<string, string> {
  const style: Record<string, string> = {};
  palette.forEach((colour, i) => (style[`--uda-p${i}`] = colour));
  return style;
}

export function formatCode(code: string): string {
  return code.length === 6 ? `${code.slice(0, 3)} ${code.slice(3)}` : code;
}

export function percent(count: number, total: number): number {
  return total > 0 ? Math.round((count / total) * 100) : 0;
}

export function formatDateTime(unix: number, lang: string): string {
  return new Date(unix * 1000).toLocaleString(lang === "de" ? "de-CH" : lang, {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

/** random token of this device (participation without login) */
export function deviceToken(): string {
  const key = "uda-token";
  try {
    const stored = window.localStorage.getItem(key);
    if (stored && /^[A-Za-z0-9_-]{16,64}$/.test(stored)) {
      return stored;
    }
  } catch {
    // storage blocked: token only for this page view
  }
  const bytes = new Uint8Array(18);
  crypto.getRandomValues(bytes);
  const token = btoa(String.fromCharCode(...bytes)).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
  try {
    window.localStorage.setItem(key, token);
  } catch {
    // ignore
  }
  return token;
}

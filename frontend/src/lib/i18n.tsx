"use client";

// Lightweight ar/en i18n with RTL flip — no routing changes, locale kept in
// a cookie so the server layout can render the right dir on first paint.

import { createContext, useCallback, useContext, useState } from "react";
import en from "@/messages/en.json";
import ar from "@/messages/ar.json";

export type Locale = "en" | "ar";

const MESSAGES: Record<Locale, Record<string, string>> = { en, ar };

interface I18n {
  locale: Locale;
  dir: "ltr" | "rtl";
  t: (key: string, vars?: Record<string, string | number>) => string;
  setLocale: (locale: Locale) => void;
}

const I18nContext = createContext<I18n>({
  locale: "en",
  dir: "ltr",
  t: (k) => k,
  setLocale: () => {},
});

export function readLocaleCookie(): Locale {
  if (typeof document === "undefined") return "en";
  return document.cookie.includes("mj_locale=ar") ? "ar" : "en";
}

/** Theme lives next to locale — a cookie so SSR paints the right mode. */
export function applyTheme(dark: boolean) {
  document.cookie = `mj_theme=${dark ? "dark" : "light"};path=/;max-age=31536000;samesite=lax`;
  document.documentElement.classList.toggle("dark", dark);
}

export function I18nProvider({
  initial,
  children,
}: {
  initial: Locale;
  children: React.ReactNode;
}) {
  const [locale, setLocaleState] = useState<Locale>(initial);

  const setLocale = useCallback((next: Locale) => {
    document.cookie = `mj_locale=${next};path=/;max-age=31536000;samesite=lax`;
    setLocaleState(next);
    document.documentElement.lang = next;
    document.documentElement.dir = next === "ar" ? "rtl" : "ltr";
  }, []);

  const t = useCallback(
    (key: string, vars?: Record<string, string | number>) => {
      let msg = MESSAGES[locale][key] ?? MESSAGES.en[key] ?? key;
      if (vars) {
        for (const [k, v] of Object.entries(vars)) {
          msg = msg.replaceAll(`:${k}`, String(v));
        }
      }
      return msg;
    },
    [locale]
  );

  return (
    <I18nContext.Provider value={{ locale, dir: locale === "ar" ? "rtl" : "ltr", t, setLocale }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n(): I18n {
  return useContext(I18nContext);
}

import type { Metadata } from "next";
import { Cairo, Inter } from "next/font/google";
import { cookies } from "next/headers";
import "./globals.css";
import { I18nProvider, type Locale } from "@/lib/i18n";
import { AuthProvider } from "@/lib/auth";

const cairo = Cairo({ variable: "--font-cairo", subsets: ["arabic", "latin"] });
const inter = Inter({ variable: "--font-inter", subsets: ["latin"] });

export const metadata: Metadata = {
  title: "MileJet ERP",
  description: "MileJet internal ERP",
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const cookieStore = await cookies();
  const locale: Locale = cookieStore.get("mj_locale")?.value === "ar" ? "ar" : "en";
  const dark = cookieStore.get("mj_theme")?.value === "dark";

  return (
    <html
      lang={locale}
      dir={locale === "ar" ? "rtl" : "ltr"}
      className={`${cairo.variable} ${inter.variable} h-full antialiased ${dark ? "dark" : ""}`}
    >
      <body className="min-h-full bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 font-sans transition-colors">
        <I18nProvider initial={locale}>
          <AuthProvider>{children}</AuthProvider>
        </I18nProvider>
      </body>
    </html>
  );
}

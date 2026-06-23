"use client";

// Fleet module help — a guided reference to every feature (vehicles, states,
// odometer, drivers, services + sub-services, categories, fuel, inspections
// with checklists/templates, and usage/checkout). Bilingual by locale.

import Link from "next/link";
import { useI18n } from "@/lib/i18n";
import { PageHeader } from "@/components/ui";

interface Topic {
  icon: string;
  en: { title: string; body: string[] };
  ar: { title: string; body: string[] };
}

const TOPICS: Topic[] = [
  {
    icon: "🚗",
    en: {
      title: "Vehicles",
      body: [
        "The vehicle list shows every active vehicle with its plate, driver, odometer and state. Use search and the state filter to narrow it down.",
        "Open a vehicle to see full details and manage everything below. Create a vehicle with “New vehicle” — pick a model and (optionally) a category, plate, fuel type and capacity, and an initial driver.",
      ],
    },
    ar: {
      title: "المركبات",
      body: [
        "تعرض قائمة المركبات كل مركبة نشطة مع اللوحة والسائق وعداد المسافة والحالة. استخدم البحث وفلتر الحالة للتصفية.",
        "افتح المركبة لرؤية التفاصيل وإدارة كل ما يلي. أنشئ مركبة عبر «مركبة جديدة» — اختر الطراز و(اختيارياً) التصنيف واللوحة ونوع الوقود وسعته والسائق المبدئي.",
      ],
    },
  },
  {
    icon: "🏷️",
    en: {
      title: "Category, fuel & state",
      body: [
        "Category groups vehicles (e.g. Sedan, Truck, Ambulance). Fuel type and fuel capacity (litres) are shown on the detail page.",
        "Change the vehicle state (e.g. Registered, To Order) from the management panel on the left of the detail page.",
      ],
    },
    ar: {
      title: "التصنيف والوقود والحالة",
      body: [
        "يجمّع التصنيف المركبات (مثل سيدان، شاحنة، إسعاف). يظهر نوع الوقود وسعته (باللتر) في صفحة التفاصيل.",
        "غيّر حالة المركبة (مثل مُسجّلة، تحت الطلب) من لوحة الإدارة على يسار صفحة التفاصيل.",
      ],
    },
  },
  {
    icon: "📊",
    en: {
      title: "Odometer & driver",
      body: [
        "Update the odometer from the management panel — the new reading must be equal to or higher than the current one.",
        "Assign or unassign a driver (an employee). The driver must have a contact record in Odoo.",
      ],
    },
    ar: {
      title: "العداد والسائق",
      body: [
        "حدّث عداد المسافة من لوحة الإدارة — يجب أن تكون القراءة الجديدة مساوية أو أعلى من الحالية.",
        "عيّن أو ألغِ تعيين سائق (موظف). يجب أن يكون للسائق سجل جهة اتصال في Odoo.",
      ],
    },
  },
  {
    icon: "🔧",
    en: {
      title: "Services & sub-services",
      body: [
        "Log a service with its type, cost, date and an optional note. You can also tag “included services” — the sub-services covered by this entry — as chips.",
        "Each service row shows the included services beneath its type.",
      ],
    },
    ar: {
      title: "الخدمات والخدمات الفرعية",
      body: [
        "سجّل خدمة بنوعها وتكلفتها وتاريخها وملاحظة اختيارية. يمكنك أيضاً تحديد «الخدمات المشمولة» — الخدمات الفرعية التي تغطيها هذه الخدمة — كوسوم.",
        "يعرض كل صف خدمة الخدمات المشمولة أسفل نوعها.",
      ],
    },
  },
  {
    icon: "✅",
    en: {
      title: "Inspections & checklists",
      body: [
        "Create an inspection (check-in or check-out). Start from a template to load its checklist, or pick individual items.",
        "Expand an inspection to mark each item Passed / Failed. Once every item has a result, press Confirm to complete it. Confirmed inspections are read-only — use Reopen to edit again, or Cancel/Delete.",
        "Templates and items are managed in Odoo (Fleet → Inspections) and appear here automatically.",
      ],
    },
    ar: {
      title: "الفحوصات وقوائم التحقق",
      body: [
        "أنشئ فحصاً (استلام أو تسليم). ابدأ من قالب لتحميل قائمة التحقق، أو اختر عناصر مفردة.",
        "وسّع الفحص لتحديد كل عنصر ناجح / راسب. بعد أن يحصل كل عنصر على نتيجة، اضغط «تأكيد» لإكماله. الفحوصات المؤكدة للقراءة فقط — استخدم «إعادة فتح» للتعديل مجدداً، أو إلغاء/حذف.",
        "تُدار القوالب والعناصر في Odoo (الأسطول ← الفحوصات) وتظهر هنا تلقائياً.",
      ],
    },
  },
  {
    icon: "🔑",
    en: {
      title: "Usage / checkout",
      body: [
        "Record who has a vehicle and for how long. Choose a driver (an employee with a login account), the pick-up and return dates, and a note.",
        "Drive the workflow with Pick up → Return, or Cancel. The vehicle’s “in use” flag follows the active usage.",
      ],
    },
    ar: {
      title: "الاستخدام / الاستلام",
      body: [
        "سجّل من بحوزته المركبة ولأي مدة. اختر سائقاً (موظفاً لديه حساب دخول)، وتاريخي الاستلام والإرجاع، وملاحظة.",
        "أدر سير العمل عبر استلام ← إرجاع، أو إلغاء. تتبع حالة «قيد الاستخدام» للمركبة الاستخدامَ النشط.",
      ],
    },
  },
];

export default function FleetHelp() {
  const { t, locale } = useI18n();
  const lang = locale === "ar" ? "ar" : "en";

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("fleet.help")}>
        <Link href="/fleet" className="text-sm text-brand-600 hover:underline">
          ← {t("nav.vehicles")}
        </Link>
      </PageHeader>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {TOPICS.map((topic, i) => {
          const c = topic[lang];
          return (
            <section key={i} className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
              <h2 className="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100 mb-2">
                <span aria-hidden>{topic.icon}</span> {c.title}
              </h2>
              <div className="space-y-2">
                {c.body.map((p, j) => (
                  <p key={j} className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    {p}
                  </p>
                ))}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}

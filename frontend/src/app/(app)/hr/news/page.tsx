"use client";

// Company news / announcements. HR (hr.view_all) posts + manages; the feed is
// what the mobile app shows employees. Laravel-only module (no Odoo).

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Spinner, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextArea, TextInput } from "@/components/form";

interface Post {
  id: number; title: string; body: string; category: string; author: string;
  pinned: boolean; published: boolean; published_at: string | null;
}
const CATEGORIES = ["announcement", "policy", "event", "general"] as const;
const CAT_TONE: Record<string, string> = { announcement: "brand", policy: "amber", event: "green", general: "slate" };

export default function NewsPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const manage = can("hr.view_all");

  const [posts, setPosts] = useState<Post[] | null>(null);
  const [editing, setEditing] = useState<Post | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const load = useCallback(() => {
    api.get<{ data: Post[] }>("/hr/news").then((r) => setPosts(r.data)).catch(() => setPosts([]));
  }, []);
  useEffect(load, [load]);

  async function remove(p: Post) {
    if (!confirm(t("news.confirm_delete"))) return;
    setError(null);
    try { await api.del(`/hr/news/${p.id}`); setOk(t("common.saved")); load(); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  async function togglePublish(p: Post) {
    setError(null);
    try { await api.put(`/hr/news/${p.id}`, { title: p.title, published: !p.published }); load(); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("news.title")}>
        {manage && <button onClick={() => { setEditing(null); setShowForm((v) => !v); }} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("news.new")}</button>}
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      {manage && showForm && (
        <PostForm
          post={editing}
          onDone={() => { setShowForm(false); setEditing(null); setOk(t("common.saved")); load(); }}
          onError={setError}
        />
      )}

      {!posts ? <Spinner /> : (
        <Table head={<><Th>{t("news.post")}</Th><Th>{t("news.category")}</Th><Th>{t("news.author")}</Th><Th>{t("common.date")}</Th>{manage && <Th end>{t("common.actions")}</Th>}</>}>
          {posts.length === 0 && <EmptyRow colSpan={manage ? 5 : 4} />}
          {posts.map((p) => (
            <tr key={p.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 align-top">
              <Td>
                <span className="font-medium text-slate-900 dark:text-slate-100">{p.pinned && <span title={t("news.pinned")}>📌 </span>}{p.title}</span>
                {p.body && <span className="block text-xs text-slate-500 line-clamp-2 max-w-md">{p.body}</span>}
              </Td>
              <Td><Badge tone={CAT_TONE[p.category] ?? "slate"}>{t(`news.cat_${p.category}`)}</Badge></Td>
              <Td className="text-sm">{p.author}</Td>
              <Td className="text-xs tabular-nums text-slate-500">{p.published_at ? p.published_at.slice(0, 10) : "—"}{!p.published && <span className="block text-amber-500">{t("news.draft")}</span>}</Td>
              {manage && (
                <Td end>
                  <span className="whitespace-nowrap">
                    <button onClick={() => togglePublish(p)} className="text-xs text-brand-600 hover:underline me-2">{p.published ? t("news.unpublish") : t("news.publish")}</button>
                    <button onClick={() => { setEditing(p); setShowForm(true); }} className="text-xs text-slate-600 hover:underline me-2">{t("common.edit")}</button>
                    <button onClick={() => remove(p)} className="text-xs text-rose-600 hover:underline">{t("common.delete")}</button>
                  </span>
                </Td>
              )}
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}

function PostForm({ post, onDone, onError }: { post: Post | null; onDone: () => void; onError: (m: string) => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState<Record<string, string>>({
    title: post?.title ?? "", body: post?.body ?? "", category: post?.category ?? "announcement",
    pinned: post?.pinned ? "1" : "0", published: post ? (post.published ? "1" : "0") : "1",
  });
  const [busy, setBusy] = useState(false);
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); onError("");
    const body = {
      title: form.title, body: form.body || undefined, category: form.category,
      pinned: form.pinned === "1", published: form.published === "1",
    };
    try {
      if (post) await api.put(`/hr/news/${post.id}`, body);
      else await api.post("/hr/news", body);
      onDone();
    } catch (err) { onError(err instanceof ApiError ? err.message : t("common.error")); setBusy(false); }
  }

  return (
    <div className="mb-5">
      <FormCard onSubmit={submit}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="md:col-span-2"><Field label={t("news.post_title")} required><TextInput value={form.title} onChange={set("title")} /></Field></div>
          <Field label={t("news.category")}>
            <Select value={form.category} onChange={set("category")}>
              {CATEGORIES.map((c) => <option key={c} value={c}>{t(`news.cat_${c}`)}</option>)}
            </Select>
          </Field>
          <Field label={t("news.pinned")}>
            <Select value={form.pinned} onChange={set("pinned")}><option value="0">—</option><option value="1">{t("news.pinned")}</option></Select>
          </Field>
          <Field label={t("news.status")}>
            <Select value={form.published} onChange={set("published")}><option value="1">{t("news.published")}</option><option value="0">{t("news.draft")}</option></Select>
          </Field>
          <div className="md:col-span-3"><Field label={t("news.body")}><TextArea value={form.body} onChange={set("body")} rows={4} /></Field></div>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}

"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TODAY, fmtDate } from "@/lib/format";
import type { CnbComment, PrebookPatient, PrebookType } from "@/lib/types";
import { Card, PageHead, TableWrap } from "@/components/ui";
import { getClientSession, isReadOnlyRole } from "@/lib/clientSession";

const LABELS: Record<PrebookType, string> = { scheduled: "Appointments Scheduled", missed: "Missed Appointments", cnb: "Consulted Not Booked" };
const DATE_LABELS: Record<PrebookType, string> = { scheduled: "Date of Appointment", missed: "Date of Missed Appointment", cnb: "Date of Consult" };
const RANGE_LABELS: Record<string, string> = { today: "today", week: "this week", month: "this month", all: "all time", custom: "custom dates" };

function inRange(iso: string, range: string, from = "", to = ""): boolean {
  if (range === "all") return true;
  if (range === "custom") {
    const day = (iso || "").slice(0, 10);
    if (from && day < from) return false;
    if (to && day > to) return false;
    return true;
  }
  const T = new Date(TODAY + "T00:00:00");
  const d = new Date(iso + "T00:00:00");
  if (range === "today") return d.getTime() === T.getTime();
  if (range === "week") {
    const dow = T.getDay();
    const off = (dow + 6) % 7;
    const mon = new Date(T);
    mon.setDate(T.getDate() - off);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    return d >= mon && d <= sun;
  }
  if (range === "month") return d.getMonth() === T.getMonth() && d.getFullYear() === T.getFullYear();
  return true;
}

const QUALITIES = ["Hot", "Cold", "Dead"] as const;
const QUALITY_CLS: Record<string, string> = { Hot: "bg-red-soft text-red", Cold: "bg-blue-soft text-blue", Dead: "bg-surface-2 text-text-soft" };

interface CnbEdit {
  quality?: (typeof QUALITIES)[number];
}

interface CommentDraft {
  text: string;
  date: string;
}

type SaveState = "saving" | "saved" | "error";

export default function PrebookList({ type, prebook }: { type: PrebookType; prebook: PrebookPatient[] }) {
  // Missed appointments are always in the past, so "today" would always be empty - default them to all time.
  const defaultRange = type === "missed" ? "all" : "today";
  const [range, setRange] = useState(defaultRange);
  const [centreFilter, setCentreFilter] = useState("all");
  const [fromDate, setFromDate] = useState("");
  const [toDate, setToDate] = useState("");
  const [doctorFilter, setDoctorFilter] = useState("all");
  const [treatmentFilter, setTreatmentFilter] = useState("all");
  const [qualityFilter, setQualityFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [edits, setEdits] = useState<Record<string, CnbEdit>>({});
  const [saveState, setSaveState] = useState<Partial<Record<string, SaveState>>>({});
  // Comment log: drafts being typed, and the full comment list per patient once a comment was added here.
  const [drafts, setDrafts] = useState<Record<string, CommentDraft>>({});
  const [commentLists, setCommentLists] = useState<Record<string, CnbComment[]>>({});
  const [commentState, setCommentState] = useState<Partial<Record<string, "saving" | "error">>>({});
  const [commentError, setCommentError] = useState<Record<string, string>>({});
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);

  // Filter choices come from this page's own patients (Consulted Not Booked only).
  const typeRows = useMemo(() => prebook.filter((p) => p.type === type), [prebook, type]);
  const centreOptions = useMemo(
    () => Array.from(new Set<string>(typeRows.map((p) => (p.centre || "").trim()).filter(Boolean))).sort((a: string, b: string) => a.localeCompare(b)),
    [typeRows]
  );
  const doctorOptions = useMemo(
    () => Array.from(new Set<string>(typeRows.map((p) => (p.doctor || "").trim()).filter(Boolean))).sort((a: string, b: string) => a.localeCompare(b)),
    [typeRows]
  );
  const treatmentOptions = useMemo(
    () =>
      Array.from(new Set<string>(typeRows.flatMap((p) => p.treatments || []).map((t: string) => t.trim()).filter(Boolean))).sort(
        (a: string, b: string) => a.localeCompare(b)
      ),
    [typeRows]
  );

  const list = useMemo(
    () =>
      typeRows.filter((p) => {
        // Search by patient (IIC) ID or name
        const q = search.trim().toLowerCase();
        if (q && !String(p.id || "").toLowerCase().includes(q) && !String(p.name || "").toLowerCase().includes(q)) return false;
        // While searching, the date range is ignored so an ID is found whatever its consult date.
        if (!q && !inRange(p.date, range, fromDate, toDate)) return false;
        if (centreFilter !== "all" && (p.centre || "").trim() !== centreFilter) return false;
        if (type === "cnb") {
          // saved quality (a row with nothing saved yet counts as "Cold", same as the dropdown shows)
          if (qualityFilter !== "all" && (p.quality || "Cold") !== qualityFilter) return false;
          if (doctorFilter !== "all" && (p.doctor || "").trim() !== doctorFilter) return false;
          if (treatmentFilter !== "all") {
            if (treatmentFilter === "__none__") {
              if ((p.treatments || []).length > 0) return false;
            } else if (!(p.treatments || []).includes(treatmentFilter)) return false;
          }
        }
        return true;
      }),
    [typeRows, type, search, range, fromDate, toDate, centreFilter, doctorFilter, treatmentFilter, qualityFilter]
  );

  const filtersActive = search.trim() !== "" || range !== defaultRange || centreFilter !== "all" || doctorFilter !== "all" || treatmentFilter !== "all" || qualityFilter !== "all";
  function clearFilters() {
    setRange(defaultRange);
    setCentreFilter("all");
    setFromDate("");
    setToDate("");
    setDoctorFilter("all");
    setTreatmentFilter("all");
    setQualityFilter("all");
    setSearch("");
  }

  useEffect(() => {
    setPage(1);
  }, [type, search, range, fromDate, toDate, centreFilter, doctorFilter, treatmentFilter, qualityFilter, pageSize]);

  const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageStart = (safePage - 1) * pageSize;
  const paged = list.slice(pageStart, pageStart + pageSize);

  const [readOnly, setReadOnly] = useState(false);
  useEffect(() => {
    setReadOnly(isReadOnlyRole(getClientSession()?.role));
  }, []);

  // Quality is the only editable field; it saves as soon as it is changed.
  async function saveQuality(p: PrebookPatient, quality: (typeof QUALITIES)[number]) {
    setEdits((s) => ({ ...s, [p.id]: { ...s[p.id], quality } }));
    setSaveState((s) => ({ ...s, [p.id]: "saving" }));
    try {
      const res = await fetch("/api/cnb-edits", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          edits: [
            {
              patient_id: p.id,
              quality,
              // keep the earlier single-box values untouched
              fc_comment: p.fcComment ?? "",
              latest_connected_date: p.lastConn || null,
              latest_comment: p.lastComment ?? "",
            },
          ],
        }),
      });
      if (!res.ok) throw new Error("save failed");
      setSaveState((s) => ({ ...s, [p.id]: "saved" }));
    } catch {
      setSaveState((s) => ({ ...s, [p.id]: "error" }));
    }
  }

  function setDraft(id: string, patch: Partial<CommentDraft>) {
    setDrafts((s) => {
      const prev: CommentDraft = s[id] ?? { text: "", date: TODAY };
      return { ...s, [id]: { ...prev, ...patch } };
    });
    setCommentError((s) => ({ ...s, [id]: "" }));
  }

  // Add one more comment to the log. Saved comments can never be edited or deleted.
  async function addComment(p: PrebookPatient) {
    const d = drafts[p.id] || { text: "", date: TODAY };
    const text = d.text.trim();
    if (!text) {
      setCommentError((s) => ({ ...s, [p.id]: "Write a comment first." }));
      return;
    }
    setCommentState((s) => ({ ...s, [p.id]: "saving" }));
    try {
      const res = await fetch("/api/cnb-comment", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          patient_id: p.id,
          comment: text,
          connected_date: d.date || TODAY,
          created_by: getClientSession()?.name || "",
        }),
      });
      const data = await res.json();
      if (!res.ok || data.status !== "success") throw new Error(data.message || "save failed");
      setCommentLists((s) => ({ ...s, [p.id]: data.comments as CnbComment[] }));
      setDrafts((s) => ({ ...s, [p.id]: { text: "", date: TODAY } }));
      setCommentState((s) => ({ ...s, [p.id]: undefined }));
    } catch (err) {
      setCommentState((s) => ({ ...s, [p.id]: "error" }));
      setCommentError((s) => ({ ...s, [p.id]: err instanceof Error ? err.message : "Could not save the comment." }));
    }
  }

  return (
    <section className="screen-enter">
      <PageHead
        title={LABELS[type]}
        sub={`${list.length} patient${list.length !== 1 ? "s" : ""} · ${
          range === "custom" ? (fromDate || toDate ? `${fromDate || "…"} to ${toDate || "…"}` : "custom dates") : RANGE_LABELS[range]
        }${type === "missed" || type === "scheduled" ? "" : " · click an IIC ID to open the patient journey"}`}
        actions={
          <>
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search patient ID / name"
              className="w-[190px] rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]"
            />
            <select value={range} onChange={(e) => setRange(e.target.value)} className="rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]">
              <option value="today">Today</option>
              <option value="week">This week</option>
              <option value="month">This month</option>
              <option value="all">All time</option>
              <option value="custom">Custom dates…</option>
            </select>
            {range === "custom" && (
              <>
                <input
                  type="date"
                  value={fromDate}
                  max={toDate || undefined}
                  onChange={(e) => setFromDate(e.target.value)}
                  title="From date"
                  className="rounded-[9px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]"
                />
                <span className="text-[11px] text-text-soft">to</span>
                <input
                  type="date"
                  value={toDate}
                  min={fromDate || undefined}
                  onChange={(e) => setToDate(e.target.value)}
                  title="To date"
                  className="rounded-[9px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]"
                />
              </>
            )}
            {centreOptions.length > 1 && (
              <select
                value={centreFilter}
                onChange={(e) => setCentreFilter(e.target.value)}
                className="max-w-[190px] rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]"
                title="Filter by centre"
              >
                <option value="all">All centres</option>
                {centreOptions.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            )}
            {type === "cnb" && (
              <>
                <select
                  value={qualityFilter}
                  onChange={(e) => setQualityFilter(e.target.value)}
                  className="rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]"
                  title="Filter by quality"
                >
                  <option value="all">All quality</option>
                  {QUALITIES.map((q) => (
                    <option key={q} value={q}>
                      {q}
                    </option>
                  ))}
                </select>
                <select
                  value={doctorFilter}
                  onChange={(e) => setDoctorFilter(e.target.value)}
                  className="max-w-[190px] rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]"
                  title="Filter by doctor"
                >
                  <option value="all">All doctors</option>
                  {doctorOptions.map((d) => (
                    <option key={d} value={d}>
                      {d}
                    </option>
                  ))}
                </select>
                <select
                  value={treatmentFilter}
                  onChange={(e) => setTreatmentFilter(e.target.value)}
                  className="max-w-[260px] rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]"
                  title="Filter by treatment advised"
                >
                  <option value="all">All treatments</option>
                  <option value="__none__">No treatment advised</option>
                  {treatmentOptions.map((t) => (
                    <option key={t} value={t}>
                      {t}
                    </option>
                  ))}
                </select>
              </>
            )}
            {filtersActive && (
              <button
                type="button"
                onClick={clearFilters}
                className="rounded-[9px] border border-border px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary"
              >
                Clear filters
              </button>
            )}
            <Link href="/dashboard" className="rounded-[9px] border border-border px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary">
              ← Back to dashboard
            </Link>
          </>
        }
      />

      <Card flush>
        {/* CNB: the table scrolls inside this box, so the header row and the first four columns
            (SN, IIC ID, Patient Name, Date of Consult) stay pinned while the rest scrolls. */}
        <TableWrap
          className={
            type === "cnb"
              ? "max-h-[calc(100vh-170px)] overflow-y-auto [&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-[3] [&_thead_th.sticky-l]:z-[5] [&_thead_th.sticky-l2]:z-[5] [&_thead_th.sticky-l3]:z-[5]"
              : ""
          }
        >
          {type === "cnb" ? (
            <table className="tbl">
              <thead>
                <tr>
                  <th className="sticky-l" style={{ width: 56, minWidth: 56, maxWidth: 56 }}>SN</th>
                  <th className="sticky-l2" style={{ left: 56, width: 150, minWidth: 150, maxWidth: 150 }}>IIC ID</th>
                  <th className="sticky-l2" style={{ left: 206, width: 200, minWidth: 200, maxWidth: 200 }}>Patient Name</th>
                  <th className="sticky-l3" style={{ left: 406, width: 120, minWidth: 120, maxWidth: 120 }}>Date of Consult</th>
                  <th>Centre</th>
                  <th>Doctor</th>
                  <th>Treatment Advised</th>
                  <th>Quality</th>
                  <th>Comments</th>
                  <th>Add Comment</th>
                </tr>
              </thead>
              <tbody>
                {list.length === 0 && (
                  <tr>
                    <td colSpan={10} className="text-center text-text-soft">
                      No patients match the current filter.
                    </td>
                  </tr>
                )}
                {paged.map((p, n) => {
                  const e = edits[p.id] || {};
                  const quality = e.quality || p.quality || "Cold";
                  const comments = commentLists[p.id] ?? p.comments ?? [];
                  const draft = drafts[p.id] || { text: "", date: TODAY };
                  return (
                    <tr key={`${p.id}-${pageStart + n}`}>
                      <td className="sn-col sticky-l" style={{ width: 56, minWidth: 56, maxWidth: 56 }}>{pageStart + n + 1}</td>
                      <td className="sticky-l2" style={{ left: 56, width: 150, minWidth: 150, maxWidth: 150 }}>
                        <Link href={`/journey?id=${p.id}`} className="iic-link">
                          {p.id}
                        </Link>
                      </td>
                      <td className="strong sticky-l2" style={{ left: 206, width: 200, minWidth: 200, maxWidth: 200, overflow: "hidden", textOverflow: "ellipsis" }} title={p.name}>
                        {p.name}
                      </td>
                      <td className="sticky-l3" style={{ left: 406, width: 120, minWidth: 120, maxWidth: 120 }}>{fmtDate(p.date)}</td>
                      <td>{p.centre}</td>
                      <td>{p.doctor || "—"}</td>
                      <td>{p.treatment || "—"}</td>
                      <td>
                        <select value={quality} disabled={readOnly} onChange={(e2) => saveQuality(p, e2.target.value as (typeof QUALITIES)[number])} className="rounded-[6px] border border-border bg-surface px-1.5 py-1 text-[11px]">
                          {QUALITIES.map((q) => (
                            <option key={q} value={q}>
                              {q}
                            </option>
                          ))}
                        </select>
                        <div className={`mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold ${QUALITY_CLS[quality]}`}>{quality}</div>
                        {saveState[p.id] === "saving" && <div className="mt-1 text-[10.5px] text-text-soft">Saving…</div>}
                        {saveState[p.id] === "saved" && <div className="mt-1 text-[10.5px] font-semibold text-green">Saved ✓</div>}
                        {saveState[p.id] === "error" && <div className="mt-1 text-[10.5px] font-semibold text-red">Save failed</div>}
                      </td>
                      <td>
                        {comments.length === 0 ? (
                          <span className="text-[11px] text-text-soft">No comments yet</span>
                        ) : (
                          <div className="scroll-thin max-h-[120px] w-[300px] space-y-1.5 overflow-y-auto pr-1">
                            {comments.map((c) => (
                              <div key={c.id} className="rounded-[6px] border border-border-soft bg-surface-2 px-2 py-1 text-[11px] leading-snug">
                                <div className="whitespace-pre-wrap break-words text-text">{c.text}</div>
                                <div className="mt-0.5 text-[10px] text-text-soft">
                                  {c.date ? `Connected ${fmtDate(c.date)}` : ""}
                                  {c.date && (c.by || c.at) ? " · " : ""}
                                  {c.by}
                                  {c.at ? ` · ${new Date(c.at).toLocaleString()}` : ""}
                                </div>
                              </div>
                            ))}
                          </div>
                        )}
                      </td>
                      <td>
                        {readOnly ? (
                          <span className="text-[10.5px] text-text-soft">View only</span>
                        ) : (
                          <div className="w-[230px] space-y-1">
                            <textarea
                              rows={2}
                              value={draft.text}
                              onChange={(e2) => setDraft(p.id, { text: e2.target.value })}
                              placeholder="Write a new comment…"
                              className="w-full rounded-[6px] border border-border bg-surface px-2 py-1 text-[11px]"
                            />
                            <div className="flex items-center gap-1.5">
                              <input
                                type="date"
                                value={draft.date}
                                max={TODAY}
                                onChange={(e2) => setDraft(p.id, { date: e2.target.value })}
                                title="Date the patient was connected"
                                className="rounded-[6px] border border-border bg-surface px-1.5 py-1 text-[11px]"
                              />
                              <button
                                type="button"
                                onClick={() => addComment(p)}
                                disabled={commentState[p.id] === "saving"}
                                className="rounded-[8px] bg-primary px-2.5 py-1 text-[11px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                              >
                                {commentState[p.id] === "saving" ? "Adding…" : "Add"}
                              </button>
                            </div>
                            {commentError[p.id] && <div className="text-[10.5px] font-semibold text-red">{commentError[p.id]}</div>}
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          ) : (
            <table className="tbl">
              <thead>
                <tr>
                  <th>SN</th>
                  <th>IIC ID</th>
                  <th>Patient Name</th>
                  <th>{DATE_LABELS[type]}</th>
                  <th>Centre</th>
                </tr>
              </thead>
              <tbody>
                {list.length === 0 && (
                  <tr>
                    <td colSpan={5} className="text-center text-text-soft">
                      No patients match the current filter.
                    </td>
                  </tr>
                )}
                {paged.map((p, n) => (
                  <tr key={`${p.id}-${pageStart + n}`}>
                    <td className="sn-col">{pageStart + n + 1}</td>
                    <td>
                      {type === "missed" || type === "scheduled" ? (
                        // Missed / scheduled patients have not been billed yet, so there is no journey to open.
                        <span>{p.id}</span>
                      ) : (
                        <Link href={`/journey?id=${p.id}`} className="iic-link">
                          {p.id}
                        </Link>
                      )}
                    </td>
                    <td className="strong">{p.name}</td>
                    <td>{fmtDate(p.date)}</td>
                    <td>{p.centre}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </TableWrap>

        <div className="flex flex-wrap items-center gap-2.5 border-t border-border-soft px-[18px] py-[12px] text-[11.5px]">
          <span className="text-text-soft">
            {list.length === 0 ? "0 records" : `Showing ${pageStart + 1}–${Math.min(pageStart + pageSize, list.length)} of ${list.length}`}
          </span>
          <select
            value={pageSize}
            onChange={(e) => setPageSize(Number(e.target.value))}
            className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]"
          >
            <option value={25}>25 / page</option>
            <option value={50}>50 / page</option>
            <option value={100}>100 / page</option>
            <option value={250}>250 / page</option>
          </select>
          <div className="ml-auto flex items-center gap-1.5">
            <button
              type="button"
              onClick={() => setPage(1)}
              disabled={safePage <= 1}
              className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
            >
              « First
            </button>
            <button
              type="button"
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={safePage <= 1}
              className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
            >
              ‹ Prev
            </button>
            <span className="px-1.5 text-text-soft">
              Page {safePage} of {totalPages}
            </span>
            <button
              type="button"
              onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
              disabled={safePage >= totalPages}
              className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
            >
              Next ›
            </button>
            <button
              type="button"
              onClick={() => setPage(totalPages)}
              disabled={safePage >= totalPages}
              className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
            >
              Last »
            </button>
          </div>
        </div>
      </Card>
    </section>
  );
}

"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TODAY, fmtDate, fmtINR } from "@/lib/format";
import { FY_LABELS, FY_MONTHS } from "@/lib/seed-data";
import { bucketIdx, patientGstSplit, paymentInMonth, paymentsTotal, pkgDisc, pkgFY, pkgMonthLabel } from "@/lib/derive";
import type { AgingPatient } from "@/lib/types";

const BUCKET_LABELS = ["0–30 d", "31–60 d", "61–90 d", "91–180 d", "180+ d"];

const FOLLOWUP_STATUSES = ["Active", "Branch Action Required", "On Hold", "Revert to Telecaller team", "Cancellation Request"] as const;

function inDateRange(iso: string, range: string, single: string, from: string, to: string): boolean {
  if (range === "all" || !iso) return true;
  const T = new Date(TODAY + "T00:00:00");
  const d = new Date(iso + "T00:00:00");
  if (range === "day") return d.getTime() === T.getTime();
  if (range === "date") return single ? iso === single : true;
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
  if (range === "year") return d.getFullYear() === T.getFullYear();
  if (range === "custom") {
    if (!from || !to) return true;
    return iso >= from && iso <= to;
  }
  return true;
}

export default function AgingPatientList({ patients }: { patients: AgingPatient[] }) {
  const [localPatients, setLocalPatients] = useState<AgingPatient[]>(patients);
  const [bucket, setBucket] = useState<number | null>(null);
  const [search, setSearch] = useState("");
  const [range, setRange] = useState("all");
  const [single, setSingle] = useState(TODAY);
  const [from, setFrom] = useState("2026-01-01");
  const [to, setTo] = useState(TODAY);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);
  // Booking / Sales Reporting / Billing centre are usually the same value — collapsed by default
  // into a single "Centre" column; the toggle in the header expands all three.
  const [centresOpen, setCentresOpen] = useState(false);
  // The 24 monthly collection columns are grouped by financial year (12 months each).
  // Each FY is collapsed by default into one total column; click its header to expand months.
  const FY_GROUPS = [
    { label: "FY 25-26", months: FY_MONTHS.slice(0, 12), labels: FY_LABELS.slice(0, 12) },
    { label: "FY 26-27", months: FY_MONTHS.slice(12, 24), labels: FY_LABELS.slice(12, 24) },
  ];
  const [fyOpen, setFyOpen] = useState<boolean[]>([false, false]);
  const toggleFy = (i: number) => setFyOpen((o) => o.map((v, j) => (j === i ? !v : v)));
  const fyColCount = FY_GROUPS.reduce((n, g, i) => n + (fyOpen[i] ? g.months.length : 1), 0);

  const [followUpTarget, setFollowUpTarget] = useState<AgingPatient | null>(null);
  const [followUpStatus, setFollowUpStatus] = useState<string>(FOLLOWUP_STATUSES[0]);
  const [followUpRemarks, setFollowUpRemarks] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState("");

  useEffect(() => {
    setLocalPatients(patients);
  }, [patients]);

  function openFollowUp(p: AgingPatient) {
    setFollowUpTarget(p);
    setFollowUpStatus((FOLLOWUP_STATUSES as readonly string[]).includes(p.status) ? p.status : FOLLOWUP_STATUSES[0]);
    setFollowUpRemarks("");
    setSaveError("");
  }

  async function submitFollowUp() {
    if (!followUpTarget) return;
    setSaving(true);
    setSaveError("");
    try {
      const res = await fetch("/api/aging-followup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          invoice: followUpTarget.invoice,
          patient_id: followUpTarget.id,
          status: followUpStatus,
          remarks: followUpRemarks.trim(),
        }),
      });
      const data = await res.json();
      if (!res.ok || data.status !== "success") throw new Error(data.message || "Failed to save follow-up.");

      const now = new Date();
      const stamp = now.toLocaleString("en-IN", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
      let logLine = `[${stamp}] ${followUpStatus}`;
      if (followUpRemarks.trim()) logLine += ` — ${followUpRemarks.trim()}`;

      setLocalPatients((list) =>
        list.map((p) =>
          p.invoice === followUpTarget.invoice
            ? {
                ...p,
                status: followUpStatus,
                lastFu: TODAY,
                referred: followUpStatus === "Revert to Telecaller team" ? TODAY : p.referred,
                history: p.history ? `${p.history}\n${logLine}` : logLine,
              }
            : p
        )
      );
      setFollowUpTarget(null);
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : "Failed to save follow-up.");
    } finally {
      setSaving(false);
    }
  }

  const bucketCounts = useMemo(() => [0, 1, 2, 3, 4].map((i) => localPatients.filter((p) => bucketIdx(p.daysOverdue) === i).length), [localPatients]);

  const filtered = useMemo(() => {
    let list = localPatients.slice();
    if (bucket !== null) list = list.filter((p) => bucketIdx(p.daysOverdue) === bucket);
    list = list.filter((p) => inDateRange(p.lastFu, range, single, from, to));
    const q = search.trim().toLowerCase();
    if (q) list = list.filter((p) => p.name.toLowerCase().includes(q) || p.id.toLowerCase().includes(q));
    return list;
  }, [localPatients, bucket, range, single, from, to, search]);

  useEffect(() => {
    setPage(1);
  }, [bucket, range, single, from, to, search, pageSize]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageStart = (safePage - 1) * pageSize;
  const paged = filtered.slice(pageStart, pageStart + pageSize);

  return (
    <div className="rounded-[14px] border border-border bg-surface shadow-card">
      <div className="flex flex-wrap items-center gap-2.5 border-b border-border-soft px-[18px] py-[15px]">
        <div className="text-sm font-semibold text-primary-dark">
          Aging Patient List <span className="font-normal text-text-soft">· {filtered.length} record{filtered.length !== 1 ? "s" : ""}</span>
        </div>
        <div className="ml-auto flex flex-wrap items-center gap-2">
          <input
            type="search"
            placeholder="Search name or IIC ID…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-[220px] rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px] outline-none focus:border-primary"
          />
          <select value={range} onChange={(e) => setRange(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]">
            <option value="all">All time</option>
            <option value="date">Specific date</option>
            <option value="day">Today</option>
            <option value="week">This week</option>
            <option value="month">This month</option>
            <option value="year">This year</option>
            <option value="custom">Custom range</option>
          </select>
          {range === "date" && (
            <input type="date" value={single} onChange={(e) => setSingle(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
          )}
          {range === "custom" && (
            <span className="flex items-center gap-1.5 text-[11.5px] text-text-soft">
              <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
              <span>to</span>
              <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
            </span>
          )}
        </div>
      </div>

      <div className="px-[18px] pb-[14px] pt-[15px]">
        <div className="mb-2 text-[11px] font-semibold uppercase tracking-[.04em] text-text-soft">Filter by aging bucket</div>
        <div className="flex flex-wrap gap-1.5">
          <button
            type="button"
            onClick={() => setBucket(null)}
            className={`rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition-colors ${bucket === null ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"}`}
          >
            All <span className="ml-1 text-text-soft">{localPatients.length}</span>
          </button>
          {BUCKET_LABELS.map((l, i) => (
            <button
              key={l}
              type="button"
              onClick={() => setBucket(i)}
              className={`rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition-colors ${bucket === i ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"}`}
            >
              {l} <span className="ml-1 text-text-soft">{bucketCounts[i]}</span>
            </button>
          ))}
        </div>
      </div>

      {/* The table scrolls inside this box (both ways), so the header row can stay pinned at the
          top while rows scroll. Tailwind utilities beat the .tbl component styles, so the th rules
          below override the plain header cells; the Date / IIC ID / Patient Name headers keep a
          higher z-index so they stay on top of the other headers at the top-left corner. */}
      <div className="scroll-thin max-h-[calc(100vh-150px)] overflow-auto border-t border-border-soft [&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-[3] [&_thead_th.sticky-l]:z-[5] [&_thead_th.sticky-l2]:z-[5] [&_thead_th.sticky-l3]:z-[5]">
        <table className="tbl">
          <thead>
            <tr>
              <th className="sticky-l" style={{ width: 110, minWidth: 110, maxWidth: 110 }}>Date</th>
              <th className="sticky-l2" style={{ width: 150, minWidth: 150, maxWidth: 150 }}>IIC ID</th>
              <th className="sticky-l3" style={{ width: 200, minWidth: 200, maxWidth: 200 }}>Patient Name</th>
              <th>Pkg Month</th>
              <th>Pkg Booking Yr</th>
              <th>FY</th>
              <th>Pkg Code</th>
              <th>Pkg Description</th>
              <th>
                <button
                  type="button"
                  onClick={() => setCentresOpen((o) => !o)}
                  title={centresOpen ? "Collapse centre columns" : "Expand centre columns"}
                  className="inline-flex items-center gap-1.5 font-[inherit] uppercase tracking-[inherit] text-inherit hover:text-primary"
                >
                  <span className="flex h-4 w-4 items-center justify-center rounded border border-border bg-surface text-[10px] leading-none">
                    {centresOpen ? "‹" : "›"}
                  </span>
                  {centresOpen ? "Booking Centre" : "Centre"}
                </button>
              </th>
              {centresOpen && <th>Sales Reporting Centre</th>}
              {centresOpen && <th>Billing Centre</th>}
              <th>Document Type</th>
              <th>Invoice No</th>
              <th className="col-num">Gross Revenue Pkg</th>
              <th className="col-num">Discount %</th>
              <th className="col-num">Discount</th>
              <th className="col-num">Booked Pkg Inc GST</th>
              <th className="col-num">GST</th>
              <th className="col-num">Booked Pkg Ex GST</th>
              {FY_GROUPS.map((g, gi) =>
                fyOpen[gi] ? (
                  g.labels.map((l, li) => (
                    <th className="col-num" key={`${g.label}-${l}`}>
                      {li === 0 ? (
                        <button
                          type="button"
                          onClick={() => toggleFy(gi)}
                          title={`Collapse ${g.label}`}
                          className="inline-flex items-center gap-1.5 font-[inherit] uppercase tracking-[inherit] text-inherit hover:text-primary"
                        >
                          <span className="flex h-4 w-4 items-center justify-center rounded border border-border bg-surface text-[10px] leading-none">‹</span>
                          {l}
                        </button>
                      ) : (
                        l
                      )}
                    </th>
                  ))
                ) : (
                  <th className="col-num" key={g.label}>
                    <button
                      type="button"
                      onClick={() => toggleFy(gi)}
                      title={`Expand ${g.label} months`}
                      className="inline-flex items-center gap-1.5 font-[inherit] uppercase tracking-[inherit] text-inherit hover:text-primary"
                    >
                      <span className="flex h-4 w-4 items-center justify-center rounded border border-border bg-surface text-[10px] leading-none">›</span>
                      {g.label}
                    </button>
                  </th>
                )
              )}
              <th className="col-num">Total Collection</th>
              <th className="col-num">Balance FY 26-27</th>
              <th className="col-num">Total Outstanding</th>
              <th>Current Status</th>
              <th>Date Referred to Tele</th>
              <th>Last Update Date</th>
              <th>Complete History Log</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {filtered.length === 0 && (
              <tr>
                <td colSpan={24 + (centresOpen ? 3 : 1) + fyColCount} className="text-center text-text-soft">
                  No records match the current filters.
                </td>
              </tr>
            )}
            {paged.map((p, i) => {
              const [exGst, gst] = patientGstSplit(p);
              const totalColl = paymentsTotal(p);
              const totalOut = p.incGst - totalColl;
              const discAmt = pkgDisc(p);
              const inFy2627 = p.signup >= "2026-04-01";
              const balFy2627 = inFy2627 ? totalOut : 0;
              return (
                <tr key={`${p.id}-${p.invoice || i}`}>
                  <td className="sticky-l" style={{ width: 110, minWidth: 110, maxWidth: 110 }}>{fmtDate(p.signup)}</td>
                  <td className="sticky-l2" style={{ width: 150, minWidth: 150, maxWidth: 150 }}>
                    <Link href={`/journey?id=${p.id}`} className="iic-link">
                      {p.id}
                    </Link>
                  </td>
                  <td
                    className="sticky-l3 strong"
                    style={{ width: 200, minWidth: 200, maxWidth: 200, overflow: "hidden", textOverflow: "ellipsis" }}
                    title={p.name}
                  >
                    {p.name}
                  </td>
                  <td>{pkgMonthLabel(p.signup)}</td>
                  <td>{p.signup.slice(0, 4)}</td>
                  <td>{pkgFY(p.signup)}</td>
                  <td>{p.pkg}</td>
                  <td>{p.desc}</td>
                  <td>{p.centre}</td>
                  {centresOpen && <td>{p.centre}</td>}
                  {centresOpen && <td>{p.centre}</td>}
                  <td>Tax Invoice</td>
                  <td>{p.invoice}</td>
                  <td className="col-num">{fmtINR(p.gross)}</td>
                  <td className="col-num">{p.discPct}%</td>
                  <td className="col-num">{fmtINR(discAmt)}</td>
                  <td className="col-num">{fmtINR(p.incGst)}</td>
                  <td className="col-num">{fmtINR(gst)}</td>
                  <td className="col-num">{fmtINR(exGst)}</td>
                  {FY_GROUPS.map((g, gi) =>
                    fyOpen[gi] ? (
                      g.months.map((ym) => {
                        const amt = paymentInMonth(p, ym);
                        return (
                          <td className="col-num" key={ym}>
                            {amt ? fmtINR(amt) : "·"}
                          </td>
                        );
                      })
                    ) : (
                      (() => {
                        const sum = g.months.reduce((t, ym) => t + paymentInMonth(p, ym), 0);
                        return (
                          <td className="col-num" key={g.label}>
                            {sum ? fmtINR(sum) : "·"}
                          </td>
                        );
                      })()
                    )
                  )}
                  <td className="col-num strong">{fmtINR(totalColl)}</td>
                  <td className="col-num">{balFy2627 ? fmtINR(balFy2627) : "·"}</td>
                  <td className="col-num strong" style={{ color: "var(--color-red)" }}>
                    {fmtINR(totalOut)}
                  </td>
                  <td>{p.status}</td>
                  <td>{fmtDate(p.referred)}</td>
                  <td>{fmtDate(p.lastFu)}</td>
                  <td className="max-w-[240px] whitespace-normal">{p.history}</td>
                  <td>
                    <button
                      type="button"
                      onClick={() => openFollowUp(p)}
                      className="rounded-[8px] bg-primary px-2.5 py-1 text-[11px] font-semibold text-white transition-colors hover:bg-primary-dark"
                    >
                      Follow-up
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <div className="flex flex-wrap items-center gap-2.5 border-t border-border-soft px-[18px] py-[12px] text-[11.5px]">
        <span className="text-text-soft">
          {filtered.length === 0
            ? "0 records"
            : `Showing ${pageStart + 1}–${Math.min(pageStart + pageSize, filtered.length)} of ${filtered.length}`}
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

      {followUpTarget && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4" onClick={() => !saving && setFollowUpTarget(null)}>
          <div className="w-full max-w-md rounded-[14px] bg-surface p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-1 text-sm font-semibold text-primary-dark">Follow-up · {followUpTarget.name}</div>
            <div className="mb-4 text-[11.5px] text-text-soft">
              {followUpTarget.id} · Invoice {followUpTarget.invoice || "—"}
            </div>

            <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Current Status</label>
            <select
              value={followUpStatus}
              onChange={(e) => setFollowUpStatus(e.target.value)}
              className="mb-3 w-full rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
            >
              {FOLLOWUP_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>

            <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Remarks</label>
            <textarea
              value={followUpRemarks}
              onChange={(e) => setFollowUpRemarks(e.target.value)}
              rows={3}
              placeholder="Add context — call logs, patient request, reason for status…"
              className="mb-3 w-full resize-none rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
            />

            {followUpTarget.history && (
              <div className="mb-3 max-h-28 overflow-y-auto rounded-[8px] bg-surface-2 p-2.5 text-[11px] leading-relaxed text-text-soft whitespace-pre-line">
                {followUpTarget.history}
              </div>
            )}

            {saveError && <div className="mb-3 rounded-[8px] bg-red-soft px-3 py-2 text-[11.5px] font-semibold text-red">{saveError}</div>}

            <div className="flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={() => setFollowUpTarget(null)}
                disabled={saving}
                className="rounded-[8px] border border-border px-3 py-1.5 text-[12px] font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={submitFollowUp}
                disabled={saving}
                className="rounded-[8px] bg-primary px-3.5 py-1.5 text-[12px] font-semibold text-white transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60"
              >
                {saving ? "Saving…" : "Save Follow-up"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

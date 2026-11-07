"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TODAY, fmtDate } from "@/lib/format";
import type { PrebookPatient, PrebookType } from "@/lib/types";
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
  fcComment?: string;
  lastConn?: string;
  lastComment?: string;
}

type SaveState = "saving" | "saved" | "error";

export default function PrebookList({ type, prebook }: { type: PrebookType; prebook: PrebookPatient[] }) {
  const [range, setRange] = useState("today");
  const [fromDate, setFromDate] = useState("");
  const [toDate, setToDate] = useState("");
  const [doctorFilter, setDoctorFilter] = useState("all");
  const [treatmentFilter, setTreatmentFilter] = useState("all");
  const [qualityFilter, setQualityFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [edits, setEdits] = useState<Record<string, CnbEdit>>({});
  const [saveState, setSaveState] = useState<Partial<Record<string, SaveState>>>({});
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);

  // Filter choices come from this page's own patients (Consulted Not Booked only).
  const typeRows = useMemo(() => prebook.filter((p) => p.type === type), [prebook, type]);
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
    [typeRows, type, search, range, fromDate, toDate, doctorFilter, treatmentFilter, qualityFilter]
  );

  const filtersActive = search.trim() !== "" || range !== "today" || doctorFilter !== "all" || treatmentFilter !== "all" || qualityFilter !== "all";
  function clearFilters() {
    setRange("today");
    setFromDate("");
    setToDate("");
    setDoctorFilter("all");
    setTreatmentFilter("all");
    setQualityFilter("all");
    setSearch("");
  }

  useEffect(() => {
    setPage(1);
  }, [type, search, range, fromDate, toDate, doctorFilter, treatmentFilter, qualityFilter, pageSize]);

  const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageStart = (safePage - 1) * pageSize;
  const paged = list.slice(pageStart, pageStart + pageSize);

  const [readOnly, setReadOnly] = useState(false);
  useEffect(() => {
    setReadOnly(isReadOnlyRole(getClientSession()?.role));
  }, []);

  function setEdit(id: string, field: keyof CnbEdit, value: string) {
    setEdits((s) => ({ ...s, [id]: { ...s[id], [field]: value as never } }));
    setSaveState((s) => ({ ...s, [id]: undefined }));
  }

  async function saveRow(p: PrebookPatient) {
    const e = edits[p.id] || {};
    const quality = e.quality || p.quality || "Cold";
    const fcComment = e.fcComment ?? p.fcComment ?? "";
    const lastConn = e.lastConn ?? p.lastConn ?? "";
    const lastComment = e.lastComment ?? p.lastComment ?? "";

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
              fc_comment: fcComment,
              latest_connected_date: lastConn || null,
              latest_comment: lastComment,
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

  return (
    <section className="screen-enter">
      <PageHead
        title={LABELS[type]}
        sub={`${list.length} patient${list.length !== 1 ? "s" : ""} · ${
          range === "custom" ? (fromDate || toDate ? `${fromDate || "…"} to ${toDate || "…"}` : "custom dates") : RANGE_LABELS[range]
        } · click an IIC ID to open the patient journey`}
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
        <TableWrap>
          {type === "cnb" ? (
            <table className="tbl">
              <thead>
                <tr>
                  <th>SN</th>
                  <th>IIC ID</th>
                  <th>Patient Name</th>
                  <th>Date of Consult</th>
                  <th>Centre</th>
                  <th>Doctor</th>
                  <th>Treatment Advised</th>
                  <th>Quality</th>
                  <th>FC Comment</th>
                  <th>Latest Connected Date</th>
                  <th>Latest Comment</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {list.length === 0 && (
                  <tr>
                    <td colSpan={12} className="text-center text-text-soft">
                      No patients match the current filter.
                    </td>
                  </tr>
                )}
                {paged.map((p, n) => {
                  const e = edits[p.id] || {};
                  const quality = e.quality || p.quality || "Cold";
                  return (
                    <tr key={`${p.id}-${pageStart + n}`}>
                      <td className="sn-col">{pageStart + n + 1}</td>
                      <td>
                        <Link href={`/journey?id=${p.id}`} className="iic-link">
                          {p.id}
                        </Link>
                      </td>
                      <td className="strong">{p.name}</td>
                      <td>{fmtDate(p.date)}</td>
                      <td>{p.centre}</td>
                      <td>{p.doctor || "—"}</td>
                      <td>{p.treatment || "—"}</td>
                      <td>
                        <select value={quality} disabled={readOnly} onChange={(e2) => setEdit(p.id, "quality", e2.target.value)} className="rounded-[6px] border border-border bg-surface px-1.5 py-1 text-[11px]">
                          {QUALITIES.map((q) => (
                            <option key={q} value={q}>
                              {q}
                            </option>
                          ))}
                        </select>
                        <div className={`mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold ${QUALITY_CLS[quality]}`}>{quality}</div>
                      </td>
                      <td>
                        <textarea
                          rows={2}
                          disabled={readOnly}
                          defaultValue={e.fcComment ?? p.fcComment ?? ""}
                          onBlur={(e2) => setEdit(p.id, "fcComment", e2.target.value)}
                          className="w-[180px] rounded-[6px] border border-border bg-surface px-2 py-1 text-[11px]"
                        />
                      </td>
                      <td>
                        <input
                          type="date"
                          disabled={readOnly}
                          defaultValue={e.lastConn ?? p.lastConn ?? ""}
                          onChange={(e2) => setEdit(p.id, "lastConn", e2.target.value)}
                          className="rounded-[6px] border border-border bg-surface px-1.5 py-1 text-[11px]"
                        />
                      </td>
                      <td>
                        <textarea
                          rows={2}
                          disabled={readOnly}
                          defaultValue={e.lastComment ?? p.lastComment ?? ""}
                          onBlur={(e2) => setEdit(p.id, "lastComment", e2.target.value)}
                          className="w-[180px] rounded-[6px] border border-border bg-surface px-2 py-1 text-[11px]"
                        />
                      </td>
                      <td>
                        {readOnly ? (
                          <span className="text-[10.5px] text-text-soft">View only</span>
                        ) : (
                        <button
                          type="button"
                          onClick={() => saveRow(p)}
                          disabled={saveState[p.id] === "saving"}
                          className="rounded-[8px] bg-primary px-2.5 py-1 text-[11px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                          {saveState[p.id] === "saving" ? "Saving…" : "Save"}
                        </button>
                        )}
                        {saveState[p.id] === "saved" && <div className="mt-1 text-[10.5px] font-semibold text-green">Saved ✓</div>}
                        {saveState[p.id] === "error" && <div className="mt-1 text-[10.5px] font-semibold text-red">Save failed</div>}
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
                      <Link href={`/journey?id=${p.id}`} className="iic-link">
                        {p.id}
                      </Link>
                    </td>
                    <td className="strong">{p.name}</td>
                    <td>{fmtDate(p.date)}</td>
                    <td>{p.centre} Centre</td>
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

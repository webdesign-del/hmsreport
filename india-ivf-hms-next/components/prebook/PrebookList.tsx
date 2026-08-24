"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TODAY, fmtDate } from "@/lib/format";
import type { PrebookPatient, PrebookType } from "@/lib/types";
import { Card, PageHead, TableWrap } from "@/components/ui";

const LABELS: Record<PrebookType, string> = { scheduled: "Appointments Scheduled", missed: "Missed Appointments", cnb: "Consulted Not Booked" };
const DATE_LABELS: Record<PrebookType, string> = { scheduled: "Date of Appointment", missed: "Date of Missed Appointment", cnb: "Date of Consult" };
const RANGE_LABELS: Record<string, string> = { today: "today", week: "this week", month: "this month", all: "all time" };

function inRange(iso: string, range: string): boolean {
  if (range === "all") return true;
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
  const [edits, setEdits] = useState<Record<string, CnbEdit>>({});
  const [saveState, setSaveState] = useState<Partial<Record<string, SaveState>>>({});
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);

  const list = useMemo(() => prebook.filter((p) => p.type === type && inRange(p.date, range)), [prebook, type, range]);

  useEffect(() => {
    setPage(1);
  }, [type, range, pageSize]);

  const totalPages = Math.max(1, Math.ceil(list.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageStart = (safePage - 1) * pageSize;
  const paged = list.slice(pageStart, pageStart + pageSize);

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
        sub={`${list.length} patient${list.length !== 1 ? "s" : ""} · ${RANGE_LABELS[range]} · click an IIC ID to open the patient journey`}
        actions={
          <>
            <select value={range} onChange={(e) => setRange(e.target.value)} className="rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px]">
              <option value="today">Today</option>
              <option value="week">This week</option>
              <option value="month">This month</option>
              <option value="all">All time</option>
            </select>
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
                        <select value={quality} onChange={(e2) => setEdit(p.id, "quality", e2.target.value)} className="rounded-[6px] border border-border bg-surface px-1.5 py-1 text-[11px]">
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
                          defaultValue={e.fcComment ?? p.fcComment ?? ""}
                          onBlur={(e2) => setEdit(p.id, "fcComment", e2.target.value)}
                          className="w-[180px] rounded-[6px] border border-border bg-surface px-2 py-1 text-[11px]"
                        />
                      </td>
                      <td>
                        <input
                          type="date"
                          defaultValue={e.lastConn ?? p.lastConn ?? ""}
                          onChange={(e2) => setEdit(p.id, "lastConn", e2.target.value)}
                          className="rounded-[6px] border border-border bg-surface px-1.5 py-1 text-[11px]"
                        />
                      </td>
                      <td>
                        <textarea
                          rows={2}
                          defaultValue={e.lastComment ?? p.lastComment ?? ""}
                          onBlur={(e2) => setEdit(p.id, "lastComment", e2.target.value)}
                          className="w-[180px] rounded-[6px] border border-border bg-surface px-2 py-1 text-[11px]"
                        />
                      </td>
                      <td>
                        <button
                          type="button"
                          onClick={() => saveRow(p)}
                          disabled={saveState[p.id] === "saving"}
                          className="rounded-[8px] bg-primary px-2.5 py-1 text-[11px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        >
                          {saveState[p.id] === "saving" ? "Saving…" : "Save"}
                        </button>
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

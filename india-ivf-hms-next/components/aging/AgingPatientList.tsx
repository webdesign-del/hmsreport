"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TODAY, fmtDate, fmtINR } from "@/lib/format";
import { FY_LABELS, FY_MONTHS } from "@/lib/seed-data";
import { bucketIdx, patientGstSplit, paymentInMonth, paymentsTotal, pkgDisc, pkgFY, pkgMonthLabel } from "@/lib/derive";
import type { AgingPatient } from "@/lib/types";

const BUCKET_LABELS = ["0–30 d", "31–60 d", "61–90 d", "91–180 d", "180+ d"];

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
  const [bucket, setBucket] = useState<number | null>(null);
  const [search, setSearch] = useState("");
  const [range, setRange] = useState("all");
  const [single, setSingle] = useState(TODAY);
  const [from, setFrom] = useState("2026-01-01");
  const [to, setTo] = useState(TODAY);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);

  const bucketCounts = useMemo(() => [0, 1, 2, 3, 4].map((i) => patients.filter((p) => bucketIdx(p.daysOverdue) === i).length), [patients]);

  const filtered = useMemo(() => {
    let list = patients.slice();
    if (bucket !== null) list = list.filter((p) => bucketIdx(p.daysOverdue) === bucket);
    list = list.filter((p) => inDateRange(p.lastFu, range, single, from, to));
    const q = search.trim().toLowerCase();
    if (q) list = list.filter((p) => p.name.toLowerCase().includes(q) || p.id.toLowerCase().includes(q));
    return list;
  }, [patients, bucket, range, single, from, to, search]);

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
            All <span className="ml-1 text-text-soft">{patients.length}</span>
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

      <div className="scroll-thin overflow-x-auto border-t border-border-soft">
        <table className="tbl">
          <thead>
            <tr>
              <th className="sticky-l">Date</th>
              <th>Pkg Month</th>
              <th>Pkg Booking Yr</th>
              <th>FY</th>
              <th>IIC ID</th>
              <th>Patient Name</th>
              <th>Pkg Code</th>
              <th>Pkg Description</th>
              <th>Booking Centre</th>
              <th>Sales Reporting Centre</th>
              <th>Billing Centre</th>
              <th>Document Type</th>
              <th>Invoice No</th>
              <th className="col-num">Gross Revenue Pkg</th>
              <th className="col-num">Discount %</th>
              <th className="col-num">Discount</th>
              <th className="col-num">Booked Pkg Inc GST</th>
              <th className="col-num">GST</th>
              <th className="col-num">Booked Pkg Ex GST</th>
              {FY_LABELS.map((l) => (
                <th className="col-num" key={l}>
                  {l}
                </th>
              ))}
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
                <td colSpan={51} className="text-center text-text-soft">
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
                  <td className="sticky-l">{fmtDate(p.signup)}</td>
                  <td>{pkgMonthLabel(p.signup)}</td>
                  <td>{p.signup.slice(0, 4)}</td>
                  <td>{pkgFY(p.signup)}</td>
                  <td>
                    <Link href={`/journey?id=${p.id}`} className="iic-link">
                      {p.id}
                    </Link>
                  </td>
                  <td className="strong">{p.name}</td>
                  <td>{p.pkg}</td>
                  <td>{p.desc}</td>
                  <td>{p.centre}</td>
                  <td>{p.centre}</td>
                  <td>{p.centre}</td>
                  <td>Tax Invoice</td>
                  <td>{p.invoice}</td>
                  <td className="col-num">{fmtINR(p.gross)}</td>
                  <td className="col-num">{p.discPct}%</td>
                  <td className="col-num">{fmtINR(discAmt)}</td>
                  <td className="col-num">{fmtINR(p.incGst)}</td>
                  <td className="col-num">{fmtINR(gst)}</td>
                  <td className="col-num">{fmtINR(exGst)}</td>
                  {FY_MONTHS.map((ym) => {
                    const amt = paymentInMonth(p, ym);
                    return (
                      <td className="col-num" key={ym}>
                        {amt ? fmtINR(amt) : "·"}
                      </td>
                    );
                  })}
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
                    <button type="button" className="rounded-[8px] bg-primary px-2.5 py-1 text-[11px] font-semibold text-white">
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
    </div>
  );
}

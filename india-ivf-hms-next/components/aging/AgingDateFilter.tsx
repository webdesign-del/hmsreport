"use client";

import { useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { TODAY } from "@/lib/format";

function iso(d: Date): string {
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${m}-${day}`;
}

/** Booking-date presets -> [from, to] (ISO). Weeks run Monday to Sunday. */
function presetRange(range: string): [string, string] | null {
  const T = new Date(TODAY + "T00:00:00");
  if (range === "day") return [TODAY, TODAY];
  if (range === "week") {
    const mon = new Date(T);
    mon.setDate(T.getDate() - ((T.getDay() + 6) % 7));
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    return [iso(mon), iso(sun)];
  }
  if (range === "month") return [iso(new Date(T.getFullYear(), T.getMonth(), 1)), iso(new Date(T.getFullYear(), T.getMonth() + 1, 0))];
  if (range === "year") return [`${T.getFullYear()}-01-01`, `${T.getFullYear()}-12-31`];
  return null;
}

/** Page-level booking-date filter for the Aging Snapshot (buckets, centre table and patient list). */
export default function AgingDateFilter({ range: initialRange, from: initialFrom, to: initialTo }: { range: string; from: string; to: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const [range, setRange] = useState(initialRange);
  const [from, setFrom] = useState(initialFrom || "2026-01-01");
  const [to, setTo] = useState(initialTo || TODAY);

  function go(r: string, f: string, t: string) {
    if (r === "all") {
      router.push(pathname);
      return;
    }
    if (!f || !t || f > t) return;
    router.push(`${pathname}?range=${r}&from=${f}&to=${t}`);
  }

  function onRange(r: string) {
    setRange(r);
    if (r === "custom") return; // wait for the dates + Apply
    const pr = presetRange(r);
    go(r, pr ? pr[0] : "", pr ? pr[1] : "");
  }

  return (
    <div className="flex flex-wrap items-center gap-2 text-[11.5px]">
      <span className="font-semibold text-text-soft">Booking date</span>
      <select value={range} onChange={(e) => onRange(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]">
        <option value="all">All time</option>
        <option value="day">Today</option>
        <option value="week">This week</option>
        <option value="month">This month</option>
        <option value="year">This year</option>
        <option value="custom">Custom range</option>
      </select>
      {range === "custom" && (
        <span className="flex items-center gap-1.5 text-text-soft">
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
          <span>to</span>
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
          <button
            type="button"
            onClick={() => go("custom", from, to)}
            className="rounded-[8px] bg-primary px-3 py-1.5 text-[11.5px] font-semibold text-white hover:opacity-90"
          >
            Apply
          </button>
        </span>
      )}
    </div>
  );
}

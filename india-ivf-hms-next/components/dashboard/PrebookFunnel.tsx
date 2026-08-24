"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { TODAY } from "@/lib/format";
import type { PrebookPatient } from "@/lib/types";
import { Card } from "@/components/ui";

function inRange(iso: string, range: string, from: string, to: string): boolean {
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
  if (range === "custom") {
    if (!from || !to) return true;
    return iso >= from && iso <= to;
  }
  return true;
}

export default function PrebookFunnel({ prebook }: { prebook: PrebookPatient[] }) {
  const [range, setRange] = useState("today");
  const [from, setFrom] = useState(TODAY);
  const [to, setTo] = useState(TODAY);

  const filtered = useMemo(() => prebook.filter((p) => inRange(p.date, range, from, to)), [prebook, range, from, to]);
  const scheduled = filtered.filter((p) => p.type === "scheduled").length;
  const missed = filtered.filter((p) => p.type === "missed").length;
  const cnb = filtered.filter((p) => p.type === "cnb").length;

  return (
    <Card
      title={
        <>
          Prebook Funnel <span className="ml-1 font-normal text-text-soft">· before 10% booking · all centres</span>
        </>
      }
      tools={
        <div className="flex flex-wrap items-center gap-1.5">
          <select
            value={range}
            onChange={(e) => setRange(e.target.value)}
            className="rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]"
          >
            <option value="today">Today</option>
            <option value="week">This week</option>
            <option value="month">This month</option>
            <option value="all">All time</option>
            <option value="custom">Custom range</option>
          </select>
          {range === "custom" && (
            <span className="flex items-center gap-1.5 text-[11.5px] text-text-soft">
              <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
              <span>to</span>
              <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
            </span>
          )}
        </div>
      }
    >
      <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
        <Link href="/prebook/scheduled" className="rounded-[14px] border border-border bg-surface p-4 shadow-card transition-transform hover:-translate-y-0.5">
          <div className="mb-2.5 flex items-center gap-2">
            <div className="flex h-[30px] w-[30px] items-center justify-center rounded-[8px] bg-blue-soft text-sm text-blue">📅</div>
            <div className="text-[11.5px] font-semibold uppercase tracking-[.04em] text-text-soft">Appointment Scheduled</div>
          </div>
          <div className="font-display text-[25px] font-semibold">{scheduled}</div>
          <div className="mt-1 text-[11.5px] text-text-soft">click to view list →</div>
        </Link>
        <Link href="/prebook/missed" className="rounded-[14px] border border-border bg-surface p-4 shadow-card transition-transform hover:-translate-y-0.5">
          <div className="mb-2.5 flex items-center gap-2">
            <div className="flex h-[30px] w-[30px] items-center justify-center rounded-[8px] bg-red-soft text-sm text-red">⚑</div>
            <div className="text-[11.5px] font-semibold uppercase tracking-[.04em] text-text-soft">Missed Appointment</div>
          </div>
          <div className="font-display text-[25px] font-semibold">{missed}</div>
          <div className="mt-1 text-[11.5px] text-text-soft">click to view list →</div>
        </Link>
        <Link href="/prebook/cnb" className="rounded-[14px] border border-border bg-surface p-4 shadow-card transition-transform hover:-translate-y-0.5">
          <div className="mb-2.5 flex items-center gap-2">
            <div className="flex h-[30px] w-[30px] items-center justify-center rounded-[8px] bg-amber-soft text-sm text-amber">◐</div>
            <div className="text-[11.5px] font-semibold uppercase tracking-[.04em] text-text-soft">Consulted Not Booked</div>
          </div>
          <div className="font-display text-[25px] font-semibold">{cnb}</div>
          <div className="mt-1 text-[11.5px] text-text-soft">click to view list →</div>
        </Link>
      </div>
    </Card>
  );
}

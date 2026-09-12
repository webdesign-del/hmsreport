"use client";

import { useEffect, useState } from "react";
import { fmtINR, localISODate, TODAY } from "@/lib/format";
import { getClientScopedCenterId } from "@/lib/clientSession";
import { BarTrack, Card, PageHead, TableWrap } from "@/components/ui";

interface CentreRow {
  center_number: string | number;
  CENTRE: string;
  EXPECTED: number;
  ACTUAL: number;
  COLLECTION_ADHERENCE_PERCENT: number;
  AGING_OUTSTANDING: number;
}

function periodRange(range: string, customFrom: string, customTo: string): { from: string; to: string } | null {
  const today = new Date(TODAY + "T00:00:00");
  const iso = localISODate;

  if (range === "today") return { from: TODAY, to: TODAY };
  if (range === "week") {
    const dow = today.getDay();
    const off = (dow + 6) % 7;
    const mon = new Date(today);
    mon.setDate(today.getDate() - off);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    return { from: iso(mon), to: iso(sun) };
  }
  if (range === "month") {
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    return { from: iso(first), to: iso(last) };
  }
  if (range === "custom") {
    if (!customFrom || !customTo) return null;
    return { from: customFrom, to: customTo };
  }
  return null; // "all"
}

export default function CentreComparisonLive() {
  const [centres, setCentres] = useState<CentreRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [range, setRange] = useState("all");
  const [customFrom, setCustomFrom] = useState(TODAY);
  const [customTo, setCustomTo] = useState(TODAY);

  const fetchCentreStats = async () => {
    try {
      setLoading(true);
      setError("");

      const centerId = getClientScopedCenterId();
      const period = periodRange(range, customFrom, customTo);
      const params = new URLSearchParams();
      if (centerId) params.set("center_id", String(centerId));
      if (period) {
        params.set("from", period.from);
        params.set("to", period.to);
      }
      const qs = params.toString() ? `?${params.toString()}` : "";
      const response = await fetch(`/api/centre-comparison${qs}`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
        },
        cache: "no-store",
      });

      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status}`);
      }

      const data = await response.json();
      setCentres(Array.isArray(data) ? data : []);
    } catch (err: any) {
      console.error("Centre Comparison API Error:", err);
      setError("Django API Connection Failed! Ensure server is running and CORS headers are allowed.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCentreStats();
  }, [range, customFrom, customTo]);

  // CSV Export Handler
  function exportCsv() {
    const headers = ["Centre", "Expected", "Actual", "Collection Adherence %", "Aging Outstanding"];
    const lines = [headers.join(",")];
    
    ranked.forEach((c) => {
      const cells = [c.CENTRE, c.EXPECTED, c.ACTUAL, c.COLLECTION_ADHERENCE_PERCENT, c.AGING_OUTSTANDING];
      lines.push(cells.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(","));
    });
    
    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `centre-comparison-${localISODate()}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  const RANGE_LABELS: Record<string, string> = { all: "all time", today: "today", week: "this week", month: "this month", custom: "custom range" };

  // Calculated Metrics — hide centres with no activity at all (no expected/actual/outstanding)
  const activeCentres = centres.filter((c) => c.EXPECTED !== 0 || c.ACTUAL !== 0 || c.AGING_OUTSTANDING !== 0);
  const ranked = activeCentres.slice().sort((a, b) => b.COLLECTION_ADHERENCE_PERCENT - a.COLLECTION_ADHERENCE_PERCENT);
  const best = ranked[0];
  const worst = ranked[ranked.length - 1];
  const highestAging = activeCentres.length ? activeCentres.slice().sort((a, b) => b.AGING_OUTSTANDING - a.AGING_OUTSTANDING)[0] : null;

  return (
    <section className="screen-enter space-y-6">
      <PageHead
        title="Centre Comparison"
        sub={`Collection adherence ranked across all centres · ${RANGE_LABELS[range]}`}
        actions={
          <>
            <select
              value={range}
              onChange={(e) => setRange(e.target.value)}
              className="rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]"
            >
              <option value="all">All time</option>
              <option value="today">Today</option>
              <option value="week">This week</option>
              <option value="month">This month</option>
              <option value="custom">Custom range</option>
            </select>
            {range === "custom" && (
              <span className="flex items-center gap-1.5 text-[11.5px] text-text-soft">
                <input
                  type="date"
                  value={customFrom}
                  onChange={(e) => setCustomFrom(e.target.value)}
                  className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]"
                />
                <span>to</span>
                <input
                  type="date"
                  value={customTo}
                  onChange={(e) => setCustomTo(e.target.value)}
                  className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]"
                />
              </span>
            )}
            <span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">
              ● Live Data
            </span>
            <button
              type="button"
              onClick={fetchCentreStats}
              className="rounded-[9px] border border-border px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary transition"
            >
              🔄 Sync
            </button>
          </>
        }
      />

      {error && (
        <div className="rounded-[12px] border border-red-200 bg-red-50 p-4 text-center text-xs font-semibold text-red-600">
          {error}
        </div>
      )}

      <Card
        title="Centre-wise Performance"
        tools={
          <button
            type="button"
            onClick={exportCsv}
            disabled={ranked.length === 0}
            className="rounded-[9px] border border-border px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 transition"
          >
            Export
          </button>
        }
        flush
      >
        <TableWrap>
          <table className="tbl">
            <thead>
              <tr>
                <th>Centre</th>
                <th className="col-num">Expected</th>
                <th className="col-num">Actual</th>
                <th>Collection Adherence</th>
                <th className="col-num">Aging Outstanding</th>
              </tr>
            </thead>
            <tbody>
              {ranked.length === 0 ? (
                <tr>
                  <td colSpan={5} className="p-8 text-center text-text-soft">
                    {loading ? "Loading live centre data..." : "No centre data found."}
                  </td>
                </tr>
              ) : (
                ranked.map((c) => (
                  <tr key={c.center_number}>
                    <td className="strong">{c.CENTRE}</td>
                    <td className="col-num">{fmtINR(c.EXPECTED)}</td>
                    <td className="col-num">{fmtINR(c.ACTUAL)}</td>
                    <td className="min-w-[140px]">
                      <BarTrack pct={c.COLLECTION_ADHERENCE_PERCENT} />
                      <div className="mt-[3px] text-[11px] text-text-soft">{c.COLLECTION_ADHERENCE_PERCENT}%</div>
                    </td>
                    <td className="col-num">{fmtINR(c.AGING_OUTSTANDING)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </TableWrap>
      </Card>

      {ranked.length > 0 && best && worst && highestAging && (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
          <Card title="Best Adherence">
            <div className="font-display text-[21px] text-green">{best.CENTRE}</div>
            <div className="mt-1.5 text-[12px] text-text-soft">{best.COLLECTION_ADHERENCE_PERCENT}% collection adherence</div>
          </Card>
          <Card title="Needs Attention">
            <div className="font-display text-[21px] text-red">{worst.CENTRE}</div>
            <div className="mt-1.5 text-[12px] text-text-soft">
              {worst.COLLECTION_ADHERENCE_PERCENT}% adherence — lowest of {activeCentres.length} centres
            </div>
          </Card>
          <Card title="Highest Aging Outstanding">
            <div className="font-display text-[21px] text-amber">{highestAging.CENTRE}</div>
            <div className="mt-1.5 text-[12px] text-text-soft">{fmtINR(highestAging.AGING_OUTSTANDING)} outstanding</div>
          </Card>
        </div>
      )}
    </section>
  );
}
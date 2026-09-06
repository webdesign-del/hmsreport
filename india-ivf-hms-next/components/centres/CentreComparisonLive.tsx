"use client";

import { useEffect, useState } from "react";
import { fmtINR } from "@/lib/format";
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

export default function CentreComparisonLive() {
  const [centres, setCentres] = useState<CentreRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchCentreStats = async () => {
    try {
      setLoading(true);
      setError("");

      // Try multiple host fallbacks to bypass localhost/127.0.0.1 origin blocks
      const centerId = getClientScopedCenterId();
      const qs = centerId ? `?center_id=${centerId}` : "";
      const apiUrls = [
        `http://127.0.0.1:8000/api/get_centre_comparison/${qs}`,
        `http://localhost:8000/api/get_centre_comparison/${qs}`,
      ];

      let response: Response | null = null;
      for (const url of apiUrls) {
        try {
          const res = await fetch(url, {
            method: "GET",
            headers: {
              "Content-Type": "application/json",
            },
            cache: "no-store",
          });
          if (res.ok) {
            response = res;
            break;
          }
        } catch (e) {
          // Continue to next URL fallback if one fails
        }
      }

      if (!response) {
        throw new Error("Unable to connect to Django API on 127.0.0.1 or localhost.");
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
  }, []);

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
    a.download = `centre-comparison-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  // Calculated Metrics
  const ranked = centres.slice().sort((a, b) => b.COLLECTION_ADHERENCE_PERCENT - a.COLLECTION_ADHERENCE_PERCENT);
  const best = ranked[0];
  const worst = ranked[ranked.length - 1];
  const highestAging = centres.length ? centres.slice().sort((a, b) => b.AGING_OUTSTANDING - a.AGING_OUTSTANDING)[0] : null;

  return (
    <section className="screen-enter space-y-6">
      <PageHead
        title="Centre Comparison"
        sub="Collection adherence ranked across all centres"
        actions={
          <>
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
              {worst.COLLECTION_ADHERENCE_PERCENT}% adherence — lowest of {centres.length} centres
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
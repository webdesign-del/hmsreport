"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { fmtINR } from "@/lib/format";
import { triggerScore } from "@/lib/derive";
import type { TriggerItem } from "@/lib/types";
import { Callout, Card, Kpi, KpiRow, PageHead, Pill, TableWrap } from "@/components/ui";

const TYPE_TONE: Record<string, "green" | "amber" | "red" | "blue" | "grey"> = {
  "Missed collection": "red",
  "OPU miss": "red",
  "Gate override": "amber",
  "Reconciliation pending": "blue",
  "Stim 12-day cap": "amber",
};

export default function TriggerPileup({ triggers }: { triggers: TriggerItem[] }) {
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);

  const missed = triggers.filter((t) => t.type === "Missed collection").length;
  const overrides = triggers.filter((t) => t.type === "Gate override").length;
  const recon = triggers.filter((t) => t.type === "Reconciliation pending").length;
  const valueAtRisk = triggers.reduce((s, t) => s + (t.value || 0), 0);
  const ranked = useMemo(() => triggers.slice().sort((a, b) => triggerScore(b) - triggerScore(a)), [triggers]);

  useEffect(() => {
    setPage(1);
  }, [pageSize, triggers]);

  const totalPages = Math.max(1, Math.ceil(ranked.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageStart = (safePage - 1) * pageSize;
  const paged = ranked.slice(pageStart, pageStart + pageSize);

  return (
    <section className="screen-enter">
      <PageHead
        title="Red Trigger Pile-up"
        sub="Missed collections, overrides & reconciliation pending — ranked by aging × value"
        actions={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>}
      />

      <KpiRow>
        <Kpi icon="⚑" color="red" label="Missed Collections" value={missed} sub="no Slack — piled here" />
        <Kpi icon="⟲" color="amber" label="Gate Overrides" value={overrides} sub="timestamped exceptions" />
        <Kpi icon="⇄" color="blue" label="Reconciliation Pending" value={recon} sub="plan / package changes" />
        <Kpi icon="₹" color="rose" label="Value at Risk" value={fmtINR(valueAtRisk).slice(1)} currency sub="across all triggers" />
      </KpiRow>

      <div className="mb-5">
        <Callout tone="danger" icon="⚑">
          Ranking factors days overdue, amount, centre and procedure stage — <b>OPU misses are treated as most critical</b>.
        </Callout>
      </div>

      <Card title="Pile-up — Ranked" tools={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>} flush>
        <TableWrap>
          <table className="tbl">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Trigger Type</th>
                <th>Patient · IIC ID</th>
                <th>Centre</th>
                <th>Procedure Stage</th>
                <th className="col-num">Value</th>
                <th className="col-num">Days Overdue</th>
                <th className="col-num">Aging × Value</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {paged.map((t, n) => {
                const i = pageStart + n;
                return (
                  <tr key={`${t.id}-${t.type}-${i}`}>
                    <td>
                      <div
                        className="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white"
                        style={{ background: i < 2 ? "var(--color-red)" : "var(--color-text-soft)" }}
                      >
                        {i + 1}
                      </div>
                    </td>
                    <td>
                      <Pill tone={TYPE_TONE[t.type] || "grey"}>{t.type}</Pill>
                    </td>
                    <td>
                      <div className="pt-name">{t.name}</div>
                      <div className="pt-id">{t.id}</div>
                    </td>
                    <td>{t.centre}</td>
                    <td className="strong">{t.stage}</td>
                    <td className="col-num">{t.value ? fmtINR(t.value) : "—"}</td>
                    <td className="col-num">{t.days} d</td>
                    <td className="col-num strong">{triggerScore(t)}</td>
                    <td>
                      <Link
                        href={`/journey?id=${t.id}`}
                        className="inline-block rounded-[8px] bg-primary px-3 py-1.5 text-[11px] font-semibold text-white transition-colors hover:bg-primary-dark"
                      >
                        Review
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </TableWrap>

        <div className="flex flex-wrap items-center gap-2.5 border-t border-border-soft px-[18px] py-[12px] text-[11.5px]">
          <span className="text-text-soft">
            {ranked.length === 0 ? "0 records" : `Showing ${pageStart + 1}–${Math.min(pageStart + pageSize, ranked.length)} of ${ranked.length}`}
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

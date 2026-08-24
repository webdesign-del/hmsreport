"use client";

import { useState } from "react";
import { fmtINR } from "@/lib/format";
import { Kpi, KpiRow } from "@/components/ui";
import type { DashboardResponse } from "@/lib/api";

const TABS = ["Today", "This Week", "This Month"] as const;

export default function KpiSubtabs({ kpi }: { kpi: DashboardResponse["kpi"] }) {
  const [tab, setTab] = useState(0);
  return (
    <div>
      <div className="mb-3 inline-flex rounded-[10px] border border-border bg-surface-2 p-1">
        {TABS.map((t, i) => (
          <button
            key={t}
            type="button"
            onClick={() => setTab(i)}
            className={`rounded-[8px] px-3.5 py-1.5 text-[12px] font-semibold transition-colors ${
              tab === i ? "bg-surface text-primary-dark shadow-card" : "text-text-mid hover:text-primary"
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 0 && (
        <KpiRow>
          <Kpi icon="₹" color="rose" label="Expected · Today" value={fmtINR(kpi.today.exp).slice(1)} currency sub="all centres" />
          <Kpi icon="✓" color="green" label="Actual · Today" value={fmtINR(kpi.today.act).slice(1)} currency sub="receipts logged" />
          <Kpi icon="◑" color="blue" label="Adherence" value={`${Math.round((kpi.today.act / kpi.today.exp) * 100)}%`} sub="actual ÷ expected" />
          <Kpi icon="⚑" color="red" label="Red Triggers" value={kpi.today.redTriggers} sub="pile-up awaiting action" />
          <Kpi icon="⚖" color="gold" label="Approvals Pending" value={kpi.today.approvalsPending} sub="director-only items" />
        </KpiRow>
      )}
      {tab === 1 && (
        <KpiRow>
          <Kpi icon="₹" color="rose" label="Expected · Week" value={fmtINR(kpi.week.exp).slice(1)} currency sub="all centres" />
          <Kpi icon="✓" color="green" label="Actual · Week" value={fmtINR(kpi.week.act).slice(1)} currency sub="receipts logged" />
          <Kpi icon="◑" color="blue" label="Adherence" value={`${Math.round((kpi.week.act / kpi.week.exp) * 100)}%`} sub="actual ÷ expected" />
          <Kpi icon="↗" color="amber" label="Projection" value={fmtINR(kpi.week.projection).slice(1)} currency sub="week-end forecast" />
          <Kpi icon="⚑" color="red" label="Red Triggers" value={kpi.week.redTriggers} sub="pile-up awaiting action" />
        </KpiRow>
      )}
      {tab === 2 && (
        <KpiRow>
          <Kpi icon="₹" color="rose" label="Expected · Month" value={fmtINR(kpi.month.exp).slice(1)} currency sub="all centres" />
          <Kpi icon="✓" color="green" label="Actual · Month" value={fmtINR(kpi.month.act).slice(1)} currency sub="receipts logged" />
          <Kpi icon="◑" color="blue" label="Adherence" value={`${Math.round((kpi.month.act / kpi.month.exp) * 100)}%`} sub="actual ÷ expected" />
          <Kpi icon="↗" color="amber" label="Projection" value={fmtINR(kpi.month.projection).slice(1)} currency sub="month-end forecast" />
          <Kpi icon="⚑" color="red" label="Red Triggers" value={kpi.month.redTriggers} sub="pile-up awaiting action" />
        </KpiRow>
      )}
    </div>
  );
}

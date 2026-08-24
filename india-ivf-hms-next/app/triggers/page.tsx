import { getTriggers } from "@/lib/api";
import { fmtINR } from "@/lib/format";
import { triggerScore } from "@/lib/derive";
import { Callout, Card, Kpi, KpiRow, PageHead, Pill, TableWrap } from "@/components/ui";

const TYPE_TONE: Record<string, "green" | "amber" | "red" | "blue" | "grey"> = {
  "Missed collection": "red",
  "OPU miss": "red",
  "Gate override": "amber",
  "Reconciliation pending": "blue",
  "Stim 12-day cap": "amber",
};

export default async function TriggersPage() {
  const triggers = await getTriggers();
  const missed = triggers.filter((t) => t.type === "Missed collection").length;
  const overrides = triggers.filter((t) => t.type === "Gate override").length;
  const recon = triggers.filter((t) => t.type === "Reconciliation pending").length;
  const valueAtRisk = triggers.reduce((s, t) => s + (t.value || 0), 0);
  const ranked = triggers.slice().sort((a, b) => triggerScore(b) - triggerScore(a));

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
              {ranked.map((t, i) => (
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
                    <button type="button" className="rounded-[8px] bg-primary px-3 py-1.5 text-[11px] font-semibold text-white">
                      Review
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </TableWrap>
      </Card>
    </section>
  );
}

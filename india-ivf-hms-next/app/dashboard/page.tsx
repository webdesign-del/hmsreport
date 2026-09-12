import { getCentreComparison, getDashboardSummary, getOverrides, getPrebook } from "@/lib/api";
import { getScopedCenterId } from "@/lib/auth";
import { fmtINR, TODAY } from "@/lib/format";
import { Card, Hbar, PageHead, Pill, Sparkline, TableWrap } from "@/components/ui";
import KpiSubtabs from "@/components/dashboard/KpiSubtabs";
import PrebookFunnel from "@/components/dashboard/PrebookFunnel";
import StageWidget from "@/components/StageWidget";

export default async function DashboardPage() {
  const centerId = await getScopedCenterId();
  const [summary, prebook, centreRows, overrides] = await Promise.all([
    getDashboardSummary(centerId),
    getPrebook(centerId),
    getCentreComparison(centerId),
    getOverrides(),
  ]);

  const centres = centreRows
    .filter((r) => r.EXPECTED !== 0 || r.ACTUAL !== 0 || r.AGING_OUTSTANDING !== 0)
    .map((r) => ({ centre: r.CENTRE, exp: r.EXPECTED, act: r.ACTUAL, adh: r.COLLECTION_ADHERENCE_PERCENT }))
    .sort((a, b) => b.adh - a.adh);
  const { weekTrend } = summary;

  const bestDayIdx = weekTrend.indexOf(Math.max(...weekTrend));
  // weekTrend[6] is today, weekTrend[0] is 6 days ago — derive each day's label from the real date.
  const dayNames = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(TODAY + "T00:00:00");
    d.setDate(d.getDate() - (6 - i));
    return d.toLocaleDateString("en-US", { weekday: "short" });
  });
  const avg = Math.round(weekTrend.reduce((s, v) => s + v, 0) / weekTrend.length);

  return (
    <section className="screen-enter max-w-[1600px] mx-auto">
      <PageHead title="Management Dashboard" sub="Company-wide collection visibility & exception oversight" />

      <div className="mb-5 flex flex-wrap items-center gap-[18px] rounded-[14px] border border-border p-[15px_18px]" style={{ background: "linear-gradient(110deg,var(--color-primary-soft),#FBF1F5 60%,var(--color-surface))" }}>
        <div className="min-w-[240px] flex-1">
          <h4 className="mb-[3px] text-[13px] text-primary-dark">Responsibility — Company-wide visibility &amp; exception oversight</h4>
          <p className="text-[12.5px] text-text-mid">
            Review the consolidated dashboard daily · approve director-only items (12-day stim extension, large refunds, special discount escalations) · act on accumulated red triggers.
          </p>
        </div>
        <div className="flex flex-col gap-1.5">
          <span className="text-[10px] font-bold uppercase tracking-[.06em] text-text-soft">I can see</span>
          <div className="flex flex-wrap gap-1.5">
            <span className="rounded-[7px] border border-border bg-surface px-[9px] py-1 text-[11px] font-semibold text-text-mid">All centres · all patients · all data</span>
            <span className="rounded-[7px] border border-border bg-surface px-[9px] py-1 text-[11px] font-semibold text-text-mid">Consolidated achievement, aging, projection</span>
            <span className="rounded-[7px] border border-border bg-surface px-[9px] py-1 text-[11px] font-semibold text-text-mid">Override &amp; exception logs</span>
            <span className="rounded-[7px] border border-border bg-surface px-[9px] py-1 text-[11px] font-semibold text-text-mid">Red trigger pile-up</span>
          </div>
        </div>
      </div>

      <KpiSubtabs kpi={summary.kpi} />

      <PrebookFunnel prebook={prebook} />

      <Card
        title={
          <>
            Booked Patients by Clinical Stage <span className="ml-1 font-normal text-text-soft">· all centres — click a stage to open those patients</span>
          </>
        }
        tools={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>}
      >
        <StageWidget stageLabels={summary.stageLabels} stageCounts={summary.stageCounts} stageByCentre={summary.stageByCentre} />
      </Card>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <Card title="Collection Adherence by Centre" tools={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>}>
          {centres.map((c) => (
            <Hbar
              key={c.centre}
              label={c.centre}
              pct={c.adh}
              valText={fmtINR(c.act)}
              color={c.adh >= 90 ? "var(--color-green)" : c.adh >= 75 ? "var(--color-gold)" : "var(--color-primary)"}
            />
          ))}
        </Card>
        <Card title={<>Collection Trend <span className="ml-1 font-normal text-text-soft">· last 7 days</span></>}>
          <Sparkline values={weekTrend} />
          <div className="my-3.5 h-px bg-border-soft" />
          <div className="flex flex-col gap-2 text-[12.5px]">
            <div className="flex justify-between">
              <span className="text-text-soft">Today&apos;s collection</span>
              <span>
                <b>{fmtINR(weekTrend[weekTrend.length - 1])}</b> across {centres.length} centres
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-text-soft">7-day average</span>
              <span>{fmtINR(avg)} / day</span>
            </div>
            <div className="flex justify-between">
              <span className="text-text-soft">Best day</span>
              <span>
                {dayNames[bestDayIdx]} — {fmtINR(weekTrend[bestDayIdx])}
              </span>
            </div>
          </div>
        </Card>
      </div>

      <Card
        title="Expected vs Actual Collection — by Centre"
        tools={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>}
        flush
      >
        <TableWrap>
          <table className="tbl">
            <thead>
              <tr>
                <th>Centre</th>
                <th className="col-num">Expected</th>
                <th className="col-num">Actual</th>
                <th className="col-num">Variance</th>
                <th>Collection Adherence</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {centres.map((c) => {
                const vr = c.act - c.exp;
                return (
                  <tr key={c.centre}>
                    <td className="strong">{c.centre}</td>
                    <td className="col-num">{fmtINR(c.exp)}</td>
                    <td className="col-num">{fmtINR(c.act)}</td>
                    <td className="col-num" style={{ color: vr < 0 ? "var(--color-red)" : "var(--color-green)" }}>
                      {vr < 0 ? "" : "+"}
                      {fmtINR(vr)}
                    </td>
                    <td>
                      <div className="h-[7px] w-full max-w-[140px] overflow-hidden rounded-full bg-surface-2">
                        <div className="h-full rounded-full bg-primary" style={{ width: `${c.adh}%` }} />
                      </div>
                      <div className="mt-[3px] text-[11px] text-text-soft">{c.adh}%</div>
                    </td>
                    <td>
                      {c.adh >= 90 ? <Pill tone="green">Healthy</Pill> : c.adh >= 75 ? <Pill tone="amber">Watch</Pill> : <Pill tone="red">Action</Pill>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </TableWrap>
      </Card>

      <Card title="Override & Exception Log" tools={<span className="text-[11.5px] text-text-soft">Every override creates a timestamped record</span>} flush>
        <TableWrap>
          <table className="tbl">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Exception / Override</th>
                <th>Patient · IIC ID</th>
                <th>Centre</th>
                <th>Approved By</th>
                <th>Reason</th>
              </tr>
            </thead>
            <tbody>
              {overrides.map((o, i) => (
                <tr key={i}>
                  <td>{o.ts}</td>
                  <td className="strong">{o.type}</td>
                  <td>
                    <div className="pt-name">{o.name}</div>
                    <div className="pt-id">{o.id}</div>
                  </td>
                  <td>{o.centre}</td>
                  <td>{o.by}</td>
                  <td>{o.reason}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </TableWrap>
      </Card>
    </section>
  );
}
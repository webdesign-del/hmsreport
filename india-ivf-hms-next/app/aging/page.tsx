import { getAgingSnapshot } from "@/lib/api";
import { getScopedCenterId } from "@/lib/auth";
import { fmtINR } from "@/lib/format";
import { Card, PageHead, TableWrap } from "@/components/ui";
import AgingPatientList from "@/components/aging/AgingPatientList";

const BUCKET_LABELS = ["0 – 30 days", "31 – 60 days", "61 – 90 days", "91 – 180 days", "180+ days"];
const BUCKET_TONE = ["border-t-green", "border-t-blue", "border-t-amber", "border-t-primary", "border-t-red"];

export default async function AgingPage() {
  const centerId = await getScopedCenterId();
  const { companyBuckets, bucketCounts, byCentre, patients: agingPatients } = await getAgingSnapshot(centerId);
  const activeByCentre = byCentre.filter((c) => c.total !== 0);

  return (
    <section className="screen-enter">
      <PageHead
        title="Aging Snapshot"
        sub="Outstanding collections across all centres"
        actions={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>}
      />

      <Card title="Company Aging">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {companyBuckets.map((v, i) => (
            <div key={i} className={`rounded-[12px] border border-border border-t-[3px] bg-surface-2 p-3.5 ${BUCKET_TONE[i]}`}>
              <div className="text-[11px] font-semibold text-text-soft">{BUCKET_LABELS[i]}</div>
              <div className="mt-1.5 font-display text-lg font-semibold text-text">{fmtINR(v)}</div>
              <div className="mt-0.5 text-[11px] text-text-soft">{bucketCounts[i]} patients</div>
            </div>
          ))}
        </div>
      </Card>

      <Card title="Aging by Centre" tools={<span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">Live data</span>} flush>
        <TableWrap>
          <table className="tbl">
            <thead>
              <tr>
                <th>Centre</th>
                <th className="col-num">0–30</th>
                <th className="col-num">31–60</th>
                <th className="col-num">61–90</th>
                <th className="col-num">91–180</th>
                <th className="col-num">180+</th>
                <th className="col-num">Total</th>
              </tr>
            </thead>
            <tbody>
              {activeByCentre.map((c) => (
                <tr key={c.centre}>
                  <td className="strong">{c.centre}</td>
                  {c.buckets.map((v, i) => (
                    <td className="col-num" key={i}>
                      {fmtINR(v)}
                    </td>
                  ))}
                  <td className="col-num strong">{fmtINR(c.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </TableWrap>
      </Card>

      <AgingPatientList patients={agingPatients} />
    </section>
  );
}

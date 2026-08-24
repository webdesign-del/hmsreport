import Link from "next/link";
import { notFound } from "next/navigation";
import { getPatients } from "@/lib/api";
import { fmtDate, fmtINR } from "@/lib/format";
import { STEP_SHORT } from "@/lib/seed-data";
import { Card, PageHead, TableWrap } from "@/components/ui";

export default async function StagePatientsPage({ params }: { params: Promise<{ idx: string }> }) {
  const { idx: idxParam } = await params;
  const idx = Number(idxParam);
  if (!Number.isInteger(idx) || idx < 0 || idx >= STEP_SHORT.length) notFound();

  const patients = await getPatients();
  const counts = STEP_SHORT.map((_, i) => patients.filter((p) => p.stage === i).length);
  const rows = patients.filter((p) => p.stage === idx);

  return (
    <section className="screen-enter">
      <PageHead
        title={`Patients at ${STEP_SHORT[idx]} Stage`}
        sub={`${rows.length} patient${rows.length !== 1 ? "s" : ""} currently at this stage · click an IIC ID to open the patient's journey`}
        actions={
          <Link href="/dashboard" className="rounded-[9px] border border-border px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary">
            ← Back to dashboard
          </Link>
        }
      />

      <Card>
        <div className="mb-1 text-[11px] font-semibold uppercase tracking-[.04em] text-text-soft">Filter by clinical stage</div>
        <div className="flex flex-wrap gap-1.5">
          {STEP_SHORT.map((s, i) => (
            <Link
              key={s}
              href={`/stage/${i}`}
              className={`rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition-colors ${i === idx ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"}`}
            >
              {i + 1} · {s} <span className="ml-1 text-text-soft">{counts[i]}</span>
            </Link>
          ))}
        </div>
      </Card>

      <Card flush>
        <TableWrap>
          <table className="tbl">
            <thead>
              <tr>
                <th>SN</th>
                <th>Patient Name</th>
                <th>IIC ID</th>
                <th>Date of Booking</th>
                <th>Package</th>
                <th className="col-num">Net Package Amount</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center text-text-soft">
                    No patients are currently at this stage.
                  </td>
                </tr>
              )}
              {rows.map((p, n) => (
                <tr key={p.id}>
                  <td className="sn-col">{n + 1}</td>
                  <td className="strong">{p.name}</td>
                  <td>
                    <Link href={`/journey?id=${p.id}`} className="iic-link">
                      {p.id}
                    </Link>
                  </td>
                  <td>{fmtDate(p.signup)}</td>
                  <td>{p.pkg}</td>
                  <td className="col-num strong">{fmtINR(p.net)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </TableWrap>
      </Card>
    </section>
  );
}

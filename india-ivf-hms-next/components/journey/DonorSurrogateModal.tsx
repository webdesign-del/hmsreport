"use client";

import { fmtDate, fmtINR, initialsOf } from "@/lib/format";
import { paidPct } from "@/lib/derive";
import type { Donor, Surrogate } from "@/lib/types";

function Kv({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-[10.5px] font-semibold uppercase tracking-[.03em] text-text-soft">{label}</span>
      <b className="text-[12.5px] text-text">{value}</b>
    </div>
  );
}
function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="mb-4">
      <div className="mb-2 text-[11.5px] font-semibold uppercase tracking-[.04em] text-primary-dark">{title}</div>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">{children}</div>
    </div>
  );
}

export default function DonorSurrogateModal({ subject, onClose }: { subject: { kind: "donor"; data: Donor } | { kind: "surrogate"; data: Surrogate }; onClose: () => void }) {
  const isDonor = subject.kind === "donor";
  const d = subject.data;
  const pct = paidPct(d);
  const phoneDigits = d.phone.replace(/[^0-9]/g, "");

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
        className="max-h-[85vh] w-full max-w-[640px] overflow-y-auto rounded-[16px] bg-surface p-6 shadow-2xl"
      >
        <button type="button" onClick={onClose} aria-label="Close" className="float-right text-xl text-text-soft hover:text-text">
          ×
        </button>
        <div className="mb-5 flex items-start gap-3.5">
          <div className={`flex h-14 w-14 flex-none items-center justify-center rounded-full text-base font-semibold text-white ${isDonor ? "bg-primary" : "bg-blue"}`}>
            {initialsOf(d.name)}
          </div>
          <div className="flex-1">
            <div className="text-[10.5px] font-bold uppercase tracking-[.05em] text-text-soft">
              {isDonor ? "Egg Donor" : "Gestational Surrogate"} · {d.id}
            </div>
            <h2 className="font-display text-lg font-semibold text-text">{d.name}</h2>
            <div className="text-[12px] text-text-soft">
              {d.age} yrs · {d.blood} · {d.height} · {d.weight} · {isDonor ? `AMH ${(d as Donor).amh}` : `BMI ${(d as Surrogate).bmi}`}
            </div>
          </div>
          <span className={`rounded-full px-2.5 py-1 text-[10.5px] font-semibold ${/Active/i.test(d.status) ? "bg-green-soft text-green" : "bg-amber-soft text-amber"}`}>{d.status}</span>
        </div>

        {isDonor ? (
          <>
            <Section title="Donor profile">
              <Kv label="Phenotype" value={(d as Donor).phenotype} />
              <Kv label="Education" value={(d as Donor).education} />
              <Kv label="Occupation" value={(d as Donor).occupation} />
              <Kv label="Marital status" value={(d as Donor).marital} />
              <Kv label="Prior cycles" value={`${(d as Donor).priorCycles} · ${(d as Donor).priorYield}`} />
              <Kv label="Anonymity" value={d.anonymity} />
            </Section>
            <Section title="Medical screening">
              <Kv label="Overall" value={d.screening} />
              <Kv label="HIV" value={d.hiv} />
              <Kv label="HBsAg" value={d.hbsag} />
              <Kv label="HCV" value={d.hcv} />
              <Kv label="VDRL" value={d.vdrl} />
              <Kv label="Thalassemia" value={(d as Donor).thalassemia} />
              <Kv label="Karyotype" value={(d as Donor).karyotype} />
            </Section>
            <Section title="Legal · consent · contract">
              <Kv label="Consent signed" value={fmtDate(d.consent)} />
              <Kv label="Agency / source" value={d.agency} />
              <Kv label="Contract value" value={fmtINR(d.contractValue)} />
              <Kv label="Paid to donor" value={`${fmtINR(d.paid)} (${pct}%)`} />
              <Kv label="Centre" value={d.centre} />
              <Kv label="Coordinator" value={d.coordinator} />
            </Section>
          </>
        ) : (
          <>
            <Section title="Surrogate profile">
              <Kv label="Parity" value={(d as Surrogate).parity} />
              <Kv label="Obstetric history" value={(d as Surrogate).obstetricHist} />
              <Kv label="Endometrium" value={(d as Surrogate).endometrium} />
              <Kv label="HSG" value={(d as Surrogate).hsg} />
              <Kv label="Husband" value={(d as Surrogate).husband} />
              <Kv label="Anonymity" value={d.anonymity} />
            </Section>
            <Section title="Medical screening">
              <Kv label="Overall" value={d.screening} />
              <Kv label="HIV" value={d.hiv} />
              <Kv label="HBsAg" value={d.hbsag} />
              <Kv label="HCV" value={d.hcv} />
              <Kv label="VDRL" value={d.vdrl} />
              <Kv label="GTT" value={(d as Surrogate).gtt} />
              <Kv label="Thyroid" value={(d as Surrogate).thyroid} />
            </Section>
            <Section title="Legal · ART Act 2021 compliance">
              <Kv label="Legal status" value={(d as Surrogate).legalStatus} />
              <Kv label="Consent signed" value={fmtDate(d.consent)} />
              <Kv label="Eligibility Certificate" value={(d as Surrogate).ec} />
              <Kv label="Insurance cover" value={(d as Surrogate).insurance} />
              <Kv label="Agency / source" value={d.agency} />
            </Section>
            <Section title="Contract · financial">
              <Kv label="Contract value" value={fmtINR(d.contractValue)} />
              <Kv label="Paid to surrogate" value={`${fmtINR(d.paid)} (${pct}%)`} />
              <Kv label="Centre" value={d.centre} />
              <Kv label="Coordinator" value={d.coordinator} />
            </Section>
          </>
        )}

        <div className="mb-4 rounded-[10px] bg-surface-2 p-3">
          <span className="text-[10.5px] font-semibold uppercase tracking-[.03em] text-text-soft">Clinical notes</span>
          <p className="mt-1 text-[12.5px] text-text-mid">{d.notes}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <a href={`tel:+91${phoneDigits}`} className="rounded-[9px] bg-primary px-3.5 py-2 text-[12px] font-semibold text-white">
            📞 Call coordinator
          </a>
          <button type="button" onClick={() => alert(isDonor ? "Donor screening PDF (placeholder)" : "Surrogacy contract PDF (placeholder)")} className="rounded-[9px] border border-border px-3.5 py-2 text-[12px] font-semibold text-text-mid">
            📄 {isDonor ? "Screening file" : "Contract"}
          </button>
          <button type="button" onClick={() => alert(isDonor ? "Donor consent PDF (placeholder)" : "Eligibility Certificate (placeholder)")} className="rounded-[9px] border border-border px-3.5 py-2 text-[12px] font-semibold text-text-mid">
            📑 {isDonor ? "Consent form" : "EC / ART Act docs"}
          </button>
        </div>
      </div>
    </div>
  );
}

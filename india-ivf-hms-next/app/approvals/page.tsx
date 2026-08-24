import { getApprovals } from "@/lib/api";
import { fmtDate } from "@/lib/format";
import { Callout, Card, PageHead } from "@/components/ui";

function FieldLabel({ children, required }: { children: React.ReactNode; required?: boolean }) {
  return (
    <div className="mb-1.5 text-[12px] font-semibold text-text-mid">
      {children} {required && <span className="text-red">✱</span>}
    </div>
  );
}
const inputCls = "w-full rounded-[9px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary";

export default async function ApprovalsPage() {
  const approvals = await getApprovals();

  return (
    <section className="screen-enter">
      <PageHead title="Approval Queue" sub="Director-only items requiring authority above the centre" />

      <Card
        title="Pending Approvals"
        tools={
          <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-soft px-2.5 py-1 text-[11px] font-semibold text-amber">
            <span className="h-1.5 w-1.5 rounded-full bg-current" />
            Awaiting director
          </span>
        }
        flush
      >
        <div className="divide-y divide-border-soft">
          {approvals.map((a, i) => (
            <div key={i} className="flex flex-wrap items-center gap-3 p-4">
              <div className="flex h-9 w-9 flex-none items-center justify-center rounded-[9px] bg-amber-soft text-base text-amber">⚖</div>
              <div className="min-w-[240px] flex-1">
                <div className="text-[13px] font-semibold text-text">
                  {a.type} · {a.name} ({a.id})
                </div>
                <div className="text-[12px] text-text-soft">
                  {a.centre} — {a.detail} · requested {fmtDate(a.req)}
                </div>
              </div>
              <div className="flex gap-1.5">
                <button type="button" className="rounded-[8px] border border-border px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary">
                  Reject
                </button>
                <button type="button" className="rounded-[8px] bg-primary px-3 py-1.5 text-[11.5px] font-semibold text-white">
                  Approve
                </button>
              </div>
            </div>
          ))}
        </div>
      </Card>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <Card title="12-day Stim Extension">
          <Callout tone="warn" icon="⚕">
            At the day-12 stim cap. Approval opens the continuation form.
          </Callout>
          <div className="h-3" />
          <FieldLabel required>Approval</FieldLabel>
          <select className={inputCls} defaultValue="">
            <option value="">Select …</option>
            <option>Approve</option>
            <option>Reject</option>
          </select>
          <div className="h-2.5" />
          <FieldLabel required>Reason</FieldLabel>
          <textarea className={inputCls} rows={2} placeholder="Reason for decision …" />
          <div className="mt-2.5 flex flex-wrap gap-1.5 text-[10.5px] font-semibold text-text-soft">
            <span className="rounded-full bg-surface-2 px-2 py-0.5">Source · Director</span>
            <span className="rounded-full bg-surface-2 px-2 py-0.5">Opens continuation form</span>
          </div>
          <div className="my-3.5 h-px bg-border-soft" />
          <button type="button" className="w-full rounded-[9px] bg-primary py-2.5 text-[13px] font-semibold text-white">
            Submit decision
          </button>
        </Card>

        <Card title="OPU / ET Payment Override">
          <Callout tone="danger" icon="🔒">
            Used when 100% is not cleared. Unlocks the OPU / ET gate.
          </Callout>
          <div className="h-3" />
          <FieldLabel required>Override</FieldLabel>
          <select className={inputCls} defaultValue="">
            <option value="">Select …</option>
            <option>Approve override</option>
            <option>Reject</option>
          </select>
          <div className="h-2.5" />
          <FieldLabel required>Reason</FieldLabel>
          <textarea className={inputCls} rows={2} placeholder="Reason for override …" />
          <div className="mt-2.5 flex flex-wrap gap-1.5 text-[10.5px] font-semibold text-text-soft">
            <span className="rounded-full bg-surface-2 px-2 py-0.5">Source · Director</span>
            <span className="rounded-full bg-surface-2 px-2 py-0.5">Unlocks OPU / ET gate</span>
          </div>
          <div className="my-3.5 h-px bg-border-soft" />
          <button type="button" className="w-full rounded-[9px] bg-primary py-2.5 text-[13px] font-semibold text-white">
            Submit decision
          </button>
        </Card>

        <Card title="Large Refund Approval">
          <Callout icon="↩">Refund above the threshold. Approval releases refund execution.</Callout>
          <div className="h-3" />
          <FieldLabel required>Approval</FieldLabel>
          <select className={inputCls} defaultValue="">
            <option value="">Select …</option>
            <option>Approve</option>
            <option>Reject</option>
          </select>
          <div className="h-2.5" />
          <FieldLabel required>Reason</FieldLabel>
          <textarea className={inputCls} rows={2} placeholder="Reason for decision …" />
          <div className="mt-2.5 flex flex-wrap gap-1.5 text-[10.5px] font-semibold text-text-soft">
            <span className="rounded-full bg-surface-2 px-2 py-0.5">Source · Director</span>
            <span className="rounded-full bg-surface-2 px-2 py-0.5">Releases refund execution</span>
          </div>
          <div className="my-3.5 h-px bg-border-soft" />
          <button type="button" className="w-full rounded-[9px] bg-primary py-2.5 text-[13px] font-semibold text-white">
            Submit decision
          </button>
        </Card>
      </div>

      <Card title={<>Special Discount Escalation <span className="ml-1 font-normal text-text-soft">· founder approval</span></>}>
        <Callout tone="warn" icon="⚖">
          A discount above the counsellor limit routes to the founder approval queue.
        </Callout>
        <div className="h-3.5" />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <FieldLabel>Discount requested</FieldLabel>
            <input className={inputCls} type="text" placeholder="₹ 0.00 / %" />
          </div>
          <div>
            <FieldLabel>Counsellor limit</FieldLabel>
            <input className={inputCls} type="text" defaultValue="₹40,000" readOnly />
          </div>
          <div>
            <FieldLabel required>Founder approval</FieldLabel>
            <select className={inputCls} defaultValue="">
              <option value="">Select …</option>
              <option>Approve</option>
              <option>Reject</option>
            </select>
          </div>
          <div>
            <FieldLabel required>Reason</FieldLabel>
            <input className={inputCls} type="text" placeholder="Reason for decision" />
          </div>
        </div>
        <div className="my-3.5 h-px bg-border-soft" />
        <button type="button" className="rounded-[9px] bg-gold px-4 py-2.5 text-[13px] font-semibold text-white">
          Submit founder decision
        </button>
      </Card>
    </section>
  );
}

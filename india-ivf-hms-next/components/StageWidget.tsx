import Link from "next/link";

interface StageWidgetProps {
  stageLabels: string[];
  stageCounts: number[];
  stageByCentre: { centre: string; counts: number[]; total: number }[];
}

export default function StageWidget({ stageLabels, stageCounts, stageByCentre }: StageWidgetProps) {
  const mx = Math.max(1, ...stageCounts);

  return (
    <div>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
        {stageLabels.map((s, i) => (
          <Link
            key={s}
            href={`/stage/${i}`}
            className="flex flex-col gap-1.5 rounded-[12px] border border-border bg-surface-2 p-3 transition-colors hover:border-primary"
            title="View all patients at this stage"
          >
            <div className="text-[11px] font-semibold text-text-soft">{i + 1}</div>
            <div className="min-h-[28px] text-[12px] font-semibold text-text">{s}</div>
            <div className="font-display text-lg font-semibold text-primary-dark">{stageCounts[i]}</div>
            <div className="h-1.5 overflow-hidden rounded-full bg-border-soft">
              <div className="h-full rounded-full bg-primary" style={{ width: `${Math.round((stageCounts[i] / mx) * 100)}%` }} />
            </div>
            <div className="text-[10.5px] text-text-soft">{stageCounts[i] === 1 ? "patient" : "patients"}</div>
          </Link>
        ))}
      </div>

      <div className="mt-[18px]">
        <div className="mb-2.5 text-[11.5px] font-semibold uppercase tracking-[.04em] text-text-soft">Centre &times; stage breakdown</div>
        <div className="scroll-thin overflow-x-auto">
          <table className="tbl">
            <thead>
              <tr>
                <th>Centre</th>
                {stageLabels.map((s) => (
                  <th key={s} className="col-num">
                    {s}
                  </th>
                ))}
                <th className="col-num">Total</th>
              </tr>
            </thead>
            <tbody>
              {stageByCentre.map((c) => (
                <tr key={c.centre}>
                  <td className="strong">{c.centre}</td>
                  {c.counts.map((n, i) => (
                    <td className="col-num" key={i}>
                      {n || "·"}
                    </td>
                  ))}
                  <td className="col-num strong">{c.total}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

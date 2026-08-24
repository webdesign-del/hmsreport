import type { ReactNode } from "react";

export function PageHead({ title, sub, actions }: { title: string; sub?: string; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-wrap items-start gap-4">
      <div>
        <div className="font-display text-[21px] text-primary-dark">{title}</div>
        {sub && <div className="mt-[3px] text-[13px] text-text-soft">{sub}</div>}
      </div>
      {actions && <div className="ml-auto flex items-center gap-[9px]">{actions}</div>}
    </div>
  );
}

export function Card({ title, tools, flush, children, className = "" }: { title?: ReactNode; tools?: ReactNode; flush?: boolean; children: ReactNode; className?: string }) {
  return (
    <div className={`mb-5 rounded-[14px] border border-border bg-surface shadow-card ${className}`}>
      {title && (
        <div className="flex items-center gap-2.5 border-b border-border-soft px-[18px] py-[15px]">
          <div className="text-sm font-semibold text-primary-dark">{title}</div>
          {tools && <div className="ml-auto flex items-center gap-2">{tools}</div>}
        </div>
      )}
      <div className={flush ? "" : "p-[18px]"}>{children}</div>
    </div>
  );
}

export function KpiRow({ children }: { children: ReactNode }) {
  return <div className="mb-5 grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-3.5">{children}</div>;
}

const KPI_ICON_CLS: Record<string, string> = {
  rose: "bg-primary-soft text-primary",
  gold: "bg-gold-soft text-gold",
  blue: "bg-blue-soft text-blue",
  green: "bg-green-soft text-green",
  red: "bg-red-soft text-red",
  amber: "bg-amber-soft text-amber",
};

export function Kpi({
  icon,
  color,
  label,
  value,
  currency,
  sub,
  onClick,
}: {
  icon: string;
  color: keyof typeof KPI_ICON_CLS;
  label: string;
  value: ReactNode;
  currency?: boolean;
  sub?: string;
  onClick?: () => void;
}) {
  const Comp = onClick ? "button" : "div";
  return (
    <Comp
      onClick={onClick}
      className={`rounded-[14px] border border-border bg-surface p-[16px_17px] text-left shadow-card ${onClick ? "cursor-pointer transition-transform hover:-translate-y-0.5" : ""}`}
    >
      <div className="mb-2.5 flex items-center gap-2">
        <div className={`flex h-[30px] w-[30px] flex-none items-center justify-center rounded-[8px] text-sm ${KPI_ICON_CLS[color]}`}>{icon}</div>
        <div className="text-[11.5px] font-semibold uppercase tracking-[.04em] text-text-soft">{label}</div>
      </div>
      <div className="font-display text-[25px] font-semibold text-text">
        {currency && <span className="mr-0.5 text-[15px] font-medium text-text-soft">₹</span>}
        {value}
      </div>
      {sub && <div className="mt-1 text-[11.5px] text-text-soft">{sub}</div>}
    </Comp>
  );
}

const PILL_CLS: Record<string, string> = {
  green: "bg-green-soft text-green",
  amber: "bg-amber-soft text-amber",
  red: "bg-red-soft text-red",
  blue: "bg-blue-soft text-blue",
  grey: "bg-surface-2 text-text-soft",
};

export function Pill({ tone, children }: { tone: keyof typeof PILL_CLS; children: ReactNode }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${PILL_CLS[tone]}`}>
      <span className="h-[6px] w-[6px] rounded-full bg-current" />
      {children}
    </span>
  );
}

export function Chip({ children }: { children: ReactNode }) {
  return <span className="rounded-[7px] border border-border bg-surface px-[9px] py-1 text-[11px] font-semibold text-text-mid">{children}</span>;
}

export function Callout({ tone = "default", icon, children }: { tone?: "default" | "warn" | "danger"; icon: string; children: ReactNode }) {
  const cls = tone === "danger" ? "bg-red-soft text-[#7a2e2a]" : tone === "warn" ? "bg-amber-soft text-[#7a5a1e]" : "bg-blue-soft text-[#2c4f6b]";
  return (
    <div className={`flex items-start gap-2.5 rounded-[12px] p-3 text-[12.5px] leading-relaxed ${cls}`}>
      <span className="mt-px">{icon}</span>
      <div>{children}</div>
    </div>
  );
}

export function BarTrack({ pct, gradient }: { pct: number; gradient?: boolean }) {
  return (
    <div className="h-[7px] w-full overflow-hidden rounded-full bg-surface-2">
      <div
        className="h-full rounded-full"
        style={{
          width: `${Math.max(2, Math.min(100, pct))}%`,
          background: gradient ? "linear-gradient(90deg,#6db193,var(--color-green))" : "var(--color-primary)",
        }}
      />
    </div>
  );
}

export function Hbar({ label, pct, valText, color = "var(--color-primary)" }: { label: string; pct: number; valText: string; color?: string }) {
  const w = Math.max(4, Math.min(100, pct));
  return (
    <div className="mb-2.5 flex items-center gap-2.5 text-[12px] last:mb-0">
      <div className="w-[110px] flex-none truncate text-text-mid">{label}</div>
      <div className="h-[18px] flex-1 overflow-hidden rounded-full bg-surface-2">
        <div className="flex h-full items-center justify-end rounded-full px-1.5 text-[10px] font-semibold text-white" style={{ width: `${w}%`, background: color }}>
          {pct}%
        </div>
      </div>
      <div className="w-[86px] flex-none text-right font-medium text-text-mid">{valText}</div>
    </div>
  );
}

export function Sparkline({ values }: { values: number[] }) {
  const mx = Math.max(...values);
  return (
    <div className="flex h-[70px] items-end gap-[6px]">
      {values.map((v, i) => (
        <span
          key={i}
          className={`flex-1 rounded-t-[3px] ${i === values.length - 1 ? "bg-primary" : "bg-primary-soft"}`}
          style={{ height: `${Math.round((v / mx) * 100)}%` }}
        />
      ))}
    </div>
  );
}

export function GateBadge({ gate }: { gate: "open" | "pay" | "data" }) {
  const m = {
    open: { icon: "✓", label: "Open", cls: "bg-green-soft text-green" },
    pay: { icon: "🔒", label: "Payment", cls: "bg-red-soft text-red" },
    data: { icon: "🔒", label: "Data", cls: "bg-amber-soft text-amber" },
  } as const;
  const x = m[gate];
  return <span className={`inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[10.5px] font-semibold ${x.cls}`}>{x.icon} {x.label}</span>;
}

export function FlagPill({ flag }: { flag: "green" | "amber" | "red" }) {
  const m = { green: { tone: "green" as const, label: "On track" }, amber: { tone: "amber" as const, label: "Within window" }, red: { tone: "red" as const, label: "Overdue" } };
  const x = m[flag];
  return <Pill tone={x.tone}>{x.label}</Pill>;
}

export function EmptyNote({ children }: { children: ReactNode }) {
  return <div className="m-3.5 text-center text-[12.5px] text-text-soft">{children}</div>;
}

export function TableWrap({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <div className={`scroll-thin overflow-x-auto ${className}`}>{children}</div>;
}

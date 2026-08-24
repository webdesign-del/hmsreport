"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { isNavGroup, MGMT_NAV } from "./nav";

function LinkRow({ href, label, ic, active, sub }: { href: string; label: string; ic: string; active: boolean; sub?: boolean }) {
  return (
    <Link
      href={href}
      className={[
        "flex items-center gap-[11px] rounded-[10px] transition-colors",
        sub ? "px-[10px] py-2 text-[12.5px]" : "px-[11px] py-2.5 text-[13px]",
        active ? "bg-primary-soft text-primary-dark font-semibold" : "text-text-mid font-medium hover:bg-surface-2 hover:text-primary",
      ].join(" ")}
    >
      <span
        className={[
          "flex flex-none items-center justify-center rounded-[7px] bg-surface-2 text-[13px]",
          sub ? "h-5 w-5 text-[11px]" : "h-6 w-6",
          active ? "bg-surface text-primary" : "",
        ].join(" ")}
      >
        {ic}
      </span>
      {label}
    </Link>
  );
}

export default function Sidebar() {
  const pathname = usePathname();
  const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>({});

  return (
    <nav className="flex w-[236px] flex-none flex-col gap-1.5 border-r border-border bg-surface p-[18px_14px]">
      <div className="px-2.5 pb-2.5 pt-1 font-display text-[11px] font-semibold uppercase tracking-[.07em] text-text-soft">
        Management
      </div>
      {MGMT_NAV.map((n) => {
        if (isNavGroup(n)) {
          const collapsed = collapsedGroups[n.group];
          const anyActive = n.children.some((c) => pathname.startsWith(c.href));
          return (
            <div key={n.group} className="flex flex-col">
              <button
                type="button"
                onClick={() => setCollapsedGroups((s) => ({ ...s, [n.group]: !s[n.group] }))}
                className={[
                  "flex select-none items-center gap-[11px] rounded-[10px] px-[11px] py-2.5 text-left text-[13px] font-semibold transition-colors hover:bg-surface-2 hover:text-primary",
                  anyActive ? "text-primary-dark" : "text-text-mid",
                ].join(" ")}
              >
                <span className="flex h-6 w-6 flex-none items-center justify-center rounded-[7px] bg-surface-2 text-[13px]">{n.ic}</span>
                {n.label}
                <span className={`ml-auto text-[11px] text-text-soft transition-transform ${collapsed ? "-rotate-90" : ""}`}>▾</span>
              </button>
              {!collapsed && (
                <div className="ml-3.5 mt-1 mb-1 flex flex-col gap-1 border-l-[1.5px] border-border-soft pl-2.5">
                  {n.children.map((c) => (
                    <LinkRow key={c.href} href={c.href} label={c.label} ic={c.ic} active={pathname.startsWith(c.href)} sub />
                  ))}
                </div>
              )}
            </div>
          );
        }
        return <LinkRow key={n.href} href={n.href} label={n.label} ic={n.ic} active={pathname === n.href} />;
      })}
      <div className="mt-auto rounded-[11px] bg-blue-soft p-[13px_11px] text-[11.5px] leading-relaxed text-[#3d6383]">
        <b className="mb-[3px] block text-[11px] uppercase tracking-[.05em] text-blue">Visibility scope</b>
        Company-wide — every centre, every patient, every exception.
      </div>
    </nav>
  );
}

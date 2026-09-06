"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { isNavGroup, MGMT_NAV } from "./nav";

function LinkRow({ href, label, ic, active, sub, collapsed }: { href: string; label: string; ic: string; active: boolean; sub?: boolean; collapsed?: boolean }) {
  return (
    <Link
      href={href}
      title={collapsed ? label : undefined}
      className={[
        "flex items-center gap-[11px] rounded-[10px] transition-colors",
        collapsed ? "justify-center px-0 py-2.5" : sub ? "px-[10px] py-2 text-[12.5px]" : "px-[11px] py-2.5 text-[13px]",
        active ? "bg-primary-soft text-primary-dark font-semibold" : "text-text-mid font-medium hover:bg-surface-2 hover:text-primary",
      ].join(" ")}
    >
      <span
        className={[
          "flex flex-none items-center justify-center rounded-[7px] bg-surface-2 text-[13px]",
          sub && !collapsed ? "h-5 w-5 text-[11px]" : "h-6 w-6",
          active ? "bg-surface text-primary" : "",
        ].join(" ")}
      >
        {ic}
      </span>
      {!collapsed && label}
    </Link>
  );
}

export default function Sidebar() {
  const pathname = usePathname();
  const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>({});
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    try {
      const stored = localStorage.getItem("sidebar_collapsed");
      if (stored === "1") setCollapsed(true);
    } catch {
      // ignore
    }
  }, []);

  function toggleCollapsed() {
    setCollapsed((c) => {
      const next = !c;
      try {
        localStorage.setItem("sidebar_collapsed", next ? "1" : "0");
      } catch {
        // ignore
      }
      return next;
    });
  }

  return (
    <nav
      className={[
        "relative flex flex-none flex-col gap-1.5 border-r border-border bg-surface p-[18px_14px] transition-[width] duration-200",
        collapsed ? "w-[68px] items-center px-[10px]" : "w-[236px]",
      ].join(" ")}
    >
      <button
        type="button"
        onClick={toggleCollapsed}
        title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        className="absolute -right-3 top-5 flex h-6 w-6 items-center justify-center rounded-full border border-border bg-surface text-[11px] text-text-mid shadow-sm transition-colors hover:border-primary hover:text-primary"
      >
        {collapsed ? "›" : "‹"}
      </button>

      {!collapsed && (
        <div className="px-2.5 pb-2.5 pt-1 font-display text-[11px] font-semibold uppercase tracking-[.07em] text-text-soft">
          Management
        </div>
      )}

      {MGMT_NAV.map((n) => {
        if (isNavGroup(n)) {
          const groupCollapsed = collapsed || collapsedGroups[n.group];
          const anyActive = n.children.some((c) => pathname.startsWith(c.href));
          return (
            <div key={n.group} className={collapsed ? "flex w-full flex-col items-center" : "flex flex-col"}>
              <button
                type="button"
                onClick={() => !collapsed && setCollapsedGroups((s) => ({ ...s, [n.group]: !s[n.group] }))}
                title={collapsed ? n.label : undefined}
                className={[
                  "flex select-none items-center gap-[11px] rounded-[10px] text-left text-[13px] font-semibold transition-colors hover:bg-surface-2 hover:text-primary",
                  collapsed ? "justify-center px-0 py-2.5" : "px-[11px] py-2.5",
                  anyActive ? "text-primary-dark" : "text-text-mid",
                ].join(" ")}
              >
                <span className="flex h-6 w-6 flex-none items-center justify-center rounded-[7px] bg-surface-2 text-[13px]">{n.ic}</span>
                {!collapsed && (
                  <>
                    {n.label}
                    <span className={`ml-auto text-[11px] text-text-soft transition-transform ${groupCollapsed ? "-rotate-90" : ""}`}>▾</span>
                  </>
                )}
              </button>
              {!groupCollapsed && (
                <div className="ml-3.5 mt-1 mb-1 flex flex-col gap-1 border-l-[1.5px] border-border-soft pl-2.5">
                  {n.children.map((c) => (
                    <LinkRow key={c.href} href={c.href} label={c.label} ic={c.ic} active={pathname.startsWith(c.href)} sub />
                  ))}
                </div>
              )}
            </div>
          );
        }
        return (
          <div key={n.href} className={collapsed ? "w-full" : undefined}>
            <LinkRow href={n.href} label={n.label} ic={n.ic} active={pathname === n.href} collapsed={collapsed} />
          </div>
        );
      })}

      {!collapsed && (
        <div className="mt-auto rounded-[11px] bg-blue-soft p-[13px_11px] text-[11.5px] leading-relaxed text-[#3d6383]">
          <b className="mb-[3px] block text-[11px] uppercase tracking-[.05em] text-blue">Visibility scope</b>
          Company-wide — every centre, every patient, every exception.
        </div>
      )}
    </nav>
  );
}

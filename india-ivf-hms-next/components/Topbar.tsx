"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { getCentreOverride, setCentreOverride } from "@/lib/clientSession";

interface CentreOption {
  id: number;
  name: string;
}

const ROLE_LABEL: Record<string, string> = {
  doctor: "Doctor",
  embryologist: "Embryologist",
  centre_head: "Centre Head",
  fc: "Financial Counsellor",
  accounts: "Accounts Team",
  management: "Management",
};

interface UserSession {
  username: string;
  name: string;
  role: string;
  centerId?: number | null;
  centerName?: string | null;
}

// Accounts Team is deliberately NOT scoped — its own login description is
// "Cross-centre collections & refund execution".
const SCOPED_ROLES = new Set(["doctor", "embryologist", "centre_head", "fc"]);

function initials(name: string): string {
  const parts = name.trim().split(/\s+/);
  return ((parts[0]?.[0] || "") + (parts[1]?.[0] || "")).toUpperCase() || "U";
}

export default function Topbar() {
  const router = useRouter();
  const [session, setSession] = useState<UserSession | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [centreOptions, setCentreOptions] = useState<CentreOption[]>([]);
  const [selectedCentre, setSelectedCentre] = useState<number | "">("");

  useEffect(() => {
    try {
      const raw = localStorage.getItem("user_session");
      if (raw) setSession(JSON.parse(raw));
    } catch {
      // ignore malformed session
    }
    setSelectedCentre(getCentreOverride() ?? "");
  }, []);

  useEffect(() => {
    if (!session || SCOPED_ROLES.has(session.role)) return;
    fetch("/api/centre-comparison", { cache: "no-store" })
      .then((res) => res.json())
      .then((rows: { center_number: number; CENTRE: string; EXPECTED: number; ACTUAL: number; AGING_OUTSTANDING: number }[]) => {
        const active = rows
          .filter((r) => r.EXPECTED !== 0 || r.ACTUAL !== 0 || r.AGING_OUTSTANDING !== 0)
          .map((r) => ({ id: r.center_number, name: r.CENTRE }))
          .sort((a, b) => a.name.localeCompare(b.name));
        setCentreOptions(active);
      })
      .catch(() => setCentreOptions([]));
  }, [session]);

  function handleCentreChange(value: string) {
    const centerId = value ? Number(value) : null;
    setSelectedCentre(centerId ?? "");
    setCentreOverride(centerId);
    window.location.reload();
  }

  useEffect(() => {
    const onChange = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener("fullscreenchange", onChange);
    return () => document.removeEventListener("fullscreenchange", onChange);
  }, []);

  function toggleFullscreen() {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else {
      document.documentElement.requestFullscreen();
    }
  }

  function handleLogout() {
    localStorage.removeItem("user_session");
    document.cookie = "user_session=; path=/; max-age=0";
    setCentreOverride(null);
    router.push("/login");
  }

  const displayName = session?.name || session?.username || "Guest";
  const roleLabel = session ? ROLE_LABEL[session.role] || session.role : "Not signed in";
  // center_id 0 is the "IndiaIVF" head-office placeholder (unassigned) — treat as unscoped,
  // matching the data-fetch behaviour in lib/auth.ts / lib/clientSession.ts.
  const isScoped = !!session && SCOPED_ROLES.has(session.role) && !!session.centerId;

  return (
    <header className="sticky top-0 z-50 flex h-[62px] items-center gap-[18px] border-b border-border bg-surface px-[22px]">
      <Link href="/dashboard" className="flex items-center gap-3.5">
        <Image src="/india-ivf-logo.png" alt="India IVF" width={140} height={38} className="h-[38px] w-auto" priority />
        <div className="h-[30px] w-px flex-none bg-border" />
        <div className="text-[11px] font-medium text-text-soft">Patient Journey &amp; Collections</div>
      </Link>
      <div className="h-[30px] w-px bg-border" />
      <div className="font-display text-[13px] font-semibold text-primary-dark">Management Workspace</div>
      <div className="ml-auto flex items-center gap-3">
        <div
          className="flex items-center gap-[7px] rounded-[9px] border border-border bg-surface-2 px-3 py-[7px] text-xs font-semibold text-text-mid"
          title={isScoped ? "Your access is scoped to this centre" : undefined}
        >
          <span>📍</span>
          {isScoped ? (
            <span className="text-primary-dark">{session?.centerName || "Your Centre"}</span>
          ) : (
            <select
              value={selectedCentre}
              onChange={(e) => handleCentreChange(e.target.value)}
              className="cursor-pointer border-none bg-transparent text-xs font-semibold text-primary-dark outline-none"
            >
              <option value="">All Centres</option>
              {centreOptions.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          )}
        </div>
        <button
          type="button"
          onClick={() => router.push("/login")}
          className="rounded-[9px] border border-border bg-surface px-3 py-[7px] text-xs font-semibold text-text-mid transition-colors hover:border-primary hover:text-primary"
        >
          ⇄ Switch role
        </button>
        <button
          type="button"
          onClick={toggleFullscreen}
          title={isFullscreen ? "Exit full screen" : "Enter full screen"}
          className="flex h-[30px] w-[30px] items-center justify-center rounded-[9px] border border-border bg-surface text-sm text-text-mid transition-colors hover:border-primary hover:text-primary"
        >
          {isFullscreen ? "⤢" : "⛶"}
        </button>
        <div className="relative">
          <button
            type="button"
            onClick={() => setMenuOpen((o) => !o)}
            className="flex items-center gap-2.5 rounded-[9px] px-1.5 py-1 hover:bg-surface-2"
          >
            <div className="flex h-[34px] w-[34px] flex-none items-center justify-center rounded-full bg-gradient-to-br from-gold to-[#b9863c] text-[13px] font-semibold text-white">
              {initials(displayName)}
            </div>
            <div className="text-left leading-[1.15]">
              <div className="text-[12.5px] font-semibold">{displayName}</div>
              <div className="text-[11px] text-text-soft">{roleLabel}</div>
            </div>
          </button>
          {menuOpen && (
            <div className="absolute right-0 top-[calc(100%+8px)] w-44 overflow-hidden rounded-[10px] border border-border bg-surface shadow-lg">
              <button
                type="button"
                onClick={handleLogout}
                className="flex w-full items-center gap-2 px-3.5 py-2.5 text-left text-[12.5px] font-semibold text-red transition-colors hover:bg-red-soft"
              >
                ⏻ Log out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

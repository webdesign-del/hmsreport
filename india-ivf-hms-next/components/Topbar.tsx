import Image from "next/image";
import Link from "next/link";

const CENTRES = ["All Centres", "Vasant Vihar", "Rohini", "Noida", "Gurgaon", "Ghaziabad", "Srinagar"];

export default function Topbar() {
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
        <div className="flex items-center gap-[7px] rounded-[9px] border border-border bg-surface-2 px-3 py-[7px] text-xs font-semibold text-text-mid">
          <span>📍</span>
          <select
            defaultValue="All Centres"
            className="cursor-pointer border-none bg-transparent text-xs font-semibold text-primary-dark outline-none"
          >
            {CENTRES.map((c) => (
              <option key={c}>{c}</option>
            ))}
          </select>
        </div>
        <button
          type="button"
          className="rounded-[9px] border border-border bg-surface px-3 py-[7px] text-xs font-semibold text-text-mid transition-colors hover:border-primary hover:text-primary"
        >
          ⇄ Switch role
        </button>
        <div className="flex items-center gap-2.5">
          <div className="flex h-[34px] w-[34px] flex-none items-center justify-center rounded-full bg-gradient-to-br from-gold to-[#b9863c] text-[13px] font-semibold text-white">
            DS
          </div>
          <div className="leading-[1.15]">
            <div className="text-[12.5px] font-semibold">Dr. Somendra</div>
            <div className="text-[11px] text-text-soft">Director · All Centres</div>
          </div>
        </div>
      </div>
    </header>
  );
}

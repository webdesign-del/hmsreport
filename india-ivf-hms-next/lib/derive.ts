import { addDays, fmtDate } from "./format";
import {
  AGING,
  CENTRE_STATS,
  DONORS,
  FY_LABELS,
  FY_MONTHS,
  J_STAGE_RANGE,
  OLD_TO_NEW_STAGE,
  PATIENTS,
  STAGE_WT,
  STAGES_FULL,
  SURROGATES,
} from "./seed-data";
import type { AgingPatient, Donor, JourneySubject, Patient, PrebookPatient, Surrogate, TriggerItem } from "./types";

export function isPatient(p: JourneySubject): p is Patient {
  return !("type" in p) || (p as PrebookPatient).type === undefined;
}

export function isPrebookPatient(p: JourneySubject): p is PrebookPatient {
  const t = (p as PrebookPatient).type;
  return t === "scheduled" || t === "missed" || t === "cnb";
}

/** STAGES_FULL index for the journey matrix (final composed / v6-shifted behaviour) */
export function patientNewStage(p: JourneySubject): number {
  if (isPrebookPatient(p)) {
    if (p.type === "scheduled" || p.type === "missed") return 0;
    if (p.type === "cnb") return 2;
  }
  const patient = p as Patient;
  if (typeof patient.stage === "number") {
    return OLD_TO_NEW_STAGE[patient.stage] ?? 3;
  }
  return 3;
}

export function flagPill(f: "green" | "amber" | "red"): { cls: string; label: string } {
  const m = {
    green: { cls: "bg-green-soft text-green", label: "On track" },
    amber: { cls: "bg-amber-soft text-amber", label: "Within window" },
    red: { cls: "bg-red-soft text-red", label: "Overdue" },
  } as const;
  return m[f];
}

export function gateBadge(g: "open" | "pay" | "data"): { icon: string; label: string; cls: string } {
  const m = {
    open: { icon: "✓", label: "Open", cls: "bg-green-soft text-green" },
    pay: { icon: "🔒", label: "Payment", cls: "bg-red-soft text-red" },
    data: { icon: "🔒", label: "Data", cls: "bg-amber-soft text-amber" },
  } as const;
  return m[g];
}

/* ---- patient KYC (couple names + ages) — deterministic per IIC ID ---- */
const HUSBAND_FIRST_NAMES = ["Rohit", "Vikram", "Anand", "Naveen", "Sanjay", "Manish", "Rakesh", "Vivek", "Aditya", "Suresh", "Ravi", "Pankaj", "Kunal", "Mohit", "Rahul", "Arjun", "Karan", "Nikhil", "Saurabh", "Tarun", "Deepak", "Amit", "Sandeep", "Pradeep"];
const HUSBAND_OVERRIDES: Record<string, { first: string }> = {
  "IIC-2602-070": { first: "Imran" },
  "IIC-2604-260": { first: "Rakesh" },
  "IIC-2605-220": { first: "Aman" },
  "IIC-2605-205": { first: "Vinay" },
};

function seedOf(id: string): number {
  let seed = 0;
  for (let i = 0; i < id.length; i++) seed += id.charCodeAt(i);
  return seed % 10000;
}

export function patientKYC(p: JourneySubject): { wifeName: string; wifeAge: number; husbandName: string; husbandAge: number } {
  const id = p.id || "";
  const seed = seedOf(id);
  const wifeName = p.name || "";
  const parts = wifeName.split(" ");
  const lastName = parts[parts.length - 1] || "";
  const ov = HUSBAND_OVERRIDES[id];
  const husbandFirst = ov ? ov.first : HUSBAND_FIRST_NAMES[seed % HUSBAND_FIRST_NAMES.length];
  const husbandName = husbandFirst + (lastName ? " " + lastName : "");
  const wifeAge = 28 + (seed % 14);
  const husbandAge = wifeAge + 2 + ((seed >> 4) % 5);
  return { wifeName, wifeAge, husbandName, husbandAge };
}

export function patientPhone(p: JourneySubject): string {
  const id = p.id || "";
  let s = 0;
  for (let i = 0; i < id.length; i++) s += id.charCodeAt(i);
  const prefix = ["98", "97", "99", "91", "87", "88", "89", "94", "96"][s % 9];
  const rest = String((s * 123457 + 987654321) % 100000000).padStart(8, "0");
  return prefix + rest;
}

export function pCollected(p: Patient): number {
  return Math.round(p.net * p.paid / 100);
}

/* ---- red trigger ranking ---- */
export function triggerScore(t: TriggerItem): number {
  const base = t.value || 60000;
  return Math.round((t.days * base * (STAGE_WT[t.stage] || 1.5)) / 1000);
}

/* ---- aging buckets ---- */
export function bucketIdx(d: number): number {
  if (d <= 30) return 0;
  if (d <= 60) return 1;
  if (d <= 90) return 2;
  if (d <= 180) return 3;
  return 4;
}
export function paymentInMonth(p: AgingPatient, ym: string): number {
  const f = p.payments.find((x) => x.m === ym);
  return f ? f.a : 0;
}
export function paymentsTotal(p: AgingPatient): number {
  return p.payments.reduce((s, x) => s + x.a, 0);
}
export function patientGstSplit(p: AgingPatient): [number, number] {
  const ex = Math.round(p.incGst / 1.18);
  return [ex, p.incGst - ex];
}
export function pkgMonthLabel(sig: string): string {
  const i = FY_MONTHS.indexOf(sig.slice(0, 7));
  return i >= 0 ? FY_LABELS[i] : sig.slice(0, 7);
}
export function pkgFY(sig: string): string {
  const y = +sig.slice(0, 4);
  const m = +sig.slice(5, 7);
  const s = m >= 4 ? y : y - 1;
  return "FY " + s + "-" + String(s + 1).slice(2);
}
export function pkgDisc(p: AgingPatient): number {
  return Math.round(p.gross * p.discPct / 100);
}

/* ---- deterministic donor / surrogate assignment per patient ---- */
function hashIdx(s: string, n: number): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 9973;
  return h % n;
}
export function patientDonor(p: JourneySubject): Donor | null {
  if (!p || !p.id) return null;
  const patient = p as Patient;
  if (patient.donorId) return DONORS.find((d) => d.id === patient.donorId) || DONORS[hashIdx(p.id, DONORS.length)];
  return DONORS[hashIdx(p.id, DONORS.length)];
}
export function patientSurrogate(p: JourneySubject): Surrogate | null {
  if (!p || !p.id) return null;
  const patient = p as Patient;
  if (patient.surrogateId) return SURROGATES.find((s) => s.id === patient.surrogateId) || SURROGATES[hashIdx(p.id, SURROGATES.length)];
  return SURROGATES[hashIdx(p.id, SURROGATES.length)];
}
export function patientDonorMode(p: JourneySubject): "Self-cycle" | "Donor" {
  const patient = p as Patient;
  if (patient.donorMode) return patient.donorMode;
  if (patient.pkg && /donor/i.test(patient.pkg)) return "Donor";
  return "Self-cycle";
}
export function patientSurrogateMode(p: JourneySubject): "Self" | "Surrogate" {
  const patient = p as Patient;
  if (patient.surrogateMode) return patient.surrogateMode;
  if (patient.pkg && /surrogate|surrogacy/i.test(patient.pkg)) return "Surrogate";
  return "Self";
}
export function paidPct(d: Donor | Surrogate): number {
  if (!d.contractValue) return 0;
  return Math.round(((d.paid || 0) / d.contractValue) * 100);
}

/* ---- booked patient list: category + payment tier ---- */
const BPL_CATMAP: Record<string, string> = {
  "IIC-2603-118": "IVF with Bed", "IIC-2603-126": "IVF without Bed", "IIC-2604-203": "IVF with Bed",
  "IIC-2604-211": "Non IVF with Bed", "IIC-2605-088": "OPD", "IIC-2602-051": "IVF with Bed",
  "IIC-2601-019": "IVF with Bed", "IIC-2603-140": "IVF with Bed", "IIC-2604-225": "Non IVF without Bed",
  "IIC-2605-101": "OPD", "IIC-2603-160": "IVF with Bed", "IIC-2604-240": "Non IVF with Bed",
  "IIC-2603-175": "IVF with Bed", "IIC-2605-115": "IVF without Bed", "IIC-2604-260": "Non IVF without Bed",
  "IIC-2602-070": "IVF with Bed",
};
export function patientCategory(p: Patient): string {
  return p.cat || BPL_CATMAP[p.id] || "IVF with Bed";
}
export function patientPayTier(p: Patient): "100" | "50" | "10" | "0" {
  const v = p.paid || 0;
  if (v >= 100) return "100";
  if (v >= 50) return "50";
  if (v >= 10) return "10";
  return "0";
}
export function bplFirstConsultDate(p: Patient): string {
  let s = 0;
  for (let i = 0; i < p.id.length; i++) s += p.id.charCodeAt(i);
  const offset = 7 + (s % 15);
  return addDays(p.signup, -offset);
}

/* ---- multi-package category ordering ---- */
const PKG_CAT: Record<string, string> = { IP222: "IVF with Bed", IP11: "IVF with Bed", Donor: "IVF with Bed", Composite: "Non IVF without Bed", FET: "IVF without Bed", IUI: "Non IVF with Bed", ICSI: "Non IVF without Bed", OPD: "OPD" };
const CAT_PRIORITY = ["IVF with Bed", "Non IVF with Bed", "Non IVF without Bed", "IVF without Bed", "OPD"];
export function jPkgCat(tok: string): string {
  tok = (tok || "").trim();
  if (PKG_CAT[tok]) return PKG_CAT[tok];
  const t = tok.toLowerCase();
  if (t.indexOf("composite") >= 0) return "Non IVF without Bed";
  if (t.indexOf("iui") >= 0) return "Non IVF with Bed";
  return "IVF with Bed";
}
export function jPkgList(p: Patient): { pkg: string; cat: string }[] {
  const toks = (p.pkg || "").split("+").map((s) => s.trim()).filter(Boolean);
  const cats = toks.map((tk) => ({ pkg: tk, cat: jPkgCat(tk) }));
  cats.sort((a, b) => {
    const ia = CAT_PRIORITY.indexOf(a.cat);
    const ib = CAT_PRIORITY.indexOf(b.cat);
    return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
  });
  return cats;
}
export function jCatChips(p: Patient): { pkg: string; cat: string }[] {
  const list = jPkgList(p);
  const seen: Record<string, boolean> = {};
  const out: { pkg: string; cat: string }[] = [];
  list.forEach((x) => {
    if (!seen[x.cat]) {
      seen[x.cat] = true;
      out.push(x);
    }
  });
  return out;
}
/** category chip(s) for a given journey row: OPD for pre-booking stages, else the package categories */
export function jCatForRow(p: Patient, i: number): { cat: string; opd: boolean }[] {
  if (STAGES_FULL[i].group === "pre") return [{ cat: "OPD", opd: true }];
  return jCatChips(p).map((x) => ({ cat: x.cat, opd: false }));
}

/* ---- per-stage financial completion threshold ---- */
export function jStageFinPct(i: number): number {
  const key = STAGES_FULL[i].key;
  const M: Record<string, number> = { "First Consult": 0, "Package Estimate": 0, "CNB Visits": 0, Booked: 10, "Pre-Procedure": 10, "Ovarian Stimulation": 50, "Endometrial Preparation": 50, Trigger: 100, OPU: 100, "Progesterone Change": 100, "Embryo Transfer": 100, "B-HCG": 100, "Cardiac Activity": 100 };
  return M[key] ?? 0;
}

/* ---- booked packages (oldest-first) ---- */
const PKG_NAME: Record<string, string> = { IP222: "Comprehensive IVF (Self)", IP11: "IVF Self Cycle", Donor: "Donor IVF Programme", Composite: "Composite Add-on", FET: "Frozen Embryo Transfer", IUI: "Intrauterine Insemination", ICSI: "ICSI (Embryology Lab)", OPD: "OPD Consultation", IP05: "Frozen Embryo Transfer" };
const PKG_PRICE: Record<string, number> = { IP222: 260000, IP11: 195000, Donor: 390000, Composite: 50000, FET: 58000, IUI: 22000, ICSI: 45000, OPD: 2000, IP05: 55000 };
export function patientPackages(p: Patient): { cat: string; code: string; name: string; date: string; net: number; received: number }[] {
  if (Array.isArray(p.packages)) {
    return p.packages.slice().sort((a, b) => String(a.date || "").localeCompare(String(b.date || "")));
  }
  const toks = (p.pkg || "").split("+").map((s) => s.trim()).filter(Boolean);
  const paid = p.paid || 0;
  const out = toks.map((tk, idx) => {
    const net = PKG_PRICE[tk] != null ? PKG_PRICE[tk] : 50000;
    return { cat: jPkgCat(tk), code: tk, name: PKG_NAME[tk] || tk, date: addDays(p.signup, idx * 4), net, received: Math.round((net * paid) / 100) };
  });
  if (out.length === 1) {
    out[0].net = p.net || out[0].net;
    out[0].received = Math.round(((p.net || 0) * paid) / 100);
  }
  return out;
}

/* ---- ideal clinical-stage date range (T+n) ---- */
export function jIdealRange(p: Patient, i: number): { from: string; to: string } | null {
  if (!p.signup || J_STAGE_RANGE[i] == null) return null;
  const r = J_STAGE_RANGE[i];
  return { from: addDays(p.signup, r[0]), to: addDays(p.signup, r[1]) };
}
export function jRangeText(r: { from: string; to: string } | null): string {
  if (!r) return "";
  return r.from === r.to ? fmtDate(r.from) : fmtDate(r.from) + " — " + fmtDate(r.to);
}
export function stageDate(idx: number, p: JourneySubject): string {
  if (isPrebookPatient(p)) {
    const s = STAGES_FULL[idx];
    return s && s.group === "pre" ? (p.date ? fmtDate(p.date) : "") : "";
  }
  const patient = p as Patient;
  return jRangeText(jIdealRange(patient, idx));
}

/* ---- role/company-wide scoped selectors (mgmt sees everything) ---- */
export function allPatients(): Patient[] {
  return PATIENTS.slice();
}
export function stageDistribution(): number[] {
  const counts = new Array(8).fill(0);
  PATIENTS.forEach((p) => {
    if (p.stage >= 0 && p.stage < 8) counts[p.stage]++;
  });
  return counts;
}
export function centreStageBreakdown() {
  return CENTRE_STATS.map((c) => {
    const cp = PATIENTS.filter((p) => p.centre === c.centre);
    const counts = new Array(8).fill(0);
    cp.forEach((p) => {
      if (p.stage >= 0 && p.stage < 8) counts[p.stage]++;
    });
    return { centre: c.centre, counts, total: cp.length };
  });
}
export function agingByCentre() {
  return CENTRE_STATS.map((c) => ({ centre: c.centre, buckets: AGING[c.centre], total: AGING[c.centre].reduce((s, x) => s + x, 0) }));
}

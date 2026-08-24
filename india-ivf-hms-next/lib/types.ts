export type Flag = "green" | "amber" | "red";
export type Gate = "open" | "pay" | "data";
export type PrebookType = "scheduled" | "missed" | "cnb";

export interface PackageLine {
  cat: string;
  code: string;
  name: string;
  date: string;
  net: number;
  received: number;
}

export interface Patient {
  id: string;
  name: string;
  centre: string;
  doctor: string;
  fc: string;
  pkg: string;
  desc?: string;
  net: number;
  discount: number;
  signup: string;
  /** old 8-step milestone index (0..7) */
  stage: number;
  flag: Flag;
  gate: Gate;
  /** cumulative % of package paid */
  paid: number;
  cat?: string;
  donorId?: string;
  surrogateId?: string;
  donorMode?: "Self-cycle" | "Donor";
  surrogateMode?: "Self" | "Surrogate";
  packages?: PackageLine[];
}

export interface PrebookPatient {
  id: string;
  name: string;
  date: string;
  centre: string;
  type: PrebookType;
  doctor?: string;
  treatment?: string;
  quality?: "Hot" | "Cold" | "Dead";
  fcComment?: string;
  lastConn?: string;
  lastComment?: string;
}

export type JourneySubject = Patient | PrebookPatient;

export interface CentreStat {
  centre: string;
  exp: number;
  act: number;
  due: number;
  aging: number;
  red: number;
  adh: number;
  ontrack: number;
}

export interface TriggerItem {
  type: string;
  id: string;
  name: string;
  centre: string;
  stage: string;
  value: number;
  days: number;
}

export interface Approval {
  type: string;
  id: string;
  name: string;
  centre: string;
  detail: string;
  req: string;
}

export interface OverrideLog {
  ts: string;
  type: string;
  id: string;
  name: string;
  centre: string;
  by: string;
  reason: string;
}

export interface Payment {
  m: string;
  a: number;
}

export interface AgingPatient {
  id: string;
  name: string;
  centre: string;
  fc: string;
  pkg: string;
  desc: string;
  signup: string;
  gross: number;
  discPct: number;
  incGst: number;
  milestone: string;
  daysOverdue: number;
  lastFu: string;
  referred: string;
  status: string;
  invoice: string;
  history: string;
  payments: Payment[];
}

export interface Donor {
  id: string;
  name: string;
  age: number;
  blood: string;
  height: string;
  weight: string;
  amh: string;
  phenotype: string;
  education: string;
  occupation: string;
  marital: string;
  priorCycles: number;
  priorYield: string;
  screening: string;
  hiv: string;
  hbsag: string;
  hcv: string;
  vdrl: string;
  thalassemia: string;
  karyotype: string;
  agency: string;
  anonymity: string;
  consent: string;
  contractValue: number;
  paid: number;
  centre: string;
  coordinator: string;
  phone: string;
  status: string;
  notes: string;
}

export interface Surrogate {
  id: string;
  name: string;
  age: number;
  blood: string;
  height: string;
  weight: string;
  bmi: string;
  parity: string;
  obstetricHist: string;
  endometrium: string;
  hsg: string;
  screening: string;
  hiv: string;
  hbsag: string;
  hcv: string;
  vdrl: string;
  gtt: string;
  thyroid: string;
  legalStatus: string;
  consent: string;
  ec: string;
  insurance: string;
  agency: string;
  anonymity: string;
  contractValue: number;
  paid: number;
  centre: string;
  coordinator: string;
  phone: string;
  husband: string;
  status: string;
  notes: string;
}

export interface StageDef {
  key: string;
  day: string;
  group: "pre" | "book" | "clinical" | "lab";
  donorChoice?: boolean;
}

export interface FormFieldGroup {
  l: string;
  f: string[];
}

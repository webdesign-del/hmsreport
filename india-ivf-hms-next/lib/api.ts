import { headers } from "next/headers";
import type {
  Approval,
  CentreStat,
  AgingPatient,
  Donor,
  OverrideLog,
  Patient,
  PrebookPatient,
  Surrogate,
  TriggerItem,
} from "./types";

/** Server Components can't fetch relative URLs — resolve the current request's origin. */
async function baseUrl(): Promise<string> {
  const h = await headers();
  const host = h.get("host") ?? "localhost:3000";
  const proto = h.get("x-forwarded-proto") ?? (host.startsWith("localhost") ? "http" : "https");
  return `${proto}://${host}`;
}

async function getJSON<T>(path: string): Promise<T> {
  const base = await baseUrl();
  const res = await fetch(`${base}${path}`, { cache: "no-store" });
  if (!res.ok) throw new Error(`Failed to fetch ${path}: ${res.status}`);
  return res.json() as Promise<T>;
}

export const getPatients = () => getJSON<Patient[]>("/api/patients");
export const getPrebook = (centerId?: number | null) =>
  getJSON<PrebookPatient[]>(`/api/prebook${centerId ? `?center_id=${centerId}` : ""}`);
export const getTriggers = (centerId?: number | null) =>
  getJSON<TriggerItem[]>(`/api/triggers${centerId ? `?center_id=${centerId}` : ""}`);
export const getApprovals = () => getJSON<Approval[]>("/api/approvals");
export const getOverrides = () => getJSON<OverrideLog[]>("/api/overrides");
export const getDonors = () => getJSON<Donor[]>("/api/donors");
export const getSurrogates = () => getJSON<Surrogate[]>("/api/surrogates");
export const getAgingPatients = () => getJSON<AgingPatient[]>("/api/aging-patients");

export interface CentreStatsResponse {
  centres: CentreStat[];
  aging: Record<string, number[]>;
  companyBuckets: number[];
  totals: { exp: number; act: number; aging: number };
  weekTrend: number[];
}
export const getCentreStats = () => getJSON<CentreStatsResponse>("/api/centre-stats");

export interface DashboardResponse {
  kpi: {
    today: { exp: number; act: number; redTriggers: number; approvalsPending: number };
    week: { exp: number; act: number; projection: number; redTriggers: number };
    month: { exp: number; act: number; projection: number; redTriggers: number };
  };
  agingBucketCounts: number[];
}
export const getDashboard = () => getJSON<DashboardResponse>("/api/dashboard");

export interface AgingSnapshotResponse {
  companyBuckets: number[];
  bucketCounts: number[];
  byCentre: { centre: string; buckets: number[]; total: number }[];
  patients: AgingPatient[];
}
export const getAgingSnapshot = (centerId?: number | null) =>
  getJSON<AgingSnapshotResponse>(`/api/aging-snapshot${centerId ? `?center_id=${centerId}` : ""}`);

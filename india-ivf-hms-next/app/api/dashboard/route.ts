import { NextResponse } from "next/server";
import { DASHBOARD_KPI, AGING_BUCKET_COUNTS } from "@/lib/data";

export async function GET() {
  return NextResponse.json({ kpi: DASHBOARD_KPI, agingBucketCounts: AGING_BUCKET_COUNTS });
}

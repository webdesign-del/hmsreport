import { NextResponse } from "next/server";
import { CENTRE_STATS, AGING, COMPANY_BUCKETS, TOT_ACT, TOT_EXP, TOT_AGING, WEEK_TREND } from "@/lib/data";

export async function GET() {
  return NextResponse.json({
    centres: CENTRE_STATS,
    aging: AGING,
    companyBuckets: COMPANY_BUCKETS,
    totals: { exp: TOT_EXP, act: TOT_ACT, aging: TOT_AGING },
    weekTrend: WEEK_TREND,
  });
}

import { NextResponse } from "next/server";
import { AGING_PATIENTS } from "@/lib/data";

export async function GET() {
  return NextResponse.json(AGING_PATIENTS);
}

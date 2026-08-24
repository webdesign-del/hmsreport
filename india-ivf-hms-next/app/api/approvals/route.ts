import { NextResponse } from "next/server";
import { APPROVALS } from "@/lib/data";

export async function GET() {
  return NextResponse.json(APPROVALS);
}

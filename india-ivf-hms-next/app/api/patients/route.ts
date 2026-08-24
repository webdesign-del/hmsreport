import { NextResponse } from "next/server";
import { PATIENTS } from "@/lib/data";

export async function GET() {
  return NextResponse.json(PATIENTS);
}

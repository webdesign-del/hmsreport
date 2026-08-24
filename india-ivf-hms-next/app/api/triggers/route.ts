import { NextResponse } from "next/server";
import { TRIGGERS } from "@/lib/data";

export async function GET() {
  return NextResponse.json(TRIGGERS);
}

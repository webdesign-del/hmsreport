import { NextResponse } from "next/server";
import { DONORS } from "@/lib/data";

export async function GET() {
  return NextResponse.json(DONORS);
}

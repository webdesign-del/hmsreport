import { NextResponse } from "next/server";
import { OVERRIDES } from "@/lib/data";

export async function GET() {
  return NextResponse.json(OVERRIDES);
}

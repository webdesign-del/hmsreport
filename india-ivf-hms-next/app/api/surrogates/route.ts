import { NextResponse } from "next/server";
import { SURROGATES } from "@/lib/data";

export async function GET() {
  return NextResponse.json(SURROGATES);
}

import { NextResponse } from "next/server";

const BASES = ["http://127.0.0.1:8000/api", "http://localhost:8000/api"];

// GET /api/aging-notifications -> { status, maxId, items: [{ id, invoice, patientId, text, at }] }
export async function GET() {
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/aging_notifications/`, { cache: "no-store" });
      return NextResponse.json(await res.json(), { status: res.status });
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

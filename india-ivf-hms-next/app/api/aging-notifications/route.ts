import { NextResponse } from "next/server";
import { getScopedCenterId } from "@/lib/auth";

const BASES = ["http://127.0.0.1:8000/api", "http://localhost:8000/api"];

// GET /api/aging-notifications -> { status, maxId, items: [{ id, invoice, patientId, text, at }] }
// Centre-scoped: a centre user (or a management user with a centre selected) only sees that centre's comments.
export async function GET() {
  const centerId = await getScopedCenterId();
  const qs = centerId ? `?center_id=${encodeURIComponent(String(centerId))}` : "";
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/aging_notifications/${qs}`, { cache: "no-store" });
      return NextResponse.json(await res.json(), { status: res.status });
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

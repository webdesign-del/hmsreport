import { NextResponse } from "next/server";

const BASES = ["http://127.0.0.1:8000/api", "http://localhost:8000/api"];

// GET  /api/stage-delay?patient_id=...  -> saved delay reasons for that patient
// POST /api/stage-delay                 -> save a delay reason (counsellor popup)
export async function GET(request: Request) {
  const patientId = new URL(request.url).searchParams.get("patient_id") ?? "";
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/get_stage_delays/?patient_id=${encodeURIComponent(patientId)}`, { cache: "no-store" });
      return NextResponse.json(await res.json(), { status: res.status });
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

export async function POST(request: Request) {
  const body = await request.text();
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/save_stage_delay/`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body,
        cache: "no-store",
      });
      return NextResponse.json(await res.json(), { status: res.status });
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

import { NextResponse } from "next/server";

const DJANGO_URLS = ["http://127.0.0.1:8000/api/add_cnb_comment/", "http://localhost:8000/api/add_cnb_comment/"];

// POST /api/cnb-comment { patient_id, comment, connected_date, created_by } -> { status, comments: [...] }
// Append-only: there is no PUT / DELETE, so a saved comment cannot be edited or removed.
export async function POST(request: Request) {
  const body = await request.text();
  for (const url of DJANGO_URLS) {
    try {
      const res = await fetch(url, { method: "POST", headers: { "Content-Type": "application/json" }, body });
      return NextResponse.json(await res.json(), { status: res.status });
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

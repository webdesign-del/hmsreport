import { NextResponse } from "next/server";

const DJANGO_URLS = ["http://127.0.0.1:8000/api/get_aging_snapshot/", "http://localhost:8000/api/get_aging_snapshot/"];

export async function GET(request: Request) {
  const sp = new URL(request.url).searchParams;
  const params = new URLSearchParams();
  for (const k of ["center_id", "date_from", "date_to"]) {
    const v = sp.get(k);
    if (v) params.set(k, v);
  }
  const qs = params.toString() ? `?${params.toString()}` : "";

  for (const url of DJANGO_URLS) {
    try {
      const res = await fetch(`${url}${qs}`, { cache: "no-store" });
      if (res.ok) return NextResponse.json(await res.json());
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ error: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

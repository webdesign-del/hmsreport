import { NextResponse } from "next/server";

const DJANGO_URLS = ["http://127.0.0.1:8000/api/get_red_triggers/", "http://localhost:8000/api/get_red_triggers/"];

export async function GET(request: Request) {
  const centerId = new URL(request.url).searchParams.get("center_id");
  const qs = centerId ? `?center_id=${encodeURIComponent(centerId)}` : "";

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

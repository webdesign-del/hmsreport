import { NextResponse } from "next/server";

const DJANGO_URLS = [
  "http://127.0.0.1:8000/api/get_centre_comparison/",
  "http://localhost:8000/api/get_centre_comparison/",
];

export async function GET(request: Request) {
  const params = new URL(request.url).searchParams;
  const forward = new URLSearchParams();
  for (const key of ["center_id", "from", "to"]) {
    const v = params.get(key);
    if (v) forward.set(key, v);
  }
  const qs = forward.toString() ? `?${forward.toString()}` : "";

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

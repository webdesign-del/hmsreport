import { NextResponse } from "next/server";

const DJANGO_URLS = ["http://127.0.0.1:8000/api/get_aging_snapshot/", "http://localhost:8000/api/get_aging_snapshot/"];

export async function GET() {
  for (const url of DJANGO_URLS) {
    try {
      const res = await fetch(url, { cache: "no-store" });
      if (res.ok) return NextResponse.json(await res.json());
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ error: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

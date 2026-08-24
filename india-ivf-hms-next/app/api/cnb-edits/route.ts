import { NextResponse } from "next/server";

const DJANGO_URLS = ["http://127.0.0.1:8000/api/save_cnb_edits/", "http://localhost:8000/api/save_cnb_edits/"];

export async function POST(request: Request) {
  const body = await request.text();
  for (const url of DJANGO_URLS) {
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body,
      });
      const data = await res.json();
      if (res.ok) return NextResponse.json(data);
      return NextResponse.json(data, { status: res.status });
    } catch {
      // try next fallback host
    }
  }
  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

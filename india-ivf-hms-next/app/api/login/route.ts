import { NextResponse } from "next/server";

const DJANGO_URLS = ["http://127.0.0.1:8000/api/login/", "http://localhost:8000/api/login/"];

export async function POST(request: Request) {
  const body = await request.text();

  for (const url of DJANGO_URLS) {
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body,
        cache: "no-store",
      });
      const data = await res.json();
      return NextResponse.json(data, { status: res.status });
    } catch {
      // try next fallback host
    }
  }

  return NextResponse.json({ status: "error", message: "Django API unreachable on 127.0.0.1 or localhost." }, { status: 502 });
}

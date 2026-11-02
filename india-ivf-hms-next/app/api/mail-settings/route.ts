import { NextResponse } from "next/server";

const BASES = ["http://127.0.0.1:8000/api", "http://localhost:8000/api"];

// GET  /api/mail-settings -> current SMTP settings (the password is never returned, only passwordSet)
// POST /api/mail-settings {host, port, useTls, user, password, fromEmail} | {op: "clear"}
export async function GET() {
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/mail_settings/`, { cache: "no-store" });
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
      const res = await fetch(`${base}/mail_settings/`, {
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

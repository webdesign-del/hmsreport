import { NextResponse } from "next/server";

const BASES = ["http://127.0.0.1:8000/api", "http://localhost:8000/api"];

// GET  /api/notify-recipients            -> { admin: string[], telecalling: string[] }
// POST /api/notify-recipients {kind, email, op: "add" | "remove"} -> updated lists
export async function GET() {
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/notify_recipients/`, { cache: "no-store" });
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
      const res = await fetch(`${base}/notify_recipients/`, {
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

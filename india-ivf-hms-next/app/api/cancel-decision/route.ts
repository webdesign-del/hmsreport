// Public page behind the Approve / Disapprove buttons in the cancellation-approval mail.
// It only proxies to Django, which checks the signed token itself.
const BASES = ["http://127.0.0.1:8000/api", "http://localhost:8000/api"];

async function forward(request: Request, method: "GET" | "POST") {
  const url = new URL(request.url);
  const body = method === "POST" ? await request.text() : undefined;
  for (const base of BASES) {
    try {
      const res = await fetch(`${base}/cancel_decision/${url.search}`, {
        method,
        headers: method === "POST" ? { "Content-Type": "application/x-www-form-urlencoded" } : {},
        body,
        cache: "no-store",
      });
      return new Response(await res.text(), {
        status: res.status,
        headers: { "Content-Type": "text/html; charset=utf-8" },
      });
    } catch {
      // try next fallback host
    }
  }
  return new Response("<p style='font-family:sans-serif'>The server is not reachable right now. Please try again in a moment.</p>", {
    status: 502,
    headers: { "Content-Type": "text/html; charset=utf-8" },
  });
}

export const GET = (request: Request) => forward(request, "GET");
export const POST = (request: Request) => forward(request, "POST");

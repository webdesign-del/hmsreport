/**
 * Formats a Date as YYYY-MM-DD using its LOCAL date parts. `d.toISOString()` converts
 * through UTC first, which silently rolls the date back a day in any timezone ahead of
 * UTC (e.g. IST) — this is the safe alternative for anything meant to read as "today"
 * or a calendar date, as opposed to an instant in time.
 */
export function localISODate(d: Date = new Date()): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

export const TODAY = localISODate();

export function fmtINR(n: number | null | undefined): string {
  if (n === 0 || n == null) return "₹0";
  const s = Math.round(Math.abs(n)).toString();
  let last3 = s.slice(-3);
  let rest = s.slice(0, -3);
  if (rest) last3 = "," + last3;
  rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ",");
  return (n < 0 ? "-₹" : "₹") + rest + last3;
}

const MON = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

export function fmtDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso + "T00:00:00");
  return d.getDate() + " " + MON[d.getMonth()] + " " + d.getFullYear();
}

export function addDays(iso: string, n: number): string {
  const d = new Date(iso + "T00:00:00");
  d.setDate(d.getDate() + n);
  return localISODate(d);
}

export function daysBetween(a: string, b: string): number {
  return Math.round((new Date(b).getTime() - new Date(a).getTime()) / 86400000);
}

export function fmtPhone(n: string | number): string {
  const s = String(n);
  return "+91 " + s.slice(0, 5) + " " + s.slice(5);
}

export function initialsOf(name: string | null | undefined): string {
  return (
    (name || "")
      .split(" ")
      .map((p) => p[0] || "")
      .join("")
      .slice(0, 2)
      .toUpperCase() || "?"
  );
}

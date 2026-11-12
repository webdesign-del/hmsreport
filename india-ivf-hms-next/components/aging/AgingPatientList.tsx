"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { TODAY, fmtDate, fmtINR } from "@/lib/format";
import { FY_LABELS, FY_MONTHS } from "@/lib/seed-data";
import { bucketIdx, patientGstSplit, paymentInMonth, paymentsTotal, pkgDisc, pkgFY, pkgMonthLabel } from "@/lib/derive";
import { getClientSession, isReadOnlyRole } from "@/lib/clientSession";
import type { AgingPatient } from "@/lib/types";

const BUCKET_LABELS = ["0–30 d", "31–60 d", "61–90 d", "91–180 d", "180+ d"];

const FOLLOWUP_STATUSES = ["Active", "Branch Action Required", "On Hold", "Revert to Telecaller team", "Cancellation Request"] as const;

function inDateRange(iso: string, range: string, single: string, from: string, to: string): boolean {
  if (range === "all" || !iso) return true;
  const T = new Date(TODAY + "T00:00:00");
  const d = new Date(iso + "T00:00:00");
  if (range === "day") return d.getTime() === T.getTime();
  if (range === "date") return single ? iso === single : true;
  if (range === "week") {
    const dow = T.getDay();
    const off = (dow + 6) % 7;
    const mon = new Date(T);
    mon.setDate(T.getDate() - off);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    return d >= mon && d <= sun;
  }
  if (range === "month") return d.getMonth() === T.getMonth() && d.getFullYear() === T.getFullYear();
  if (range === "year") return d.getFullYear() === T.getFullYear();
  if (range === "custom") {
    if (!from || !to) return true;
    return iso >= from && iso <= to;
  }
  return true;
}

const DELAY_REASONS = ["Financial Problem", "Medical Issues", "Personal Issues", "Relocated"] as const;
const CANCEL_REASONS = [
  "Financial",
  "Medical issues",
  "Divorce",
  "Adoption",
  "Self conceived",
  "Done from other IVF centre",
  "Relocated",
  "Personal Issues",
] as const;

/** Final status = manual override (Likely Cancellation) if set, else the auto-calculated one. */
function effectiveStatus(p: AgingPatient): string {
  return p.statusOverride || p.autoStatus || "";
}

/** A follow-up status managed by the Action menu (not Active / On Hold) - the popup must not overwrite it. */
function isWorkflowStatus(status: string): boolean {
  return (FOLLOWUP_STATUSES as readonly string[]).includes(status) && status !== "Active" && status !== "On Hold";
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case "Treatment Completed":
      return "border-blue-300 bg-blue-100 text-blue-800";
    case "On Track":
      return "border-green-300 bg-green-100 text-green-700";
    case "Delayed":
      return "border-red-300 bg-red-100 text-red-700";
    case "Likely Cancellation":
      return "border-amber-300 bg-amber-100 text-amber-800";
    default:
      return "border-border bg-surface-2 text-text-soft";
  }
}

type ActionKind = "status" | "centre" | "revert" | "cancel" | "review" | "centre_review" | "likely";

function requestBadgeClass(state: string): string {
  switch (state) {
    case "Cancellation Requested":
      return "border-amber-300 bg-amber-100 text-amber-800";
    case "Cancellation Approved":
      return "border-red-300 bg-red-100 text-red-700";
    case "With Telecalling":
    case "With Telecalling (Megha)":
    case "Forwarded to Megha":
      return "border-blue-300 bg-blue-100 text-blue-800";
    case "ReActive – Returned to Branch":
    case "Returned to Branch":
      return "border-green-300 bg-green-100 text-green-700";
    default:
      return "border-border bg-surface-2 text-text-mid";
  }
}

/** Small add / remove list of notification email addresses. */
function RecipientEditor({
  title,
  list,
  onChange,
  canRemove = false,
}: {
  title: string;
  list: string[];
  onChange: (email: string, op: "add" | "remove") => Promise<string | null>;
  canRemove?: boolean;
}) {
  const [val, setVal] = useState("");
  const [err, setErr] = useState("");
  const [busy, setBusy] = useState(false);

  async function add() {
    const email = val.trim();
    if (!email) return;
    setBusy(true);
    const e = await onChange(email, "add");
    setBusy(false);
    if (e) setErr(e);
    else {
      setErr("");
      setVal("");
    }
  }

  return (
    <div className="mb-3 rounded-[10px] border border-border-soft bg-surface-2 p-2.5">
      <div className="mb-1.5 text-[11px] font-semibold uppercase tracking-[.04em] text-text-soft">✉ {title}</div>
      <div className="mb-2 flex flex-wrap gap-1.5">
        {list.length === 0 && <span className="text-[11.5px] italic text-text-soft">No email added yet</span>}
        {list.map((em) => (
          <span key={em} className="inline-flex items-center gap-1 rounded-full border border-border bg-surface px-2.5 py-0.5 text-[11.5px] font-semibold text-text-mid">
            {em}
            {canRemove && (
              <button type="button" onClick={() => onChange(em, "remove")} className="text-text-soft hover:text-red" title="Remove">
                ✕
              </button>
            )}
          </span>
        ))}
      </div>
      <div className="flex gap-1.5">
        <input
          type="email"
          value={val}
          onChange={(e) => setVal(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              add();
            }
          }}
          placeholder="name@example.com"
          className="min-w-0 flex-1 rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[12px] outline-none focus:border-primary"
        />
        <button
          type="button"
          onClick={add}
          disabled={busy}
          className="rounded-[8px] bg-primary px-3 py-1.5 text-[11.5px] font-semibold text-white hover:bg-primary-dark disabled:opacity-60"
        >
          + Add
        </button>
      </div>
      {err && <div className="mt-1 text-[11px] font-semibold text-red">{err}</div>}
    </div>
  );
}

export default function AgingPatientList({ patients, centres = [] }: { patients: AgingPatient[]; centres?: string[] }) {
  const [localPatients, setLocalPatients] = useState<AgingPatient[]>(patients);
  const [bucket, setBucket] = useState<number | null>(null);
  const [search, setSearch] = useState("");
  const [range, setRange] = useState("all");
  const [single, setSingle] = useState(TODAY);
  const [from, setFrom] = useState("2026-01-01");
  const [to, setTo] = useState(TODAY);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(50);
  // Booking / Sales Reporting / Billing centre are usually the same value — collapsed by default
  // into a single "Centre" column; the toggle in the header expands all three.
  const [centresOpen, setCentresOpen] = useState(false);
  // The 24 monthly collection columns are grouped by financial year (12 months each).
  // Each FY is collapsed by default into one total column; click its header to expand months.
  const FY_GROUPS = [
    { label: "FY 25-26", months: FY_MONTHS.slice(0, 12), labels: FY_LABELS.slice(0, 12) },
    { label: "FY 26-27", months: FY_MONTHS.slice(12, 24), labels: FY_LABELS.slice(12, 24) },
  ];
  const [fyOpen, setFyOpen] = useState<boolean[]>([false, false]);
  const toggleFy = (i: number) => setFyOpen((o) => o.map((v, j) => (j === i ? !v : v)));
  const fyColCount = FY_GROUPS.reduce((n, g, i) => n + (fyOpen[i] ? g.months.length : 1), 0);

  const [followUpTarget, setFollowUpTarget] = useState<AgingPatient | null>(null);
  const [followUpStatus, setFollowUpStatus] = useState<string>(FOLLOWUP_STATUSES[0]);
  const [followUpRemarks, setFollowUpRemarks] = useState("");
  const [followUpDate, setFollowUpDate] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState("");

  useEffect(() => {
    setLocalPatients(patients);
  }, [patients]);

  // ---- Status + Actions (Change Centre / Revert to Telecalling / Cancellation / Status reason) ----
  const [menu, setMenu] = useState<{ p: AgingPatient; right: number; top: number } | null>(null);
  const [action, setAction] = useState<{ kind: ActionKind; p: AgingPatient } | null>(null);
  const [actSaving, setActSaving] = useState(false);
  const [actError, setActError] = useState("");
  // status popup
  const [stMode, setStMode] = useState<"auto" | "likely_cancellation">("auto");
  const [stReason, setStReason] = useState("");
  // change-centre popup
  const [toCentre, setToCentre] = useState("");
  const [allCentres, setAllCentres] = useState<string[]>([]);
  const [ccDecision, setCcDecision] = useState<"approved" | "rejected">("approved");
  const [ccCentre, setCcCentre] = useState("");
  useEffect(() => {
    // Every centre — the page's own data is scoped to the user's centre, which would leave the picker empty.
    fetch("/api/centre-list", { cache: "no-store" })
      .then((r) => r.json())
      .then((d) => {
        if (d && d.status === "success" && Array.isArray(d.centres)) setAllCentres(d.centres.map((c: { name: string }) => c.name));
      })
      .catch(() => {});
  }, []);
  const [centreReason, setCentreReason] = useState("");
  // shared
  const [actRemarks, setActRemarks] = useState("");
  // revert-to-telecalling popup
  const [revMode, setRevMode] = useState<"revert" | "reactive" | "cancellation_request">("revert");
  // superadmin cancellation review popup
  const [reviewDecision, setReviewDecision] = useState<"approved" | "not_approved">("approved");
  const [reviewRoute, setReviewRoute] = useState<"forward_megha" | "return_centre">("forward_megha");
  const [refundAmt, setRefundAmt] = useState("");

  // ---- Email notifications: Admin gets cancellation requests / decisions, Telecalling gets reverts ----
  const sessionEmail = () => {
    const s = getClientSession() as { email?: string; username?: string } | null;
    const e = s?.email || s?.username || "";
    return e.includes("@") ? e : "";
  };
  const sessionUser = () => {
    const s = getClientSession();
    return s?.username || s?.name || "";
  };
  const [recipients, setRecipients] = useState<{ admin: string[]; telecalling: string[]; accountant: string[] }>({ admin: [], telecalling: [], accountant: [] });
  const [mailSettingsOpen, setMailSettingsOpen] = useState(false);
  const [notice, setNotice] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  // ---- Comment notifications (bell + toast); click jumps to the patient's comment ----
  type Notif = { id: number; invoice: string; patientId: string; text: string; at: string };
  const [notifs, setNotifs] = useState<Notif[]>([]);
  const [unread, setUnread] = useState(0);
  const [notifOpen, setNotifOpen] = useState(false);
  const [toast, setToast] = useState<Notif | null>(null);
  const [pendingJump, setPendingJump] = useState<string | null>(null);
  const [highlight, setHighlight] = useState<string | null>(null);
  const lastNotifId = useRef<number | null>(null);
  const [mailMode, setMailMode] = useState<"smtp" | "console" | null>(null);
  const [fromEmail, setFromEmail] = useState("");
  const [smtp, setSmtp] = useState({ host: "smtp.gmail.com", port: "587", useTls: true, verifySsl: true, user: "", password: "", fromEmail: "" });
  const [smtpPwSet, setSmtpPwSet] = useState(false);
  const [smtpBusy, setSmtpBusy] = useState(false);
  const [smtpMsg, setSmtpMsg] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  const [testTo, setTestTo] = useState("");
  const [testBusy, setTestBusy] = useState<"admin" | "telecalling" | null>(null);
  const [testMsg, setTestMsg] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);

  useEffect(() => {
    fetch("/api/notify-recipients", { cache: "no-store" })
      .then((r) => r.json())
      .then((d) => {
        if (d && d.status === "success") {
          setRecipients({ admin: d.admin || [], telecalling: d.telecalling || [], accountant: d.accountant || [] });
          setMailMode(d.mailMode === "smtp" ? "smtp" : d.mailMode === "console" ? "console" : null);
          setFromEmail(d.fromEmail || "");
        }
      })
      .catch(() => {});
  }, []);

  function applySmtp(d: Record<string, unknown>) {
    setMailMode(d.mailMode === "smtp" ? "smtp" : "console");
    setFromEmail(String(d.fromEmail || ""));
    setSmtpPwSet(!!d.passwordSet);
    if (d.host) {
      setSmtp({
        host: String(d.host),
        port: String(d.port || 587),
        useTls: d.useTls !== false,
        verifySsl: d.verifySsl !== false,
        user: String(d.user || ""),
        password: "",
        fromEmail: String(d.fromEmail || ""),
      });
    }
  }

  function openMailSettings() {
    setMailSettingsOpen(true);
    setSmtpMsg(null);
    setTestMsg(null);
    fetch("/api/mail-settings", { cache: "no-store" })
      .then((r) => r.json())
      .then((d) => {
        if (d && d.status === "success") applySmtp(d);
      })
      .catch(() => {});
  }

  async function saveSmtp(clear = false) {
    setSmtpBusy(true);
    setSmtpMsg(null);
    try {
      const res = await fetch("/api/mail-settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(clear ? { op: "clear" } : smtp),
      });
      const d = await res.json();
      if (!res.ok || d.status !== "success") {
        setSmtpMsg({ tone: "warn", text: d.message || "Could not save the mail settings." });
      } else {
        if (clear) setSmtp({ host: "smtp.gmail.com", port: "587", useTls: true, verifySsl: true, user: "", password: "", fromEmail: "" });
        applySmtp(d);
        if (clear) setSmtpPwSet(false);
        setSmtpMsg({ tone: "ok", text: clear ? "Saved mail settings removed." : "Mail settings saved. Use “Send test mail” to check them." });
      }
    } catch {
      setSmtpMsg({ tone: "warn", text: "Could not reach the server." });
    } finally {
      setSmtpBusy(false);
    }
  }

  async function sendTestMail(kind: "admin" | "telecalling") {
    setTestBusy(kind);
    setTestMsg(null);
    try {
      const res = await fetch("/api/notify-recipients", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ kind, op: "test", to: testTo.trim() }),
      });
      const d = await res.json();
      if (!res.ok || d.status !== "success") {
        setTestMsg({ tone: "warn", text: d.message || "Test mail failed." });
      } else {
        const m = d.mail || {};
        if (m.error) setTestMsg({ tone: "warn", text: `Mail failed: ${m.error}` });
        else if (m.sent > 0 && m.mode === "console")
          setTestMsg({ tone: "warn", text: "Not actually sent — SMTP settings are not saved, the mail was only printed in the Django terminal." });
        else if (m.sent > 0) setTestMsg({ tone: "ok", text: `Test mail sent to ${m.sent} recipient${m.sent === 1 ? "" : "s"}. Check inbox / spam.` });
        else setTestMsg({ tone: "warn", text: m.note || "No recipient email added in this list." });
      }
    } catch {
      setTestMsg({ tone: "warn", text: "Could not reach the server." });
    } finally {
      setTestBusy(null);
    }
  }

  async function changeRecipient(kind: "admin" | "telecalling" | "accountant", email: string, op: "add" | "remove"): Promise<string | null> {
    try {
      const res = await fetch("/api/notify-recipients", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ kind, email, op, role }),
      });
      const d = await res.json();
      if (!res.ok || d.status !== "success") return d.message || "Could not save the email.";
      setRecipients({ admin: d.admin || [], telecalling: d.telecalling || [], accountant: d.accountant || [] });
      return null;
    } catch {
      return "Could not reach the server.";
    }
  }

  // Which list will the open popup's action email? (null = no mail for that action)
  function popupMailKind(): "admin" | "telecalling" | null {
    if (!action) return null;
    if (action.kind === "revert") return revMode === "revert" ? "telecalling" : revMode === "cancellation_request" ? "admin" : null;
    if (action.kind === "cancel") return "admin";
    if (action.kind === "review") return reviewDecision === "approved" ? null : reviewRoute === "forward_megha" ? "telecalling" : null;
    return null;
  }

  // Only Management ("Superadmin") can approve / reject a cancellation request.
  const [role, setRole] = useState<string | null>(null);
  useEffect(() => {
    setRole(getClientSession()?.role ?? null);
  }, []);
  const readOnly = isReadOnlyRole(role);   // "View Only" workspace: look, don't touch
  // Accounts / Management change a centre directly and approve requests; everyone else can only request.
  const isFinance = role === "accounts" || role === "management" || role === "superadmin";
  // Accounts only REVIEWS requests; Management / Superadmin may also change a centre directly.
  const canChangeDirect = role === "management" || role === "superadmin";
  const canApprove = !readOnly && (!role || role === "management" || role === "superadmin");

  function openAction(kind: ActionKind, p: AgingPatient) {
    setMenu(null);
    setActError("");
    setActRemarks("");
    if (kind === "status") {
      setStMode(p.statusOverride === "Likely Cancellation" ? "likely_cancellation" : "auto");
      setStReason(p.statusReason || "");
    } else if (kind === "likely") {
      setStMode("likely_cancellation");
      setStReason(p.statusOverride === "Likely Cancellation" ? p.statusReason || "" : "");
    } else if (kind === "centre") {
      setToCentre("");
      setCentreReason("");
    } else if (kind === "centre_review") {
      setCcDecision("approved");
      setCcCentre(p.centreChangeTo || "");
    } else if (kind === "revert") {
      setRevMode("revert");
      setStReason("");
    } else if (kind === "cancel") {
      setStReason("");
    } else {
      setReviewDecision("approved");
      setReviewRoute("forward_megha");
      setRefundAmt(String(paymentsTotal(p) || 0));
    }
    setAction({ kind, p });
  }

  async function submitAction() {
    if (!action) return;
    const { kind, p } = action;
    let payload: Record<string, unknown> = {
      kind,
      invoice: p.invoice,
      patient_id: p.id,
      remarks: actRemarks.trim(),
      user: sessionUser(),
      requester_email: sessionEmail(),
      origin: typeof window !== "undefined" ? window.location.origin : "",
      role: role || "",
      // context for the notification email
      ctx: { name: p.name, centre: p.salesCentre || p.centre, pkg: p.pkg, outstanding: p.outstanding ?? p.incGst - paymentsTotal(p), collected: paymentsTotal(p) },
    };

    if (kind === "likely") {
      if (!stReason) {
        setActError("Select a cancellation reason.");
        return;
      }
      payload = { ...payload, kind: "status", mode: "likely_cancellation", reason: stReason };
    } else if (kind === "status") {
      const needsReason = stMode === "likely_cancellation" || p.autoStatus === "Delayed";
      if (needsReason && !stReason) {
        setActError(stMode === "likely_cancellation" ? "Select a cancellation reason." : "Select a delay reason.");
        return;
      }
      payload = { ...payload, kind: "status", mode: stMode, reason: needsReason ? stReason : "" };
    } else if (kind === "centre") {
      if (!toCentre) return setActError("Select the new centre.");
      if (!centreReason.trim()) return setActError("Reason is required.");
      payload = { ...payload, kind: "change_centre", from_centre: p.salesCentre || p.centre, to_centre: toCentre, reason: centreReason.trim() };
    } else if (kind === "centre_review") {
      payload = { ...payload, kind: "review_centre_change", decision: ccDecision, to_centre: ccDecision === "approved" ? ccCentre : "" };
    } else if (kind === "revert") {
      payload = { ...payload, kind: "revert_tele", mode: "revert", reason: "" };
    } else if (kind === "cancel") {
      if (!stReason) return setActError("Select a cancellation reason.");
      payload = { ...payload, kind: "direct_cancellation", reason: stReason };
    } else if (kind === "review") {
      if (reviewDecision === "approved") {
        const amt = Number(refundAmt);
        if (refundAmt.trim() === "" || !Number.isFinite(amt) || amt < 0) return setActError("Enter a valid refund amount.");
        payload = { ...payload, kind: "review_cancellation", decision: "approved", refund_amount: amt };
      } else {
        if (reviewRoute === "return_centre" && !actRemarks.trim()) return setActError("A comment is required when returning to the centre.");
        payload = { ...payload, kind: "review_cancellation", decision: "not_approved", route: reviewRoute };
      }
    }

    setActSaving(true);
    setActError("");
    try {
      const res = await fetch("/api/aging-action", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok || data.status !== "success") throw new Error(data.message || "Failed to save.");
      const logLine: string = data.logLine || "";
      if (data.mail) {
        const m = data.mail;
        if (m.sent > 0 && m.mode === "console")
          setNotice({ tone: "warn", text: "Saved, but the mail was NOT delivered — SMTP settings are not saved yet. Open “✉ Notification emails” and fill in the SMTP settings." });
        else if (m.sent > 0) setNotice({ tone: "ok", text: `✉ Mail sent to ${m.sent} recipient${m.sent === 1 ? "" : "s"}.` });
        else if (m.error) setNotice({ tone: "warn", text: `Saved, but the mail could not be sent: ${m.error}` });
        else setNotice({ tone: "warn", text: `Saved. ${m.note || "No recipient email configured."} Add one under “✉ Notification emails”.` });
      }

      setLocalPatients((list) =>
        list.map((x) => {
          if (x.invoice !== p.invoice) return x;
          // newest entry on top
          const history = logLine ? (x.history ? `${logLine}\n${x.history}` : logLine) : x.history;
          if (kind === "status" || kind === "likely") {
            return {
              ...x,
              history,
              statusOverride: kind === "likely" || stMode === "likely_cancellation" ? "Likely Cancellation" : "",
              statusReason: String(payload.reason || ""),
            };
          }
          if (kind === "centre" || kind === "centre_review") {
            const cc = data.centreChange as { state?: string; to?: string; reason?: string; by?: string } | undefined;
            return {
              ...x,
              history,
              salesCentre: data.salesCentre ? String(data.salesCentre) : x.salesCentre,
              centreChangeState: cc?.state || "",
              centreChangeTo: cc?.to || "",
              centreChangeReason: cc?.reason || "",
              centreChangeBy: cc?.by || "",
            };
          }
          if (kind === "revert" || kind === "cancel" || kind === "review") {
            return {
              ...x,
              history,
              lastFu: TODAY,
              status: data.followStatus ?? x.status,
              referred: data.referredToday ? TODAY : x.referred,
              requestState: data.requestState || "",
              requestReason: data.requestReason || "",
              refundAmount: data.refundAmount ?? null,
              requestComment: data.requestComment || "",
            };
          }
          return { ...x, history, lastFu: TODAY };
        })
      );
      setAction(null);
    } catch (err) {
      setActError(err instanceof Error ? err.message : "Failed to save.");
    } finally {
      setActSaving(false);
    }
  }

  function openFollowUp(p: AgingPatient) {
    setFollowUpTarget(p);
    // Follow-up popup only switches a case between Active and On Hold; any other (workflow) status is kept.
    // (p.status can also be the procedure status when no follow-up exists yet -> treated as Active.)
    setFollowUpStatus(p.status === "On Hold" ? "On Hold" : isWorkflowStatus(p.status) ? "" : "Active");
    setFollowUpRemarks("");
    setFollowUpDate("");
    setSaveError("");
  }

  async function submitFollowUp() {
    if (!followUpTarget) return;
    if (!followUpRemarks.trim()) {
      setSaveError("Please write the follow-up remarks.");
      return;
    }
    if (!followUpDate) {
      setSaveError("Select the next follow-up date.");
      return;
    }
    setSaving(true);
    setSaveError("");
    try {
      const res = await fetch("/api/aging-followup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          invoice: followUpTarget.invoice,
          patient_id: followUpTarget.id,
          status: followUpStatus || followUpTarget.status || "Active",
          set_status: followUpStatus === "Active" || followUpStatus === "On Hold",
          followup_only: true,
          remarks: followUpRemarks.trim(),
          next_date: followUpDate,
          user: sessionUser(),
        }),
      });
      const data = await res.json();
      if (!res.ok || data.status !== "success") throw new Error(data.message || "Failed to save follow-up.");

      const logLine: string = data.logLine || "";

      setLocalPatients((list) =>
        list.map((p) =>
          p.invoice === followUpTarget.invoice
            ? {
                ...p,
                lastFu: TODAY,
                nextFu: followUpDate,
                status: followUpStatus === "Active" || followUpStatus === "On Hold" ? followUpStatus : p.status,
                fuOverdue: false,
                fuOverdueDays: 0,
                history: logLine ? (p.history ? `${logLine}\n${p.history}` : logLine) : p.history,
              }
            : p
        )
      );
      setFollowUpTarget(null);
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : "Failed to save follow-up.");
    } finally {
      setSaving(false);
    }
  }

  const bucketCounts = useMemo(() => [0, 1, 2, 3, 4].map((i) => localPatients.filter((p) => bucketIdx(p.daysOverdue) === i).length), [localPatients]);

  const filtered = useMemo(() => {
    let list = localPatients.slice();
    if (bucket !== null) list = list.filter((p) => bucketIdx(p.daysOverdue) === bucket);
    list = list.filter((p) => inDateRange(p.lastFu, range, single, from, to));
    const q = search.trim().toLowerCase();
    if (q)
      list = list.filter((p) =>
        [p.name, p.id, p.invoice].some((v) => String(v ?? "").toLowerCase().includes(q))
      );
    return list;
  }, [localPatients, bucket, range, single, from, to, search]);

  useEffect(() => {
    setPage(1);
  }, [bucket, range, single, from, to, search, pageSize]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageStart = (safePage - 1) * pageSize;
  const paged = filtered.slice(pageStart, pageStart + pageSize);

  // Poll for new comments. The first response only sets the baseline, so old comments are not "new".
  useEffect(() => {
    let stop = false;
    async function poll() {
      try {
        const res = await fetch("/api/aging-notifications", { cache: "no-store" });
        const d = await res.json();
        if (stop || !res.ok || d.status !== "success") return;
        const items: Notif[] = d.items || [];
        setNotifs(items);
        const prev = lastNotifId.current;
        if (prev === null) {
          lastNotifId.current = d.maxId || 0;
        } else if ((d.maxId || 0) > prev) {
          const fresh = items.filter((n) => n.id > prev);
          lastNotifId.current = d.maxId;
          setUnread((u) => u + fresh.length);
          if (fresh.length) setToast(fresh[0]);
        }
      } catch {
        /* ignore - try again on the next tick */
      }
    }
    poll();
    const t = setInterval(poll, 15000);
    return () => {
      stop = true;
      clearInterval(t);
    };
  }, []);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 9000);
    return () => clearTimeout(t);
  }, [toast]);

  // Jump to a patient's comment: clear filters if needed, open the right page, scroll to the history cell.
  useEffect(() => {
    if (!pendingJump) return;
    const idx = filtered.findIndex((p) => p.invoice === pendingJump);
    const noFilters = bucket === null && !search && range === "all";
    if (idx < 0) {
      if (noFilters) {
        setPendingJump(null);
        setNotice({ tone: "warn", text: "This patient is not in the Aging list (the comment was saved)." });
      }
      return;
    }
    const target = Math.floor(idx / pageSize) + 1;
    if (target !== safePage) {
      setPage(target);
      return;
    }
    const row = document.getElementById(`aging-row-${pendingJump}`);
    if (!row) return;
    row.scrollIntoView({ behavior: "smooth", block: "center" });
    (row.querySelector("[data-history]") as HTMLElement | null)?.scrollIntoView({ behavior: "smooth", block: "center", inline: "center" });
    setHighlight(pendingJump);
    setPendingJump(null);
    setTimeout(() => setHighlight(null), 5000);
  }, [pendingJump, filtered, safePage, pageSize, bucket, search, range]);

  function goToComment(n: Notif) {
    setNotifOpen(false);
    setToast(null);
    setBucket(null);
    setSearch("");
    setRange("all");
    setPendingJump(n.invoice);
  }

  function notifName(n: Notif): string {
    const p = localPatients.find((x) => x.invoice === n.invoice);
    return p?.name || n.patientId || n.invoice;
  }

  return (
    <div className="rounded-[14px] border border-border bg-surface shadow-card">
      <div className="flex flex-wrap items-center gap-2.5 border-b border-border-soft px-[18px] py-[15px]">
        <div className="text-sm font-semibold text-primary-dark">
          Aging Patient List <span className="font-normal text-text-soft">· {filtered.length} record{filtered.length !== 1 ? "s" : ""}</span>
        </div>
        <div className="ml-auto flex flex-wrap items-center gap-2">
          <div className="relative">
            <button
              type="button"
              onClick={() => {
                setNotifOpen((o) => !o);
                setUnread(0);
              }}
              className="relative rounded-[8px] border border-border px-2.5 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary"
              title="New comments"
            >
              🔔 Comments
              {unread > 0 && (
                <span className="absolute -right-1.5 -top-1.5 min-w-[17px] rounded-full bg-red px-1 text-center text-[10px] font-bold leading-[17px] text-white">
                  {unread}
                </span>
              )}
            </button>
            {notifOpen && (
              <div className="absolute right-0 z-40 mt-1.5 max-h-[360px] w-[360px] overflow-y-auto rounded-[10px] border border-border bg-surface p-1.5 shadow-card">
                {notifs.length === 0 && <div className="px-3 py-4 text-center text-[11.5px] text-text-soft">No comments yet.</div>}
                {notifs.map((n) => (
                  <button
                    key={n.id}
                    type="button"
                    onClick={() => goToComment(n)}
                    className="block w-full rounded-[8px] px-3 py-2 text-left hover:bg-surface-2"
                  >
                    <div className="flex items-center gap-2 text-[11.5px] font-semibold text-primary-dark">
                      <span className="truncate">{notifName(n)}</span>
                      <span className="ml-auto shrink-0 text-[10.5px] font-normal text-text-soft">{n.at ? new Date(n.at).toLocaleString() : ""}</span>
                    </div>
                    <div className="mt-0.5 line-clamp-2 text-[11.5px] leading-snug text-text-mid">{n.text}</div>
                  </button>
                ))}
              </div>
            )}
          </div>
          {!readOnly && (
          <button
            type="button"
            onClick={openMailSettings}
            className="rounded-[8px] border border-border px-2.5 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary"
            title="Add / remove the Admin and Telecalling notification emails"
          >
            ✉ Notification emails
          </button>
          )}
          <input
            type="search"
            placeholder="Search name or IIC ID…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-[220px] rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px] outline-none focus:border-primary"
          />
          <select value={range} onChange={(e) => setRange(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2.5 py-1.5 text-[11.5px]">
            <option value="all">All time</option>
            <option value="date">Specific date</option>
            <option value="day">Today</option>
            <option value="week">This week</option>
            <option value="month">This month</option>
            <option value="year">This year</option>
            <option value="custom">Custom range</option>
          </select>
          {range === "date" && (
            <input type="date" value={single} onChange={(e) => setSingle(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
          )}
          {range === "custom" && (
            <span className="flex items-center gap-1.5 text-[11.5px] text-text-soft">
              <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
              <span>to</span>
              <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]" />
            </span>
          )}
        </div>
      </div>

      {toast && (
        <button
          type="button"
          onClick={() => goToComment(toast)}
          className="fixed bottom-5 right-5 z-50 w-[340px] rounded-[12px] border border-primary bg-surface p-3 text-left shadow-card"
        >
          <div className="flex items-center gap-2 text-[11.5px] font-semibold text-primary-dark">
            🔔 New comment · {notifName(toast)}
            <span
              role="button"
              onClick={(e) => {
                e.stopPropagation();
                setToast(null);
              }}
              className="ml-auto text-text-soft hover:text-text"
            >
              ✕
            </span>
          </div>
          <div className="mt-1 line-clamp-3 text-[11.5px] leading-snug text-text-mid">{toast.text}</div>
          <div className="mt-1 text-[10.5px] text-primary">Click to open the comment →</div>
        </button>
      )}

      {notice && (
        <div
          className={`mx-[18px] mt-3 flex items-center gap-2 rounded-[8px] px-3 py-2 text-[11.5px] font-semibold ${
            notice.tone === "ok" ? "bg-green-soft text-green" : "bg-amber-100 text-amber-800"
          }`}
        >
          <span className="flex-1">{notice.text}</span>
          <button type="button" onClick={() => setNotice(null)} className="opacity-70 hover:opacity-100">
            ✕
          </button>
        </div>
      )}

      <div className="px-[18px] pb-[14px] pt-[15px]">
        <div className="mb-2 text-[11px] font-semibold uppercase tracking-[.04em] text-text-soft">Filter by aging bucket</div>
        <div className="flex flex-wrap gap-1.5">
          <button
            type="button"
            onClick={() => setBucket(null)}
            className={`rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition-colors ${bucket === null ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"}`}
          >
            All <span className="ml-1 text-text-soft">{localPatients.length}</span>
          </button>
          {BUCKET_LABELS.map((l, i) => (
            <button
              key={l}
              type="button"
              onClick={() => setBucket(i)}
              className={`rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition-colors ${bucket === i ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"}`}
            >
              {l} <span className="ml-1 text-text-soft">{bucketCounts[i]}</span>
            </button>
          ))}
        </div>
      </div>

      {/* The table scrolls inside this box (both ways), so the header row can stay pinned at the
          top while rows scroll. Tailwind utilities beat the .tbl component styles, so the th rules
          below override the plain header cells; the Date / IIC ID / Patient Name headers keep a
          higher z-index so they stay on top of the other headers at the top-left corner. */}
      <div className="scroll-thin max-h-[calc(100vh-150px)] overflow-auto border-t border-border-soft [&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-[3] [&_thead_th.sticky-l]:z-[5] [&_thead_th.sticky-l2]:z-[5] [&_thead_th.sticky-l3]:z-[5]">
        <table className="tbl">
          <thead>
            <tr>
              <th className="sticky-l" style={{ width: 110, minWidth: 110, maxWidth: 110 }}>Date</th>
              <th className="sticky-l2" style={{ width: 150, minWidth: 150, maxWidth: 150 }}>IIC ID</th>
              <th className="sticky-l3" style={{ width: 200, minWidth: 200, maxWidth: 200 }}>Patient Name</th>
              <th>Pkg Month</th>
              <th>Pkg Booking Yr</th>
              <th>FY</th>
              <th>Pkg Code</th>
              <th>Pkg Description</th>
              <th>
                <button
                  type="button"
                  onClick={() => setCentresOpen((o) => !o)}
                  title={centresOpen ? "Collapse centre columns" : "Expand centre columns"}
                  className="inline-flex items-center gap-1.5 font-[inherit] uppercase tracking-[inherit] text-inherit hover:text-primary"
                >
                  <span className="flex h-4 w-4 items-center justify-center rounded border border-border bg-surface text-[10px] leading-none">
                    {centresOpen ? "‹" : "›"}
                  </span>
                  {centresOpen ? "Booking Centre" : "Centre"}
                </button>
              </th>
              {centresOpen && <th>Sales Reporting Centre</th>}
              {centresOpen && <th>Billing Centre</th>}
              <th>Document Type</th>
              <th>Invoice No</th>
              <th className="col-num">Gross Revenue Pkg</th>
              <th className="col-num">Discount %</th>
              <th className="col-num">Discount</th>
              <th className="col-num">Booked Pkg Inc GST</th>
              <th className="col-num">GST</th>
              <th className="col-num">Booked Pkg Ex GST</th>
              {FY_GROUPS.map((g, gi) =>
                fyOpen[gi] ? (
                  g.labels.map((l, li) => (
                    <th className="col-num" key={`${g.label}-${l}`}>
                      {li === 0 ? (
                        <button
                          type="button"
                          onClick={() => toggleFy(gi)}
                          title={`Collapse ${g.label}`}
                          className="inline-flex items-center gap-1.5 font-[inherit] uppercase tracking-[inherit] text-inherit hover:text-primary"
                        >
                          <span className="flex h-4 w-4 items-center justify-center rounded border border-border bg-surface text-[10px] leading-none">‹</span>
                          {l}
                        </button>
                      ) : (
                        l
                      )}
                    </th>
                  ))
                ) : (
                  <th className="col-num" key={g.label}>
                    <button
                      type="button"
                      onClick={() => toggleFy(gi)}
                      title={`Expand ${g.label} months`}
                      className="inline-flex items-center gap-1.5 font-[inherit] uppercase tracking-[inherit] text-inherit hover:text-primary"
                    >
                      <span className="flex h-4 w-4 items-center justify-center rounded border border-border bg-surface text-[10px] leading-none">›</span>
                      {g.label}
                    </button>
                  </th>
                )
              )}
              <th className="col-num">Total Collection</th>
              <th className="col-num">Balance FY 26-27</th>
              <th className="col-num">Total Outstanding</th>
              <th>Status</th>
              <th>Request Status</th>
              <th>Follow-up Status</th>
              <th>Date Referred to Tele</th>
              <th>Last Update Date</th>
              <th>Next Follow-up Date</th>
              <th>Complete History Log</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            {filtered.length === 0 && (
              <tr>
                <td colSpan={27 + (centresOpen ? 3 : 1) + fyColCount} className="text-center text-text-soft">
                  No records match the current filters.
                </td>
              </tr>
            )}
            {paged.map((p, i) => {
              const [exGst, gst] = patientGstSplit(p);
              const totalColl = paymentsTotal(p);
              const totalOut = p.outstanding ?? p.incGst - totalColl;
              const discAmt = pkgDisc(p);
              const inFy2627 = p.signup >= "2026-04-01";
              const balFy2627 = inFy2627 ? totalOut : 0;
              return (
                <tr
                  key={`${p.id}-${p.invoice || i}`}
                  id={`aging-row-${p.invoice}`}
                  className={highlight === p.invoice ? "[&>td]:!bg-amber-100" : undefined}
                >
                  <td className="sticky-l" style={{ width: 110, minWidth: 110, maxWidth: 110 }}>{fmtDate(p.signup)}</td>
                  <td className="sticky-l2" style={{ width: 150, minWidth: 150, maxWidth: 150 }}>
                    <Link href={`/journey?id=${p.id}`} className="iic-link">
                      {p.id}
                    </Link>
                  </td>
                  <td
                    className="sticky-l3 strong"
                    style={{ width: 200, minWidth: 200, maxWidth: 200, overflow: "hidden", textOverflow: "ellipsis" }}
                    title={p.name}
                  >
                    {p.name}
                  </td>
                  <td>{pkgMonthLabel(p.signup)}</td>
                  <td>{p.signup.slice(0, 4)}</td>
                  <td>{pkgFY(p.signup)}</td>
                  <td>{p.pkg}</td>
                  <td>{p.desc}</td>
                  <td>
                    {p.centre}
                    {p.centreChangeState === "Requested" && (
                      <span className="mt-0.5 block text-[10.5px] font-semibold text-amber-700">⏳ → {p.centreChangeTo} (awaiting Accounts)</span>
                    )}
                    {p.centreChangeState === "Rejected" && (
                      <span className="mt-0.5 block text-[10.5px] font-semibold text-red">Centre change not approved</span>
                    )}
                  </td>
                  {centresOpen && <td>{p.salesCentre || p.centre}</td>}
                  {centresOpen && <td>{p.centre}</td>}
                  <td>Tax Invoice</td>
                  <td>{p.invoice}</td>
                  <td className="col-num">{fmtINR(p.gross)}</td>
                  <td className="col-num">{p.discPct}%</td>
                  <td className="col-num">{fmtINR(discAmt)}</td>
                  <td className="col-num">{fmtINR(p.incGst)}</td>
                  <td className="col-num">{fmtINR(gst)}</td>
                  <td className="col-num">{fmtINR(exGst)}</td>
                  {FY_GROUPS.map((g, gi) =>
                    fyOpen[gi] ? (
                      g.months.map((ym) => {
                        const amt = paymentInMonth(p, ym);
                        return (
                          <td className="col-num" key={ym}>
                            {amt ? fmtINR(amt) : "·"}
                          </td>
                        );
                      })
                    ) : (
                      (() => {
                        const sum = g.months.reduce((t, ym) => t + paymentInMonth(p, ym), 0);
                        return (
                          <td className="col-num" key={g.label}>
                            {sum ? fmtINR(sum) : "·"}
                          </td>
                        );
                      })()
                    )
                  )}
                  <td className="col-num strong">{fmtINR(totalColl)}</td>
                  <td className="col-num">{balFy2627 ? fmtINR(balFy2627) : "·"}</td>
                  <td className="col-num strong" style={{ color: "var(--color-red)" }}>
                    {fmtINR(totalOut)}
                  </td>
                  <td>
                    {(() => {
                      const st = effectiveStatus(p);
                      if (!st) return <span className="text-text-soft">—</span>;
                      return (
                        <button
                          type="button"
                          onClick={() => openAction("status", p)}
                          disabled={readOnly}
                          title={readOnly ? undefined : "Click to set the reason / mark Likely Cancellation"}
                          className={`text-left ${readOnly ? "cursor-default" : ""}`}
                        >
                          <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase ${statusBadgeClass(st)}`}>
                            {st}
                            {!readOnly && <span className="text-[9px] opacity-60">✎</span>}
                          </span>
                          {p.statusReason && (st === "Likely Cancellation" || st === "Delayed") && (
                            <span className="mt-0.5 block text-[10.5px] font-semibold normal-case text-text-soft">{p.statusReason}</span>
                          )}
                          {st === "Delayed" && !p.statusReason && (
                            <span className="mt-0.5 block text-[10.5px] font-semibold normal-case text-red">Reason pending</span>
                          )}
                        </button>
                      );
                    })()}
                  </td>
                  <td>
                    {p.requestState ? (
                      <div title={p.requestComment || ""}>
                        <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase ${requestBadgeClass(p.requestState)}`}>
                          {p.requestState.replace(" (Megha)", "")}
                        </span>
                        {p.requestReason && (
                          <span className="mt-0.5 block text-[10.5px] font-semibold text-text-soft">{p.requestReason}</span>
                        )}
                        {p.requestState === "Cancellation Approved" && p.refundAmount != null && (
                          <span className="mt-0.5 block text-[10.5px] font-semibold text-red">Refund {fmtINR(p.refundAmount)}</span>
                        )}
                        {p.requestState === "Cancellation Requested" && canApprove && (
                          <button
                            type="button"
                            onClick={() => openAction("review", p)}
                            className="mt-1 rounded-[6px] border border-primary px-2 py-0.5 text-[10.5px] font-semibold text-primary hover:bg-primary-soft"
                          >
                            Review
                          </button>
                        )}
                      </div>
                    ) : (
                      <span className="text-text-soft">—</span>
                    )}
                  </td>
                  <td>{p.status}</td>
                  <td>{fmtDate(p.referred)}</td>
                  <td>{fmtDate(p.lastFu)}</td>
                  <td>
                    {p.nextFu ? (
                      <div className="flex flex-col items-start gap-1">
                        <span>{fmtDate(p.nextFu)}</span>
                        {p.fuOverdue && (
                          <span className="rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-[10.5px] font-bold text-red-700">
                            Overdue{p.fuOverdueDays ? ` · ${p.fuOverdueDays}d` : ""}
                          </span>
                        )}
                      </div>
                    ) : (
                      <span className="text-text-soft">—</span>
                    )}
                  </td>
                  <td data-history className="min-w-[300px] max-w-[360px] whitespace-normal">
                    {p.history ? (
                      <div className="max-h-40 space-y-1.5 overflow-y-auto text-[11.5px] leading-snug">
                        {p.history.split("\n").filter(Boolean).map((line, i) => (
                          <div key={i}>{line}</div>
                        ))}
                      </div>
                    ) : (
                      <span className="text-text-soft">No updates yet</span>
                    )}
                  </td>
                  <td>
                    {readOnly ? (
                      <span className="text-text-soft">—</span>
                    ) : (
                    <div className="flex items-center gap-1.5">
                      <button
                        type="button"
                        onClick={(e) => {
                          const r = e.currentTarget.getBoundingClientRect();
                          setMenu({ p, right: Math.max(8, window.innerWidth - r.right), top: r.bottom + 4 });
                        }}
                        className="rounded-[8px] bg-primary px-2.5 py-1 text-[11px] font-semibold text-white transition-colors hover:bg-primary-dark"
                      >
                        Action ▾
                      </button>
                      <button
                        type="button"
                        onClick={() => openFollowUp(p)}
                        className="rounded-[8px] border border-primary px-2.5 py-1 text-[11px] font-semibold text-primary transition-colors hover:bg-primary-soft"
                      >
                        Follow-up
                      </button>
                    </div>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <div className="flex flex-wrap items-center gap-2.5 border-t border-border-soft px-[18px] py-[12px] text-[11.5px]">
        <span className="text-text-soft">
          {filtered.length === 0
            ? "0 records"
            : `Showing ${pageStart + 1}–${Math.min(pageStart + pageSize, filtered.length)} of ${filtered.length}`}
        </span>
        <select
          value={pageSize}
          onChange={(e) => setPageSize(Number(e.target.value))}
          className="rounded-[8px] border border-border bg-surface px-2 py-1.5 text-[11.5px]"
        >
          <option value={25}>25 / page</option>
          <option value={50}>50 / page</option>
          <option value={100}>100 / page</option>
          <option value={250}>250 / page</option>
        </select>
        <div className="ml-auto flex items-center gap-1.5">
          <button
            type="button"
            onClick={() => setPage(1)}
            disabled={safePage <= 1}
            className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
          >
            « First
          </button>
          <button
            type="button"
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={safePage <= 1}
            className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
          >
            ‹ Prev
          </button>
          <span className="px-1.5 text-text-soft">
            Page {safePage} of {totalPages}
          </span>
          <button
            type="button"
            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
            disabled={safePage >= totalPages}
            className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
          >
            Next ›
          </button>
          <button
            type="button"
            onClick={() => setPage(totalPages)}
            disabled={safePage >= totalPages}
            className="rounded-[8px] border border-border px-2.5 py-1.5 font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
          >
            Last »
          </button>
        </div>
      </div>

      {followUpTarget && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4" onClick={() => !saving && setFollowUpTarget(null)}>
          <div className="w-full max-w-md rounded-[14px] bg-surface p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-1 text-sm font-semibold text-primary-dark">Follow-up · {followUpTarget.name}</div>
            <div className="mb-4 text-[11.5px] text-text-soft">
              {followUpTarget.id} · Invoice {followUpTarget.invoice || "—"}
            </div>

            <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Status</label>
            <select
              value={followUpStatus}
              onChange={(e) => setFollowUpStatus(e.target.value)}
              className="mb-3 w-full rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
            >
              {isWorkflowStatus(followUpTarget.status) && (
                <option value="">Keep current ({followUpTarget.status})</option>
              )}
              <option value="Active">Active</option>
              <option value="On Hold">On Hold</option>
            </select>

            <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Next follow-up date</label>
            <input
              type="date"
              value={followUpDate}
              min={TODAY}
              onChange={(e) => setFollowUpDate(e.target.value)}
              className="mb-3 w-full rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
            />
            <div className="-mt-2 mb-3 text-[11px] text-text-soft">If nothing happens on this patient by this date, it shows as Overdue in the list.</div>

            <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Remarks</label>
            <textarea
              value={followUpRemarks}
              onChange={(e) => setFollowUpRemarks(e.target.value)}
              rows={3}
              placeholder="Add the follow-up — call details, patient response, next step…"
              className="mb-3 w-full resize-none rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
            />

            {followUpTarget.history && (
              <div className="mb-3 max-h-28 overflow-y-auto rounded-[8px] bg-surface-2 p-2.5 text-[11px] leading-relaxed text-text-soft whitespace-pre-line">
                {followUpTarget.history}
              </div>
            )}

            {saveError && <div className="mb-3 rounded-[8px] bg-red-soft px-3 py-2 text-[11.5px] font-semibold text-red">{saveError}</div>}

            <div className="flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={() => setFollowUpTarget(null)}
                disabled={saving}
                className="rounded-[8px] border border-border px-3 py-1.5 text-[12px] font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={submitFollowUp}
                disabled={saving}
                className="rounded-[8px] bg-primary px-3.5 py-1.5 text-[12px] font-semibold text-white transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60"
              >
                {saving ? "Saving…" : "Save Follow-up"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Action dropdown — fixed-position so the scrolling table box can't clip it */}
      {menu && (
        <div className="fixed inset-0 z-[90]" onClick={() => setMenu(null)}>
          <div
            className="absolute w-64 overflow-hidden rounded-[10px] border border-border bg-surface py-1 shadow-xl"
            style={{ top: menu.top, right: menu.right }}
            onClick={(e) => e.stopPropagation()}
          >
            {[
              ...(menu.p.centreChangeState === "Requested" && isFinance
                ? [{ label: "✅ Review centre change request", onClick: () => openAction("centre_review", menu.p) }]
                : []),
              ...(menu.p.centreChangeState === "Requested" && !isFinance
                ? [{ label: "⏳ Centre change awaiting Accounts", onClick: () => setMenu(null) }]
                : []),
              ...(canChangeDirect
                ? [{ label: "🏢 Change Centre", onClick: () => openAction("centre", menu.p) }]
                : []),
              ...(!isFinance && menu.p.centreChangeState !== "Requested"
                ? [{ label: "🏢 Request Centre Change", onClick: () => openAction("centre", menu.p) }]
                : []),
              { label: "⚠️ Likely Cancellation", onClick: () => openAction("likely", menu.p) },
              { label: "📞 Revert to Telecalling Team", onClick: () => openAction("revert", menu.p) },
              { label: "🛑 Direct cancellation request", onClick: () => openAction("cancel", menu.p) },
              ...(menu.p.requestState === "Cancellation Requested" && canApprove
                ? [{ label: "✅ Review cancellation (Superadmin)", onClick: () => openAction("review", menu.p) }]
                : []),
            ].map((it) => (
              <button
                key={it.label}
                type="button"
                onClick={it.onClick}
                className="block w-full px-3.5 py-2 text-left text-[12.5px] font-medium text-text-mid hover:bg-surface-2 hover:text-primary"
              >
                {it.label}
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Status / Change Centre / Payment received popups */}
      {action && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4" onClick={() => !actSaving && setAction(null)}>
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[14px] bg-surface p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-1 text-sm font-semibold text-primary-dark">
              {action.kind === "status"
                ? "Update Status"
                : action.kind === "likely"
                ? "Likely Cancellation (manual)"
                : action.kind === "centre"
                ? isFinance
                  ? "Change Centre (Sales Reporting)"
                  : "Request Centre Change (Sales Reporting)"
                : action.kind === "centre_review"
                ? "Centre Change Request — Accounts approval"
                : action.kind === "revert"
                ? "Revert to Telecalling Team"
                : action.kind === "cancel"
                ? "Direct Cancellation Request"
                : "Cancellation Review (Superadmin)"}{" "}
              · {action.p.name}
            </div>
            <div className="mb-4 text-[11.5px] text-text-soft">
              {action.p.id} · Invoice {action.p.invoice || "—"}
            </div>

            {action.kind === "status" && (
              <>
                <div className="mb-3 flex items-center gap-2 text-[12px] text-text-mid">
                  Auto status:
                  <span className={`rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase ${statusBadgeClass(action.p.autoStatus || "")}`}>
                    {action.p.autoStatus || "No journey data"}
                  </span>
                </div>

                <div className="mb-3 space-y-2">
                  {(
                    [
                      ["auto", `Keep auto status${action.p.autoStatus ? ` (${action.p.autoStatus})` : ""}`],
                      ["likely_cancellation", "Mark as Likely Cancellation (manual)"],
                    ] as const
                  ).map(([val, label]) => (
                    <label
                      key={val}
                      className={`flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-[12.5px] font-semibold ${
                        stMode === val ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid"
                      }`}
                    >
                      <input
                        type="radio"
                        name="st-mode"
                        checked={stMode === val}
                        onChange={() => {
                          setStMode(val);
                          setStReason("");
                        }}
                      />
                      {label}
                    </label>
                  ))}
                </div>

                {(stMode === "likely_cancellation" || action.p.autoStatus === "Delayed") && (
                  <>
                    <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">
                      {stMode === "likely_cancellation" ? "Cancellation reason" : "Delay reason"}
                    </label>
                    <div className="mb-3 flex flex-wrap gap-1.5">
                      {(stMode === "likely_cancellation" ? CANCEL_REASONS : DELAY_REASONS).map((r) => (
                        <button
                          key={r}
                          type="button"
                          onClick={() => setStReason(r)}
                          className={`rounded-full border px-3 py-1 text-[11.5px] font-semibold transition-colors ${
                            stReason === r ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"
                          }`}
                        >
                          {r}
                        </button>
                      ))}
                    </div>
                  </>
                )}
              </>
            )}

            {action.kind === "likely" && (
              <>
                <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Reason</label>
                <div className="mb-3 flex flex-wrap gap-1.5">
                  {CANCEL_REASONS.map((r) => (
                    <button
                      key={r}
                      type="button"
                      onClick={() => setStReason(r)}
                      className={`rounded-full border px-3 py-1 text-[11.5px] font-semibold transition-colors ${
                        stReason === r ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"
                      }`}
                    >
                      {r}
                    </button>
                  ))}
                </div>
                <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[11.5px] text-text-soft">
                  The patient's status will show “Likely Cancellation” (set manually).
                </div>
              </>
            )}

            {action.kind === "centre" && (
              <>
                <div className="mb-3 grid grid-cols-2 gap-3">
                  <div>
                    <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">From</label>
                    <div className="rounded-[8px] border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-mid">
                      {action.p.salesCentre || action.p.centre || "—"}
                    </div>
                  </div>
                  <div>
                    <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">To</label>
                    <select
                      value={toCentre}
                      onChange={(e) => setToCentre(e.target.value)}
                      className="w-full rounded-[8px] border border-border bg-surface px-2.5 py-2 text-[12.5px] outline-none focus:border-primary"
                    >
                      <option value="">Select centre…</option>
                      {Array.from(new Set([...allCentres, ...centres, ...localPatients.map((x) => x.centre)]))
                        .filter((c) => c && c !== (action.p.salesCentre || action.p.centre))
                        .sort()
                        .map((c) => (
                          <option key={c} value={c}>
                            {c}
                          </option>
                        ))}
                    </select>
                  </div>
                </div>
                <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Reason</label>
                <input
                  value={centreReason}
                  onChange={(e) => setCentreReason(e.target.value)}
                  placeholder="Why is the sales reporting centre being switched?"
                  className="mb-3 w-full rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
                />
                {!isFinance && (
                  <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[11.5px] text-text-soft">
                    This is a request — the centre changes only after the Accounts team approves it.
                  </div>
                )}
              </>
            )}

            {action.kind === "centre_review" && (
              <>
                <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[12px] leading-relaxed text-text-mid">
                  <div>
                    <b>{action.p.salesCentre || action.p.centre || "—"}</b> → <b>{action.p.centreChangeTo || "—"}</b>
                  </div>
                  <div>Reason: {action.p.centreChangeReason || "—"}</div>
                  {action.p.centreChangeBy && <div>Requested by: {action.p.centreChangeBy}</div>}
                </div>
                <div className="mb-3 grid grid-cols-2 gap-2">
                  {(
                    [
                      ["approved", "✅ Approve"],
                      ["rejected", "✖ Reject"],
                    ] as const
                  ).map(([val, label]) => (
                    <label
                      key={val}
                      className={`flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-[12.5px] font-semibold ${
                        ccDecision === val ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid"
                      }`}
                    >
                      <input type="radio" name="cc-decision" checked={ccDecision === val} onChange={() => setCcDecision(val)} />
                      {label}
                    </label>
                  ))}
                </div>
                {ccDecision === "approved" && (
                  <div className="mb-3">
                    <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Change to centre</label>
                    <select
                      value={ccCentre}
                      onChange={(e) => setCcCentre(e.target.value)}
                      className="w-full rounded-[8px] border border-border bg-surface px-2.5 py-2 text-[12.5px] outline-none focus:border-primary"
                    >
                      {Array.from(new Set([action.p.centreChangeTo || "", ...allCentres, ...centres, ...localPatients.map((x) => x.centre)]))
                        .filter((c) => c && c !== (action.p.salesCentre || action.p.centre))
                        .sort()
                        .map((c) => (
                          <option key={c} value={c}>
                            {c}
                          </option>
                        ))}
                    </select>
                    <div className="mt-1 text-[10.5px] text-text-soft">The requested centre is pre-selected — pick another one if needed.</div>
                  </div>
                )}
              </>
            )}

            {action.kind === "revert" && (
              <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[12px] leading-relaxed text-text-mid">
                This patient will be sent back to the Telecalling Team. The mail to the Telecalling Team has two buttons — Return to branch and Cancellation request. Add a remark below if needed.
              </div>
            )}

            {action.kind === "cancel" && (
              <>
                <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Cancellation reason</label>
                <div className="mb-3 flex flex-wrap gap-1.5">
                  {CANCEL_REASONS.map((r) => (
                    <button
                      key={r}
                      type="button"
                      onClick={() => setStReason(r)}
                      className={`rounded-full border px-3 py-1 text-[11.5px] font-semibold transition-colors ${
                        stReason === r ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"
                      }`}
                    >
                      {r}
                    </button>
                  ))}
                </div>
                <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[11.5px] leading-relaxed text-text-soft">
                  This goes to the Administrator for approval — the mail has <b>Approve / Disapprove</b> buttons. The Accountant is in cc, and you get a
                  confirmation mail now and another one when it is approved or disapproved.
                </div>
                {!sessionEmail() && (
                  <div className="mb-3 rounded-[8px] bg-amber-100 px-3 py-2 text-[11.5px] text-amber-800">
                    Your login has no email address, so you will not receive the confirmation mails.
                  </div>
                )}
                <RecipientEditor
                  title="Accountant (cc on request / approved / disapproved)"
                  list={recipients.accountant}
                  onChange={(email, op) => changeRecipient("accountant", email, op)}
                  canRemove={canChangeDirect}
                />
              </>
            )}

            {action.kind === "review" && (
              <>
                <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[11.5px] text-text-mid">
                  Requested reason: <b>{action.p.requestReason || "—"}</b> · Collected so far: <b>{fmtINR(paymentsTotal(action.p))}</b>
                </div>
                <div className="mb-3 grid grid-cols-2 gap-2">
                  {(
                    [
                      ["approved", "✅ Approved"],
                      ["not_approved", "✖ Not Approved"],
                    ] as const
                  ).map(([val, label]) => (
                    <label
                      key={val}
                      className={`flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-[12.5px] font-semibold ${
                        reviewDecision === val ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid"
                      }`}
                    >
                      <input type="radio" name="review-decision" checked={reviewDecision === val} onChange={() => setReviewDecision(val)} />
                      {label}
                    </label>
                  ))}
                </div>
                <div className="mb-3 rounded-[8px] bg-surface-2 px-3 py-2 text-[11.5px] text-text-soft">
                  The person who raised the request gets a mail, with the Accountant in cc.
                </div>
                {reviewDecision === "approved" ? (
                  <>
                    <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">Refund amount (₹) — as per refund matrix</label>
                    <input
                      value={refundAmt}
                      onChange={(e) => setRefundAmt(e.target.value)}
                      inputMode="decimal"
                      className="mb-3 w-full rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
                    />
                  </>
                ) : (
                  <div className="mb-3 space-y-2">
                    {(
                      [
                        ["forward_megha", "Forward to Megha"],
                        ["return_centre", "Return to centre with comment"],
                      ] as const
                    ).map(([val, label]) => (
                      <label
                        key={val}
                        className={`flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-[12.5px] font-semibold ${
                          reviewRoute === val ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid"
                        }`}
                      >
                        <input type="radio" name="review-route" checked={reviewRoute === val} onChange={() => setReviewRoute(val)} />
                        {label}
                      </label>
                    ))}
                  </div>
                )}
              </>
            )}

            {popupMailKind() && (
              <RecipientEditor
                title={popupMailKind() === "admin" ? "Mail goes to Admin" : "Mail goes to Telecalling Team"}
                list={recipients[popupMailKind() as "admin" | "telecalling"]}
                onChange={(email, op) => changeRecipient(popupMailKind() as "admin" | "telecalling", email, op)}
                canRemove={canChangeDirect}
              />
            )}

            <label className="mb-1 block text-[11.5px] font-semibold text-text-mid">
              {action.kind === "review" && reviewDecision === "not_approved" && reviewRoute === "return_centre" ? "Comment" : "Remarks"}{" "}
              {action.kind === "review" && reviewDecision === "not_approved" && reviewRoute === "return_centre" ? "(required)" : "(optional)"}
            </label>
            <textarea
              value={actRemarks}
              onChange={(e) => setActRemarks(e.target.value)}
              rows={2}
              className="mb-3 w-full resize-none rounded-[8px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
            />

            {actError && <div className="mb-3 rounded-[8px] bg-red-soft px-3 py-2 text-[11.5px] font-semibold text-red">{actError}</div>}

            <div className="flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={() => setAction(null)}
                disabled={actSaving}
                className="rounded-[8px] border border-border px-3 py-1.5 text-[12px] font-semibold text-text-mid hover:border-primary disabled:cursor-not-allowed disabled:opacity-40"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={submitAction}
                disabled={actSaving}
                className="rounded-[8px] bg-primary px-3.5 py-1.5 text-[12px] font-semibold text-white transition-colors hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60"
              >
                {actSaving ? "Saving…" : "Save"}
              </button>
            </div>
          </div>
        </div>
      )}

      {mailSettingsOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4" onClick={() => setMailSettingsOpen(false)}>
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[14px] bg-surface p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-1 text-sm font-semibold text-primary-dark">✉ Notification emails</div>
            <div className="mb-4 text-[11.5px] leading-relaxed text-text-soft">
              Admin gets the cancellation approval mails. The Telecalling Team gets the “Revert to Telecalling” mails. The Accountant is cc’d on every cancellation request and decision.
            </div>
            {mailMode === "console" && (
              <div className="mb-3 rounded-[8px] bg-amber-100 px-3 py-2 text-[11.5px] leading-relaxed text-amber-800">
                <b>Mail sending is OFF.</b> No SMTP settings are saved, so mails are only printed in the Django terminal and never reach an inbox.
                {canApprove ? " Fill in the SMTP settings below." : " Ask the Superadmin to add the SMTP settings."}
              </div>
            )}
            {mailMode === "smtp" && (
              <div className="mb-3 rounded-[8px] bg-green-soft px-3 py-2 text-[11.5px] text-green">
                SMTP is configured{fromEmail ? <> — mails go out from <b>{fromEmail}</b></> : null}.
              </div>
            )}
            {canApprove && (
              <div className="mb-4 rounded-[10px] border border-border p-3">
                <div className="mb-2 text-[12px] font-semibold text-primary-dark">SMTP / sender settings</div>
                <div className="grid grid-cols-3 gap-2">
                  <label className="col-span-2 text-[11px] text-text-soft">
                    SMTP host
                    <input
                      value={smtp.host}
                      onChange={(e) => setSmtp({ ...smtp, host: e.target.value })}
                      placeholder="smtp.gmail.com"
                      className="mt-0.5 w-full rounded-[7px] border border-border bg-surface px-2 py-1.5 text-[12px] text-text outline-none focus:border-primary"
                    />
                  </label>
                  <label className="text-[11px] text-text-soft">
                    Port
                    <input
                      value={smtp.port}
                      onChange={(e) => setSmtp({ ...smtp, port: e.target.value.replace(/\D/g, "") })}
                      placeholder="587"
                      className="mt-0.5 w-full rounded-[7px] border border-border bg-surface px-2 py-1.5 text-[12px] text-text outline-none focus:border-primary"
                    />
                  </label>
                  <label className="col-span-3 text-[11px] text-text-soft">
                    Login email (SMTP user)
                    <input
                      value={smtp.user}
                      onChange={(e) => setSmtp({ ...smtp, user: e.target.value })}
                      placeholder="youraddress@gmail.com"
                      autoComplete="off"
                      className="mt-0.5 w-full rounded-[7px] border border-border bg-surface px-2 py-1.5 text-[12px] text-text outline-none focus:border-primary"
                    />
                  </label>
                  <label className="col-span-3 text-[11px] text-text-soft">
                    Password / App password
                    <input
                      type="password"
                      value={smtp.password}
                      onChange={(e) => setSmtp({ ...smtp, password: e.target.value })}
                      placeholder={smtpPwSet ? "•••••••• saved — leave blank to keep" : "your-app-password"}
                      autoComplete="new-password"
                      className="mt-0.5 w-full rounded-[7px] border border-border bg-surface px-2 py-1.5 text-[12px] text-text outline-none focus:border-primary"
                    />
                  </label>
                  <label className="col-span-3 text-[11px] text-text-soft">
                    From email (shown as sender)
                    <input
                      value={smtp.fromEmail}
                      onChange={(e) => setSmtp({ ...smtp, fromEmail: e.target.value })}
                      placeholder="youraddress@gmail.com"
                      className="mt-0.5 w-full rounded-[7px] border border-border bg-surface px-2 py-1.5 text-[12px] text-text outline-none focus:border-primary"
                    />
                  </label>
                  <label className="col-span-3 flex items-center gap-2 text-[11.5px] text-text-soft">
                    <input type="checkbox" checked={smtp.useTls} onChange={(e) => setSmtp({ ...smtp, useTls: e.target.checked })} />
                    Use TLS (recommended — port 587)
                  </label>
                  <label className="col-span-3 flex items-start gap-2 text-[11.5px] text-text-soft">
                    <input type="checkbox" className="mt-0.5" checked={!smtp.verifySsl} onChange={(e) => setSmtp({ ...smtp, verifySsl: !e.target.checked })} />
                    <span>
                      Skip SSL certificate check — only tick this if mails fail with “CERTIFICATE_VERIFY_FAILED” (e.g. an office network that
                      intercepts HTTPS). The mail is still encrypted.
                    </span>
                  </label>
                </div>
                <div className="mt-1.5 text-[10.5px] leading-relaxed text-text-soft">
                  Gmail: use an App Password (Google Account → Security → 2-Step Verification → App passwords), not your normal password.
                </div>
                {smtpMsg && (
                  <div className={`mt-2 rounded-[8px] px-3 py-2 text-[11.5px] ${smtpMsg.tone === "ok" ? "bg-green-soft text-green" : "bg-amber-100 text-amber-800"}`}>
                    {smtpMsg.text}
                  </div>
                )}
                <div className="mt-2 flex items-center justify-end gap-2">
                  {mailMode === "smtp" && (
                    <button
                      type="button"
                      disabled={smtpBusy}
                      onClick={() => saveSmtp(true)}
                      className="rounded-[8px] border border-border px-2.5 py-1 text-[11.5px] font-semibold text-red hover:bg-red-soft disabled:opacity-50"
                    >
                      Remove
                    </button>
                  )}
                  <button
                    type="button"
                    disabled={smtpBusy || !smtp.host.trim()}
                    onClick={() => saveSmtp(false)}
                    className="rounded-[8px] bg-primary px-3 py-1 text-[11.5px] font-semibold text-white hover:bg-primary-dark disabled:opacity-50"
                  >
                    {smtpBusy ? "Saving…" : "Save settings"}
                  </button>
                </div>
              </div>
            )}
            <RecipientEditor title="Admin" list={recipients.admin} onChange={(e, op) => changeRecipient("admin", e, op)} canRemove={canChangeDirect} />
            <RecipientEditor title="Telecalling Team" list={recipients.telecalling} onChange={(e, op) => changeRecipient("telecalling", e, op)} canRemove={canChangeDirect} />
            <RecipientEditor title="Accountant (cc on cancellation request / decision)" list={recipients.accountant} onChange={(e, op) => changeRecipient("accountant", e, op)} canRemove={canChangeDirect} />
            <label className="mb-2 block text-[11px] text-text-soft">
              Test mail to (optional — blank = the saved list)
              <input
                value={testTo}
                onChange={(e) => setTestTo(e.target.value)}
                placeholder="yourname@indiaivf.in"
                className="mt-0.5 w-full rounded-[7px] border border-border bg-surface px-2 py-1.5 text-[12px] text-text outline-none focus:border-primary"
              />
            </label>
            <div className="mb-3 flex flex-wrap items-center gap-2">
              <button
                type="button"
                disabled={testBusy !== null}
                onClick={() => sendTestMail("admin")}
                className="rounded-[8px] border border-border px-2.5 py-1 text-[11.5px] font-semibold text-primary-dark hover:bg-primary-soft disabled:opacity-50"
              >
                {testBusy === "admin" ? "Sending…" : "Send test mail → Admin"}
              </button>
              <button
                type="button"
                disabled={testBusy !== null}
                onClick={() => sendTestMail("telecalling")}
                className="rounded-[8px] border border-border px-2.5 py-1 text-[11.5px] font-semibold text-primary-dark hover:bg-primary-soft disabled:opacity-50"
              >
                {testBusy === "telecalling" ? "Sending…" : "Send test mail → Telecalling"}
              </button>
            </div>
            {testMsg && (
              <div className={`mb-3 rounded-[8px] px-3 py-2 text-[11.5px] ${testMsg.tone === "ok" ? "bg-green-soft text-green" : "bg-amber-100 text-amber-800"}`}>
                {testMsg.text}
              </div>
            )}
            <div className="flex justify-end">
              <button
                type="button"
                onClick={() => setMailSettingsOpen(false)}
                className="rounded-[8px] bg-primary px-3.5 py-1.5 text-[12px] font-semibold text-white hover:bg-primary-dark"
              >
                Done
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

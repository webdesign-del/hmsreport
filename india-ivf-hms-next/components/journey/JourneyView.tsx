"use client";

import { useState, useEffect, Fragment } from "react";
import { getClientSession, hidesFinancials, isReadOnlyRole } from "@/lib/clientSession";
import { localISODate } from "@/lib/format";

interface JourneyViewProps {
  initialId?: string;
}

// 🖼️ Robust Image URL Normalizer
const getImageUrl = (photoPath: string) => {
  if (!photoPath || typeof photoPath !== "string") return "";
  const trimmed = photoPath.trim();
  if (!trimmed) return "";
  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    return trimmed;
  }
  const cleanPath = trimmed.startsWith("/") ? trimmed.slice(1) : trimmed;
  return `https://indiaivf.website/${cleanPath}`;
};

// KYC (patient edit) page on the HMS site — shown under Compliances on the First Consult row.
const patientKycUrl = (patientId: unknown): string => {
  const id = String(patientId ?? "").trim();
  return id ? `https://indiaivf.website/patients/edit/${encodeURIComponent(id)}` : "";
};

// Patient-wise page on the HMS site (opened from the Clinical Forms column when no clinical form is linked).
const patientDetailsUrl = (patientId: unknown): string => {
  const id = String(patientId ?? "").trim();
  return id ? `https://indiaivf.website/patient_details/${encodeURIComponent(id)}` : "";
};

export default function JourneyView({ initialId = "" }: JourneyViewProps) {
  const [patientIdInput, setPatientIdInput] = useState(initialId || "");
  const [patientProfile, setPatientProfile] = useState<any>(null);
  const [procedures, setProcedures] = useState<any[]>([]);
  const [stages, setStages] = useState<any[]>([]);
  const [donors, setDonors] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [hideFinancials, setHideFinancials] = useState(false);
  const [readOnly, setReadOnly] = useState(false);
  const [hideClinicalForms, setHideClinicalForms] = useState(false);

  useEffect(() => {
    const role = getClientSession()?.role;
    setHideFinancials(hidesFinancials(role));
    setReadOnly(isReadOnlyRole(role));
    setUserRole(role ?? null);
    // Embryologists work the lab side (Embryology Forms) — clinical/daycare forms aren't theirs.
    setHideClinicalForms(role === "embryologist");
  }, []);

  // Track image load error states locally
  const [wifeImgError, setWifeImgError] = useState(false);
  const [husbandImgError, setHusbandImgError] = useState(false);

  // Track visibility toggles
  const [hiddenTracks, setHiddenTracks] = useState<Set<string>>(new Set());
  const [collapseDone, setCollapseDone] = useState(false);
  const [stagesOpen, setStagesOpen] = useState(true); // whole "Clinical Journey Stages" card

  // 📅 DATE CHANGE INLINE POPOVER STATE
  const [editingStageIdx, setEditingStageIdx] = useState<number | null>(null);
  const [dateChangeForm, setDateChangeForm] = useState({
    fromDate: localISODate(),
    toDate: localISODate(),
    reason: "",
  });

  // 👤 DONOR / SURROGATE MODAL STATE
  const [activeDonorModal, setActiveDonorModal] = useState<any | null>(null);

  // 💰 PARTIAL PAYMENT BREAKDOWN LEDGER MODAL STATE
  const [activePaymentModal, setActivePaymentModal] = useState<{
    procedureName: string;
    code: string;
    receiptNo: string;
    fees: number;
    paymentDone: number;
    pending: number;
    breakups: any[];
  } | null>(null);

  // 📂 BOOKED PROCEDURES ACCORDION — which procedure row is currently expanded
  // to show its own Clinical / Embryology / Compliance forms. Keyed by the
  // procedure's own entry id (not array index), so it stays stable across refetches.
  const [expandedProcKey, setExpandedProcKey] = useState<string | number | null>(null);

  // 🗂 CATEGORY SECTIONS — which category groups are collapsed (Booked Procedures table keys are
  // the plain category name; the Non-IVF forms section uses a "nonivf:" prefix).
  const [collapsedCats, setCollapsedCats] = useState<Set<string>>(new Set());

  // ⏱ STAGE DELAY REASONS — when Stimulation / Trigger / OPU runs past its ideal date the
  // counsellor records why (Financial / Medical / Personal). Saved reasons are keyed by
  // "<cycle>|<stage>"; the popup only opens for a stage that is actually Delayed.
  const DELAY_REASONS = ["Financial Problem", "Medical Issues", "Personal Issues", "Other"] as const;
  type StageDelayInfo = { reason: string; remarks: string; reply?: string; repliedBy?: string; repliedAt?: string };
  const [stageDelays, setStageDelays] = useState<Record<string, StageDelayInfo>>({});
  const [userRole, setUserRole] = useState<string | null>(null);
  const [replyText, setReplyText] = useState("");
  const [replyBusy, setReplyBusy] = useState(false);
  const [delayNotice, setDelayNotice] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  const showDelayNotice = (n: { tone: "ok" | "warn"; text: string }) => {
    setDelayNotice(n);
    setTimeout(() => setDelayNotice(null), 7000);
  };
  const isAdminRole = userRole === "management" || userRole === "superadmin";
  const mailNotice = (m: { sent?: number; mode?: string; error?: string; note?: string } | undefined, who: string) => {
    if (!m) return;
    if (m.error) showDelayNotice({ tone: "warn", text: `Saved, but the mail could not be sent: ${m.error}` });
    else if (m.sent && m.mode === "console")
      showDelayNotice({ tone: "warn", text: "Saved, but the mail was NOT delivered — SMTP settings are not saved yet (Aging page → ✉ Notification emails)." });
    else if (m.sent) showDelayNotice({ tone: "ok", text: `✉ Mail sent to ${who}.` });
    else showDelayNotice({ tone: "warn", text: `Saved. ${m.note || "No mail was sent."}` });
  };
  const sessionIdentity = () => {
    const s = getClientSession() as { email?: string; username?: string; name?: string } | null;
    const email = s?.email || s?.username || "";
    return { user: s?.username || s?.name || "", email: email.includes("@") ? email : "" };
  };
  const [delayModal, setDelayModal] = useState<{
    key: string;
    cycleKey: string;
    stageKey: string;
    idealISO: string;
    actualISO: string | null;
  } | null>(null);
  const [delayReason, setDelayReason] = useState<string>("");
  const [delayRemarks, setDelayRemarks] = useState("");
  const [delaySaving, setDelaySaving] = useState(false);
  const [delayError, setDelayError] = useState("");

  const loadStageDelays = async (patientId: string) => {
    try {
      const res = await fetch(`/api/stage-delay?patient_id=${encodeURIComponent(patientId)}`, { cache: "no-store" });
      const json = await res.json();
      const map: Record<string, StageDelayInfo> = {};
      (json.delays || []).forEach((d: any) => {
        map[`${d.cycle_key}|${d.stage_key}`] = {
          reason: d.reason,
          remarks: d.remarks || "",
          reply: d.reply || "",
          repliedBy: d.replied_by || "",
          repliedAt: d.replied_at || "",
        };
      });
      setStageDelays(map);
    } catch {
      setStageDelays({});
    }
  };

  const openDelayModal = (m: NonNullable<typeof delayModal>) => {
    const saved = stageDelays[m.key];
    setDelayReason(saved?.reason || "");
    setReplyText("");
    setDelayRemarks(saved?.remarks || "");
    setDelayError("");
    setDelayModal(m);
  };

  const saveDelayReason = async () => {
    if (!delayModal) return;
    if (!delayReason) {
      setDelayError("Please select a reason.");
      return;
    }
    if (delayReason === "Other" && !delayRemarks.trim()) {
      setDelayError("Please describe the reason in the remarks.");
      return;
    }
    setDelaySaving(true);
    setDelayError("");
    try {
      const res = await fetch("/api/stage-delay", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          patient_id: patientProfile?.patient_id,
          cycle_key: delayModal.cycleKey,
          stage_key: delayModal.stageKey,
          reason: delayReason,
          remarks: delayRemarks.trim(),
          ideal_date: delayModal.idealISO,
          actual_date: delayModal.actualISO,
          patient_name: patientProfile?.wife_name || "",
          user: sessionIdentity().user,
          requester_email: sessionIdentity().email,
          role: userRole || "",
          origin: typeof window !== "undefined" ? window.location.origin : "",
        }),
      });
      const data = await res.json();
      if (!res.ok || data.status !== "success") throw new Error(data.message || "Failed to save.");
      setStageDelays((prev) => ({
        ...prev,
        [delayModal.key]: { ...(prev[delayModal.key] || {}), reason: delayReason, remarks: delayRemarks.trim() },
      }));
      setDelayModal(null);
      mailNotice(data.mail, "the Administrator");
    } catch (e: any) {
      setDelayError(e?.message || "Failed to save.");
    } finally {
      setDelaySaving(false);
    }
  };
  const toggleCat = (key: string) =>
    setCollapsedCats((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });

  // Fetch Patient Details from Django Backend
  const fetchPatientDetails = async (idToFetch: string) => {
    if (!idToFetch || !idToFetch.trim()) return;

    try {
      setLoading(true);
      setError("");
      setWifeImgError(false);
      setHusbandImgError(false);

      const res = await fetch(
        `/api/patient-profile?receipt_number=${encodeURIComponent(idToFetch.trim())}`,
        { cache: "no-store" }
      );

      const json = await res.json();

      if (res.ok && json.status === "success") {
        setPatientProfile(json.data);
        if (json.data?.patient_id) loadStageDelays(String(json.data.patient_id));
        // Completed stages (First Consult, Booked, …) always start visible for a freshly loaded patient.
        setCollapseDone(false);
        const fetchedProcs = json.procedures || [];
        setProcedures(fetchedProcs);

        setDonors(
          json.data?.donors || [
            {
              ID: "1",
              type: "Donor",
              donor_PatientName: "Rekha Bisht",
              donor_patient_id: "DNR-2025-031",
              donor_uhid: "UHID-9921",
              age: 25,
              blood_group: "B+",
              height: "5'4\"",
              weight: "54 kg",
              amh: "5.1 ng/mL",
              education: "B.Com",
              occupation: "Bank teller",
              marital_status: "Married · 1 child",
              anonymity: "Anonymous",
              status: "Active · OPU planned",
            },
            {
              ID: "2",
              type: "Surrogate",
              donor_PatientName: "Lakshmi Yadav",
              donor_patient_id: "SRG-2025-014",
              donor_uhid: "UHID-8812",
              age: 28,
              blood_group: "O+",
              height: "5'2\"",
              weight: "58 kg",
              amh: "3.8 ng/mL",
              education: "12th Pass",
              occupation: "Homemaker",
              marital_status: "Married · 2 children",
              anonymity: "Open Surrogate",
              status: "Active · Transfer Planned",
            },
          ]
        );

        if (json.stages && json.stages.length > 0) {
          setStages(json.stages);
        } else {
          setStages([
            { key: "First Consult", day: "Day 1", status: "done" },
            { key: "CNB Visits", day: "Day 2", status: "done" },
            { key: "Booked", day: "Day 3", status: fetchedProcs.some((p: any) => Number(p.payment_done) > 0) ? "done" : "current" },
            { key: "Pre-Procedure", day: "Day 4 - 12", status: "upcoming" },
            { key: "Ovarian Stimulation", day: "Day 13", status: "upcoming" },
            { key: "Endometrial Preparation", day: "Day 14", status: "upcoming" },
            { key: "Trigger", day: "Day 15 - 18", status: "upcoming" },
            { key: "OPU", day: "Day 19", status: "upcoming" },
            { key: "Progesterone Change", day: "Day 30", status: "upcoming" },
            { key: "Embryo Transfer", day: "Day 30", status: "upcoming" },
            { key: "B-HCG", day: "Day 30", status: "upcoming" },
            { key: "Cardiac Activity", day: "Day 30", status: "upcoming" },
          ]);
        }
      } else {
        setError(json.message || "No patient profile match found in live database.");
        setPatientProfile(null);
        setProcedures([]);
        setStages([]);
        setDonors([]);
      }
    } catch (err: any) {
      console.error("Journey API Error:", err);
      setError("Unable to connect to the backend server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (initialId) {
      fetchPatientDetails(initialId);
    }
  }, [initialId]);

  const toggleTrack = (trackKey: string) => {
    setHiddenTracks((prev) => {
      const next = new Set(prev);
      if (next.has(trackKey)) next.delete(trackKey);
      else next.add(trackKey);
      return next;
    });
  };

  const handleOpenDateChange = (idx: number) => {
    setEditingStageIdx(idx);
    const today = localISODate();
    setDateChangeForm({ fromDate: today, toDate: today, reason: "" });
  };

  const handleSaveDateChange = (idx: number) => {
    if (!dateChangeForm.reason.trim()) {
      alert("Please enter reason for change!");
      return;
    }
    const updatedStages = [...stages];
    updatedStages[idx].day = `${dateChangeForm.fromDate} - ${dateChangeForm.toDate}`;
    setStages(updatedStages);
    setEditingStageIdx(null);
  };

  // Codes to hide from the "Booked Procedures & Services" table — IP218/IP219 are
  // repeat under-package monitoring scans (Follicle Monitoring / Endometrial
  // Thickness) that clutter this list; they're still counted everywhere else
  // (stage linking, payments, etc.), just not shown in this table.
  const HIDDEN_PROCEDURE_CODES = ["IP218", "IP219"];

  // Header totals: the backend only sends per-procedure fees/payment_done/pending, so sum
  // them here (all booked procedures, including the hidden IP218/IP219 rows).
  const totalGross = procedures.reduce((sum: number, p: any) => sum + (Number(p.fees) || 0), 0);
  const totalReceived = procedures.reduce((sum: number, p: any) => sum + (Number(p.payment_done) || 0), 0);
  const totalPending = procedures.reduce((sum: number, p: any) => sum + (Number(p.pending) || 0), 0);
  const visibleProcedures = procedures.filter(
    (proc: any) => !HIDDEN_PROCEDURE_CODES.includes(String(proc.code || "").trim().toUpperCase())
  );

  // 🎯 KEY MATCHING LOGIC FOR form_for — shared by the Booked Procedures
  // accordion AND the Clinical Journey Stages table, so a form is always
  // classified Clinical vs Embryology the same way everywhere it appears.
  const isClinicalForm = (f: any) => {
    const target = String(f.form_for || f.type || "").toLowerCase().trim();
    const name = String(f.form_name || f.name || "").toLowerCase();

    // Strict check on form_for="daycare_procedure"
    if (target === "daycare_procedure" || target === "daycare" || target === "clinical") return true;

    // Intelligent fallback keywords if form_for is empty
    if (!target || target === "general") {
      if (name.includes("discharge") || name.includes("prescription") || name.includes("induction") || name.includes("check") || name.includes("outcome")) return true;
    }
    return false;
  };

  const isEmbryologyForm = (f: any) => {
    const target = String(f.form_for || f.type || "").toLowerCase().trim();
    const name = String(f.form_name || f.name || "").toLowerCase();

    // Strict check on form_for="lab_procedure"
    if (target === "lab_procedure" || target === "lab" || target === "embryology") return true;

    // Intelligent fallback keywords if form_for is empty
    if (!target || target === "general") {
      if (name.includes("embryo") || name.includes("oocyte") || name.includes("sperm") || name.includes("icsi") || name.includes("preparation")) return true;
    }
    return false;
  };

  // `kind` picks the URL path — "procedure_form" for Clinical/Embryology forms,
  // "check_concern_form" for Compliance/consent forms. Same 4 params either way:
  // formId/procedureId/appointmentId/patientProcedureId.
  const buildFormUrl = (proc: any, formId: any, kind: "procedure_form" | "check_concern_form" = "procedure_form") => {
    const appointmentId = proc.appointment_id || patientProfile?.appointment_id || "0";
    const patientProcedureId = proc.procedure_entry_id || proc.ID || "0";
    const procedureId = proc.procedure_id || "0";
    return `https://indiaivf.website/${kind}/${formId}/${procedureId}/${appointmentId}/${patientProcedureId}`;
  };

  // 🔓 PAYMENT-GATED FORMS — each procedure form carries its own unlock_percent
  // (hms_procedure_forms.unlock_percent — "kitna % payment hone par form khule").
  // A form with unlock_percent=0 is always open. Otherwise it only opens once
  // that procedure's own payment_done/fees ratio reaches the required %.
  const isFormUnlocked = (form: any, proc: any): boolean => {
    const requiredPercent = Number(form?.unlock_percent) || 0;
    if (requiredPercent <= 0) return true;
    const fees = Number(proc?.fees) || 0;
    const paid = Number(proc?.payment_done) || 0;
    const paidPercent = fees > 0 ? (paid / fees) * 100 : 0;
    return paidPercent >= requiredPercent;
  };

  // 🗺️ FORM → STAGE MAPPING — an IVF procedure's forms (e.g. IP11 + IP222
  // combined) used to be copy-pasted onto every stage row from "Booked"
  // onward. Instead, each form is matched to the ONE stage it actually
  // belongs to, by keyword on its name/area. Unmatched forms fall back onto
  // the "Booked" row instead of silently disappearing. Adjust the keywords
  // below if a form ever lands on the wrong stage.
  // A form can belong to more than one stage (e.g. an "outcome" form is
  // relevant to both B-HCG and Cardiac Activity), so each entry lists every
  // stage it should appear on.
  const FORM_STAGE_KEYWORDS: { stageKeys: string[]; keywords: string[] }[] = [
    { stageKeys: ["Embryo Transfer"], keywords: ["embryo_transfer", "embryo transfer", "transfer_day"] },
    { stageKeys: ["Endometrial Preparation"], keywords: ["endometrial", "lining"] },
    { stageKeys: ["Trigger"], keywords: ["trigger"] },
    { stageKeys: ["OPU"], keywords: ["opu", "ovum", "oocyte", "sperm", "icsi", "fertiliz", "retrieval"] },
    { stageKeys: ["Ovarian Stimulation"], keywords: ["stimulat", "induction", "follicle"] },
    { stageKeys: ["Progesterone Change"], keywords: ["progesterone"] },
    { stageKeys: ["B-HCG"], keywords: ["hcg", "beta"] },
    { stageKeys: ["Cardiac Activity"], keywords: ["cardiac", "fetal heart", "fhr"] },
    // A pregnancy "outcome" form covers both the beta-HCG result and the
    // follow-up cardiac-activity scan, so it shows on both stages.
    { stageKeys: ["B-HCG", "Cardiac Activity"], keywords: ["outcome"] },
    { stageKeys: ["Pre-Procedure"], keywords: ["baseline", "pre_procedure", "pre-procedure", "screening", "consent"] },
  ];

  const matchFormToStageKeys = (form: any): string[] => {
    const haystack = `${form.form_name || form.name || ""} ${form.form_area || ""}`.toLowerCase();
    for (const { stageKeys, keywords } of FORM_STAGE_KEYWORDS) {
      if (keywords.some((kw) => haystack.includes(kw))) return stageKeys;
    }
    return ["Booked"]; // unclassified forms still show up (on Booked) instead of vanishing
  };

  // 🔁 CYCLE GROUPING — an IVF cycle is really a PACKAGE of several codes
  // booked together (e.g. "IVF (Self Single Cycle till embryo transfer)" =
  // IP11 + IP222; its repeat booking comes back as a distinctly-named package
  // too, e.g. "...- 2nd Cycle" = IP223 + IP243; other packages pair up as
  // IP227/IP228, IP224/IP244, and so on). `proc.category` is where that
  // package name lives, so group by category — NOT by booking date, since a
  // package's own codes are already booked together and a genuine repeat
  // cycle gets its own distinct category string from the backend.
  const ivfProcedures = procedures.filter(
    (proc: any) => String(proc.broad_procedure || "").trim().toUpperCase() === "IVF"
  );
  const cycleGroupsMap = new Map<string, any[]>();
  ivfProcedures.forEach((proc: any) => {
    const categoryKey = String(proc.category || "").trim() || String(proc.procedure_name || "IVF Procedure").trim();
    if (!cycleGroupsMap.has(categoryKey)) cycleGroupsMap.set(categoryKey, []);
    cycleGroupsMap.get(categoryKey)!.push(proc);
  });
  const cycleGroups =
    cycleGroupsMap.size > 0
      ? Array.from(cycleGroupsMap.entries())
          .map(([label, procs]) => {
            const earliestDate =
              procs
                .map((p: any) => p.on_date)
                .filter(Boolean)
                .sort()[0] || null;
            return { label, date: earliestDate, procedures: procs };
          })
          // Chronological order, so cycle 1 lists before "...- 2nd Cycle"
          .sort((a, b) => (a.date || "").localeCompare(b.date || ""))
      : [{ label: null as string | null, date: null as string | null, procedures: [] as any[] }];

  // 🗂 CATEGORY-WISE GROUPING (e.g. "IVF with Bed" vs "Non IVF without Bed").
  // IVF-style categories come first, then the rest in the order they first appear.
  const catOf = (proc: any) => String(proc.category || "").trim() || "Non IVF without Bed";
  const orderCategories = (cats: string[]) =>
    [...cats].sort((a, b) => {
      const ai = /^ivf/i.test(a) ? 0 : 1;
      const bi = /^ivf/i.test(b) ? 0 : 1;
      return ai - bi;
    });
  // 📅 IDEAL SCHEDULE — for these stages the ideal date is the cycle's booking date + N days.
  const IDEAL_OFFSET_DAYS: Record<string, number> = {
    "ovarian stimulation": 10,
    trigger: 18,
    opu: 20,
  };
  const idealOffsetFor = (stageKey: string): number | null => {
    const v = IDEAL_OFFSET_DAYS[String(stageKey || "").trim().toLowerCase()];
    return v === undefined ? null : v;
  };
  // "2025-11-25T16:15:30" + 10  ->  "2025-12-05"  (UTC math, so no timezone/DST drift)
  const addDaysISO = (iso: string | null | undefined, days: number): string | null => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ""));
    if (!m) return null;
    const d = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]) + days));
    return d.toISOString().slice(0, 10);
  };
  // "2026-01-05" -> "05-01-2026"
  const fmtDMY = (iso: string | null | undefined): string => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ""));
    return m ? `${m[3]}-${m[2]}-${m[1]}` : String(iso || "");
  };
  const todayISO = (() => {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, "0")}-${String(t.getDate()).padStart(2, "0")}`;
  })();
  const fmtProcDate = (iso: string | null | undefined): string => {
    const m = /^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/.exec(String(iso || ""));
    if (m) return `${fmtDMY(m[1])} ${m[2]}`;
    return fmtDMY(String(iso || "").slice(0, 10));
  };

  const sumOf = (list: any[], k: string) => list.reduce((t: number, x: any) => t + (Number(x[k]) || 0), 0);

  const procCategoryMap = new Map<string, { proc: any; idx: number }[]>();
  visibleProcedures.forEach((proc: any, idx: number) => {
    const c = catOf(proc);
    if (!procCategoryMap.has(c)) procCategoryMap.set(c, []);
    procCategoryMap.get(c)!.push({ proc, idx });
  });
  // Flat row list for the Booked Procedures table: one header row per category, then its procedures.
  const procRows: any[] = [];
  orderCategories(Array.from(procCategoryMap.keys())).forEach((cat) => {
    const items = procCategoryMap.get(cat)!;
    const list = items.map((x) => x.proc);
    const collapsed = collapsedCats.has(cat);
    procRows.push({
      kind: "header",
      cat,
      count: items.length,
      fees: sumOf(list, "fees"),
      received: sumOf(list, "payment_done"),
      pending: sumOf(list, "pending"),
      collapsed,
    });
    if (!collapsed) items.forEach((x) => procRows.push({ kind: "proc", proc: x.proc, idx: x.idx }));
  });

  // Procedures that are NOT part of an IVF cycle flow (they have no stage-by-stage journey),
  // grouped by category and shown in their own forms/financials section under the stages table.
  const ivfProcIds = new Set(ivfProcedures.map((x: any) => x.procedure_entry_id ?? x.ID ?? x));
  const nonIvfProcedures = visibleProcedures.filter(
    (proc: any) => !ivfProcIds.has(proc.procedure_entry_id ?? proc.ID ?? proc)
  );
  const nonIvfCategoryMap = new Map<string, any[]>();
  nonIvfProcedures.forEach((proc: any) => {
    const c = catOf(proc);
    if (!nonIvfCategoryMap.has(c)) nonIvfCategoryMap.set(c, []);
    nonIvfCategoryMap.get(c)!.push(proc);
  });
  const nonIvfCategories = orderCategories(Array.from(nonIvfCategoryMap.keys()));

  // Pill list for a set of forms (used by the Non-IVF section; mirrors the stage-row pills).
  const renderFormPills = (forms: any[], proc: any, tone: "clinical" | "embryology") => {
    if (forms.length === 0) {
      return (
        <span className="text-text-soft italic text-[11px]">
          {tone === "clinical" ? "— No clinical form linked" : "— No lab form linked"}
        </span>
      );
    }
    return (
      <div className="flex flex-wrap gap-1.5">
        {forms.map((frm: any, fIdx: number) => {
          const isFilledInDB = Boolean(frm.is_filled || frm.filled);
          const formId = frm.form_id || frm.ID || "0";
          if (!isFormUnlocked(frm, proc)) {
            return (
              <span
                key={fIdx}
                className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm bg-slate-200 text-slate-500 border border-slate-300 cursor-not-allowed"
                title={`Unlocks at ${frm.unlock_percent}% payment`}
              >
                <span>🔒</span>
                <span>{frm.form_name || frm.name}</span>
              </span>
            );
          }
          return (
            <a
              key={fIdx}
              href={buildFormUrl(proc, formId)}
              target="_blank"
              rel="noopener noreferrer"
              className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm transition-transform hover:scale-105 ${
                isFilledInDB
                  ? tone === "clinical"
                    ? "bg-[#2563eb] text-white border border-[#1d4ed8]"
                    : "bg-[#7c3aed] text-white border border-[#6d28d9]"
                  : "bg-[#e11d48] text-white border border-[#be123c]"
              }`}
            >
              <span>{isFilledInDB ? "✓" : "✎"}</span>
              <span>{frm.form_name || frm.name}</span>
            </a>
          );
        })}
      </div>
    );
  };

  return (
    <section className="screen-enter max-w-[1700px] mx-auto">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-text">Booked Patient Clinical Journey</h1>
          <p className="text-xs text-text-soft">
            Track live date-wise clinical, financial &amp; compliance journey of every booked patient from Django Engine
          </p>
        </div>
        <button
          onClick={() => fetchPatientDetails(patientIdInput)}
          className="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-text-mid hover:bg-surface-2 transition"
        >
          🔄 Refresh Live Data
        </button>
      </div>

      {/* Search Box */}
      <div className="mb-6 rounded-[12px] border border-border bg-surface p-4 shadow-card">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            fetchPatientDetails(patientIdInput);
          }}
          className="flex gap-3"
        >
          <input
            type="text"
            placeholder="Enter Patient ID or Receipt Token (e.g. 1612695786)..."
            value={patientIdInput}
            onChange={(e) => setPatientIdInput(e.target.value)}
            className="flex-1 rounded-[10px] border border-border bg-surface px-4 py-2.5 text-sm outline-none focus:border-primary transition"
          />
          <button
            type="submit"
            className="rounded-[10px] bg-[#9333ea] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#7e22ce] transition"
          >
            Fetch Journey
          </button>
        </form>
      </div>

      {loading && (
        <div className="p-8 text-center text-sm font-semibold text-text-soft animate-pulse">
          ⌛ Syncing Live Patient Records, Photos &amp; Donor Data...
        </div>
      )}

      {error && (
        <div className="p-4 rounded-lg bg-red-50 border border-red-200 text-red-600 text-xs font-semibold text-center mb-6">
          {error}
        </div>
      )}

      {/* 🖼️ PATIENT DETAILS KYC HEADER */}
      {patientProfile && !loading && (
        <div className="mb-6 rounded-[14px] border border-border bg-surface p-5 shadow-card">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <div className="flex items-center -space-x-3">
                {patientProfile.wife_photo && !wifeImgError ? (
                  <img
                    src={getImageUrl(patientProfile.wife_photo)}
                    alt="Wife"
                    className="h-12 w-12 rounded-full object-cover border-2 border-white shadow-md z-10"
                    onError={() => setWifeImgError(true)}
                  />
                ) : (
                  <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#8b5cf6] text-white font-bold text-lg border-2 border-white shadow-md z-10">
                    {patientProfile.wife_name ? patientProfile.wife_name.charAt(0) : "W"}
                  </div>
                )}

                {patientProfile.husband_photo && !husbandImgError ? (
                  <img
                    src={getImageUrl(patientProfile.husband_photo)}
                    alt="Husband"
                    className="h-10 w-10 rounded-full object-cover border-2 border-white shadow-sm"
                    onError={() => setHusbandImgError(true)}
                  />
                ) : (
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-500 text-white font-bold text-xs border-2 border-white shadow-sm">
                    {patientProfile.husband_name ? patientProfile.husband_name.charAt(0) : "H"}
                  </div>
                )}
              </div>

              <div>
                <div className="flex items-center gap-2">
                  <span className="rounded bg-pink-100 px-2 py-0.5 text-[10px] font-bold text-pink-600">WIFE</span>
                  <h2 className="text-lg font-bold text-text uppercase tracking-wide">
                    {patientProfile.wife_name || "—"}
                  </h2>
                  <span className="text-xs text-text-soft">({patientProfile.wife_age || "—"} yrs)</span>
                </div>
                <div className="flex items-center gap-2 mt-1">
                  <span className="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-600">HUSBAND</span>
                  <span className="text-sm font-semibold text-text-mid uppercase">
                    {patientProfile.husband_name || "—"}
                  </span>
                  <span className="text-xs text-text-soft">({patientProfile.husband_age || "—"} yrs)</span>
                </div>
              </div>
            </div>

            <div className="flex flex-wrap gap-8 text-xs">
              <div>
                <span className="text-text-soft block text-[11px]">Patient ID</span>
                <strong className="text-[#8b5cf6] text-sm font-bold">{patientProfile.patient_id}</strong>
              </div>
              {!hideFinancials && (
                <>
                  <div>
                    <span className="text-text-soft block text-[11px]">Gross Billed</span>
                    <strong className="text-slate-800 text-sm">
                      ₹ {totalGross.toLocaleString("en-IN")}
                    </strong>
                  </div>
                  <div>
                    <span className="text-text-soft block text-[11px]">Amount Received</span>
                    <strong className="text-green-600 text-sm">
                      ₹ {totalReceived.toLocaleString("en-IN")}
                    </strong>
                  </div>
                  <div>
                    <span className="text-text-soft block text-[11px]">Pending Balance</span>
                    <strong className={`text-sm ${totalPending > 0 ? "text-red-500" : "text-green-600"}`}>
                      ₹ {totalPending.toLocaleString("en-IN")}
                    </strong>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 📊 BOOKED PROCEDURES TABLE */}
      {patientProfile && !loading && visibleProcedures.length > 0 && (
        <div className="mb-6 rounded-[14px] border border-border bg-surface shadow-card overflow-hidden">
          <div className="border-b border-border px-5 py-3.5 flex justify-between items-center bg-surface-2">
            <h3 className="font-bold text-sm">Booked Procedures &amp; Services ({visibleProcedures.length})</h3>
            <span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">
              ● LIVE DATA
            </span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-surface border-b border-border font-semibold text-text-mid">
                <tr>
                  <th className="p-3 w-8"></th>
                  <th className="p-3">Date</th>
                  <th className="p-3">Category</th>
                  <th className="p-3">{hideFinancials ? "Code" : "Code (Click to View Partial Payments)"}</th>
                  <th className="p-3">Procedure Name</th>
                  <th className="p-3">Receipt No</th>
                  {!hideFinancials && (
                    <>
                      <th className="p-3 text-right">Fees</th>
                      <th className="p-3 text-right">Received</th>
                      <th className="p-3 text-right">Pending</th>
                    </>
                  )}
                </tr>
              </thead>
              <tbody>
                {procRows.map((item: any, rowIdx: number) => {
                  // 🗂 Category header row — click to collapse / expand that category.
                  if (item.kind === "header") {
                    return (
                      <tr
                        key={`cat-${item.cat}`}
                        onClick={() => toggleCat(item.cat)}
                        className="cursor-pointer border-b border-border bg-slate-100 hover:bg-slate-200/70 transition"
                        title={item.collapsed ? "Click to expand category" : "Click to collapse category"}
                      >
                        <td colSpan={hideFinancials ? 6 : 6} className="p-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-700">
                          <span className={`mr-2 inline-block transition-transform ${item.collapsed ? "" : "rotate-90"}`}>▶</span>
                          {item.cat}
                          <span className="ml-2 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-text-soft normal-case">
                            {item.count} {item.count === 1 ? "procedure" : "procedures"}
                          </span>
                        </td>
                        {!hideFinancials && (
                          <>
                            <td className="p-2.5 text-right text-[11px] font-bold text-slate-700">₹ {item.fees.toLocaleString("en-IN")}</td>
                            <td className="p-2.5 text-right text-[11px] font-bold text-green-600">₹ {item.received.toLocaleString("en-IN")}</td>
                            <td className={`p-2.5 text-right text-[11px] font-bold ${item.pending > 0 ? "text-red-500" : "text-green-600"}`}>
                              ₹ {item.pending.toLocaleString("en-IN")}
                            </td>
                          </>
                        )}
                      </tr>
                    );
                  }
                  const { proc, idx } = item;
                  const procKey = proc.procedure_entry_id ?? proc.ID ?? `${proc.code}-${idx}`;
                  const isExpanded = expandedProcKey === procKey;
                  const colCount = hideFinancials ? 6 : 9;

                  // This procedure's own forms — same classification helpers used
                  // by the Clinical Journey Stages table below, so a form reads the
                  // same way everywhere it's shown.
                  const procRawForms: any[] = proc.linked_forms || proc.forms || [];
                  const procClinicalForms = procRawForms.filter(isClinicalForm);
                  const procEmbryologyForms = procRawForms.filter(isEmbryologyForm);
                  const procConcernForms: any[] = proc.linked_concern_forms || [];

                  return (
                    <Fragment key={procKey}>
                      <tr
                        onClick={() => setExpandedProcKey(isExpanded ? null : procKey)}
                        className="border-b border-border-soft hover:bg-surface-2/60 transition cursor-pointer"
                        title="Click row to view this procedure's linked forms"
                      >
                        <td className="p-3 text-center text-text-soft">
                          <span className={`inline-block transition-transform ${isExpanded ? "rotate-90" : ""}`}>▶</span>
                        </td>
                        <td className="p-3 font-semibold text-slate-600">{proc.on_date || "—"}</td>
                        <td className="p-3">
                          <span className="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                            {proc.category || "Non IVF without Bed"}
                          </span>
                        </td>
                        <td className="p-3">
                          {hideFinancials ? (
                            <span className="bg-amber-100 text-amber-900 px-2.5 py-1 rounded-md font-bold inline-flex items-center gap-1 border border-amber-300">
                              <span>🏷️</span>
                              <span>{proc.code || "IP288"}</span>
                            </span>
                          ) : (
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                setActivePaymentModal({
                                  procedureName: proc.procedure_name,
                                  code: proc.code || "PROC",
                                  receiptNo: proc.receipt_number,
                                  fees: proc.fees,
                                  paymentDone: proc.payment_done,
                                  pending: proc.pending,
                                  breakups: proc.payment_breakups || [],
                                });
                              }}
                              className="bg-amber-100 text-amber-900 hover:bg-amber-200 px-2.5 py-1 rounded-md font-bold transition shadow-sm border border-amber-300 inline-flex items-center gap-1 cursor-pointer"
                              title="Click to view partial payment breakups"
                            >
                              <span>🏷️</span>
                              <span>{proc.code || "IP288"}</span>
                            </button>
                          )}
                        </td>
                        <td className="p-3 font-bold text-text-dark">{proc.procedure_name || "—"}</td>
                        <td className="p-3 text-text-soft">{proc.receipt_number || "—"}</td>
                        {!hideFinancials && (
                          <>
                            <td className="p-3 text-right font-medium">₹ {proc.fees?.toLocaleString("en-IN")}</td>
                            <td className="p-3 text-right font-bold text-green-600">
                              ₹ {proc.payment_done?.toLocaleString("en-IN")}
                            </td>
                            <td
                              className={`p-3 text-right font-bold ${
                                proc.pending > 0 ? "text-red-500" : "text-green-600"
                              }`}
                            >
                              ₹ {proc.pending?.toLocaleString("en-IN")}
                            </td>
                          </>
                        )}
                      </tr>

                      {/* 📂 EXPANDED — all forms linked to THIS specific procedure code */}
                      {isExpanded && (
                        <tr className="bg-surface-2/40 border-b border-border-soft">
                          <td colSpan={colCount} className="p-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                              <div>
                                <div className="text-[10px] font-bold uppercase tracking-wide text-blue-600 mb-1.5">
                                  Clinical Forms
                                </div>
                                {procClinicalForms.length > 0 ? (
                                  <div className="flex flex-wrap gap-1.5">
                                    {procClinicalForms.map((frm: any, fIdx: number) => {
                                      const isFilledInDB = Boolean(frm.is_filled || frm.filled);
                                      const formId = frm.form_id || frm.ID || "0";
                                      if (!isFormUnlocked(frm, proc)) {
                                        return (
                                          <span
                                            key={fIdx}
                                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm bg-slate-200 text-slate-500 border border-slate-300 cursor-not-allowed"
                                            title={`Unlocks at ${frm.unlock_percent}% payment`}
                                          >
                                            <span>🔒</span>
                                            <span>{frm.form_name || frm.name}</span>
                                          </span>
                                        );
                                      }
                                      return (
                                        <a
                                          key={fIdx}
                                          href={buildFormUrl(proc, formId)}
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          onClick={(e) => e.stopPropagation()}
                                          className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm transition-transform hover:scale-105 ${
                                            isFilledInDB
                                              ? "bg-[#2563eb] text-white border border-[#1d4ed8]"
                                              : "bg-[#e11d48] text-white border border-[#be123c]"
                                          }`}
                                        >
                                          <span>{isFilledInDB ? "✓" : "✎"}</span>
                                          <span>{frm.form_name || frm.name}</span>
                                        </a>
                                      );
                                    })}
                                  </div>
                                ) : (
                                  <span className="text-text-soft italic text-[11px]">— None linked</span>
                                )}
                              </div>

                              <div>
                                <div className="text-[10px] font-bold uppercase tracking-wide text-purple-600 mb-1.5">
                                  Embryology Forms
                                </div>
                                {procEmbryologyForms.length > 0 ? (
                                  <div className="flex flex-wrap gap-1.5">
                                    {procEmbryologyForms.map((frm: any, fIdx: number) => {
                                      const isFilledInDB = Boolean(frm.is_filled || frm.filled);
                                      const formId = frm.form_id || frm.ID || "0";
                                      if (!isFormUnlocked(frm, proc)) {
                                        return (
                                          <span
                                            key={fIdx}
                                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm bg-slate-200 text-slate-500 border border-slate-300 cursor-not-allowed"
                                            title={`Unlocks at ${frm.unlock_percent}% payment`}
                                          >
                                            <span>🔒</span>
                                            <span>{frm.form_name || frm.name}</span>
                                          </span>
                                        );
                                      }
                                      return (
                                        <a
                                          key={fIdx}
                                          href={buildFormUrl(proc, formId)}
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          onClick={(e) => e.stopPropagation()}
                                          className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm transition-transform hover:scale-105 ${
                                            isFilledInDB
                                              ? "bg-[#7c3aed] text-white border border-[#6d28d9]"
                                              : "bg-[#e11d48] text-white border border-[#be123c]"
                                          }`}
                                        >
                                          <span>{isFilledInDB ? "✓" : "✎"}</span>
                                          <span>{frm.form_name || frm.name}</span>
                                        </a>
                                      );
                                    })}
                                  </div>
                                ) : (
                                  <span className="text-text-soft italic text-[11px]">— None linked</span>
                                )}
                              </div>

                              <div>
                                <div className="text-[10px] font-bold uppercase tracking-wide text-slate-500 mb-1.5">
                                  Compliance / Consent Forms
                                </div>
                                {procConcernForms.length > 0 ? (
                                  <div className="flex flex-wrap gap-1.5">
                                    {procConcernForms.map((cf: any, cfIdx: number) => (
                                      <span
                                        key={cf.form_id || cfIdx}
                                        className="inline-block rounded bg-blue-50 border border-blue-200 px-2 py-1 text-[10.5px] font-bold text-blue-800"
                                        title={cf.form_for || cf.type || ""}
                                      >
                                        {cf.form_name}
                                      </span>
                                    ))}
                                  </div>
                                ) : (
                                  <span className="text-text-soft italic text-[11px]">— None linked</span>
                                )}
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Track Visibility Filter Controls */}
      {patientProfile && !loading && stages.length > 0 && (
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <span className="text-[11px] font-semibold uppercase tracking-[.03em] text-text-soft">
            Track Filters:
          </span>
          {[
            { key: "comm", label: "Communication" },
            { key: "plan", label: "Change in Plan" },
            ...(hideClinicalForms ? [] : [{ key: "clinical_forms", label: "Clinical Forms" }]),
            { key: "embryo_forms", label: "Embryology Forms" },
            ...(hideFinancials ? [] : [{ key: "financial", label: "Financials" }]),
            { key: "compliances", label: "Compliances" },
          ].map((t) => {
            const active = !hiddenTracks.has(t.key);
            return (
              <button
                key={t.key}
                type="button"
                onClick={() => toggleTrack(t.key)}
                className={`rounded-full border px-3 py-1 text-[11px] font-semibold transition ${
                  active
                    ? "border-primary bg-primary-soft text-primary-dark"
                    : "border-border text-text-soft opacity-50"
                }`}
              >
                {t.label}
              </button>
            );
          })}
          <button
            type="button"
            onClick={() => setCollapseDone((v) => !v)}
            className={`ml-auto rounded-full border px-3 py-1 text-[11px] font-semibold ${
              collapseDone ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid"
            }`}
          >
            ⇕{" "}
            {collapseDone
              ? `Show Completed (${stages.filter((x: any) => x.status === "done").length} hidden)`
              : "Collapse Completed"}
          </button>
        </div>
      )}

      {/* 📊 FULL MULTI-TRACK CLINICAL JOURNEY STAGES TABLE */}
      {patientProfile && !loading && stages.length > 0 && (() => {
        // Column count for the "Cycle N" divider row's colSpan — must track
        // every optional column below exactly.
        const stageColCount =
          1 + // Stage
          (hiddenTracks.has("comm") ? 0 : 1) + // Patient Comm
          1 + // Ideal Schedule
          (hiddenTracks.has("plan") ? 0 : 1) + // Change in Plan
          1 + // Linked Procedure
          (!hideClinicalForms && !hiddenTracks.has("clinical_forms") ? 1 : 0) +
          (!hiddenTracks.has("embryo_forms") ? 1 : 0) +
          (!hideFinancials && !hiddenTracks.has("financial") ? 1 : 0) +
          (!hiddenTracks.has("compliances") ? 1 : 0);

        return (
        <div className="rounded-[14px] border border-border bg-surface shadow-card overflow-hidden">
          <div
            role="button"
            tabIndex={0}
            aria-expanded={stagesOpen}
            onClick={() => setStagesOpen((o) => !o)}
            onKeyDown={(e) => {
              if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                setStagesOpen((o) => !o);
              }
            }}
            title={stagesOpen ? "Click to collapse" : "Click to expand"}
            className={`flex cursor-pointer select-none items-center justify-between bg-surface-2 px-5 py-4 hover:bg-surface ${stagesOpen ? "border-b border-border" : ""}`}
          >
            <h3 className="flex items-center gap-2 text-sm font-bold">
              <span className="inline-block w-3 text-[11px] text-text-soft">{stagesOpen ? "▼" : "▶"}</span>
              <span>
                Clinical Journey Stages &amp; Milestones
                {ivfProcedures.length > 0 ? ` — ${catOf(ivfProcedures[0])}` : ""} ({stages.length}
                {cycleGroups.length > 1 ? ` × ${cycleGroups.length} cycles` : ""})
              </span>
            </h3>
            <span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">
              ● Django Live Synced
            </span>
          </div>

          <div className={`overflow-x-auto ${stagesOpen ? "" : "hidden"}`}>
            <table className="w-full text-left text-xs">
              <thead className="bg-surface border-b border-border font-semibold text-text-mid">
                <tr>
                  <th className="p-3.5">Stage</th>
                  {!hiddenTracks.has("comm") && <th className="p-3.5">Patient Comm</th>}
                  <th className="p-3.5">Ideal Schedule</th>
                  {!hiddenTracks.has("plan") && <th className="p-3.5">Change in Plan</th>}
                  <th className="p-3.5">Linked Procedure</th>
                  
                  {/* 🔵 CLINICAL FORMS COLUMN HEADER */}
                  {!hideClinicalForms && !hiddenTracks.has("clinical_forms") && (
                    <th className="p-3.5 text-blue-700 bg-blue-50/50">
                      Clinical Forms
                      <span className="block text-[9px] font-normal text-blue-500 lowercase">daycare_procedure</span>
                    </th>
                  )}

                  {/* 🟣 EMBRYOLOGY FORMS COLUMN HEADER */}
                  {!hiddenTracks.has("embryo_forms") && (
                    <th className="p-3.5 text-purple-700 bg-purple-50/50">
                      Embryology Forms
                      <span className="block text-[9px] font-normal text-purple-500 lowercase">lab_procedure</span>
                    </th>
                  )}

                  {!hideFinancials && !hiddenTracks.has("financial") && <th className="p-3.5 text-right">Financials</th>}
                  {!hiddenTracks.has("compliances") && <th className="p-3.5 text-center">Compliances</th>}
                </tr>
              </thead>
              <tbody>
                {cycleGroups.map((cycle, cIdx) => (
                  <Fragment key={cIdx}>
                    {cycleGroups.length > 1 && (
                      <tr className="bg-slate-100 border-b border-border">
                        <td colSpan={stageColCount} className="p-2.5 font-bold text-slate-700 text-[11px] uppercase tracking-wide">
                          🔁 {cycle.label}{cycle.date ? ` — Booked ${cycle.date}` : ""}
                        </td>
                      </tr>
                    )}
                    {stages.map((stg, i) => {
                  if (collapseDone && stg.status === "done") return null;

                  const isBookedOrLater = i >= 2 || stg.key === "Booked";
                  const cycleProcedures = cycle.procedures;

                  // Combine every form across THIS cycle's procedures (e.g. IP11 +
                  // IP222 booked together), then keep only the ones that actually
                  // match this stage (by name/area keyword — see matchFormToStageKeys
                  // above) instead of copy-pasting the whole pool onto every row.
                  // Each form keeps a reference to its own source procedure, so its
                  // "fill form" link points at the right procedure/appointment.
                  const clinicalForms: any[] = [];
                  const embryologyForms: any[] = [];
                  if (isBookedOrLater) {
                    cycleProcedures.forEach((cproc: any) => {
                      const rawForms: any[] = cproc.linked_forms || cproc.forms || [];
                      rawForms.forEach((f: any) => {
                        if (!matchFormToStageKeys(f).includes(stg.key)) return;
                        const tagged = { ...f, _sourceProc: cproc };
                        if (isEmbryologyForm(f)) embryologyForms.push(tagged);
                        // Anything not clearly embryology/lab defaults to Clinical —
                        // a form that matched neither used to vanish silently here
                        // (this is exactly how the "outcome" form went missing).
                        else clinicalForms.push(tagged);
                      });
                    });
                  }

                  // Every procedure code in this cycle, shown from "Booked" onward
                  // (this is what now lets IP11 AND IP222 both show up, instead of
                  // only ever the first IVF procedure found).
                  const linkedProcsForRow: any[] = isBookedOrLater ? cycleProcedures : [];

                  // Compliance/consent forms are signed once at booking, not
                  // re-shown at every later milestone — so only the "Booked" row
                  // of this cycle lists them (previously every Booked+ row
                  // repeated the full ~30-form consent list, which is what made
                  // this column feel like a wall of duplicated badges).
                  const concernForms: any[] =
                    stg.key === "Booked"
                      ? Array.from(
                          new Map(
                            cycleProcedures
                              .flatMap((cproc: any) =>
                                (cproc.linked_concern_forms || []).map((cf: any) => ({ ...cf, _sourceProc: cproc }))
                              )
                              .map((cf: any) => [cf.form_id ?? cf.form_name, cf])
                          ).values()
                        )
                      : [];

                  const primaryProc = cycleProcedures[0] || {};

                  // Ideal date = this cycle's booking date + the stage's offset (Stimulation +10,
                  // Trigger +18, OPU +20 days). Only when the cycle actually has a booking date.
                  const idealOffset = idealOffsetFor(stg.key);
                  const idealDateForRow = idealOffset !== null ? addDaysISO(cycle.date, idealOffset) : null;

                  // The "Booked" stage date is the day THIS cycle's IVF-with-Bed package was billed
                  // (not the First Consult date, not an unrelated add-on procedure).
                  const completedForRow =
                    stg.key === "Booked" && cycle.date ? fmtProcDate(cycle.date) : stg.completed_date;

                  // Actual date = when the stage really happened (only once it is done). Delayed when
                  // that date is after the ideal date — or, if not done yet, when today already is.
                  const stageDone =
                    stg.status?.toLowerCase() === "done" ||
                    stg.status?.toLowerCase() === "completed" ||
                    Boolean(stg.completed_date && stg.completed_date !== "—" && stg.completed_date !== "");
                  const actualISO = stageDone && /^\d{4}-\d{2}-\d{2}/.test(String(stg.completed_date || ""))
                    ? String(stg.completed_date).slice(0, 10)
                    : null;
                  const isDelayed = idealDateForRow
                    ? actualISO
                      ? actualISO > idealDateForRow
                      : !stageDone && todayISO > idealDateForRow
                    : false;
                  const delayKey = `${cycle.label ?? ""}|${stg.key}`;
                  const savedDelay = stageDelays[delayKey];

                  const matchedDonor =
                    donors.find((d) =>
                      stg.key.includes("OPU") || stg.key.includes("Stimulation")
                        ? d.type === "Donor"
                        : d.type === "Surrogate"
                    ) || donors[0];

                  const rowBg =
                    stg.status === "done"
                      ? "bg-green-50/20"
                      : stg.status === "current"
                      ? "bg-amber-50/40"
                      : "";

                  return (
                    <tr key={`${cIdx}-${i}`} className={`border-b border-border-soft hover:bg-surface-2/60 transition ${rowBg}`}>
                      {/* 1. Stage Name */}
                      <td className="p-3.5">
                        <div className="flex items-center gap-2">
                          <span className="flex h-6 w-6 items-center justify-center rounded-full bg-surface-2 font-bold text-text-mid text-[11px]">
                            {i + 1}
                          </span>
                          <div>
                            <div className="font-bold text-text">{stg.key}</div>
                            <div className="text-[10.5px] text-text-soft">{stg.day}</div>
                          </div>
                        </div>
                      </td>

                      {/* 2. Patient Comm */}
                      {!hiddenTracks.has("comm") && (
                        <td className="p-3.5">
                          {stg.status === "done" ? (
                            <span className="text-green-600 font-semibold text-[11px]">✓ Diet/Psy Sent</span>
                          ) : (
                            <button
                              type="button"
                              onClick={() => alert(`Sending instructions for ${stg.key}`)}
                              className="rounded border border-border px-2 py-1 text-[10.5px] font-semibold text-text-mid hover:border-primary"
                            >
                              ✉ Send Guide
                            </button>
                          )}
                        </td>
                      )}

{/* 3. IDEAL SCHEDULE & STATUS COLUMN */}
<td className="p-3.5">
  {(() => {
// Condition check: Status is 'done' OR a valid completed_date is present
const isDone =
  stg.status?.toLowerCase() === "done" ||
  stg.status?.toLowerCase() === "completed" ||
  (stg.completed_date && stg.completed_date !== "—" && stg.completed_date !== "");

return (
  <>
    <span
      className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase inline-flex items-center gap-1 ${
        isDone
          ? "bg-green-100 text-green-700 border border-green-300"
          : stg.status?.toLowerCase() === "current"
          ? "bg-amber-100 text-amber-800 border border-amber-300"
          : "bg-slate-100 text-slate-500 border border-slate-200"
      }`}
    >
      <span>{isDone ? "✓ DONE" : stg.status}</span>
    </span>

    <div className="text-[10.5px] text-text-soft mt-1.5 space-y-0.5">
      {isDone ? (
        <div className="font-semibold text-emerald-800 flex items-center gap-1">
          <span>📅</span>
          <span>{completedForRow !== "—" ? completedForRow : stg.day}</span>
        </div>
      ) : idealDateForRow ? (
        <div className="font-semibold text-slate-700 flex items-center gap-1">
          <span>📅</span>
          <span>{fmtDMY(idealDateForRow)}</span>
        </div>
      ) : (
        <div>{stg.day}</div>
      )}
      {idealDateForRow && (
        <div className="text-[10px] text-text-soft">
          {isDone ? `Ideal: ${fmtDMY(idealDateForRow)} · ` : ""}Booked +{idealOffset} days
        </div>
      )}
    </div>
  </>
);
  })()}
</td>

{/* 4. CHANGE IN PLAN COLUMN (Hide Form/Button when Status is DONE) */}
{!hiddenTracks.has("plan") && (
  <td className="p-3.5 relative">
{stg.status?.toLowerCase() === "done" || stg.status?.toLowerCase() === "completed" ? (
  <span className="text-slate-400 text-xs italic">— Stage Completed</span>
) : editingStageIdx === i ? (
  <div className="z-20 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl space-y-2 text-xs animate-in fade-in zoom-in duration-150">
    <div>
      <label className="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">FROM</label>
      <input
        type="date"
        value={dateChangeForm.fromDate}
        onChange={(e) => setDateChangeForm({ ...dateChangeForm, fromDate: e.target.value })}
        className="w-full rounded-md border border-slate-300 p-1.5 text-xs outline-none focus:border-purple-600"
      />
    </div>

    <div>
      <label className="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">TO</label>
      <input
        type="date"
        value={dateChangeForm.toDate}
        onChange={(e) => setDateChangeForm({ ...dateChangeForm, toDate: e.target.value })}
        className="w-full rounded-md border border-slate-300 p-1.5 text-xs outline-none focus:border-purple-600"
      />
    </div>

    <div>
      <label className="block text-[10px] font-bold uppercase text-slate-400 mb-0.5">
        REASON <span className="text-red-500">*</span>
      </label>
      <textarea
        rows={2}
        placeholder="Reason for change..."
        value={dateChangeForm.reason}
        onChange={(e) => setDateChangeForm({ ...dateChangeForm, reason: e.target.value })}
        className="w-full rounded-md border border-slate-300 p-1.5 text-xs outline-none focus:border-purple-600"
      />
    </div>

    <div className="flex gap-2 pt-1">
      <button
        type="button"
        onClick={() => handleSaveDateChange(i)}
        className="rounded-md bg-[#6b21a8] px-3 py-1 font-bold text-white text-[11px] hover:bg-purple-900 transition"
      >
        Save
      </button>
      <button
        type="button"
        onClick={() => setEditingStageIdx(null)}
        className="rounded-md border border-slate-300 px-3 py-1 font-bold text-slate-600 text-[11px] hover:bg-slate-100 transition"
      >
        Cancel
      </button>
    </div>
  </div>
) : (
  <button
    type="button"
    onClick={() => handleOpenDateChange(i)}
    className="rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:border-purple-600 transition shadow-sm cursor-pointer"
  >
    ✎ Change dates
  </button>
)}
  </td>
)}

{/* 5. LINKED PROCEDURE — every IVF procedure in this cycle (IP11, IP222, …),
    starting from the 'Booked' stage, instead of only ever the first match. */}
<td className="p-3.5">
  {linkedProcsForRow.length > 0 ? (
    <div>
      <div className="space-y-1.5">
        {linkedProcsForRow.map((lp: any, lpIdx: number) => (
          <div key={lpIdx}>
            <div className="font-bold text-text">{lp.procedure_name || stg.key}</div>
            <code className="bg-amber-50 text-amber-800 px-1.5 py-0.5 rounded text-[10.5px] font-bold">
              {lp.code || "IP64"}
            </code>
            {/* Each procedure's own billing date sits right beside its code — shown once, on the
                "Booked" row (where it was booked), not repeated on every later stage. */}
            {stg.key === "Booked" && lp.on_date && (
              <span className="ml-2 text-[10.5px] font-semibold text-text-soft">📅 {fmtProcDate(lp.on_date)}</span>
            )}
          </div>
        ))}
      </div>

      {/* Donor / Surrogate Trigger */}
      {matchedDonor &&
        (stg.key.includes("OPU") ||
          stg.key.includes("Stimulation") ||
          stg.key.includes("Transfer")) && (
          <div className="mt-2 space-y-1">
            <div className="inline-block rounded-md bg-slate-100 border border-slate-300 px-2 py-0.5 text-[10.5px] font-semibold text-slate-700">
              {matchedDonor.type} ▾
            </div>
            <div>
              <button
                type="button"
                onClick={() => setActiveDonorModal(matchedDonor)}
                className="inline-flex items-center gap-1 rounded-md bg-slate-200/80 px-2 py-1 text-[11px] font-bold text-blue-900 hover:bg-blue-100 hover:text-blue-800 transition"
                title="Open donor detailed page"
              >
                <span>👁</span>
                <span>{matchedDonor.donor_PatientName}</span>
              </button>
            </div>
          </div>
        )}

      {/* ⏱ Actual vs ideal date — Delayed opens the counsellor's reason popup, On Track doesn't. */}
      {idealDateForRow && (
        <div className="mt-2 flex flex-wrap items-center gap-1.5 text-[10.5px]">
          <span className="font-semibold text-text-soft">
            📅 {actualISO ? fmtDMY(actualISO) : "Actual: pending"}
          </span>
          {isDelayed ? (
            <button
              type="button"
              onClick={() =>
                openDelayModal({
                  key: delayKey,
                  cycleKey: String(cycle.label ?? ""),
                  stageKey: stg.key,
                  idealISO: idealDateForRow,
                  actualISO,
                })
              }
              className="inline-flex items-center gap-1 rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase text-red-700 transition hover:bg-red-200"
              title={readOnly ? "Delayed — click to view the reason" : "Delayed — click to record the reason"}
            >
              ⚠ Delayed{savedDelay ? ` · ${savedDelay.reason}` : ""}{savedDelay?.reply ? " · 💬" : ""}
            </button>
          ) : (
            <span className="inline-flex items-center gap-1 rounded-full border border-green-300 bg-green-100 px-2 py-0.5 text-[10px] font-bold uppercase text-green-700">
              ✓ On Track
            </span>
          )}
        </div>
      )}
    </div>
  ) : (
    <span className="text-text-soft italic text-[11px]">
      — No IVF procedure booked
    </span>
  )}
</td>

                      {/* 🔵 6. CLINICAL FORMS (daycare_procedure) */}
                      {!hideClinicalForms && !hiddenTracks.has("clinical_forms") && (
                        <td className="p-3.5 bg-blue-50/20">
                          {clinicalForms.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                              {clinicalForms.map((frm: any, fIdx: number) => {
                                const isFilledInDB = Boolean(frm.is_filled || frm.filled);
                                const formId = frm.form_id || frm.ID || "0";
                                const sourceProc = frm._sourceProc || primaryProc;
                                const unlocked = isFormUnlocked(frm, sourceProc);
                                const targetUrl = buildFormUrl(sourceProc, formId);

                                if (!unlocked) {
                                  return (
                                    <span
                                      key={fIdx}
                                      className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm bg-slate-200 text-slate-500 border border-slate-300 cursor-not-allowed"
                                      title={`Unlocks at ${frm.unlock_percent}% payment`}
                                    >
                                      <span>🔒</span>
                                      <span>{frm.form_name || frm.name}</span>
                                    </span>
                                  );
                                }

                                return (
                                  <a
                                    key={fIdx}
                                    href={targetUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm transition-transform hover:scale-105 ${
                                      isFilledInDB
                                        ? "bg-[#2563eb] text-white border border-[#1d4ed8]"
                                        : "bg-[#e11d48] text-white border border-[#be123c]"
                                    }`}
                                  >
                                    <span>{isFilledInDB ? "✓" : "✎"}</span>
                                    <span>{frm.form_name || frm.name}</span>
                                  </a>
                                );
                              })}
                            </div>
                          ) : stg.key === "First Consult" && patientDetailsUrl(patientProfile?.patient_id) ? (
                            <a
                              href={patientDetailsUrl(patientProfile?.patient_id)}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="inline-flex items-center gap-1.5 rounded-full border border-[#1d4ed8] bg-[#2563eb] px-3 py-1 text-[11px] font-bold text-white shadow-sm transition-transform hover:scale-105"
                              title="Open the patient's details page"
                            >
                              <span>👁</span>
                              <span>Patient details</span>
                            </a>
                          ) : (
                            <span className="text-text-soft italic text-[11px]">— No clinical form linked</span>
                          )}
                        </td>
                      )}

                      {/* 🟣 7. EMBRYOLOGY FORMS (lab_procedure) */}
                      {!hiddenTracks.has("embryo_forms") && (
                        <td className="p-3.5 bg-purple-50/20">
                          {embryologyForms.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                              {embryologyForms.map((frm: any, fIdx: number) => {
                                const isFilledInDB = Boolean(frm.is_filled || frm.filled);
                                const formId = frm.form_id || frm.ID || "0";
                                const sourceProc = frm._sourceProc || primaryProc;
                                const unlocked = isFormUnlocked(frm, sourceProc);
                                const targetUrl = buildFormUrl(sourceProc, formId);

                                if (!unlocked) {
                                  return (
                                    <span
                                      key={fIdx}
                                      className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm bg-slate-200 text-slate-500 border border-slate-300 cursor-not-allowed"
                                      title={`Unlocks at ${frm.unlock_percent}% payment`}
                                    >
                                      <span>🔒</span>
                                      <span>{frm.form_name || frm.name}</span>
                                    </span>
                                  );
                                }

                                return (
                                  <a
                                    key={fIdx}
                                    href={targetUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold shadow-sm transition-transform hover:scale-105 ${
                                      isFilledInDB
                                        ? "bg-[#7c3aed] text-white border border-[#6d28d9]"
                                        : "bg-[#e11d48] text-white border border-[#be123c]"
                                    }`}
                                  >
                                    <span>{isFilledInDB ? "✓" : "✎"}</span>
                                    <span>{frm.form_name || frm.name}</span>
                                  </a>
                                );
                              })}
                            </div>
                          ) : (
                            <span className="text-text-soft italic text-[11px]">— No lab form linked</span>
                          )}
                        </td>
                      )}

                      {/* 8. Financials */}
                      {!hideFinancials && !hiddenTracks.has("financial") && (
                        <td className="p-3.5 text-right">
                          <div className="font-bold text-text">
                            ₹ {(primaryProc.fees || totalGross / stages.length).toLocaleString("en-IN")}
                          </div>
                          <div
                            className={`text-[10.5px] font-semibold ${
                              stg.status === "done" ? "text-green-600" : "text-amber-600"
                            }`}
                          >
                            {stg.status === "done" ? "Paid" : "Due"}
                          </div>
                        </td>
                      )}

                      {/* 9. Compliances — linked from hms_concern_relation / hms_concern_forms,
                          combined across every procedure in this cycle (not tied to one
                          specific clinical day), not the generic procedure forms. */}
                      {!hiddenTracks.has("compliances") && (
                        <td className="p-3.5 text-center">
                          {stg.key === "First Consult" && patientKycUrl(patientProfile?.patient_id) && (
                            <a
                              href={patientKycUrl(patientProfile?.patient_id)}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="mb-1.5 inline-flex items-center gap-1.5 rounded-full border border-[#1d4ed8] bg-[#2563eb] px-3 py-1 text-[11px] font-bold text-white shadow-sm transition-transform hover:scale-105"
                              title="Open the patient's KYC page"
                            >
                              <span>🪪</span>
                              <span>KYC</span>
                            </a>
                          )}
                          {concernForms.length > 0 ? (
                            <div className="flex flex-wrap justify-center gap-1.5">
                              {concernForms.map((cf: any, cfIdx: number) => (
                                <a
                                  key={cf.form_id || cfIdx}
                                  href={buildFormUrl(cf._sourceProc || primaryProc, cf.form_id, "check_concern_form")}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="inline-flex items-center gap-1 rounded bg-blue-50 border border-blue-200 px-2 py-1 text-[10.5px] font-bold text-blue-800 shadow-sm transition-transform hover:scale-105 hover:bg-blue-100"
                                  title={cf.form_for || cf.type || ""}
                                >
                                  <span>✎</span>
                                  <span>{cf.form_name}</span>
                                </a>
                              ))}
                            </div>
                          ) : stg.key === "First Consult" && patientKycUrl(patientProfile?.patient_id) ? null : (
                            <span className="text-text-soft text-[11px]">—</span>
                          )}
                        </td>
                      )}
                    </tr>
                  );
                    })}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </div>
        );
      })()}

      {/* 🗂 NON-IVF PROCEDURES — category-wise forms & financials.
          These procedures have no stage-by-stage IVF flow, so each category gets its own
          section listing every procedure with its linked forms, payment status and consents. */}
      {patientProfile && !loading && nonIvfProcedures.length > 0 && (() => {
        const nonIvfColCount =
          2 + // Procedure + Booked date
          (!hideClinicalForms && !hiddenTracks.has("clinical_forms") ? 1 : 0) +
          (!hiddenTracks.has("embryo_forms") ? 1 : 0) +
          (!hideFinancials && !hiddenTracks.has("financial") ? 1 : 0) +
          (!hiddenTracks.has("compliances") ? 1 : 0);

        return (
          <div className="mt-6 rounded-[14px] border border-border bg-surface shadow-card overflow-hidden">
            <div className="border-b border-border px-5 py-4 flex justify-between items-center bg-surface-2">
              <h3 className="font-bold text-sm">
                Other Procedures &amp; Services — by Category ({nonIvfProcedures.length})
              </h3>
              <span className="text-[11px] text-text-soft">Not part of an IVF cycle flow</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-surface border-b border-border font-semibold text-text-mid">
                  <tr>
                    <th className="p-3.5">Procedure</th>
                    <th className="p-3.5">Booked On</th>
                    {!hideClinicalForms && !hiddenTracks.has("clinical_forms") && (
                      <th className="p-3.5 text-blue-700 bg-blue-50/50">Clinical Forms</th>
                    )}
                    {!hiddenTracks.has("embryo_forms") && (
                      <th className="p-3.5 text-purple-700 bg-purple-50/50">Embryology Forms</th>
                    )}
                    {!hideFinancials && !hiddenTracks.has("financial") && <th className="p-3.5 text-right">Financials</th>}
                    {!hiddenTracks.has("compliances") && <th className="p-3.5 text-center">Compliances</th>}
                  </tr>
                </thead>
                <tbody>
                  {nonIvfCategories.map((cat) => {
                    const list = nonIvfCategoryMap.get(cat)!;
                    const key = `nonivf:${cat}`;
                    const collapsed = collapsedCats.has(key);
                    return (
                      <Fragment key={key}>
                        <tr
                          onClick={() => toggleCat(key)}
                          className="cursor-pointer border-b border-border bg-slate-100 hover:bg-slate-200/70 transition"
                          title={collapsed ? "Click to expand category" : "Click to collapse category"}
                        >
                          <td colSpan={nonIvfColCount} className="p-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-700">
                            <span className={`mr-2 inline-block transition-transform ${collapsed ? "" : "rotate-90"}`}>▶</span>
                            {cat}
                            <span className="ml-2 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-text-soft normal-case">
                              {list.length} {list.length === 1 ? "procedure" : "procedures"}
                            </span>
                            {!hideFinancials && (
                              <span className="ml-3 normal-case font-semibold text-text-mid">
                                ₹ {sumOf(list, "fees").toLocaleString("en-IN")} billed · ₹{" "}
                                {sumOf(list, "payment_done").toLocaleString("en-IN")} received ·{" "}
                                <span className={sumOf(list, "pending") > 0 ? "text-red-500" : "text-green-600"}>
                                  ₹ {sumOf(list, "pending").toLocaleString("en-IN")} pending
                                </span>
                              </span>
                            )}
                          </td>
                        </tr>
                        {!collapsed &&
                          list.map((proc: any, pIdx: number) => {
                            const rawForms: any[] = proc.linked_forms || proc.forms || [];
                            const clinical = rawForms.filter((f: any) => !isEmbryologyForm(f));
                            const embryo = rawForms.filter(isEmbryologyForm);
                            const consents: any[] = proc.linked_concern_forms || [];
                            const paid = !(proc.pending > 0);
                            return (
                              <tr
                                key={`${key}-${proc.procedure_entry_id ?? proc.ID ?? pIdx}`}
                                className="border-b border-border-soft hover:bg-surface-2/60 transition align-top"
                              >
                                <td className="p-3.5">
                                  <div className="font-bold text-text">{proc.procedure_name || "—"}</div>
                                  <span className="mt-1 inline-block rounded bg-amber-100 border border-amber-300 px-2 py-0.5 text-[10.5px] font-bold text-amber-900">
                                    {proc.code || "—"}
                                  </span>
                                </td>
                                <td className="p-3.5 font-semibold text-slate-600">{proc.on_date || "—"}</td>
                                {!hideClinicalForms && !hiddenTracks.has("clinical_forms") && (
                                  <td className="p-3.5 bg-blue-50/20">{renderFormPills(clinical, proc, "clinical")}</td>
                                )}
                                {!hiddenTracks.has("embryo_forms") && (
                                  <td className="p-3.5 bg-purple-50/20">{renderFormPills(embryo, proc, "embryology")}</td>
                                )}
                                {!hideFinancials && !hiddenTracks.has("financial") && (
                                  <td className="p-3.5 text-right">
                                    <div className="font-bold text-text">₹ {(Number(proc.fees) || 0).toLocaleString("en-IN")}</div>
                                    <div className={`text-[10.5px] font-semibold ${paid ? "text-green-600" : "text-amber-600"}`}>
                                      {paid ? "Paid" : `Due ₹ ${(Number(proc.pending) || 0).toLocaleString("en-IN")}`}
                                    </div>
                                  </td>
                                )}
                                {!hiddenTracks.has("compliances") && (
                                  <td className="p-3.5 text-center">
                                    {consents.length > 0 ? (
                                      <details className="inline-block text-left">
                                        <summary className="cursor-pointer text-[11px] font-semibold text-blue-700">
                                          {consents.length} consent form{consents.length === 1 ? "" : "s"}
                                        </summary>
                                        <div className="mt-1.5 flex max-w-[360px] flex-wrap gap-1.5">
                                          {consents.map((cf: any, cfIdx: number) => (
                                            <a
                                              key={cf.form_id || cfIdx}
                                              href={buildFormUrl(proc, cf.form_id, "check_concern_form")}
                                              target="_blank"
                                              rel="noopener noreferrer"
                                              className="inline-flex items-center gap-1 rounded bg-blue-50 border border-blue-200 px-2 py-1 text-[10.5px] font-bold text-blue-800 shadow-sm transition-transform hover:scale-105 hover:bg-blue-100"
                                              title={cf.form_for || cf.type || ""}
                                            >
                                              <span>✎</span>
                                              <span>{cf.form_name}</span>
                                            </a>
                                          ))}
                                        </div>
                                      </details>
                                    ) : (
                                      <span className="text-text-soft text-[11px]">—</span>
                                    )}
                                  </td>
                                )}
                              </tr>
                            );
                          })}
                      </Fragment>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        );
      })()}

      {/* ⏱ DELAY REASON POPUP (counsellor) — only reachable from a Delayed stage badge */}
      {delayNotice && (
        <div
          className={`fixed bottom-5 right-5 z-[70] max-w-sm rounded-lg px-4 py-3 text-xs font-semibold shadow-xl ${
            delayNotice.tone === "ok" ? "bg-green-100 text-green-800" : "bg-amber-100 text-amber-800"
          }`}
        >
          {delayNotice.text}
        </div>
      )}

      {delayModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div className="flex items-start justify-between border-b pb-3 mb-4">
              <div>
                <h3 className="text-lg font-bold text-slate-800">Why is this stage delayed?</h3>
                <p className="mt-0.5 text-xs text-slate-500">
                  <b>{delayModal.stageKey}</b> · Ideal {fmtDMY(delayModal.idealISO)} ·{" "}
                  {delayModal.actualISO ? `Actual ${fmtDMY(delayModal.actualISO)}` : "Not done yet"}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setDelayModal(null)}
                className="text-slate-400 hover:text-slate-600 text-xl font-bold"
              >
                ✕
              </button>
            </div>

            <div className="space-y-2">
              {DELAY_REASONS.map((r) => (
                <label
                  key={r}
                  className={`flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm font-semibold transition ${
                    delayReason === r ? "border-primary bg-primary-soft text-primary-dark" : "border-border text-text-mid hover:border-primary"
                  }`}
                >
                  <input
                    type="radio"
                    name="delay-reason"
                    value={r}
                    checked={delayReason === r}
                    disabled={readOnly}
                    onChange={() => setDelayReason(r)}
                  />
                  {r}
                </label>
              ))}
            </div>

            <textarea
              value={delayRemarks}
              onChange={(e) => setDelayRemarks(e.target.value)}
              disabled={readOnly}
              placeholder={delayReason === "Other" ? "Describe the reason (required for Other)" : "Remarks (optional)"}
              rows={3}
              className="mt-3 w-full rounded-lg border border-border px-3 py-2 text-xs outline-none focus:border-primary"
            />
            {delayError && <p className="mt-2 text-xs font-semibold text-red-600">{delayError}</p>}

            {stageDelays[delayModal.key]?.reply && (
              <div className="mt-3 rounded-lg border border-primary/30 bg-primary-soft px-3 py-2 text-xs text-primary-dark">
                <div className="mb-0.5 font-bold">
                  💬 Administrator’s reply
                  {stageDelays[delayModal.key]?.repliedBy ? ` · ${stageDelays[delayModal.key]?.repliedBy}` : ""}
                  {stageDelays[delayModal.key]?.repliedAt ? ` · ${stageDelays[delayModal.key]?.repliedAt}` : ""}
                </div>
                <div className="whitespace-pre-line">{stageDelays[delayModal.key]?.reply}</div>
              </div>
            )}

            {isAdminRole && stageDelays[delayModal.key] && (
              <div className="mt-3 rounded-lg border border-border p-3">
                <div className="mb-1 text-xs font-bold text-slate-700">Reply (Administrator)</div>
                <textarea
                  value={replyText}
                  onChange={(e) => setReplyText(e.target.value)}
                  rows={2}
                  placeholder="Write your reply — it is mailed to the person who recorded this reason"
                  className="w-full rounded-lg border border-border px-3 py-2 text-xs outline-none focus:border-primary"
                />
                <div className="mt-2 flex justify-end">
                  <button
                    type="button"
                    disabled={replyBusy || !replyText.trim()}
                    onClick={async () => {
                      if (!delayModal) return;
                      setReplyBusy(true);
                      setDelayError("");
                      try {
                        const res = await fetch("/api/stage-delay", {
                          method: "POST",
                          headers: { "Content-Type": "application/json" },
                          body: JSON.stringify({
                            op: "reply",
                            patient_id: patientProfile?.patient_id,
                            cycle_key: delayModal.cycleKey,
                            stage_key: delayModal.stageKey,
                            reply: replyText.trim(),
                            user: sessionIdentity().user,
                            role: userRole || "",
                          }),
                        });
                        const data = await res.json();
                        if (!res.ok || data.status !== "success") throw new Error(data.message || "Could not send the reply.");
                        setStageDelays((prev) => ({
                          ...prev,
                          [delayModal.key]: { ...(prev[delayModal.key] || { reason: "", remarks: "" }), reply: data.reply, repliedBy: data.repliedBy, repliedAt: "just now" },
                        }));
                        setReplyText("");
                        mailNotice(data.mail, "the person who recorded the reason");
                      } catch (e: any) {
                        setDelayError(e?.message || "Could not send the reply.");
                      } finally {
                        setReplyBusy(false);
                      }
                    }}
                    className="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-50"
                  >
                    {replyBusy ? "Sending…" : "Send reply"}
                  </button>
                </div>
              </div>
            )}

            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setDelayModal(null)}
                className="rounded-lg border border-border px-4 py-2 text-xs font-semibold text-text-mid hover:border-primary"
              >
                Cancel
              </button>
              {!readOnly && (
              <button
                type="button"
                onClick={saveDelayReason}
                disabled={delaySaving}
                className="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-60"
              >
                {delaySaving ? "Saving…" : "Save reason"}
              </button>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 💵 PARTIAL PAYMENT LEDGER BREAKDOWN MODAL */}
      {activePaymentModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in duration-150">
            <div className="flex items-center justify-between border-b pb-3 mb-4">
              <div>
                <div className="flex items-center gap-2">
                  <span className="rounded bg-amber-100 border border-amber-300 px-2 py-0.5 text-xs font-bold text-amber-900">
                    {activePaymentModal.code}
                  </span>
                  <h3 className="text-lg font-bold text-slate-800">{activePaymentModal.procedureName}</h3>
                </div>
                <p className="text-xs text-slate-500 mt-0.5">
                  Receipt No: <b>{activePaymentModal.receiptNo}</b>
                </p>
              </div>
              <button
                type="button"
                onClick={() => setActivePaymentModal(null)}
                className="text-slate-400 hover:text-slate-600 text-xl font-bold"
              >
                ✕
              </button>
            </div>

            <div className="grid grid-cols-3 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs mb-4">
              <div>
                <span className="text-slate-500 block text-[10px] font-bold uppercase">Total Billed</span>
                <strong className="text-slate-800 text-sm">
                  ₹ {activePaymentModal.fees.toLocaleString("en-IN")}
                </strong>
              </div>
              <div>
                <span className="text-slate-500 block text-[10px] font-bold uppercase">Total Received</span>
                <strong className="text-green-600 text-sm">
                  ₹ {activePaymentModal.paymentDone.toLocaleString("en-IN")}
                </strong>
              </div>
              <div>
                <span className="text-slate-500 block text-[10px] font-bold uppercase">Pending Balance</span>
                <strong
                  className={`text-sm ${activePaymentModal.pending > 0 ? "text-red-500" : "text-green-600"}`}
                >
                  ₹ {activePaymentModal.pending.toLocaleString("en-IN")}
                </strong>
              </div>
            </div>

            <div className="overflow-x-auto border border-slate-200 rounded-xl">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-100 font-bold text-slate-700 border-b">
                  <tr>
                    <th className="p-2.5">#</th>
                    <th className="p-2.5">Payment Date</th>
                    <th className="p-2.5">Receipt / Transaction No</th>
                    <th className="p-2.5">Payment Mode</th>
                    <th className="p-2.5 text-right">Received Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {activePaymentModal.breakups.length > 0 ? (
                    activePaymentModal.breakups.map((item, pIdx) => (
                      <tr key={pIdx} className="border-b hover:bg-slate-50">
                        <td className="p-2.5 font-bold text-slate-400">{pIdx + 1}</td>
                        <td className="p-2.5 font-medium text-slate-700">{item.date || "—"}</td>
                        <td className="p-2.5 text-slate-600 font-mono">{item.receipt_no || "—"}</td>
                        <td className="p-2.5">
                          <span className="rounded bg-blue-50 text-blue-700 px-2 py-0.5 font-bold text-[10.5px]">
                            {item.mode || "Cash"}
                          </span>
                        </td>
                        <td className="p-2.5 text-right font-bold text-green-600">
                          ₹ {item.amount?.toLocaleString("en-IN")}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="p-6 text-center text-slate-400 italic">
                        No partial payment records found for this receipt.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end pt-4 mt-2 border-t">
              <button
                type="button"
                onClick={() => setActivePaymentModal(null)}
                className="rounded-lg bg-slate-800 px-5 py-2 text-xs font-bold text-white hover:bg-slate-900 transition"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 📋 DONOR / SURROGATE PROFILE MODAL */}
      {activeDonorModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl animate-in fade-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b pb-4 mb-4">
              <div className="flex items-center gap-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#1e3a8a] text-white font-bold text-lg">
                  {activeDonorModal.donor_PatientName.split(" ").map((n: string) => n[0]).join("")}
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <span className="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800 uppercase">
                      EGG {activeDonorModal.type.toUpperCase()} · {activeDonorModal.donor_patient_id}
                    </span>
                    <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-[10px] font-bold text-green-700">
                      {activeDonorModal.status}
                    </span>
                  </div>
                  <h2 className="text-xl font-bold text-slate-800 mt-0.5">
                    {activeDonorModal.donor_PatientName}
                  </h2>
                  <p className="text-xs text-slate-500">
                    {activeDonorModal.age} yrs · Blood Group {activeDonorModal.blood_group} · Height {activeDonorModal.height} · Weight {activeDonorModal.weight} · AMH {activeDonorModal.amh}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setActiveDonorModal(null)}
                className="text-slate-400 hover:text-slate-600 text-2xl font-bold"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4 text-xs text-slate-700">
              <div className="rounded-xl bg-slate-50 p-4 border border-slate-200">
                <h4 className="font-bold uppercase tracking-wider text-pink-700 text-[10.5px] mb-3">
                  DONOR PROFILE
                </h4>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <span className="block font-bold text-slate-400 text-[10px] uppercase">PHENOTYPE</span>
                    <strong className="text-slate-800">Wheatish · Black hair · Brown eyes</strong>
                  </div>
                  <div>
                    <span className="block font-bold text-slate-400 text-[10px] uppercase">EDUCATION</span>
                    <strong className="text-slate-800">{activeDonorModal.education}</strong>
                  </div>
                  <div>
                    <span className="block font-bold text-slate-400 text-[10px] uppercase">OCCUPATION</span>
                    <strong className="text-slate-800">{activeDonorModal.occupation}</strong>
                  </div>
                  <div>
                    <span className="block font-bold text-slate-400 text-[10px] uppercase">MARITAL STATUS</span>
                    <strong className="text-slate-800">{activeDonorModal.marital_status}</strong>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-4 border-t mt-4">
              <button
                type="button"
                onClick={() => setActiveDonorModal(null)}
                className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
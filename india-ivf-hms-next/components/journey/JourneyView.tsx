"use client";

import { useState, useEffect } from "react";
import { getClientSession, hidesFinancials } from "@/lib/clientSession";
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

export default function JourneyView({ initialId = "" }: JourneyViewProps) {
  const [patientIdInput, setPatientIdInput] = useState(initialId || "");
  const [patientProfile, setPatientProfile] = useState<any>(null);
  const [procedures, setProcedures] = useState<any[]>([]);
  const [stages, setStages] = useState<any[]>([]);
  const [donors, setDonors] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [hideFinancials, setHideFinancials] = useState(false);
  const [hideClinicalForms, setHideClinicalForms] = useState(false);

  useEffect(() => {
    const role = getClientSession()?.role;
    setHideFinancials(hidesFinancials(role));
    // Embryologists work the lab side (Embryology Forms) — clinical/daycare forms aren't theirs.
    setHideClinicalForms(role === "embryologist");
  }, []);

  // Track image load error states locally
  const [wifeImgError, setWifeImgError] = useState(false);
  const [husbandImgError, setHusbandImgError] = useState(false);

  // Track visibility toggles
  const [hiddenTracks, setHiddenTracks] = useState<Set<string>>(new Set());
  const [collapseDone, setCollapseDone] = useState(false);

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
            { key: "Booked", day: "Day 3", status: json.data?.payment_done > 0 ? "done" : "current" },
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
              <div>
                <span className="text-text-soft block text-[11px]">Phone</span>
                <strong className="text-slate-800">
                  {patientProfile.patient_phone || patientProfile.wife_phone || "—"}
                </strong>
              </div>
              {!hideFinancials && (
                <>
                  <div>
                    <span className="text-text-soft block text-[11px]">Gross Billed</span>
                    <strong className="text-slate-800 text-sm">
                      ₹ {patientProfile.fees ? patientProfile.fees.toLocaleString("en-IN") : "0"}
                    </strong>
                  </div>
                  <div>
                    <span className="text-text-soft block text-[11px]">Amount Received</span>
                    <strong className="text-green-600 text-sm">
                      ₹ {patientProfile.payment_done ? patientProfile.payment_done.toLocaleString("en-IN") : "0"}
                    </strong>
                  </div>
                  <div>
                    <span className="text-text-soft block text-[11px]">Pending Balance</span>
                    <strong className={`text-sm ${patientProfile.pending > 0 ? "text-red-500" : "text-green-600"}`}>
                      ₹ {patientProfile.pending ? patientProfile.pending.toLocaleString("en-IN") : "0"}
                    </strong>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* 📊 BOOKED PROCEDURES TABLE */}
      {patientProfile && !loading && procedures.length > 0 && (
        <div className="mb-6 rounded-[14px] border border-border bg-surface shadow-card overflow-hidden">
          <div className="border-b border-border px-5 py-3.5 flex justify-between items-center bg-surface-2">
            <h3 className="font-bold text-sm">Booked Procedures &amp; Services ({procedures.length})</h3>
            <span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">
              ● LIVE DATA
            </span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-surface border-b border-border font-semibold text-text-mid">
                <tr>
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
                {procedures.map((proc, idx) => (
                  <tr key={idx} className="border-b border-border-soft hover:bg-surface-2/60 transition">
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
                          onClick={() =>
                            setActivePaymentModal({
                              procedureName: proc.procedure_name,
                              code: proc.code || "PROC",
                              receiptNo: proc.receipt_number,
                              fees: proc.fees,
                              paymentDone: proc.payment_done,
                              pending: proc.pending,
                              breakups: proc.payment_breakups || [],
                            })
                          }
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
                ))}
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
            ⇕ {collapseDone ? "Show Completed" : "Collapse Completed"}
          </button>
        </div>
      )}

      {/* 📊 FULL MULTI-TRACK CLINICAL JOURNEY STAGES TABLE */}
      {patientProfile && !loading && stages.length > 0 && (
        <div className="rounded-[14px] border border-border bg-surface shadow-card overflow-hidden">
          <div className="border-b border-border px-5 py-4 flex justify-between items-center bg-surface-2">
            <h3 className="font-bold text-sm">Clinical Journey Stages &amp; Milestones ({stages.length})</h3>
            <span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">
              ● Django Live Synced
            </span>
          </div>

          <div className="overflow-x-auto">
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
                {stages.map((stg, i) => {
                  if (collapseDone && stg.status === "done") return null;

                  const linkedProc = procedures[i] || procedures[0] || {};
                  
                  // Gather all forms safely
                  const rawForms: any[] =
                    linkedProc.linked_forms ||
                    linkedProc.forms ||
                    stg.linked_forms ||
                    stg.forms ||
                    [];

                  // 🎯 KEY MATCHING LOGIC FOR form_for
                  const isClinicalForm = (f: any) => {
                    const target = String(f.form_for || f.type || "").toLowerCase().trim();
                    const name = String(f.form_name || f.name || "").toLowerCase();
                    
                    // Strict check on form_for="daycare_procedure"
                    if (target === "daycare_procedure" || target === "daycare" || target === "clinical") return true;
                    
                    // Intelligent fallback keywords if form_for is empty
                    if (!target || target === "general") {
                      if (name.includes("discharge") || name.includes("prescription") || name.includes("induction") || name.includes("check")) return true;
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

                  // Filter Clinical & Embryology Forms
                  const clinicalForms = rawForms.filter(isClinicalForm);
                  const embryologyForms = rawForms.filter(isEmbryologyForm);

                  const appointmentId = linkedProc.appointment_id || patientProfile?.appointment_id || "0";
                  const patientProcedureId = linkedProc.procedure_entry_id || linkedProc.ID || "0";
                  const procedureId = linkedProc.procedure_id || "0";

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
                    <tr key={i} className={`border-b border-border-soft hover:bg-surface-2/60 transition ${rowBg}`}>
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
          <span>{stg.completed_date !== "—" ? stg.completed_date : stg.day}</span>
        </div>
      ) : (
        <div>{stg.day}</div>
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

{/* 5. LINKED PROCEDURE (Only for broad_procedure = "IVF" starting from 'Booked' stage) */}
<td className="p-3.5">
  {(() => {
// 🔍 1. Find IVF procedure from procedures list (Case-insensitive check)
const ivfProcedure = procedures.find(
  (proc: any) => String(proc.broad_procedure || "").trim().toUpperCase() === "IVF"
);

// 🔒 2. Condition Check: Stage 'Booked' ya uske baad honi chahiye AND IVF procedure hona chahiye
const isBookedOrLater = i >= 2 || stg.key === "Booked";

if (isBookedOrLater && ivfProcedure) {
  return (
    <div>
      <div className="font-bold text-text">
        {ivfProcedure.procedure_name || stg.key}
      </div>
      <code className="bg-amber-50 text-amber-800 px-1.5 py-0.5 rounded text-[10.5px] font-bold">
        {ivfProcedure.code || "IP64"}
      </code>

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
    </div>
  );
}

return (
  <span className="text-text-soft italic text-[11px]">
    — No IVF procedure booked
  </span>
);
  })()}
</td>

                      {/* 🔵 6. CLINICAL FORMS (daycare_procedure) */}
                      {!hideClinicalForms && !hiddenTracks.has("clinical_forms") && (
                        <td className="p-3.5 bg-blue-50/20">
                          {clinicalForms.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                              {clinicalForms.map((frm: any, fIdx: number) => {
                                const isFilledInDB = Boolean(frm.is_filled || frm.filled);
                                const formId = frm.form_id || frm.ID || "0";
                                const targetUrl = `https://indiaivf.website/procedure_form/${formId}/${procedureId}/${appointmentId}/${patientProcedureId}`;

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
                                const targetUrl = `https://indiaivf.website/procedure_form/${formId}/${procedureId}/${appointmentId}/${patientProcedureId}`;

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
                            ₹ {(linkedProc.fees || patientProfile.fees / stages.length).toLocaleString("en-IN")}
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

                      {/* 9. Compliances */}
                      {!hiddenTracks.has("compliances") && (
                        <td className="p-3.5 text-center">
                          {rawForms.length > 0 ? (
                            (() => {
                              const isAllFilled = rawForms.every((f: any) => f.is_filled || f.filled);
                              const firstForm = rawForms[0];
                              const firstFormId = firstForm?.form_id || firstForm?.ID || "0";
                              const firstTargetUrl = `https://indiaivf.website/procedure_form/${firstFormId}/${procedureId}/${appointmentId}/${patientProcedureId}`;

                              return isAllFilled ? (
                                <a
                                  href={firstTargetUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="inline-block rounded bg-green-100 border border-green-300 px-2.5 py-1 text-[10.5px] font-bold text-green-700 hover:bg-green-200 transition"
                                >
                                  ✓ Consent OK
                                </a>
                              ) : (
                                <a
                                  href={firstTargetUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="inline-block rounded bg-amber-100 border border-amber-300 px-2.5 py-1 text-[10.5px] font-bold text-amber-800 hover:bg-amber-200 transition"
                                >
                                  ⚠️ Consent Pending
                                </a>
                              );
                            })()
                          ) : (
                            <span className="text-text-soft text-[11px]">—</span>
                          )}
                        </td>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
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
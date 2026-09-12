"use client";

import { useMemo, useState, useEffect } from "react";
import Link from "next/link";
import { getClientScopedCenterId, getClientSession, hidesFinancials } from "@/lib/clientSession";

const inputCls = "w-full rounded-[9px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary";

export default function BookedPatientList() {
  const [patients, setPatients] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [hideFinancials, setHideFinancials] = useState(false);

  const [f, setF] = useState({
    search: "",
    centre: "",
  });

  useEffect(() => {
    setHideFinancials(hidesFinancials(getClientSession()?.role));
  }, []);

  // 1. Live Dynamic Fetching from Django Backend API
  const fetchDynamicPatients = async () => {
    try {
      setLoading(true);
      setError("");
      const centerId = getClientScopedCenterId();
      const qs = centerId ? `?center_id=${centerId}` : "";
      const res = await fetch(`/api/booked-patients${qs}`, {
        cache: "no-store",
      });

      if (!res.ok) throw new Error(`HTTP Error: ${res.status}`);

      const data = await res.json();
      setPatients(Array.isArray(data) ? data : []);
    } catch (err: any) {
      console.error("API Fetch Error:", err);
      setError("Django API Connection Failed! Ensure the backend server is running.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDynamicPatients();
  }, []);

  // Filter Unique Centres
  const centres = useMemo(() => Array.from(new Set(patients.map((p) => p.center_name).filter(Boolean))).sort(), [patients]);

  // Filtered Rows
  const filteredRows = useMemo(() => {
    return patients.filter((p) => {
      if (f.centre && p.center_name !== f.centre) return false;
      if (f.search) {
        const q = f.search.toLowerCase();
        const searchHay = `${p.name} ${p.husband_name} ${p.patient_id} ${p.doctor_name}`.toLowerCase();
        if (!searchHay.includes(q)) return false;
      }
      return true;
    });
  }, [patients, f]);

  // Export CSV Function
  function exportCsv() {
    const headers = ["Patient ID", "Wife Name", "Husband Name", "Appointment Date", "Centre", "Doctor", "Procedure Code"];
    if (!hideFinancials) headers.push("Gross Fees", "Paid Amount", "Pending Amount");
    const lines = [headers.join(",")];
    filteredRows.forEach((p) => {
      const cells = [p.patient_id, p.name, p.husband_name || "—", p.date || "—", p.center_name || "—", p.doctor_name || "—", p.code || "—"];
      if (!hideFinancials) cells.push(p.fees, p.total_payment_done, p.pending_amount);
      lines.push(cells.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","));
    });
    const blob = new Blob([lines.join("\n")], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "live-booked-patients.csv";
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  return (
    <section className="screen-enter max-w-[1600px] mx-auto">
      {/* Header Title Bar */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-text">Booked Patient List</h1>
          <p className="text-xs text-text-soft">Real-time dynamic data synced directly from Django MySQL Engine</p>
        </div>
        <div className="flex items-center gap-2">
          <span className="rounded-full bg-green-soft px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green">
            ● Live Data
          </span>
          <button
            type="button"
            onClick={fetchDynamicPatients}
            className="rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary"
          >
            🔄 Sync Live DB
          </button>
          <button
            type="button"
            onClick={exportCsv}
            className="rounded-[9px] border border-border bg-surface px-3 py-1.5 text-[11.5px] font-semibold text-text-mid hover:border-primary hover:text-primary"
          >
            Export CSV
          </button>
        </div>
      </div>

      {/* Search & Filters */}
      <div className="mb-6 rounded-[12px] border border-border bg-surface p-4 shadow-card">
        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
          <div className="sm:col-span-2">
            <label className="mb-1.5 block text-[11.5px] font-semibold text-text-mid">
              Search Patient Name, ID, or Doctor
            </label>
            <input
              type="search"
              placeholder="Type patient name, husband name, or ID..."
              value={f.search}
              onChange={(e) => setF({ ...f, search: e.target.value })}
              className={inputCls}
            />
          </div>
          <div>
            <label className="mb-1.5 block text-[11.5px] font-semibold text-text-mid">Filter Centre</label>
            <select
              value={f.centre}
              onChange={(e) => setF({ ...f, centre: e.target.value })}
              className={inputCls}
            >
              <option value="">All Centres ({centres.length})</option>
              {centres.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Dynamic Table Card */}
      <div className="rounded-[12px] border border-border bg-surface shadow-card overflow-hidden">
        <div className="border-b border-border px-5 py-3.5 flex justify-between items-center">
          <h3 className="font-bold text-sm">Patients Registry ({filteredRows.length})</h3>
          {loading && <span className="text-xs text-text-soft animate-pulse">Syncing MySQL Tables...</span>}
        </div>

        {error ? (
          <div className="p-6 text-center text-red-500 bg-red-50 text-xs font-semibold">{error}</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[12.5px]">
              <thead className="bg-surface-2 border-b border-border font-semibold text-text-mid">
                <tr>
                  <th className="p-3">Patient ID</th>
                  <th className="p-3">Wife Name</th>
                  <th className="p-3">Husband Name</th>
                  <th className="p-3">Appointment Date</th>
                  <th className="p-3">Centre</th>
                  <th className="p-3">Doctor</th>
                  <th className="p-3">Procedure Code</th>
                  {!hideFinancials && (
                    <>
                      <th className="p-3 text-right">Gross Fees</th>
                      <th className="p-3 text-right">Received</th>
                      <th className="p-3 text-right">Pending</th>
                    </>
                  )}
                  <th className="p-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.length === 0 ? (
                  <tr>
                    <td colSpan={hideFinancials ? 8 : 11} className="p-8 text-center text-text-soft">
                      {loading ? "Loading dynamic records..." : "No matching booked patients found in live database."}
                    </td>
                  </tr>
                ) : (
                  filteredRows.map((p, idx) => (
                    <tr key={idx} className="border-b border-border-soft hover:bg-surface-2/50 transition">
                      <td className="p-3 font-semibold text-primary">{p.patient_id}</td>
                      <td className="p-3 font-bold text-text">{p.name}</td>
                      <td className="p-3 text-text-mid">{p.husband_name || "—"}</td>
                      <td className="p-3 text-text-soft">{p.date || "—"}</td>
                      <td className="p-3">{p.center_name || "—"}</td>
                      <td className="p-3">{p.doctor_name || "—"}</td>
                      <td className="p-3">
                        <span className="rounded bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                          {p.code || "—"}
                        </span>
                      </td>
                      {!hideFinancials && (
                        <>
                          <td className="p-3 text-right font-medium">₹ {p.fees.toLocaleString('en-IN')}</td>
                          <td className="p-3 text-right font-bold text-green-600">
                            ₹ {p.total_payment_done.toLocaleString('en-IN')}
                          </td>
                          <td className={`p-3 text-right font-bold ${p.pending_amount > 0 ? "text-red-500" : "text-green-600"}`}>
                            ₹ {p.pending_amount.toLocaleString('en-IN')}
                          </td>
                        </>
                      )}
                      <td className="p-3 text-center">
                        <Link
                          href={`/journey?id=${p.patient_id}`}
                          className="inline-block rounded-md bg-primary px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-primary-dark"
                        >
                          View Journey →
                        </Link>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </section>
  );
}
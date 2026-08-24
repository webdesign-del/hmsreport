"use client";

import { useState } from "react";
import type { FormDef } from "@/lib/journey-forms";

export default function JourneyFormModal({
  stageName,
  patientName,
  def,
  initialValues,
  onSave,
  onClose,
}: {
  stageName: string;
  patientName: string;
  def: FormDef;
  initialValues: Record<number, string>;
  onSave: (values: Record<number, string>, done: boolean) => void;
  onClose: () => void;
}) {
  const [values, setValues] = useState<Record<number, string>>(initialValues);
  const filled = def.f.length > 0 && def.f.every((_, ix) => (values[ix] || "").trim() !== "");

  return (
    <div className="fixed inset-0 z-[110] flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()} className="max-h-[85vh] w-full max-w-[480px] overflow-y-auto rounded-[16px] bg-surface shadow-2xl">
        <div className="flex items-start justify-between border-b border-border-soft p-4">
          <div>
            <div className="text-[10.5px] font-semibold uppercase tracking-[.03em] text-text-soft">
              {stageName} · {patientName}
            </div>
            <h3 className="font-display text-base font-semibold text-text">{def.l}</h3>
          </div>
          <button type="button" onClick={onClose} aria-label="Close" className="text-xl text-text-soft hover:text-text">
            ×
          </button>
        </div>
        <div className="flex flex-col gap-3 p-4">
          {def.f.map((lbl, ix) => (
            <div key={ix}>
              <label className="mb-1 block text-[12px] font-semibold text-text-mid">
                {lbl} <span className="text-red">✱</span>
              </label>
              <input
                className="w-full rounded-[9px] border border-border bg-surface px-3 py-2 text-[12.5px] outline-none focus:border-primary"
                value={values[ix] || ""}
                onChange={(e) => setValues((v) => ({ ...v, [ix]: e.target.value }))}
                placeholder={`Enter ${lbl.toLowerCase()}`}
              />
            </div>
          ))}
        </div>
        <div className="flex items-center gap-3 border-t border-border-soft p-4">
          <span className="text-[11px] text-text-soft">{filled ? "All mandatory fields filled → saves as Complete (blue)." : "Fill all ✱ fields to mark Complete (otherwise stays Open / pink)."}</span>
          <button type="button" onClick={() => onSave(values, filled)} className="ml-auto rounded-[9px] bg-primary px-4 py-2 text-[12.5px] font-semibold text-white">
            Save
          </button>
        </div>
      </div>
    </div>
  );
}

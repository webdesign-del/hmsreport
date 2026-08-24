/** Mapped clinical / embryology forms per journey stage, gated by payment-unlock %. */
export interface FormDef {
  l: string;
  f: string[];
}

export const JFORM_DEFS: Record<string, FormDef> = {
  c_kyc: { l: "KYC", f: ["Patient full name", "Government ID type", "ID number", "Address", "Emergency contact"] },
  c_initial: { l: "Initial Assessment Sheet", f: ["Chief complaint", "Menstrual / obstetric history", "Examination findings", "Provisional diagnosis", "Advice"] },
  c_opd_presc: { l: "OPD Prescription", f: ["Diagnosis", "Medications", "Investigations advised", "Follow-up date"] },
  c_withdrawal: { l: "Withdrawal Format", f: ["Indication", "Consent obtained", "Witness name", "Date"] },
  c_oi: { l: "Ovulation Induction Protocol", f: ["Protocol type", "Start date", "Gonadotropin and dose", "Monitoring schedule"] },
  c_trigger: { l: "Trigger Module", f: ["Trigger drug", "Dose", "Date and time given", "Planned OPU date"] },
  c_admission: { l: "Admission Form", f: ["Admission date", "Ward / Bed", "Admitting diagnosis", "Consultant"] },
  c_ga: { l: "GA / Anaesthesia Record", f: ["ASA grade", "Pre-anaesthetic check", "Anaesthetic plan", "Consent signed"] },
  c_opu: { l: "Clinical Ovum Pick-Up (OPU)", f: ["Follicles aspirated", "Oocytes retrieved", "Complications", "Operator"] },
  c_opu_disch: { l: "OPU Discharge Summary", f: ["Condition at discharge", "Medications on discharge", "Follow-up date", "Instructions"] },
  c_pre_et: { l: "Pre-Embryo Transfer", f: ["Endometrial thickness", "Embryo readiness", "Transfer plan", "Consent signed"] },
  c_et: { l: "Embryo Transfer", f: ["Catheter type", "Number transferred", "Difficulty", "Operator"] },
  c_et_disch: { l: "Embryo Transfer Discharge", f: ["Condition at discharge", "Medications on discharge", "Follow-up date", "Instructions"] },
  c_bhcg: { l: "Serum Beta-hCG", f: ["Sample date", "Beta-hCG value (mIU/ml)", "Interpretation"] },
  c_outcome: { l: "Outcome", f: ["Clinical pregnancy", "Cardiac activity", "Remarks", "Date"] },
  e_oocyte_d3: { l: "Oocyte-Embryo Record (D3)", f: ["Date", "Embryologist", "Oocyte count", "Grade", "Remarks"] },
  e_sperm_prep: { l: "Sperm Preparation", f: ["Date", "Embryologist", "Count / motility", "Preparation method", "Remarks"] },
  e_emb_opu: { l: "Embryology OPU", f: ["Date", "Embryologist", "Oocytes retrieved", "Maturity (MII)", "Remarks"] },
  e_emb_record: { l: "Embryology Embryo Record", f: ["Date", "Embryologist", "No. of embryos", "Grade", "Remarks"] },
  e_emb_transfer: { l: "Embryology Embryo Transfer", f: ["Date", "Embryos transferred", "Grade", "Cryo-preserved", "Remarks"] },
  f_pkg_estimate: { l: "Pkg Estimate", f: ["Recommended package", "Package code", "Estimated amount", "Discount offered", "Counsellor name", "Valid till", "Remarks"] },
};

/** [formKey, unlockPct][] per stage, for clinical (c) and embryology (e) tracks */
export const JSTAGE_FORMS: Record<string, { c: [string, number][]; e: [string, number][] }> = {
  "First Consult": { c: [["c_kyc", 0], ["c_initial", 0]], e: [] },
  "Package Estimate": { c: [], e: [] },
  "CNB Visits": { c: [["c_opd_presc", 0]], e: [] },
  Booked: { c: [["c_withdrawal", 10]], e: [["e_oocyte_d3", 10], ["e_sperm_prep", 10]] },
  "Pre-Procedure": { c: [], e: [] },
  "Ovarian Stimulation": { c: [["c_oi", 50]], e: [] },
  "Endometrial Preparation": { c: [], e: [] },
  Trigger: { c: [["c_trigger", 100], ["c_admission", 100], ["c_ga", 100]], e: [] },
  OPU: { c: [["c_opu", 100], ["c_opu_disch", 100]], e: [["e_emb_opu", 100], ["e_emb_record", 100]] },
  "Progesterone Change": { c: [], e: [] },
  "Embryo Transfer": { c: [["c_pre_et", 100], ["c_et", 100], ["c_et_disch", 100]], e: [["e_emb_record", 100], ["e_emb_transfer", 100]] },
  "B-HCG": { c: [["c_bhcg", 100]], e: [] },
  "Cardiac Activity": { c: [["c_outcome", 100]], e: [] },
};

export const JCOMM_META: Record<"diet" | "psy", { lbl: string; ic: string }> = {
  diet: { lbl: "Diet chart", ic: "🍎" },
  psy: { lbl: "Counselling", ic: "🧠" },
};

export const JCOMP_META: Record<"video" | "consent" | "affidavit", { lbl: string; ic: string }> = {
  video: { lbl: "Video testimonial", ic: "🎥" },
  consent: { lbl: "Consent form", ic: "✍️" },
  affidavit: { lbl: "Affidavit", ic: "📜" },
};

/** each compliance document appears exactly once, on its most relevant stage */
export const JCOMP_MAP: Record<string, ("video" | "consent" | "affidavit")[]> = {
  Booked: ["affidavit"],
  OPU: ["consent"],
  "Cardiac Activity": ["video"],
};

export const TRACK_DEFS = [
  { key: "clinical-stage", label: "Ideal Clinical Stage", color: "var(--color-primary)" },
  { key: "procedure", label: "Procedure", color: "var(--color-gold)" },
  { key: "clinical-form", label: "Clinical Form", color: "var(--color-blue)" },
  { key: "embryology-form", label: "Embryology", color: "#8a64b8" },
  { key: "financial", label: "Financial", color: "var(--color-green)" },
] as const;
export type TrackKey = (typeof TRACK_DEFS)[number]["key"];

import type {
  Approval,
  CentreStat,
  AgingPatient,
  Donor,
  OverrideLog,
  Patient,
  PrebookPatient,
  StageDef,
  Surrogate,
  TriggerItem,
} from "./types";

/* ---- clinical journey stages (final composed shape incl. "Package Estimate") ---- */
export const STAGES_FULL: StageDef[] = [
  { key: "First Consult", day: "Pre-booking", group: "pre" },
  { key: "Package Estimate", day: "Pre-booking", group: "pre" },
  { key: "CNB Visits", day: "Pre-booking", group: "pre" },
  { key: "Booked", day: "T (Day 0)", group: "book" },
  { key: "Pre-Procedure", day: "T+1 — T+9", group: "clinical" },
  { key: "Ovarian Stimulation", day: "T+10", group: "clinical", donorChoice: true },
  { key: "Endometrial Preparation", day: "T+12 — T+18", group: "clinical" },
  { key: "Trigger", day: "T+18", group: "clinical" },
  { key: "OPU", day: "T+20", group: "clinical" },
  { key: "Progesterone Change", day: "T+22 — T+24", group: "lab" },
  { key: "Embryo Transfer", day: "T+25 — T+28", group: "clinical" },
  { key: "B-HCG", day: "T+42", group: "clinical" },
  { key: "Cardiac Activity", day: "T+56", group: "clinical" },
];

/** old patient.stage (0..7) -> STAGES_FULL index (shifted +1 for Package Estimate insert) */
export const OLD_TO_NEW_STAGE = [3, 4, 5, 7, 8, 9, 10, 11];

/** old 8-step milestone short names, used by the dashboard "clinical stage" widget */
export const STEP_SHORT = ["Booking", "LMP", "Stimulation", "Trigger", "OPU", "Embryology", "Embryo Transfer", "Beta HCG"];

/** ideal T+n date range per STAGES_FULL index (final v6-shifted indices) */
export const J_STAGE_RANGE: Record<number, [number, number]> = {
  3: [0, 0],
  4: [1, 9],
  5: [10, 10],
  6: [12, 18],
  7: [18, 18],
  8: [20, 20],
  9: [22, 24],
  10: [25, 28],
  11: [42, 42],
  12: [56, 56],
};

export const PATIENTS: Patient[] = [
  { id: "IIC-2603-118", name: "Anjali Mehra", centre: "Noida", doctor: "Dr. A. Verma", fc: "P. Rao", pkg: "IP222", net: 260000, discount: 25000, signup: "2026-05-05", stage: 4, flag: "green", gate: "open", paid: 100 },
  { id: "IIC-2603-126", name: "Priya Sharma", centre: "Noida", doctor: "Dr. A. Verma", fc: "P. Rao", pkg: "IP11", net: 195000, discount: 18000, signup: "2026-05-15", stage: 2, flag: "amber", gate: "pay", paid: 10 },
  { id: "IIC-2604-203", name: "Kavita Reddy", centre: "Noida", doctor: "Dr. A. Verma", fc: "P. Rao", pkg: "IP222 + Composite", net: 310000, discount: 30000, signup: "2026-05-07", stage: 3, flag: "red", gate: "pay", paid: 50 },
  { id: "IIC-2604-211", name: "Sunita Nair", centre: "Noida", doctor: "Dr. A. Verma", fc: "J. Saini", pkg: "IP11", net: 180000, discount: 15000, signup: "2026-05-24", stage: 1, flag: "green", gate: "open", paid: 10 },
  { id: "IIC-2605-088", name: "Meena Gupta", centre: "Noida", doctor: "Dr. A. Verma", fc: "P. Rao", pkg: "IP222", net: 275000, discount: 22000, signup: "2026-05-25", stage: 0, flag: "amber", gate: "data", paid: 0 },
  { id: "IIC-2602-051", name: "Ritu Verma", centre: "Noida", doctor: "Dr. A. Verma", fc: "J. Saini", pkg: "Donor", net: 390000, discount: 0, signup: "2026-04-29", stage: 6, flag: "green", gate: "open", paid: 100 },
  { id: "IIC-2601-019", name: "Pooja Singh", centre: "Noida", doctor: "Dr. A. Verma", fc: "P. Rao", pkg: "IP11", net: 185000, discount: 16000, signup: "2026-04-13", stage: 7, flag: "green", gate: "open", paid: 100 },
  { id: "IIC-2603-140", name: "Neha Kapoor", centre: "Vasant Vihar", doctor: "Dr. K. Bhatia", fc: "A. Dutta", pkg: "IP222", net: 240000, discount: 20000, signup: "2026-05-03", stage: 5, flag: "green", gate: "open", paid: 100 },
  { id: "IIC-2604-225", name: "Shalini Roy", centre: "Vasant Vihar", doctor: "Dr. K. Bhatia", fc: "A. Dutta", pkg: "IP11", net: 225000, discount: 18000, signup: "2026-05-02", stage: 2, flag: "red", gate: "pay", paid: 10 },
  { id: "IIC-2605-101", name: "Divya Menon", centre: "Vasant Vihar", doctor: "Dr. K. Bhatia", fc: "A. Dutta", pkg: "IP222", net: 290000, discount: 24000, signup: "2026-05-24", stage: 0, flag: "green", gate: "data", paid: 0 },
  { id: "IIC-2603-160", name: "Aarti Joshi", centre: "Rohini", doctor: "Dr. N. Sethi", fc: "S. Bose", pkg: "IP222", net: 255000, discount: 21000, signup: "2026-05-05", stage: 4, flag: "amber", gate: "pay", paid: 92 },
  { id: "IIC-2604-240", name: "Rekha Das", centre: "Rohini", doctor: "Dr. N. Sethi", fc: "S. Bose", pkg: "IP11", net: 270000, discount: 22000, signup: "2026-05-04", stage: 3, flag: "red", gate: "pay", paid: 50 },
  { id: "IIC-2603-175", name: "Swati Malhotra", centre: "Gurgaon", doctor: "Dr. R. Pillai", fc: "V. Khanna", pkg: "IP222", net: 235000, discount: 19000, signup: "2026-04-29", stage: 6, flag: "green", gate: "open", paid: 100 },
  { id: "IIC-2605-115", name: "Nidhi Agarwal", centre: "Gurgaon", doctor: "Dr. R. Pillai", fc: "V. Khanna", pkg: "IP11", net: 200000, discount: 16000, signup: "2026-05-23", stage: 1, flag: "green", gate: "open", paid: 10 },
  { id: "IIC-2604-260", name: "Geeta Yadav", centre: "Ghaziabad", doctor: "Dr. M. Chawla", fc: "K. Menon", pkg: "IP222", net: 215000, discount: 17000, signup: "2026-05-14", stage: 2, flag: "amber", gate: "open", paid: 50 },
  { id: "IIC-2602-070", name: "Sana Mir", centre: "Srinagar", doctor: "Dr. F. Wani", fc: "R. Bhat", pkg: "IP11", net: 250000, discount: 20000, signup: "2026-05-02", stage: 5, flag: "green", gate: "open", paid: 100 },
  // demo patients covering every booked-list category
  { id: "IIC-2605-301", name: "Megha Kapoor", centre: "Noida", doctor: "Dr. A. Verma", fc: "P. Rao", pkg: "IP222 + IUI + ICSI + FET + OPD", net: 520000, discount: 40000, signup: "2026-05-06", stage: 6, flag: "green", gate: "open", paid: 100, cat: "IVF with Bed" },
  { id: "IIC-2605-302", name: "Reena Iyer", centre: "Vasant Vihar", doctor: "Dr. K. Bhatia", fc: "A. Dutta", pkg: "FET", net: 58000, discount: 4000, signup: "2026-05-12", stage: 3, flag: "amber", gate: "pay", paid: 50, cat: "IVF without Bed" },
  { id: "IIC-2605-303", name: "Sofia Khan", centre: "Gurgaon", doctor: "Dr. R. Pillai", fc: "V. Khanna", pkg: "IUI", net: 22000, discount: 2000, signup: "2026-05-18", stage: 1, flag: "green", gate: "open", paid: 10, cat: "Non IVF with Bed" },
  { id: "IIC-2605-304", name: "Tara Bose", centre: "Rohini", doctor: "Dr. N. Sethi", fc: "S. Bose", pkg: "ICSI", net: 45000, discount: 3000, signup: "2026-05-15", stage: 2, flag: "red", gate: "pay", paid: 50, cat: "Non IVF without Bed" },
  { id: "IIC-2605-305", name: "Diya Rao", centre: "Noida", doctor: "Dr. A. Verma", fc: "J. Saini", pkg: "OPD", net: 2000, discount: 0, signup: "2026-05-22", stage: 1, flag: "green", gate: "open", paid: 100, cat: "OPD" },
];

/* ---- red triggers (HMS exception pile-up) ---- */
export const TRIGGERS: TriggerItem[] = [
  { type: "Missed collection", id: "IIC-2604-203", name: "Kavita Reddy", centre: "Noida", stage: "Trigger", value: 155000, days: 6 },
  { type: "Missed collection", id: "IIC-2604-240", name: "Rekha Das", centre: "Rohini", stage: "Trigger", value: 135000, days: 9 },
  { type: "OPU miss", id: "IIC-2603-160", name: "Aarti Joshi", centre: "Rohini", stage: "OPU", value: 20400, days: 3 },
  { type: "Gate override", id: "IIC-2604-225", name: "Shalini Roy", centre: "Vasant Vihar", stage: "OPU", value: 0, days: 2 },
  { type: "Reconciliation pending", id: "IIC-2604-260", name: "Geeta Yadav", centre: "Ghaziabad", stage: "Stimulation", value: 28500, days: 4 },
  { type: "Missed collection", id: "IIC-2603-126", name: "Priya Sharma", centre: "Noida", stage: "Stimulation", value: 78000, days: 5 },
  { type: "Stim 12-day cap", id: "IIC-2604-225", name: "Shalini Roy", centre: "Vasant Vihar", stage: "Stimulation", value: 0, days: 1 },
];

export const STAGE_WT: Record<string, number> = { OPU: 3, "OPU miss": 3, Trigger: 2.4, "Embryo Transfer": 2.2, Stimulation: 1.6, Booking: 1 };

/* ---- director approval queue ---- */
export const APPROVALS: Approval[] = [
  { type: "OPU payment override", id: "IIC-2603-160", name: "Aarti Joshi", centre: "Rohini", detail: "OPU theatre needed — 92% cleared, ₹20,400 balance pending.", req: "2026-05-25" },
  { type: "12-day stim extension", id: "IIC-2604-225", name: "Shalini Roy", centre: "Vasant Vihar", detail: "OI form hit the day-12 cap — continuation form requested.", req: "2026-05-24" },
  { type: "Large refund approval", id: "IIC-2604-240", name: "Rekha Das", centre: "Rohini", detail: "Refund ₹62,000 — above the ₹50,000 director threshold.", req: "2026-05-24" },
  { type: "Special discount escalation", id: "IIC-2605-101", name: "Divya Menon", centre: "Vasant Vihar", detail: "Discount ₹58,000 exceeds the ₹40,000 counsellor limit.", req: "2026-05-22" },
];

/* ---- override & exception log ---- */
export const OVERRIDES: OverrideLog[] = [
  { ts: "2026-05-24 16:20", type: "OPU / ET payment override", id: "IIC-2603-175", name: "Swati Malhotra", centre: "Gurgaon", by: "Dr. Somendra", reason: "ET slot released — 96% cleared, balance scheduled next day" },
  { ts: "2026-05-23 11:05", type: "12-day stim extension", id: "IIC-2604-260", name: "Geeta Yadav", centre: "Ghaziabad", by: "Dr. Somendra", reason: "Slow responder — 2-day extension clinically justified" },
  { ts: "2026-05-22 09:40", type: "Large refund approval", id: "IIC-2602-051", name: "Ritu Verma", centre: "Noida", by: "Dr. Richika", reason: "Donor residual ₹19,500 bundled into combined code — approved" },
  { ts: "2026-05-21 18:12", type: "Discount escalation", id: "IIC-2605-088", name: "Meena Gupta", centre: "Noida", by: "Founder", reason: "Festive package discount — approved within policy" },
];

/* ---- centre-level collection & performance ---- */
export const CENTRE_STATS: CentreStat[] = [
  { centre: "Vasant Vihar", exp: 268000, act: 268000, due: 4, aging: 392000, red: 2, adh: 100, ontrack: 82 },
  { centre: "Noida", exp: 312000, act: 234000, due: 5, aging: 486000, red: 2, adh: 75, ontrack: 71 },
  { centre: "Rohini", exp: 195000, act: 122000, due: 3, aging: 540000, red: 2, adh: 63, ontrack: 64 },
  { centre: "Gurgaon", exp: 224000, act: 210000, due: 3, aging: 268000, red: 0, adh: 94, ontrack: 88 },
  { centre: "Ghaziabad", exp: 148000, act: 120000, due: 2, aging: 175000, red: 1, adh: 81, ontrack: 76 },
  { centre: "Srinagar", exp: 96000, act: 96000, due: 1, aging: 84000, red: 0, adh: 100, ontrack: 90 },
];

/* ---- aging buckets per centre [0-30,31-60,61-90,91-180,180+] ---- */
export const AGING: Record<string, number[]> = {
  Noida: [180000, 126000, 90000, 52000, 38000],
  "Vasant Vihar": [150000, 98000, 74000, 40000, 30000],
  Rohini: [140000, 150000, 120000, 80000, 50000],
  Gurgaon: [120000, 78000, 40000, 20000, 10000],
  Ghaziabad: [70000, 45000, 32000, 18000, 10000],
  Srinagar: [44000, 22000, 12000, 6000, 0],
};

/* ---- weekly collection trend (for sparkline) ---- */
export const WEEK_TREND = [920000, 1040000, 880000, 1180000, 1010000, 1243000, 1050000];

/* ---- prebook funnel (pre 10% booking) ---- */
export const PREBOOK: PrebookPatient[] = [
  { id: "IIC-2605-220", name: "Reema Bansal", date: "2026-05-25", centre: "Noida", type: "scheduled" },
  { id: "IIC-2605-221", name: "Tina Verma", date: "2026-05-25", centre: "Noida", type: "scheduled" },
  { id: "IIC-2605-222", name: "Pallavi Singh", date: "2026-05-25", centre: "Vasant Vihar", type: "scheduled" },
  { id: "IIC-2605-223", name: "Roshni Saxena", date: "2026-05-25", centre: "Gurgaon", type: "scheduled" },
  { id: "IIC-2605-225", name: "Lakshmi Iyer", date: "2026-05-26", centre: "Noida", type: "scheduled" },
  { id: "IIC-2605-228", name: "Anita Pathak", date: "2026-05-27", centre: "Rohini", type: "scheduled" },
  { id: "IIC-2605-232", name: "Surbhi Joshi", date: "2026-05-28", centre: "Gurgaon", type: "scheduled" },
  { id: "IIC-2605-235", name: "Charu Bali", date: "2026-05-30", centre: "Noida", type: "scheduled" },
  { id: "IIC-2605-240", name: "Heena Singhal", date: "2026-06-02", centre: "Vasant Vihar", type: "scheduled" },
  { id: "IIC-2605-200", name: "Veena Sahay", date: "2026-05-25", centre: "Noida", type: "missed" },
  { id: "IIC-2605-180", name: "Smita Kapoor", date: "2026-05-22", centre: "Noida", type: "missed" },
  { id: "IIC-2605-178", name: "Ritika Khanna", date: "2026-05-21", centre: "Vasant Vihar", type: "missed" },
  { id: "IIC-2605-175", name: "Mansi Tandon", date: "2026-05-23", centre: "Noida", type: "missed" },
  { id: "IIC-2605-165", name: "Pooja Saxena", date: "2026-05-18", centre: "Ghaziabad", type: "missed" },
  { id: "IIC-2605-205", name: "Asha Tiwari", date: "2026-05-25", centre: "Noida", type: "cnb", doctor: "Dr. A. Verma", treatment: "IVF Self-cycle (IP222)", quality: "Hot", fcComment: "Couple keen — finalising package after weekend.", lastConn: "2026-05-27", lastComment: "Sent IP222 brochure on WhatsApp; will revert Mon." },
  { id: "IIC-2605-188", name: "Sunita Chopra", date: "2026-05-24", centre: "Noida", type: "cnb", doctor: "Dr. A. Verma", treatment: "IUI x3 then IVF if needed", quality: "Hot", fcComment: "Husband travelling — booking deferred to next week.", lastConn: "2026-05-28", lastComment: "Follow-up call Mon; mentioned insurance query." },
  { id: "IIC-2605-185", name: "Aditi Sharma", date: "2026-05-23", centre: "Gurgaon", type: "cnb", doctor: "Dr. R. Pillai", treatment: "IVF Self-cycle (IP11)", quality: "Cold", fcComment: "Comparing with another clinic - price sensitive.", lastConn: "2026-05-26", lastComment: "Asked for EMI options; brochure resent." },
  { id: "IIC-2605-170", name: "Kiran Mathur", date: "2026-05-20", centre: "Rohini", type: "cnb", doctor: "Dr. N. Sethi", treatment: "IVF + Composite Add-on (IP222+C)", quality: "Hot", fcComment: "Waiting for husband's semen analysis report.", lastConn: "2026-05-27", lastComment: "SA done at local lab; awaiting upload." },
  { id: "IIC-2605-160", name: "Bhavna Dixit", date: "2026-05-17", centre: "Noida", type: "cnb", doctor: "Dr. A. Verma", treatment: "Donor egg IVF", quality: "Cold", fcComment: "Family discussion ongoing on donor cycle.", lastConn: "2026-05-25", lastComment: "Counselled on donor protocols; will decide in 2 wks." },
  { id: "IIC-2605-156", name: "Manisha Rao", date: "2026-05-16", centre: "Vasant Vihar", type: "cnb", doctor: "Dr. K. Bhatia", treatment: "IUI cycle", quality: "Cold", fcComment: "Wants 2nd opinion before committing.", lastConn: "2026-05-24", lastComment: "Visiting another consultant 28-May." },
  { id: "IIC-2605-150", name: "Sneha Kulkarni", date: "2026-05-14", centre: "Noida", type: "cnb", doctor: "Dr. A. Verma", treatment: "IVF Self-cycle (IP11)", quality: "Dead", fcComment: "No response after 5 follow-ups - treat as dead lead.", lastConn: "2026-05-20", lastComment: "Phone unreachable; WhatsApp not delivered." },
];

/* ---- aging-patients FY calendar ---- */
export const FY_MONTHS = [
  "2025-04", "2025-05", "2025-06", "2025-07", "2025-08", "2025-09",
  "2025-10", "2025-11", "2025-12", "2026-01", "2026-02", "2026-03",
  "2026-04", "2026-05", "2026-06", "2026-07", "2026-08", "2026-09",
  "2026-10", "2026-11", "2026-12", "2027-01", "2027-02", "2027-03",
];
export const FY_LABELS = ["Apr'25", "May'25", "Jun'25", "Jul'25", "Aug'25", "Sep'25", "Oct'25", "Nov'25", "Dec'25", "Jan'26", "Feb'26", "Mar'26", "Apr'26", "May'26", "Jun'26", "Jul'26", "Aug'26", "Sep'26", "Oct'26", "Nov'26", "Dec'26", "Jan'27", "Feb'27", "Mar'27"];

export const AGING_PATIENTS: AgingPatient[] = [
  { id: "IIC-2604-203", name: "Kavita Reddy", centre: "Noida", fc: "P. Rao", pkg: "IP222+CMP", desc: "IVF Self-cycle + Composite Add-on", signup: "2026-05-07", gross: 340000, discPct: 8.8, incGst: 310000, milestone: "Trigger", daysOverdue: 6, lastFu: "2026-05-22", referred: "2026-05-20", status: "Active · Trigger balance due", invoice: "IIC/N/26-27/0203", history: "Booked 07-May · 40% paid 14-May · Trigger balance 6d overdue · 2 follow-ups", payments: [{ m: "2026-05", a: 155000 }] },
  { id: "IIC-2603-126", name: "Priya Sharma", centre: "Noida", fc: "P. Rao", pkg: "IP11", desc: "IVF Self-cycle Standard", signup: "2026-05-15", gross: 215000, discPct: 9.3, incGst: 195000, milestone: "Stimulation", daysOverdue: 5, lastFu: "2026-05-22", referred: "2026-05-21", status: "Active · Stim 40% pending", invoice: "IIC/N/26-27/0126", history: "Booked 15-May · 10% paid · Stim 40% 5d overdue · Tele follow-up 22-May", payments: [{ m: "2026-05", a: 19500 }] },
  { id: "IIC-2603-160", name: "Aarti Joshi", centre: "Rohini", fc: "S. Bose", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2026-05-05", gross: 280000, discPct: 8.9, incGst: 255000, milestone: "OPU", daysOverdue: 3, lastFu: "2026-05-23", referred: "2026-05-22", status: "OPU clearance 8% short", invoice: "IIC/R/26-27/0160", history: "92% cleared · ₹20,400 short of OPU · awaiting director override", payments: [{ m: "2026-05", a: 234600 }] },
  { id: "IIC-2604-240", name: "Rekha Das", centre: "Rohini", fc: "S. Bose", pkg: "IP11", desc: "IVF Self-cycle Standard", signup: "2026-05-04", gross: 295000, discPct: 8.5, incGst: 270000, milestone: "Trigger", daysOverdue: 9, lastFu: "2026-05-22", referred: "2026-05-20", status: "Trigger balance overdue", invoice: "IIC/R/26-27/0240", history: "50% paid 14-May · Trigger balance 9d overdue · patient cited cashflow", payments: [{ m: "2026-05", a: 135000 }] },
  { id: "IIC-2604-225", name: "Shalini Roy", centre: "Vasant Vihar", fc: "A. Dutta", pkg: "IP11", desc: "IVF Self-cycle Standard", signup: "2026-05-02", gross: 245000, discPct: 8.2, incGst: 225000, milestone: "Stim cancelled", daysOverdue: 12, lastFu: "2026-05-21", referred: "2026-05-19", status: "Stim cancelled · refund pending", invoice: "IIC/V/26-27/0225", history: "No eggs · pro-rata refund ₹45,000 computed · awaiting mode confirmation", payments: [{ m: "2026-05", a: 22500 }] },
  { id: "IIC-2604-260", name: "Geeta Yadav", centre: "Ghaziabad", fc: "K. Menon", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2026-05-14", gross: 235000, discPct: 8.5, incGst: 215000, milestone: "Stim · pkg change", daysOverdue: 4, lastFu: "2026-05-22", referred: "2026-05-20", status: "Package change · reconciliation open", invoice: "IIC/G/26-27/0260", history: "Package change mid-stim · new code pending · ₹28.5k residual", payments: [{ m: "2026-05", a: 107500 }] },
  { id: "IIC-2502-180", name: "Renu Singh", centre: "Noida", fc: "P. Rao", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2026-02-10", gross: 290000, discPct: 6.9, incGst: 270000, milestone: "Stimulation", daysOverdue: 45, lastFu: "2026-04-15", referred: "2026-03-25", status: "Stim 40% pending · cycle on hold", invoice: "IIC/N/25-26/0180", history: "Booked Feb · only 10% paid · cycle paused 45d · ₹85k pending", payments: [{ m: "2026-02", a: 27000 }] },
  { id: "IIC-2603-140", name: "Neha Kapoor", centre: "Vasant Vihar", fc: "A. Dutta", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2026-03-15", gross: 265000, discPct: 9.4, incGst: 240000, milestone: "Trigger", daysOverdue: 38, lastFu: "2026-04-19", referred: "2026-04-10", status: "Trigger balance pending", invoice: "IIC/V/25-26/0140", history: "50% paid Apr · Trigger balance 38d · 4 follow-ups · open", payments: [{ m: "2026-03", a: 24000 }, { m: "2026-04", a: 96000 }] },
  { id: "IIC-2502-145", name: "Ranjana Sahay", centre: "Rohini", fc: "S. Bose", pkg: "IP222+CMP", desc: "IVF + Composite Add-on", signup: "2026-02-18", gross: 325000, discPct: 9.2, incGst: 295000, milestone: "Trigger", daysOverdue: 60, lastFu: "2026-03-27", referred: "2026-03-15", status: "Trigger balance 60d overdue", invoice: "IIC/R/25-26/0145", history: "Booked Feb · 50% paid Mar · Trigger 60d overdue · escalated", payments: [{ m: "2026-02", a: 29500 }, { m: "2026-03", a: 118000 }] },
  { id: "IIC-2502-205", name: "Komal Aggarwal", centre: "Noida", fc: "J. Saini", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2026-02-22", gross: 295000, discPct: 8.5, incGst: 270000, milestone: "Trigger", daysOverdue: 78, lastFu: "2026-03-08", referred: "2026-03-01", status: "Trigger pending · likely withdrawal", invoice: "IIC/N/25-26/0205", history: "50% paid · Trigger 78d overdue · patient stopped responding", payments: [{ m: "2026-02", a: 27000 }, { m: "2026-03", a: 108000 }] },
  { id: "IIC-2502-095", name: "Anjali Khanna", centre: "Gurgaon", fc: "V. Khanna", pkg: "IP11", desc: "IVF Self-cycle Standard", signup: "2026-02-26", gross: 225000, discPct: 8.9, incGst: 205000, milestone: "Stim", daysOverdue: 88, lastFu: "2026-03-01", referred: "2026-02-28", status: "Cycle on hold · 40% pending", invoice: "IIC/G/25-26/0095", history: "10% paid · Stim 40% overdue 88d · patient lost interest", payments: [{ m: "2026-02", a: 20500 }] },
  { id: "IIC-2502-090", name: "Tina Bhardwaj", centre: "Noida", fc: "P. Rao", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2026-02-02", gross: 280000, discPct: 7.9, incGst: 258000, milestone: "OPU · override", daysOverdue: 110, lastFu: "2026-02-05", referred: "2026-02-01", status: "OPU override unresolved", invoice: "IIC/N/25-26/0090", history: "OPU done with director override · ₹40k still pending · 110d overdue", payments: [{ m: "2026-02", a: 218000 }] },
  { id: "IIC-2501-200", name: "Megha Pillai", centre: "Vasant Vihar", fc: "A. Dutta", pkg: "IP222+CMP", desc: "IVF + Composite Add-on", signup: "2026-01-08", gross: 340000, discPct: 8.2, incGst: 312000, milestone: "Trigger", daysOverdue: 150, lastFu: "2025-12-29", referred: "2025-12-25", status: "Cycle stalled · 50% pending", invoice: "IIC/V/25-26/0200", history: "50% paid Jan · Trigger 150d overdue · refund discussion", payments: [{ m: "2026-01", a: 31200 }, { m: "2026-01", a: 125000 }] },
  { id: "IIC-2501-300", name: "Sweta Kumari", centre: "Noida", fc: "J. Saini", pkg: "IP11", desc: "IVF Self-cycle Standard", signup: "2025-11-08", gross: 215000, discPct: 9.3, incGst: 195000, milestone: "Stim", daysOverdue: 200, lastFu: "2025-12-10", referred: "2025-12-01", status: "Likely write-off", invoice: "IIC/N/25-26/0300", history: "Booked Nov 2025 · only 10% paid · 200d overdue · likely write-off", payments: [{ m: "2025-11", a: 19500 }] },
  { id: "IIC-2412-010", name: "Sarika Iyer", centre: "Ghaziabad", fc: "K. Menon", pkg: "IP222", desc: "IVF Self-cycle Premium", signup: "2025-10-10", gross: 275000, discPct: 9.1, incGst: 250000, milestone: "OPU", daysOverdue: 220, lastFu: "2025-11-15", referred: "2025-11-01", status: "Cycle abandoned · ₹35k pending", invoice: "IIC/G/25-26/0010", history: "OPU done · ₹35k pending since Oct 2025 · 220d overdue", payments: [{ m: "2025-10", a: 25000 }, { m: "2025-10", a: 100000 }, { m: "2025-10", a: 90000 }] },
  { id: "IIC-2412-300", name: "Rashmi Verma", centre: "Srinagar", fc: "R. Bhat", pkg: "IP11", desc: "IVF Self-cycle Standard", signup: "2025-09-17", gross: 200000, discPct: 7.5, incGst: 185000, milestone: "Trigger", daysOverdue: 250, lastFu: "2025-10-15", referred: "2025-10-01", status: "Write-off recommended", invoice: "IIC/S/25-26/0300", history: "50% paid Sep 2025 · Trigger 250d overdue · write-off recommended", payments: [{ m: "2025-09", a: 18500 }, { m: "2025-09", a: 74000 }] },
];

/* ============================================================
   THIRD-PARTY REPRODUCTION — Donors & Surrogates
   ============================================================ */
export const DONORS: Donor[] = [
  { id: "DNR-2025-014", name: "Asha Kapoor", age: 27, blood: "O+", height: "5'5\"", weight: "56 kg", amh: "4.2 ng/mL", phenotype: "Wheatish · Black hair · Brown eyes", education: "B.Sc Nursing", occupation: "Staff Nurse", marital: "Married · 1 child", priorCycles: 2, priorYield: "14 / 16 eggs (avg)", screening: "Cleared", hiv: "Non-reactive", hbsag: "Non-reactive", hcv: "Non-reactive", vdrl: "Non-reactive", thalassemia: "Negative", karyotype: "46,XX Normal", agency: "In-house registry", anonymity: "Anonymous", consent: "2026-04-12", contractValue: 85000, paid: 85000, centre: "Noida", coordinator: "P. Rao", phone: "9876xx2014", status: "Active · cycle in progress", notes: "High AMH, predictable response. Cleared all genetic and infectious screening. Consent signed for current cycle." },
  { id: "DNR-2025-027", name: "Meera Joshi", age: 29, blood: "A+", height: "5'3\"", weight: "58 kg", amh: "3.6 ng/mL", phenotype: "Fair · Brown hair · Hazel eyes", education: "B.A.", occupation: "Homemaker", marital: "Married · 2 children", priorCycles: 1, priorYield: "12 eggs", screening: "Cleared", hiv: "Non-reactive", hbsag: "Non-reactive", hcv: "Non-reactive", vdrl: "Non-reactive", thalassemia: "Negative", karyotype: "46,XX Normal", agency: "Sparsh Surrogacy Agency", anonymity: "Anonymous", consent: "2026-04-30", contractValue: 90000, paid: 45000, centre: "Vasant Vihar", coordinator: "A. Dutta", phone: "9876xx2027", status: "Active · stimulation phase", notes: "Reliable donor, second cycle with India IVF. Repeat KYC done 28 Apr 2026." },
  { id: "DNR-2025-031", name: "Rekha Bisht", age: 25, blood: "B+", height: "5'4\"", weight: "54 kg", amh: "5.1 ng/mL", phenotype: "Wheatish · Black hair · Brown eyes", education: "B.Com", occupation: "Bank teller", marital: "Married · 1 child", priorCycles: 0, priorYield: "First cycle", screening: "Cleared", hiv: "Non-reactive", hbsag: "Non-reactive", hcv: "Non-reactive", vdrl: "Non-reactive", thalassemia: "Negative", karyotype: "46,XX Normal", agency: "In-house registry", anonymity: "Anonymous", consent: "2026-05-15", contractValue: 80000, paid: 40000, centre: "Rohini", coordinator: "S. Bose", phone: "9876xx2031", status: "Active · OPU planned", notes: "First-time donor. AMH high — risk-of-OHSS protocol to be followed." },
];

export const SURROGATES: Surrogate[] = [
  { id: "SUR-2025-006", name: "Sunita Devi", age: 31, blood: "O+", height: "5'2\"", weight: "62 kg", bmi: "24.8", parity: "G3P2 · 2 living children", obstetricHist: "Both prior deliveries full-term vaginal, no complications", endometrium: "8.2 mm (trilaminar)", hsg: "Normal cavity", screening: "Cleared", hiv: "Non-reactive", hbsag: "Non-reactive", hcv: "Non-reactive", vdrl: "Non-reactive", gtt: "Normal", thyroid: "TSH 2.1", legalStatus: "Surrogacy contract signed · ART Act 2021 compliant", consent: "2026-04-20", ec: "Eligibility Certificate issued", insurance: "36 months · Star Health", agency: "In-house registry", anonymity: "Known to couple", contractValue: 550000, paid: 275000, centre: "Noida", coordinator: "P. Rao", phone: "9876xx3006", husband: "Ramesh Kumar · 34 yrs", status: "Active · endometrial preparation", notes: "Altruistic surrogacy under ART Act. Endometrium responding well to oestrogen." },
  { id: "SUR-2025-009", name: "Lakshmi Yadav", age: 33, blood: "B+", height: "5'3\"", weight: "64 kg", bmi: "25.3", parity: "G2P1 · 1 living child", obstetricHist: "Prior LSCS · uneventful recovery", endometrium: "9.0 mm (trilaminar)", hsg: "Normal cavity", screening: "Cleared", hiv: "Non-reactive", hbsag: "Non-reactive", hcv: "Non-reactive", vdrl: "Non-reactive", gtt: "Normal", thyroid: "TSH 1.8", legalStatus: "Surrogacy contract signed · ART Act 2021 compliant", consent: "2026-05-02", ec: "EC under processing", insurance: "36 months · Niva Bupa", agency: "Sparsh Surrogacy Agency", anonymity: "Known to couple", contractValue: 600000, paid: 300000, centre: "Vasant Vihar", coordinator: "A. Dutta", phone: "9876xx3009", husband: "Vinod Yadav · 36 yrs", status: "Active · ET planned", notes: "Prior LSCS — flagged for high-risk obstetric monitoring post-transfer." },
];

/* ---- aggregate totals ---- */
export const TOT_EXP = CENTRE_STATS.reduce((s, c) => s + c.exp, 0);
export const TOT_ACT = CENTRE_STATS.reduce((s, c) => s + c.act, 0);
export const TOT_AGING = Object.values(AGING).reduce((s, a) => s + a.reduce((x, y) => x + y, 0), 0);
export const COMPANY_BUCKETS = [0, 1, 2, 3, 4].map((i) => Object.values(AGING).reduce((s, a) => s + a[i], 0));

/* ---- dashboard KPI row, per period subtab (week/month are point-in-time demo figures) ---- */
export const DASHBOARD_KPI = {
  today: { exp: TOT_EXP, act: TOT_ACT, redTriggers: TRIGGERS.length, approvalsPending: APPROVALS.length },
  week: { exp: 5600000, act: 4820000, projection: 5750000, redTriggers: TRIGGERS.length },
  month: { exp: 24200000, act: 20800000, projection: 23800000, redTriggers: TRIGGERS.length },
};
export const AGING_BUCKET_COUNTS = [28, 19, 13, 8, 5];

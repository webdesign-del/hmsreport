export interface NavLeaf {
  href: string;
  label: string;
  ic: string;
  hideForRoles?: string[];
}
export interface NavGroup {
  group: string;
  label: string;
  ic: string;
  children: NavLeaf[];
  hideForRoles?: string[];
}
export type NavEntry = NavLeaf | NavGroup;

export function isNavGroup(n: NavEntry): n is NavGroup {
  return "children" in n;
}

export const MGMT_NAV: NavEntry[] = [
  { href: "/dashboard", label: "Dashboard", ic: "▦" },
  { href: "/prebook/scheduled", label: "Appointment Scheduled", ic: "📅", hideForRoles: ["doctor", "embryologist"] },
  { href: "/prebook/missed", label: "Missed Appointments", ic: "⚑", hideForRoles: ["doctor", "embryologist"] },
  { href: "/prebook/cnb", label: "Consulted Not Booked", ic: "◐", hideForRoles: ["doctor", "embryologist"] },
  {
    group: "booked",
    label: "Booked Patient",
    ic: "◷",
    children: [
      { href: "/patients", label: "Booked Patient List", ic: "≡" },
      { href: "/journey", label: "Booked Patient Journey", ic: "◷" },
    ],
  },
  { href: "/centres", label: "Centre Comparison", ic: "▥" },
  { href: "/aging", label: "Aging Snapshot", ic: "⏳" },
  { href: "/triggers", label: "Red Trigger Pile-up", ic: "⚑" },
  { href: "/approvals", label: "Approval Queue", ic: "⚖" },
];

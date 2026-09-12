import { cookies } from "next/headers";

export interface UserSession {
  username: string;
  name: string;
  role: string;
  centerId?: number | null;
  centerName?: string | null;
}

/**
 * Roles whose data must be scoped to their own centre; anything else sees all centres by
 * default and can switch via the Topbar picker. Accounts Team is deliberately NOT scoped —
 * its own login description is "Cross-centre collections & refund execution".
 */
const SCOPED_ROLES = new Set(["doctor", "embryologist", "centre_head", "fc"]);

export async function getServerSession(): Promise<UserSession | null> {
  const store = await cookies();
  const raw = store.get("user_session")?.value;
  if (!raw) return null;
  try {
    return JSON.parse(raw) as UserSession;
  } catch {
    return null;
  }
}

/**
 * The center_id to scope server-side data fetches by, or null when the role should see every centre.
 * Scoped roles (Doctor, Centre Head, ...) always use their own centre. Unscoped roles
 * (Management, Viewer) use whatever centre was picked in the Topbar, if any (selected_centre_id cookie).
 */
export async function getScopedCenterId(): Promise<number | null> {
  const session = await getServerSession();
  if (!session) return null;
  if (SCOPED_ROLES.has(session.role)) {
    // center_id 0 is the "IndiaIVF" head-office placeholder, not a real centre with billing
    // records — an employee stuck on it (unassigned) should see all data, not an empty screen.
    return session.centerId || null;
  }
  const store = await cookies();
  const raw = store.get("selected_centre_id")?.value;
  return raw ? Number(raw) : null;
}

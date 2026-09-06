import { cookies } from "next/headers";

export interface UserSession {
  username: string;
  name: string;
  role: string;
  centerId?: number | null;
  centerName?: string | null;
}

/** Roles whose data must be scoped to their own centre; anything else (e.g. management) sees all centres. */
const SCOPED_ROLES = new Set(["doctor", "centre_head", "fc", "accounts"]);

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

/** The center_id to scope server-side data fetches by, or null when the role should see every centre. */
export async function getScopedCenterId(): Promise<number | null> {
  const session = await getServerSession();
  if (!session || !SCOPED_ROLES.has(session.role)) return null;
  // center_id 0 is the "IndiaIVF" head-office placeholder, not a real centre with billing
  // records — an employee stuck on it (unassigned) should see all data, not an empty screen.
  return session.centerId || null;
}

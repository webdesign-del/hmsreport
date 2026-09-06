export interface ClientSession {
  username: string;
  name: string;
  role: string;
  centerId?: number | null;
  centerName?: string | null;
}

/** Roles whose data must be scoped to their own centre; anything else (e.g. management) sees all centres. */
const SCOPED_ROLES = new Set(["doctor", "centre_head", "fc", "accounts"]);

export function getClientSession(): ClientSession | null {
  try {
    const raw = localStorage.getItem("user_session");
    if (!raw) return null;
    return JSON.parse(raw) as ClientSession;
  } catch {
    return null;
  }
}

/** The center_id to scope client-side fetches by, or null when the role should see every centre. */
export function getClientScopedCenterId(): number | null {
  const session = getClientSession();
  if (!session || !SCOPED_ROLES.has(session.role)) return null;
  // center_id 0 is the "IndiaIVF" head-office placeholder, not a real centre with billing
  // records — an employee stuck on it (unassigned) should see all data, not an empty screen.
  return session.centerId || null;
}

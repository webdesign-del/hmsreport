export interface ClientSession {
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

/** Clinical-only roles that should never see billing/collections figures. */
const NO_FINANCIALS_ROLES = new Set(["doctor", "embryologist"]);

/** Key used for the unscoped-role "view as centre" picker (Topbar), both in localStorage and as a cookie. */
const CENTRE_OVERRIDE_KEY = "selected_centre_id";

export function hidesFinancials(role?: string | null): boolean {
  return !!role && NO_FINANCIALS_ROLES.has(role);
}

export function getClientSession(): ClientSession | null {
  try {
    const raw = localStorage.getItem("user_session");
    if (!raw) return null;
    return JSON.parse(raw) as ClientSession;
  } catch {
    return null;
  }
}

/** The manually-picked centre for unscoped roles (Management/Viewer), or null for "All Centres". */
export function getCentreOverride(): number | null {
  try {
    const raw = localStorage.getItem(CENTRE_OVERRIDE_KEY);
    return raw ? Number(raw) : null;
  } catch {
    return null;
  }
}

export function setCentreOverride(centerId: number | null): void {
  try {
    if (centerId) {
      localStorage.setItem(CENTRE_OVERRIDE_KEY, String(centerId));
      document.cookie = `${CENTRE_OVERRIDE_KEY}=${centerId}; path=/; max-age=86400`;
    } else {
      localStorage.removeItem(CENTRE_OVERRIDE_KEY);
      document.cookie = `${CENTRE_OVERRIDE_KEY}=; path=/; max-age=0`;
    }
  } catch {
    // ignore
  }
}

/**
 * The center_id to scope client-side fetches by, or null when the role should see every centre.
 * Scoped roles (Doctor, Centre Head, ...) always use their own centre. Unscoped roles
 * (Management, Viewer) use whatever centre was picked in the Topbar, if any.
 */
export function getClientScopedCenterId(): number | null {
  const session = getClientSession();
  if (!session) return null;
  if (SCOPED_ROLES.has(session.role)) {
    // center_id 0 is the "IndiaIVF" head-office placeholder, not a real centre with billing
    // records — an employee stuck on it (unassigned) should see all data, not an empty screen.
    return session.centerId || null;
  }
  return getCentreOverride();
}

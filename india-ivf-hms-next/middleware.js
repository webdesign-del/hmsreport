import { NextResponse } from 'next/server';

// Every page and every /api/* data route needs a signed-in session cookie. Only /login and
// /api/login are public. A "view_only" session can read everything but every write request
// (POST / PUT / PATCH / DELETE) on /api/* is rejected here.
const WRITE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

function readSession(request) {
  const raw = request.cookies.get('user_session')?.value;
  if (!raw) return null;
  try {
    const s = JSON.parse(raw);
    return s && typeof s === 'object' && s.role ? s : null;
  } catch {
    return null;
  }
}

export function middleware(request) {
  const { pathname } = request.nextUrl;
  const isApi = pathname.startsWith('/api/');

  // Public: the login page, the login API, and the e-mail Approve / Disapprove page (it is protected by a signed token).
  if (
    pathname === '/login' ||
    pathname.startsWith('/login/') ||
    pathname.startsWith('/api/login') ||
    pathname.startsWith('/api/cancel-decision') ||
    pathname.startsWith('/api/tele-action') ||
    pathname.startsWith('/api/stage-delay-reply')
  ) {
    return NextResponse.next();
  }

  const session = readSession(request);

  // Guests (no valid session) never get any page or data.
  if (!session) {
    if (isApi) {
      return NextResponse.json({ status: 'error', message: 'Please sign in first.' }, { status: 401 });
    }
    return NextResponse.redirect(new URL('/login', request.url));
  }

  if (pathname === '/') {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }

  // View-only users cannot change anything.
  if (session.role === 'view_only' && isApi && WRITE_METHODS.has(request.method)) {
    return NextResponse.json(
      { status: 'error', message: 'View-only access — you cannot make changes.' },
      { status: 403 }
    );
  }

  // Prebook lists (Appointment Scheduled / Missed / Consulted Not Booked) are hidden
  // from Doctor and Embryologist workspaces — block direct URL access too.
  if (pathname.startsWith('/prebook') && (session.role === 'doctor' || session.role === 'embryologist')) {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }

  return NextResponse.next();
}

export const config = {
  // Everything except Next internals and static files.
  matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.(?:png|jpg|jpeg|gif|svg|webp|ico|css|js|map|woff|woff2|ttf)$).*)'],
};

import { NextResponse } from 'next/server';

export function middleware(request) {
  const { pathname } = request.nextUrl;
  
  // Check if session cookie or token exists
  const token = request.cookies.get('user_session')?.value;

  // 1. Agar root http://localhost:3001/ par aaye
  if (pathname === '/') {
    if (token) {
      return NextResponse.redirect(new URL('/dashboard', request.url));
    } else {
      return NextResponse.redirect(new URL('/login', request.url));
    }
  }

  // 2. Agar user bina login ke direct /dashboard kholne ki koshish kare
  if (pathname.startsWith('/dashboard') && !token) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  // 3. Prebook lists (Appointment Scheduled / Missed / Consulted Not Booked) are hidden
  // from Doctor and Embryologist workspaces — block direct URL access too.
  if (pathname.startsWith('/prebook')) {
    if (!token) return NextResponse.redirect(new URL('/login', request.url));
    let role = null;
    try {
      role = JSON.parse(token)?.role;
    } catch {
      // malformed session cookie — fall through, treat as no role restriction
    }
    if (role === 'doctor' || role === 'embryologist') {
      return NextResponse.redirect(new URL('/dashboard', request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/', '/dashboard/:path*', '/prebook/:path*'],
};
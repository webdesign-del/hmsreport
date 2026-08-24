'use client';

import { usePathname } from 'next/navigation';
import Shell from '@/components/Shell';
import './globals.css';

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();

  // Check if current page is '/login'
  const isLoginPage = pathname === '/login';

  return (
    <html lang="en">
      <body className="antialiased">
        {isLoginPage ? (
          // 🔒 Login Page: Fullscreen Layout
          <main className="min-h-screen w-full bg-surface">{children}</main>
        ) : (
          // 📊 Dashboard & Other Pages: Standard Layout (Topbar + Sidebar)
          <Shell>{children}</Shell>
        )}
      </body>
    </html>
  );
}
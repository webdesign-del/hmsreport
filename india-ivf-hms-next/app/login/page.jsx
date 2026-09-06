'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function LoginPage() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  // Unified Dynamic Login Handler
  const handleLogin = async (e, targetRole = 'doctor') => {
    if (e) e.preventDefault();
    setLoading(true);
    setError('');

    // Form Validation
    if (!username.trim() || !password.trim()) {
      setError('Please enter both username/email and password.');
      setLoading(false);
      return;
    }

    try {
      // 1. JSON Payload Fetching
      //  const res = await fetch('http://127.0.0.1:8000/login/', {
      //   method: 'POST',
      //  headers: {
      //    'Content-Type': 'application/json',
      //  },
      //  body: JSON.stringify({
      //    username: username.trim(),
      //    password: password.trim(),
      //  }),
      // });

      // Post Request to Django Auth
const res = await fetch('/api/login/', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ username, password, role: targetRole }),
});

      const data = await res.json();

      if (res.ok && data.status === 'success') {
        // Save Session to LocalStorage & Middleware Cookie
        const sessionPayload = {
          username: data.user?.username || username,
          name: data.user?.name || username,
          role: targetRole,
          centerId: data.user?.center_id ?? null,
          centerName: data.user?.center_name ?? null,
        };

        localStorage.setItem('user_session', JSON.stringify(sessionPayload));
        document.cookie = `user_session=${JSON.stringify(sessionPayload)}; path=/; max-age=86400`;

        // Direct Redirect to Main Dashboard
        router.push('/dashboard');
      } else {
        setError(data.message || 'Invalid credentials or username context.');
      }
    } catch (err) {
      console.error('Login Exception:', err);
      // Dynamic Error Handling
      setError(err?.message || 'Server connection failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-wrap" style={styles.loginWrap}>
      {/* Brand Panel */}
      <aside className="login-brand" style={styles.loginBrand}>
        <div className="lb-top">
          <div className="lb-logo-plate" style={styles.lbLogoPlate}>
            <h2 style={{ color: '#be123c', margin: 0 }}>🏥 India IVF</h2>
          </div>
        </div>
        <div className="lb-mid" style={styles.lbMid}>
          <div className="lb-tag" style={styles.lbTag}>Patient Journey &amp; Collections</div>
          <div className="lb-head" style={styles.lbHead}>One bird's-eye view of every patient, every rupee, every centre.</div>
          <div className="lb-sub" style={styles.lbSub}>From the 10% booking payment to cycle close and final reconciliation — the HMS module that keeps revenue, clinical protocol and patient coordination in tight sync.</div>
          <div className="lb-stats" style={styles.lbStats}>
            <div className="lb-stat"><div style={styles.statNum}>6+</div><div style={styles.statLabel}>Centres</div></div>
            <div className="lb-stat"><div style={styles.statNum}>7</div><div style={styles.statLabel}>Clinical milestones</div></div>
            <div className="lb-stat"><div style={styles.statNum}>5</div><div style={styles.statLabel}>Role workspaces</div></div>
          </div>
        </div>
        <div className="lb-foot" style={styles.lbFoot}>© 2026 Pashupati Lifecare Pvt. Ltd. · India IVF Fertility · Conceiving Miracles. Spreading Hope.</div>
      </aside>

      {/* Sign-in Panel */}
      <main className="login-panel" style={styles.loginPanel}>
        <div className="login-card" style={styles.loginCard}>
          <h1 style={{ fontSize: '24px', margin: '0 0 6px 0', color: '#0f172a' }}>Sign in to HMS</h1>
          <div className="lc-sub" style={{ fontSize: '13px', color: '#64748b', marginBottom: '20px' }}>Patient Journey &amp; Collections module · access is scoped to your role</div>

          {error && <div style={styles.errorAlert}>{error}</div>}

          <form onSubmit={(e) => handleLogin(e, 'doctor')}>
            <div className="login-field" style={{ marginBottom: '16px' }}>
              <label style={styles.label}>Username or Work email</label>
              <input 
                className="input" 
                type="text" 
                required
                placeholder="name@indiaivf.in" 
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                style={styles.input} 
              />
            </div>
            <div className="login-field" style={{ marginBottom: '20px' }}>
              <label style={styles.label}>Password</label>
              <input 
                className="input" 
                type="password" 
                required
                placeholder="••••••••••" 
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                style={styles.input} 
              />
            </div>

            <div className="role-pick-lbl" style={{ fontSize: '12px', fontWeight: '600', color: '#475569', marginBottom: '10px' }}>
              Choose your workspace to continue
            </div>

            <div className="role-cards" style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              <button 
                type="button" 
                disabled={loading}
                onClick={(e) => handleLogin(e, 'doctor')}
                style={styles.roleCard}
              >
                <div style={{ ...styles.rcIc, backgroundColor: '#ffe4e6', color: '#e11d48' }}>✚</div>
                <div style={styles.rcBody}>
                  <div style={styles.rcName}>Doctor</div>
                  <div style={styles.rcDesc}>Map &amp; update the clinical journey end to end</div>
                </div>
                <div style={styles.rcArrow}>{loading ? '...' : '→'}</div>
              </button>

              <button 
                type="button" 
                disabled={loading}
                onClick={(e) => handleLogin(e, 'centre_head')}
                style={styles.roleCard}
              >
                <div style={{ ...styles.rcIc, backgroundColor: '#fef3c7', color: '#d97706' }}>₹</div>
                <div style={styles.rcBody}>
                  <div style={styles.rcName}>Centre Head</div>
                  <div style={styles.rcDesc}>Own collection performance at your centre</div>
                </div>
                <div style={styles.rcArrow}>{loading ? '...' : '→'}</div>
              </button>

              <button 
                type="button" 
                disabled={loading}
                onClick={(e) => handleLogin(e, 'fc')}
                style={styles.roleCard}
              >
                <div style={{ ...styles.rcIc, backgroundColor: '#dbeafe', color: '#2563eb' }}>◈</div>
                <div style={styles.rcBody}>
                  <div style={styles.rcName}>Financial Counsellor</div>
                  <div style={styles.rcDesc}>Patient-level financial mapping &amp; adjustments</div>
                </div>
                <div style={styles.rcArrow}>{loading ? '...' : '→'}</div>
              </button>

              <button 
                type="button" 
                disabled={loading}
                onClick={(e) => handleLogin(e, 'accounts')}
                style={styles.roleCard}
              >
                <div style={{ ...styles.rcIc, backgroundColor: '#dcfce7', color: '#16a34a' }}>↩</div>
                <div style={styles.rcBody}>
                  <div style={styles.rcName}>Accounts Team</div>
                  <div style={styles.rcDesc}>Cross-centre collections &amp; refund execution</div>
                </div>
                <div style={styles.rcArrow}>{loading ? '...' : '→'}</div>
              </button>

              <button 
                type="button" 
                disabled={loading}
                onClick={(e) => handleLogin(e, 'management')}
                style={styles.roleCard}
              >
                <div style={{ ...styles.rcIc, backgroundColor: '#f1f5f9', color: '#475569' }}>⚖</div>
                <div style={styles.rcBody}>
                  <div style={styles.rcName}>Management</div>
                  <div style={styles.rcDesc}>Company-wide visibility &amp; exception oversight</div>
                </div>
                <div style={styles.rcArrow}>{loading ? '...' : '→'}</div>
              </button>
            </div>
          </form>
        </div>
      </main>
    </div>
  );
}

// Layout Inline Styles
const styles = {
  loginWrap: { display: 'flex', minHeight: '100vh', fontFamily: 'system-ui, -apple-system, sans-serif' },
  loginBrand: { flex: '1', backgroundColor: '#0f172a', color: '#ffffff', padding: '40px', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' },
  lbLogoPlate: { backgroundColor: '#ffffff', padding: '8px 16px', borderRadius: '8px', display: 'inline-block' },
  lbMid: { marginTop: '40px' },
  lbTag: { color: '#fb7185', fontSize: '12px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '1px' },
  lbHead: { fontSize: '28px', fontWeight: '800', margin: '12px 0', lineHeight: '1.2' },
  lbSub: { color: '#94a3b8', fontSize: '14px', lineHeight: '1.6' },
  lbStats: { display: 'flex', gap: '30px', marginTop: '30px' },
  statNum: { fontSize: '24px', fontWeight: '800', color: '#38bdf8' },
  statLabel: { fontSize: '12px', color: '#94a3b8' },
  lbFoot: { fontSize: '11px', color: '#64748b' },
  loginPanel: { flex: '1', backgroundColor: '#f8fafc', padding: '40px', display: 'flex', justifyContent: 'center', alignItems: 'center' },
  loginCard: { width: '100%', maxWidth: '440px', backgroundColor: '#ffffff', padding: '32px', borderRadius: '16px', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' },
  label: { display: 'block', fontSize: '12px', fontWeight: '600', color: '#334155', marginBottom: '6px' },
  input: { width: '100%', padding: '10px 14px', borderRadius: '8px', border: '1px solid #cbd5e1', fontSize: '14px', outline: 'none', boxSizing: 'border-box' },
  roleCard: { display: 'flex', alignItems: 'center', width: '100%', padding: '12px', borderRadius: '10px', border: '1px solid #e2e8f0', backgroundColor: '#ffffff', cursor: 'pointer', textAlign: 'left', transition: 'all 0.2s' },
  rcIc: { width: '32px', height: '32px', borderRadius: '8px', display: 'flex', justifyContent: 'center', alignItems: 'center', fontWeight: 'bold', marginRight: '12px' },
  rcBody: { flex: 1 },
  rcName: { fontSize: '13px', fontWeight: '700', color: '#0f172a' },
  rcDesc: { fontSize: '11px', color: '#64748b' },
  rcArrow: { fontSize: '14px', color: '#94a3b8' },
  errorAlert: { backgroundColor: '#fef2f2', color: '#dc2626', padding: '10px', borderRadius: '8px', fontSize: '12px', marginBottom: '16px', textAlign: 'center' }
};
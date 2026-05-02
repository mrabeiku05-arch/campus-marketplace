import React, { useEffect } from 'react';
import { HashRouter, Navigate, Route, Routes, useLocation, useParams } from 'react-router-dom';
import Layout from './components/layout/Layout';
import { AuthProvider } from './contexts/AuthContext';
import { ThemeProvider } from './contexts/ThemeContext';
import { buildLegacyUrl } from './utils/legacyAuth';
import Home from './pages/Home';

function ScrollToTop() {
  const { pathname, search } = useLocation();

  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
  }, [pathname, search]);

  return null;
}

function LegacyRedirect({
  target,
  message = 'Opening page...',
}: {
  target: string;
  message?: string;
}) {
  useEffect(() => {
    window.location.replace(buildLegacyUrl(target));
  }, [target]);

  return (
    <div className="container" style={{ padding: '4rem 0', textAlign: 'center' }}>
      <p style={{ marginBottom: '1rem' }}>{message}</p>
      <a href={buildLegacyUrl(target)} className="btn btn-primary">
        Continue
      </a>
    </div>
  );
}

function LegacyProductRedirect() {
  const { id } = useParams();
  return <LegacyRedirect target={`/product.php?id=${id || ''}`} message="Opening product..." />;
}

function LegacyChatRedirect() {
  const { search } = useLocation();
  return <LegacyRedirect target={`/chat.php${search}`} message="Opening messages..." />;
}

function AppRoutes() {
  return (
    <HashRouter>
      <ScrollToTop />
      <Routes>
        <Route element={<Layout />}>
          <Route path="/" element={<Home />} />
          <Route path="/leaderboard" element={<LegacyRedirect target="/leaderboard.php" message="Opening leaderboard..." />} />
          <Route path="/product/:id" element={<LegacyProductRedirect />} />
          <Route path="/login" element={<LegacyRedirect target="/login.php" message="Opening login..." />} />
          <Route path="/register" element={<LegacyRedirect target="/register.php" message="Opening registration..." />} />
          <Route path="/dashboard" element={<LegacyRedirect target="/dashboard.php" message="Opening dashboard..." />} />
          <Route path="/chat" element={<LegacyChatRedirect />} />
          <Route path="/add-product" element={<LegacyRedirect target="/add_product.php" message="Opening product form..." />} />
          <Route path="/edit-profile" element={<LegacyRedirect target="/edit_profile.php" message="Opening profile editor..." />} />
        </Route>

        <Route path="/admin" element={<LegacyRedirect target="/admin/" message="Opening admin dashboard..." />} />
        <Route path="/admin/analytics" element={<LegacyRedirect target="/admin/analytics.php" message="Opening analytics..." />} />
        <Route path="/admin/ads" element={<LegacyRedirect target="/admin/ads.php" message="Opening ads manager..." />} />
        <Route path="/admin/users" element={<LegacyRedirect target="/admin/users.php" message="Opening admin users..." />} />
        <Route path="/admin/*" element={<LegacyRedirect target="/admin/" message="Opening admin dashboard..." />} />

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </HashRouter>
  );
}

function GlobalImageViewer() {
  const [src, setSrc] = React.useState<string | null>(null);
  const [alt, setAlt] = React.useState<string | null>(null);

  React.useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      const isPreviewable = target.classList.contains('viewable-image') || target.classList.contains('profile-pic-previewable');
      if (target.tagName === 'IMG' && isPreviewable) {
        const img = target as HTMLImageElement;
        setSrc(img.src);
        setAlt(img.alt || '');
      }
    };
    document.addEventListener('click', handleClick);
    return () => document.removeEventListener('click', handleClick);
  }, []);

  React.useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && src) setSrc(null);
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [src]);

  if (!src) return null;

  return (
    <div 
      style={{
        position: 'fixed', inset: 0, zIndex: 1000001, 
        background: 'rgba(0,0,0,0.92)', 
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        cursor: 'zoom-out',
        animation: 'fadeIn 0.3s ease forwards'
      }}
      onClick={() => setSrc(null)}
    >
      <div style={{
          position: 'relative', display: 'flex', flexDirection: 'column',
          alignItems: 'center', gap: '1rem', padding: '20px',
          animation: 'zoomIn 0.35s cubic-bezier(0.19,1,0.22,1) forwards'
      }}>
        <button 
          style={{
            position: 'absolute', top: '-12px', right: '-12px', 
            background: 'rgba(255,255,255,0.12)', color: '#fff', border: 'none', 
            borderRadius: '50%', width: '42px', height: '42px', cursor: 'pointer',
            fontSize: '1.4rem', display: 'flex', alignItems: 'center', justifyContent: 'center',
            transition: 'background 0.2s'
          }}
          onClick={(e) => { e.stopPropagation(); setSrc(null); }}
          onMouseOver={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.22)')}
          onMouseOut={(e) => (e.currentTarget.style.background = 'rgba(255,255,255,0.12)')}
          aria-label="Close preview"
        >
          ×
        </button>
        <img 
          src={src} 
          alt={alt || "Preview"} 
          style={{
            maxWidth: 'min(500px, 88vw)', maxHeight: '72vh', 
            borderRadius: '20px', objectFit: 'contain',
            boxShadow: '0 32px 100px rgba(0,0,0,0.55)',
            border: '1px solid rgba(255,255,255,0.08)',
            background: '#111'
          }} 
          onClick={(e) => e.stopPropagation()}
        />
        {alt && (
          <div style={{
            color: 'rgba(255,255,255,0.85)', fontWeight: 600,
            fontSize: '0.95rem', letterSpacing: '-0.01em'
          }}>
            {alt}
          </div>
        )}
      </div>
      <style>{`
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes zoomIn { from { transform: scale(0.92); } to { transform: scale(1); } }
      `}</style>
    </div>
  );
}

export default function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <AppRoutes />
        <GlobalImageViewer />
      </AuthProvider>
    </ThemeProvider>
  );
}

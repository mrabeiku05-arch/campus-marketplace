/**
 * Header — Converted from includes/header.php
 * Preserves exact same navigation, categories dropdown, mobile toggle, and styling.
 */
import React, { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { useTheme } from '../../contexts/ThemeContext';
import { buildLegacyUrl } from '../../utils/legacyAuth';

export default function Header() {
  const { user, isLoggedIn, isAdmin, isSeller, logout } = useAuth();
  const { toggleTheme, isDark } = useTheme();
  const [catOpen, setCatOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const catRef = useRef<HTMLDivElement>(null);

  // Poll for unread messages
  useEffect(() => {
    if (user) {
      setUnreadCount(user.unread_messages || 0);
    }
  }, [user]);

  // Close category dropdown on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (catRef.current && !catRef.current.contains(e.target as Node)) {
        setCatOpen(false);
      }
    };
    document.addEventListener('click', handler);
    return () => document.removeEventListener('click', handler);
  }, []);

  useEffect(() => {
    return () => {
      document.body.style.overflow = '';
    };
  }, []);

  const toggleMobileNav = () => {
    setMobileOpen(prev => {
      const next = !prev;
      document.body.style.overflow = next ? 'hidden' : '';
      if (!next) setCatOpen(false);
      return next;
    });
  };

  const closeMobile = () => {
    setMobileOpen(false);
    setCatOpen(false);
    document.body.style.overflow = '';
  };

  const categories = [
    { name: 'Computer & Accessories', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> },
    { name: 'Phone & Accessories', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> },
    { name: 'Electrical Appliances', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> },
    { name: 'Fashion', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.57a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.57a2 2 0 0 0-1.34-2.13z"/></svg> },
    { name: 'Food & Groceries', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg> },
    { name: 'Education & Books', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg> },
    { name: 'Hostels for Rent', icon: <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> },
  ];

  const accountHomeUrl = isAdmin ? buildLegacyUrl('/admin/') : buildLegacyUrl('/dashboard.php');
  const accountHomeLabel = isAdmin ? 'Admin Panel' : 'Dashboard';
  const messagesUrl = isAdmin ? buildLegacyUrl('/admin/messages.php') : buildLegacyUrl('/chat.php');
  const canSellFromNav = isSeller && !isAdmin;

  return (
    <nav style={{position:'sticky', top:0, zIndex:999999, background: isDark ? '#1c1c1e' : '#ffffff', borderBottom: `1px solid ${isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)'}`, transition:'background 0.3s, border-color 0.3s', padding:'0 5%'}}>
      <div className="nav-shell" style={{display:'flex', alignItems:'center', justifyContent:'space-between', gap:'1rem', height:'58px', maxWidth:'1400px', margin:'0 auto', width:'100%'}}>
        {/* Brand */}
        <Link to="/" className="nav-brand-link" aria-label="Campus Marketplace home" title="Campus Marketplace" style={{color:'var(--text-main)', textDecoration:'none', display:'flex', alignItems:'center', justifyContent:'center', width:'40px', height:'40px', borderRadius:'12px', flexShrink:0, transition:'background 0.2s, opacity 0.2s'}} onClick={closeMobile}>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </Link>

        {/* Center Nav Links */}
        <div className={`nav-links ${mobileOpen ? 'open' : ''}`} id="mobileNavLinks" style={{display:'flex', alignItems:'center', justifyContent:'center', gap:'1.25rem', flex:1, minWidth:0}}>
          <Link to="/" className="nav-link-item" onClick={closeMobile} style={{color:'var(--text-muted)', fontWeight:600, fontSize:'0.95rem', padding:'0.55rem 0.9rem', borderRadius:'10px', transition:'all 0.2s', textDecoration:'none', whiteSpace:'nowrap', flexShrink:0}}>Explore</Link>

          {/* Categories Dropdown */}
          <div ref={catRef} className="cat-dropdown" style={{position:'relative', display:'inline-block', flexShrink:0}}>
            <a href="#" onClick={(e) => { e.preventDefault(); setCatOpen(!catOpen); }} style={{color:'var(--text-muted)', fontWeight:600, fontSize:'0.95rem', padding:'0.55rem 0.9rem', borderRadius:'10px', transition:'all 0.2s', textDecoration:'none', cursor:'pointer', display:'flex', alignItems:'center', gap:'4px', whiteSpace:'nowrap'}}>
              Categories
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </a>
            <div 
              className={`cat-dropdown-menu ${catOpen ? 'cat-open' : ''}`} 
              style={{
                position: mobileOpen ? 'static' : 'absolute', 
                top: mobileOpen ? '0' : 'calc(100% + 8px)', 
                left: mobileOpen ? '0' : '50%', 
                transform: mobileOpen ? 'none' : 'translateX(-50%)', 
                width: mobileOpen ? '100%' : '240px', 
                background: mobileOpen ? 'rgba(0,0,0,0.03)' : 'var(--card-bg)', 
                border: mobileOpen ? 'none' : '1px solid var(--border)', 
                borderRadius: mobileOpen ? '12px' : '16px', 
                boxShadow: isDark ? '0 10px 40px rgba(0,0,0,0.5)' : '0 10px 40px rgba(0,0,0,0.1)', 
                overflow: 'hidden', 
                zIndex: 999,
                marginTop: mobileOpen && catOpen ? '10px' : '0',
                maxHeight: catOpen ? '500px' : '0',
                transition: 'all 0.3s ease-in-out',
                display: mobileOpen || catOpen ? 'block' : 'none'
              }}
            >
              {categories.map((cat) => (
                <Link
                  key={cat.name}
                  to={{ pathname: '/', search: `?category=${encodeURIComponent(cat.name)}` }}
                  className="cat-item"
                  onClick={() => { setCatOpen(false); closeMobile(); }}
                  style={{ padding: mobileOpen ? '12px 16px' : '10px 16px' }}
                >
                  {cat.icon}
                  {cat.name}
                </Link>
              ))}
            </div>
          </div>

          {isLoggedIn ? (
            <>
              <a href={buildLegacyUrl('/leaderboard.php')} onClick={closeMobile} style={{color:'var(--text-muted)', fontWeight:600, fontSize:'0.95rem', padding:'0.55rem 0.9rem', borderRadius:'10px', transition:'all 0.2s', textDecoration:'none', whiteSpace:'nowrap', flexShrink:0}}>🏆 Rank</a>
              <a href={accountHomeUrl} onClick={closeMobile} style={{color:'var(--text-muted)', fontWeight:600, fontSize:'0.95rem', padding:'0.55rem 0.9rem', borderRadius:'10px', transition:'all 0.2s', textDecoration:'none', whiteSpace:'nowrap', flexShrink:0}}>{accountHomeLabel}</a>
              <a href={messagesUrl} onClick={closeMobile} style={{color:'var(--text-muted)', fontWeight:600, fontSize:'0.95rem', padding:'0.55rem 0.9rem', borderRadius:'10px', transition:'all 0.2s', textDecoration:'none', position:'relative', whiteSpace:'nowrap', flexShrink:0}}>
                Messages
                {unreadCount > 0 && <span className="notif-badge msg-unread-badge" style={{display:'flex'}}>{unreadCount}</span>}
              </a>
              {canSellFromNav && (
                <a href={buildLegacyUrl('/add_product.php')} onClick={closeMobile} className="btn btn-primary" style={{display:'inline-flex', alignItems:'center', justifyContent:'center', flexShrink:0, minHeight:'38px', padding:'0.5rem 1rem', borderRadius:980, fontSize:'0.85rem', fontWeight:700, textDecoration:'none'}}>+ Sell</a>
              )}
              <a href="#" className="nav-pill-link" onClick={(e) => { e.preventDefault(); closeMobile(); logout(); }} style={{color:'var(--text-muted)', fontWeight:600, fontSize:'0.95rem', padding:'0.55rem 0.95rem', borderRadius:'999px', transition:'all 0.2s', textDecoration:'none', whiteSpace:'nowrap', flexShrink:0}}>Sign Out</a>
            </>
          ) : (
            <>
              <a href={buildLegacyUrl('/login.php')} className="nav-pill-link" onClick={closeMobile} style={{color:'var(--text-muted)', fontWeight:500, fontSize:'0.85rem', padding:'0.4rem 0.85rem', borderRadius:'999px', transition:'all 0.2s', textDecoration:'none'}}>Sign In</a>
              <a href={buildLegacyUrl('/register.php')} onClick={closeMobile} className="btn btn-primary" style={{display:'inline-flex', alignItems:'center', justifyContent:'center', minHeight:'38px', padding:'0.45rem 1.1rem', borderRadius:'980px', textDecoration:'none', transition:'all 0.2s', fontWeight:600, fontSize:'0.85rem'}}>Sign Up</a>
            </>
          )}
        </div>

        {/* Right-side icons */}
        <div style={{display:'flex', alignItems:'center', gap:'12px', flexShrink:0}}>
          <button
            onClick={toggleTheme}
            className="theme-toggle-btn"
            title={isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
            aria-label="Toggle theme"
          >
            {isDark ? (
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            ) : (
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            )}
          </button>
          <a href="#" onClick={(e) => { e.preventDefault(); if (typeof (window as any).openSideCart === 'function') (window as any).openSideCart(); }} style={{position:'relative', color:'var(--text-main)', textDecoration:'none', padding:'6px', borderRadius:'8px', transition:'all 0.2s', display:'flex', alignItems:'center', justifyContent:'center'}} title="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span className="cart-count-badge" style={{display:'none', position:'absolute', top:'-5px', right:'-6px', background:'#ff3b30', color:'#fff', fontSize:'0.6rem', fontWeight:700, width:'17px', height:'17px', borderRadius:'50%', alignItems:'center', justifyContent:'center', boxShadow:'0 2px 8px rgba(255,59,48,0.4)'}}>0</span>
          </a>
          <button className="mobile-toggle" id="mobileNavToggle" onClick={toggleMobileNav} style={{color:'var(--text-main)', cursor:'pointer', background:'none', border:'none', padding:'6px', borderRadius:'8px'}} aria-label="Toggle menu">
            {mobileOpen ? (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            ) : (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            )}
          </button>
        </div>
      </div>
    </nav>
  );
}

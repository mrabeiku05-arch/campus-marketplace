import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Search, Upload, Users, ShoppingBag, GraduationCap, Rocket, BadgeDollarSign, Leaf } from 'lucide-react';
import { assetUrl } from './utils/assetUrl';

const cycleWords = ['Buy', 'Sell', 'Rent', 'Advertise'];

const LEGACY_BASE = (window as any).MARKETPLACE_BASE_URL || '/';
const usesSpaRouting = typeof document !== 'undefined' && !!document.getElementById('root');

function getAppBasePath() {
  if (typeof window === 'undefined') {
    return '';
  }

  return window.location.pathname.startsWith('/marketplace/') ? '/marketplace' : '';
}

function getSpaBaseUrl() {
  if (typeof window === 'undefined') {
    return '/';
  }

  const basePath = getAppBasePath();
  return `${window.location.origin}${basePath ? `${basePath}/` : '/'}`;
}

function buildHomeUrl(params: Record<string, string>) {
  const query = new URLSearchParams(params).toString();
  if (usesSpaRouting) {
    return `${getSpaBaseUrl()}#/${query ? `?${query}` : ''}`;
  }
  const basePath = `${LEGACY_BASE}index.php`;
  return `${basePath}${query ? `?${query}` : ''}`;
}

const categories = [
  { title: "Computer & Accessories", image: assetUrl('IMG_5825.webp') },
  { title: "Phone & Accessories",    image: assetUrl('IMG_5822.webp') },
  { title: "Electrical Appliances",  image: assetUrl('IMG_5827.webp') },
  { title: "Fashion",                image: assetUrl('IMG_5828.webp') },
  { title: "Food & Groceries",       image: assetUrl('IMG_5830.webp') },
  { title: "Education & Books",      image: assetUrl('IMG_5831.webp') },
  { title: "Hostels for Rent",       image: assetUrl('IMG_5833.webp') }
];

const howItWorks = [
  {
    icon: <Upload size={28} strokeWidth={1.5} />,
    title: 'Post Your Item',
    desc: 'Create a listing in minutes. Add photos, set a price, and go live — your item is instantly visible to everyone on campus.',
  },
  {
    icon: <Users size={28} strokeWidth={1.5} />,
    title: 'Connect with Buyers',
    desc: 'Receive messages from interested students. Chat, negotiate, and agree on a time and place that works for both of you.',
  },
  {
    icon: <ShoppingBag size={28} strokeWidth={1.5} />,
    title: 'Sell Your Item',
    desc: 'Meet safely on campus, hand over the item, and collect your payment in person. Simple, fast, and completely free.',
  },
];

const whyUs = [
  { icon: <GraduationCap size={22} strokeWidth={1.5} />, title: 'Exclusive to Students', desc: 'A safe, trusted community built specifically for campus life.' },
  { icon: <Rocket size={22} strokeWidth={1.5} />,          title: 'Fast & Easy',           desc: 'No shipping waits. Find what you need and collect it today.' },
  { icon: <BadgeDollarSign size={22} strokeWidth={1.5} />, title: 'Better Prices',          desc: 'Student-friendly deals with no hidden fees or markups.' },
  { icon: <Leaf size={22} strokeWidth={1.5} />,            title: 'Sustainable',            desc: 'Buy and sell second-hand to reduce campus waste.' },
];

// ─── Liquid Glass Card ────────────────────────────────────────────────
const GlassCard = ({ children, style = {} }: { children: React.ReactNode; style?: React.CSSProperties }) => {
  const [hovered, setHovered] = useState(false);
  return (
    <div
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      className="liquid-glass-card"
      style={{
        position: 'relative',
        overflow: 'hidden',
        borderRadius: '24px',
        padding: '2rem 1.75rem',
        border: '1px solid rgba(255,255,255,0.1)',
        background: '#1c1c1e',
        boxShadow: hovered
          ? '0 8px 32px rgba(0,0,0,0.3)'
          : '0 4px 16px rgba(0,0,0,0.2)',
        transform: hovered ? 'translateY(-6px) scale(1.01)' : 'translateY(0) scale(1)',
        transition: 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)',
        cursor: 'default',
        ...style,
      }}
    >
      <div style={{ position: 'relative', zIndex: 1 }}>
        {children}
      </div>
    </div>
  );
};

export default function Hero() {
  const [searchQuery, setSearchQuery] = useState('');
  const [wordIndex, setWordIndex] = useState(0);

  useEffect(() => {
    const interval = setInterval(() => {
      setWordIndex(i => (i + 1) % cycleWords.length);
    }, 2000);
    return () => clearInterval(interval);
  }, []);

  const handleSearch = () => {
    if (searchQuery.trim()) {
      if (usesSpaRouting) {
        window.location.hash = `#/?search=${encodeURIComponent(searchQuery.trim())}`;
      } else {
        window.location.href = buildHomeUrl({ search: searchQuery.trim() });
      }
    }
  };

  const handleSearchKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') handleSearch();
  };

  return (
    <>
      {/* ═══════════════════════════════════════
          HERO — Full-bleed video background
      ═══════════════════════════════════════ */}
      <div className="relative w-full flex flex-col justify-start items-center overflow-hidden h-[100vh]">
        <video
          autoPlay 
          loop 
          muted 
          playsInline
          className="absolute inset-0 w-full h-full object-cover object-center md:object-[center_30%]"
        >
          <source 
            src={assetUrl('hero.mp4')} 
            type="video/mp4" 
          />
        </video>

        {/* Gradient overlay */}
        <div className="absolute inset-0 z-[1]"
          style={{ background: 'linear-gradient(to bottom, rgba(0,0,0,0.62) 0%, rgba(0,0,0,0.28) 55%, rgba(0,0,0,0.55) 100%)' }}
        />
        {/* Bottom fade */}
        <div className="absolute inset-x-0 bottom-0 h-48 z-[2]"
          style={{ background: 'linear-gradient(to top, var(--bg, #fff), transparent)' }}
        />

        {/* ── Hero Content ── */}
        <div className="relative z-10 flex flex-col items-center text-center px-6 w-full max-w-4xl pt-[14vh]">

          {/* 1. Main Heading — "CampusMarketplace" */}
          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1] }}
            style={{
              fontSize: 'clamp(2.4rem, 6vw, 4rem)',
              fontWeight: 800,
              color: '#ffffff',
              letterSpacing: '-0.04em',
              lineHeight: 1.05,
              marginBottom: '1rem',
              fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            }}
          >
            CampusMarketplace
          </motion.h1>

          {/* 2. Animated cycling word — "Buy", "Sell", "Advertise" */}
          <div style={{
            position: 'relative',
            height: 'clamp(3rem, 7vw, 4.5rem)',
            width: '100%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            marginBottom: '0.6rem',
            overflow: 'hidden',
          }}>
            <AnimatePresence mode="wait">
              <motion.span
                key={cycleWords[wordIndex]}
                initial={{ opacity: 0, y: 30, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: -30, scale: 0.95 }}
                transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
                style={{
                  position: 'absolute',
                  whiteSpace: 'nowrap',
                  fontSize: 'clamp(2.5rem, 7vw, 5rem)',
                  fontWeight: 900,
                  letterSpacing: '-0.04em',
                  background: 'linear-gradient(105deg, #ffffff 0%, #a0d4ff 50%, #ffffff 100%)',
                  WebkitBackgroundClip: 'text',
                  WebkitTextFillColor: 'transparent',
                  lineHeight: 1.0,
                  fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
                }}
              >
                {cycleWords[wordIndex]}
              </motion.span>
            </AnimatePresence>
          </div>

          {/* 3. Static sub-line — "Everything You Need on Campus" */}
          <motion.p
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.2, ease: [0.22, 1, 0.36, 1] }}
            style={{
              fontSize: 'clamp(0.95rem, 2.2vw, 1.15rem)',
              color: 'rgba(255,255,255,0.72)',
              marginBottom: '2.5rem',
              fontWeight: 400,
              letterSpacing: '0.01em',
              fontFamily: "'Inter', sans-serif",
            }}
          >
            Everything You Need on Campus
          </motion.p>

          {/* Search Bar */}
          <motion.div
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.75, delay: 0.35, ease: [0.22, 1, 0.36, 1] }}
            className="w-full max-w-xl relative"
          >
            <div
              className="relative flex items-center overflow-hidden"
              style={{
                background: 'rgba(255,255,255,0.05)',
                border: '1px solid rgba(255,255,255,0.15)',
                borderRadius: '999px',
                boxShadow: '0 8px 32px rgba(0,0,0,0.2)',
              }}
            >
              <div className="pl-5 text-white/60">
                <Search size={17} />
              </div>
              <input
                type="text"
                placeholder="Search products, textbooks, phones..."
                style={{
                  flex: 1,
                  background: 'transparent',
                  border: 'none',
                  color: '#fff',
                  padding: '1rem 0.75rem',
                  fontWeight: 500,
                  fontSize: '0.9rem',
                  outline: 'none',
                }}
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                onKeyDown={handleSearchKeyDown}
              />
              <div style={{ paddingRight: '0.35rem' }}>
                <button
                  onClick={handleSearch}
                  style={{
                    background: '#0071e3',
                    color: '#fff',
                    border: 'none',
                    padding: '0.6rem 1.4rem',
                    borderRadius: '999px',
                    fontWeight: 600,
                    fontSize: '0.85rem',
                    cursor: 'pointer',
                    transition: 'all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275)',
                  }}
                  onMouseOver={e => { e.currentTarget.style.background = '#0080f8'; e.currentTarget.style.transform = 'scale(1.04)'; }}
                  onMouseOut={e => { e.currentTarget.style.background = '#0071e3'; e.currentTarget.style.transform = 'scale(1)'; }}
                >
                  Search
                </button>
              </div>
            </div>
          </motion.div>
        </div>
      </div>

      {/* ═══════════════════════════════════════
          HOW IT WORKS
      ═══════════════════════════════════════ */}
      <div id="how-it-works" className="hiw-section" style={{ padding: '3rem 1.5rem 2rem', position: 'relative', zIndex: 10 }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.55 }}
            style={{ textAlign: 'center', marginBottom: '2rem' }}
          >
            <span style={{
              display: 'inline-block', fontSize: '0.72rem', fontWeight: 700,
              letterSpacing: '0.16em', textTransform: 'uppercase' as const,
              color: '#0071e3', marginBottom: '0.7rem',
            }}>
              Simple Process
            </span>
            <h2 style={{
              fontSize: 'clamp(2rem, 4vw, 2.8rem)', fontWeight: 800,
              letterSpacing: '-0.03em', lineHeight: 1.1, margin: 0,
            }}>
              How It Works
            </h2>
          </motion.div>

          <div className="flex md:grid md:grid-cols-3 gap-6 overflow-x-auto snap-x snap-mandatory pb-4 hide-scrollbar" style={{ scrollbarWidth: 'none', WebkitOverflowScrolling: 'touch' }}>
            {howItWorks.map((step, i) => (
              <motion.div
                className="flex-shrink-0 w-[85vw] md:w-auto snap-center"
                key={i}
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-50px' }}
                transition={{ duration: 0.55, delay: i * 0.1, ease: [0.22, 1, 0.36, 1] }}
              >
                <GlassCard>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.85rem', marginBottom: '1.25rem' }}>
                    <div style={{
                      width: '52px', height: '52px', borderRadius: '16px',
                      background: 'linear-gradient(135deg, #0071e3 0%, #34aaff+ 100%)',
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      color: '#fff', flexShrink: 0,
                      boxShadow: '0 8px 20px rgba(0,113,227,0.28)',
                    }}>
                      {step.icon}
                    </div>
                    <span style={{ fontSize: '0.72rem', fontWeight: 700, letterSpacing: '0.12em', textTransform: 'uppercase' as const, color: 'var(--text-muted, #86868b)' }}>
                      Step {i + 1}
                    </span>
                  </div>
                  <h3 style={{ fontSize: '1.15rem', fontWeight: 700, marginBottom: '0.55rem', letterSpacing: '-0.02em' }}>
                    {step.title}
                  </h3>
                  <p style={{ fontSize: '0.88rem', color: 'var(--text-muted, #86868b)', lineHeight: 1.7, margin: 0 }}>
                    {step.desc}
                  </p>
                </GlassCard>
              </motion.div>
            ))}
          </div>
        </div>
      </div>

      {/* ═══════════════════════════════════════
          WHY CHOOSE US — Directly under How It Works
      ═══════════════════════════════════════ */}
      <div className="why-section" style={{ padding: '2rem 1.5rem 2.5rem', position: 'relative', zIndex: 10 }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto' }}>
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.55 }}
            style={{ textAlign: 'center', marginBottom: '2rem' }}
          >
            <span style={{
              display: 'inline-block', fontSize: '0.72rem', fontWeight: 700,
              letterSpacing: '0.16em', textTransform: 'uppercase' as const,
              color: '#0071e3', marginBottom: '0.7rem',
            }}>
              Campus First
            </span>
            <h2 style={{
              fontSize: 'clamp(2rem, 4vw, 2.8rem)', fontWeight: 800,
              letterSpacing: '-0.03em', lineHeight: 1.1, margin: 0,
            }}>
              Why CampusMarketplace?
            </h2>
          </motion.div>

          <div className="flex md:grid md:grid-cols-4 gap-5 overflow-x-auto snap-x snap-mandatory pb-4 hide-scrollbar" style={{ scrollbarWidth: 'none', WebkitOverflowScrolling: 'touch' }}>
            {whyUs.map((item, i) => (
              <motion.div
                className="flex-shrink-0 w-[75vw] md:w-auto snap-center"
                key={i}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-40px' }}
                transition={{ duration: 0.5, delay: i * 0.08 }}
              >
                <GlassCard style={{ padding: '1.6rem' }}>
                  <div style={{
                    width: '42px', height: '42px', borderRadius: '12px',
                    background: 'rgba(0,113,227,0.1)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    color: '#0071e3', marginBottom: '1rem',
                  }}>
                    {item.icon}
                  </div>
                  <h4 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: '0.4rem', letterSpacing: '-0.01em' }}>
                    {item.title}
                  </h4>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-muted, #86868b)', lineHeight: 1.65, margin: 0 }}>
                    {item.desc}
                  </p>
                </GlassCard>
              </motion.div>
            ))}
          </div>
        </div>
      </div>

      {/* ═══════════════════════════════════════
          CATEGORIES — Apple-style full-bleed cards
      ═══════════════════════════════════════ */}
      <div className="relative z-10 w-full pb-1">
        <div className="w-full">
          <div className="text-center py-8 md:py-16 px-6">
            <motion.p
              initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ duration: 0.6 }}
              style={{ color: '#0071e3', fontSize: '0.72rem', textTransform: 'uppercase', letterSpacing: '0.16em', fontWeight: 700, marginBottom: '0.7rem' }}
            >
              Categories
            </motion.p>
            <motion.h2
              initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.6, delay: 0.1 }}
              style={{ fontSize: 'clamp(2rem, 5vw, 3rem)', fontWeight: 800, letterSpacing: '-0.03em', lineHeight: 1.1 }}
            >
              Browse by category.
            </motion.h2>
          </div>

          {/* Desktop */}
          <div className="hero-desktop">
            <div className="grid grid-cols-2 gap-3 px-3 mb-3">
              {categories.slice(0, 2).map((cat, i) => (
                <CategoryCard key={i} cat={cat} height="h-[520px]" textSize="text-[32px]" padding="p-10" />
              ))}
            </div>
            <div className="grid grid-cols-3 gap-3 px-3 mb-3">
              {categories.slice(2, 5).map((cat, i) => (
                <CategoryCard key={i} cat={cat} height="h-[420px]" textSize="text-[26px]" padding="p-8" />
              ))}
            </div>
            <div className="grid grid-cols-2 gap-3 px-3 pb-3">
              {categories.slice(5, 7).map((cat, i) => (
                <CategoryCard key={i} cat={cat} height="h-[420px]" textSize="text-[26px]" padding="p-8" />
              ))}
            </div>
          </div>

          {/* Mobile scroll */}
          <div
            className="hero-mobile flex overflow-x-auto gap-4 px-4 pb-8 snap-x snap-mandatory scroll-smooth w-full"
            style={{ WebkitOverflowScrolling: 'touch' } as any}
          >
            {categories.map((cat) => (
              <a
                key={cat.title}
                href={buildHomeUrl({ category: cat.title })}
                className="group relative block overflow-hidden rounded-2xl no-underline flex-shrink-0 w-[80vw] snap-center"
              >
                <div className="relative w-full h-[350px] overflow-hidden rounded-2xl">
                  <img src={cat.image} alt={cat.title} className="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-[1.2s] ease-out" loading="lazy" />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
                  <div className="absolute bottom-0 left-0 right-0 p-6 text-left">
                    <h3 className="text-[22px] font-bold text-white tracking-[-0.01em] mb-1">{cat.title}</h3>
                    <p className="text-[#0071e3] text-[14px] font-medium opacity-80">Explore →</p>
                  </div>
                </div>
              </a>
            ))}
          </div>
        </div>
      </div>
    </>
  );
}

// ─── Category Card ──────────────────────────────────────────────
function CategoryCard({ cat, height, textSize, padding }: {
  cat: { title: string; image: string };
  height: string; textSize: string; padding: string;
}) {
  return (
    <motion.a
      href={buildHomeUrl({ category: cat.title })}
      initial={{ opacity: 0, y: 50 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
      className="group relative block overflow-hidden rounded-3xl no-underline"
    >
      <div className={`relative w-full ${height} overflow-hidden`}>
        <img src={cat.image} alt={cat.title}
          className="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-[1.2s] ease-out" loading="lazy" />
        <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
        <div className={`absolute bottom-0 left-0 right-0 ${padding} text-left`}>
          <h3 className={`${textSize} font-bold text-[#f5f5f7] tracking-[-0.01em] mb-1`}>{cat.title}</h3>
          <p className="text-[#0071e3] text-[14px] font-medium opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300">
            Explore →
          </p>
        </div>
      </div>
    </motion.a>
  );
}

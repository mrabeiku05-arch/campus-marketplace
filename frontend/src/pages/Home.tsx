import React, { Suspense, lazy, useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { ads, products, recommendations, search as searchApi } from '../services/api';
import { assetUrl } from '../utils/assetUrl';
import { buildLegacyUrl, redirectToLegacyDashboard } from '../utils/legacyAuth';

const Hero = lazy(() => import('../Hero'));

type SellerBadge = {
  label: string;
  color: string;
  bg: string;
};

type ProductItem = {
  id: number;
  title: string;
  price: number | string;
  main_image?: string | null;
  seller_name?: string;
  seller_tier?: string;
  seller_badge?: SellerBadge;
  seller_verified?: boolean | number;
  boosted_until?: string | null;
  original_price_before_discount?: number | string | null;
  discount_percent?: number;
  promo_tag?: string | null;
  quantity?: number;
};

type AdItem = {
  id?: number;
  title: string;
  link_url: string;
  image_url?: string | null;
  image_path?: string | null;
};

type RecommendationItem = {
  id: number;
  title: string;
  price: number | string;
  main_image?: string | null;
};

type SearchSuggestion = {
  id: number;
  title: string;
  price: number | string;
  category?: string;
  image?: string | null;
};

const defaultCategories = [
  'Computer & Accessories',
  'Phone & Accessories',
  'Electrical Appliances',
  'Fashion',
  'Food & Groceries',
  'Education & Books',
  'Hostels for Rent',
];

const catImages: Record<string, string> = {
  'Computer & Accessories': 'IMG_5825.webp',
  'Phone & Accessories': 'IMG_5822.webp',
  'Electrical Appliances': 'IMG_5827.webp',
  Fashion: 'IMG_5828.webp',
  'Food & Groceries': 'IMG_5830.webp',
  'Education & Books': 'IMG_5831.webp',
  'Hostels for Rent': 'IMG_5833.webp',
};

const catDescriptions: Record<string, string> = {
  'Computer & Accessories': 'Laptops, monitors, and all computing essentials.',
  'Phone & Accessories': 'Smartphones, covers, screen protectors, and mobile gear.',
  'Electrical Appliances': 'Home and dorm gadgets, microwaves, fans, and blenders.',
  Fashion: 'Trendy clothing, shoes, bags, and stylish accessories.',
  'Food & Groceries': 'Snacks, beverages, fresh produce, and daily provisions.',
  'Education & Books': 'Textbooks, notebooks, stationery, and study materials.',
  'Hostels for Rent': 'Accommodation, shared rooms, apartments, and living spaces.',
};

function dedupeById<T extends { id?: string | number }>(items: T[]) {
  const seen = new Set<string>();
  return items.filter((item, index) => {
    const key = item?.id != null ? String(item.id) : `fallback-${index}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function SellerBadgePill({ badge, tier, verified }: { badge?: SellerBadge; tier?: string; verified?: boolean | number }) {
  const normalizedTier = (tier || 'basic').toLowerCase();
  const icon = normalizedTier === 'premium' ? '⭐' : normalizedTier === 'pro' ? '⚡' : '✔️';

  return (
    <>
      <span
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: '0.28rem',
          marginLeft: '0.4rem',
          padding: '4px 10px',
          borderRadius: '999px',
          background: badge?.bg || 'rgba(0,113,227,0.1)',
          color: badge?.color || '#0071e3',
          fontSize: '0.62rem',
          fontWeight: 800,
          letterSpacing: '0.06em',
          textTransform: 'uppercase',
          verticalAlign: 'middle',
        }}
      >
        <span aria-hidden="true">{icon}</span>
        {badge?.label || normalizedTier}
      </span>
      {Boolean(verified) && (
        <span
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            marginLeft: '0.35rem',
            padding: '4px 8px',
            borderRadius: '999px',
            background: 'rgba(52,199,89,0.12)',
            color: '#34c759',
            fontSize: '0.62rem',
            fontWeight: 800,
            letterSpacing: '0.05em',
            textTransform: 'uppercase',
            verticalAlign: 'middle',
          }}
        >
          ✓ Verified
        </span>
      )}
    </>
  );
}

export default function Home() {
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const [productsList, setProductsList] = useState<ProductItem[]>([]);
  const [adsList, setAdsList] = useState<AdItem[]>([]);
  const [aiRecs, setAiRecs] = useState<RecommendationItem[]>([]);
  const [availableCategories, setAvailableCategories] = useState<string[]>(defaultCategories);
  const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
  const [suggestionsOpen, setSuggestionsOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(12);
  const [searchInput, setSearchInput] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [minPriceInput, setMinPriceInput] = useState('');
  const [maxPriceInput, setMaxPriceInput] = useState('');

  const [activeAdIndex, setActiveAdIndex] = useState(0);
  const [isAdCarouselPaused, setIsAdCarouselPaused] = useState(false);
  const searchBoxRef = useRef<HTMLDivElement | null>(null);

  const search = searchParams.get('search') || '';
  const category = searchParams.get('category') || '';
  const minPrice = searchParams.get('min_price') || '';
  const maxPrice = searchParams.get('max_price') || '';
  const seller = searchParams.get('seller') || '';
  const sellerId = searchParams.get('seller_id') || '';
  const page = parseInt(searchParams.get('page') || '1', 10);

  useEffect(() => {
    setSearchInput(search);
    setSelectedCategory(category);
    setMinPriceInput(minPrice);
    setMaxPriceInput(maxPrice);
  }, [search, category, minPrice, maxPrice]);

  useEffect(() => {
    if (user?.has_unreviewed_orders) {
      redirectToLegacyDashboard(user, '/dashboard.php#buyer_orders').catch((err) => {
        console.error('Failed to open legacy dashboard', err);
      });
    }
  }, [user]);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (searchBoxRef.current && !searchBoxRef.current.contains(event.target as Node)) {
        setSuggestionsOpen(false);
      }
    };

    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  useEffect(() => {
    const query = searchInput.trim();

    if (query.length < 2) {
      setSuggestions([]);
      setSuggestionsOpen(false);
      return;
    }

    const timeoutId = window.setTimeout(async () => {
      try {
        const result = await searchApi.suggest(query);
        const nextSuggestions = dedupeById((result as any).suggestions || []);
        setSuggestions(nextSuggestions);
        setSuggestionsOpen(nextSuggestions.length > 0);
      } catch (err) {
        console.error('Search suggestions failed', err);
        setSuggestions([]);
        setSuggestionsOpen(false);
      }
    }, 180);

    return () => window.clearTimeout(timeoutId);
  }, [searchInput]);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const adLoc = category ? 'category' : 'homepage';
        const productParams: Record<string, string> = {
          q: search,
          category,
          min_price: minPrice,
          max_price: maxPrice,
          page: page.toString(),
          per_page: '12',
        };

        if (seller) productParams.seller = seller;
        if (sellerId) productParams.seller_id = sellerId;

        const [prodRes, adRes] = await Promise.all([
          products.list(productParams),
          ads.list(adLoc).catch(() => ({ ads: [] })),
        ]);

        setProductsList(dedupeById((prodRes as any).products || []));
        setAvailableCategories(dedupeById((((prodRes as any).categories || defaultCategories).map((name: string, index: number) => ({ id: name || index, value: name })))).map((item: any) => item.value));
        setTotal((prodRes as any).pagination?.total || 0);
        setPerPage((prodRes as any).pagination?.per_page || 12);
        setAdsList(dedupeById((adRes as any).ads || []));

        if (!search && !category && page === 1) {
          const recsRes = await recommendations.get('home').catch(() => ({ recommendations: [] }));
          setAiRecs(dedupeById((recsRes as any).recommendations || []));
        } else {
          setAiRecs([]);
        }
      } catch (err) {
        console.error('Failed to load home data', err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [search, category, minPrice, maxPrice, page, seller, sellerId]);

  const stableProducts = useMemo(() => dedupeById(productsList), [productsList]);
  const stableAds = useMemo(() => dedupeById(adsList), [adsList]);
  const stableAiRecs = useMemo(() => dedupeById(aiRecs), [aiRecs]);
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const isDefaultHome = !search && !category && !minPrice && !maxPrice && page === 1 && !seller && !sellerId;
  const productUrl = (id: number) => buildLegacyUrl(`/product.php?id=${id}`);

  useEffect(() => {
    if (stableAds.length <= 1) return;
    if (isAdCarouselPaused) return;

    const interval = window.setInterval(() => {
      setActiveAdIndex((prev) => (prev + 1) % stableAds.length);
    }, 5000);

    return () => window.clearInterval(interval);
  }, [stableAds.length, isAdCarouselPaused]);

  useEffect(() => {
    if (stableAds.length === 0) {
      setActiveAdIndex(0);
      return;
    }

    if (activeAdIndex > stableAds.length - 1) {
      setActiveAdIndex(0);
    }
  }, [stableAds.length, activeAdIndex]);

  const buildParams = (values: Record<string, string>) => {
    const params: Record<string, string> = {};

    Object.entries(values).forEach(([key, value]) => {
      if (value !== '') {
        params[key] = value;
      }
    });

    if (seller) params.seller = seller;
    if (sellerId) params.seller_id = sellerId;

    return params;
  };

  const handleSearch = (event: React.FormEvent) => {
    event.preventDefault();
    setSuggestionsOpen(false);
    setSearchParams(
      buildParams({
        search: searchInput.trim(),
        category: selectedCategory,
        min_price: minPriceInput,
        max_price: maxPriceInput,
        page: '1',
      }),
    );
  };

  const handleSuggestionSelect = (suggestion: SearchSuggestion) => {
    setSearchInput(suggestion.title);
    setSuggestionsOpen(false);
    setSearchParams(
      buildParams({
        search: suggestion.title,
        category: selectedCategory,
        min_price: minPriceInput,
        max_price: maxPriceInput,
        page: '1',
      }),
    );
  };

  return (
    <>
      {isDefaultHome ? (
        <Suspense fallback={<div style={{ minHeight: '60vh' }} />}>
          <Hero />
        </Suspense>
      ) : category && catImages[category] ? (
        <div style={{ position: 'relative', width: '100%', height: '40vh', minHeight: '300px', display: 'flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden', marginBottom: '2rem' }}>
          <img src={assetUrl(catImages[category])} alt={category} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'center top', zIndex: 0 }} />
          <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 1 }} />
          <div style={{ textAlign: 'center', zIndex: 2, padding: '0 20px' }}>
            <h1 style={{ fontSize: '3.5rem', fontWeight: 800, color: '#fff', letterSpacing: '-0.04em', textShadow: '0 12px 40px rgba(0,0,0,0.8)' }}>{category}</h1>
            <p style={{ color: '#f5f5f7', fontSize: '1.2rem', marginTop: '0.5rem', fontWeight: 500 }}>{catDescriptions[category]}</p>
            <p style={{ color: '#f5f5f7', fontSize: '1rem', marginTop: '0.5rem', fontWeight: 400 }}>Found {total} products</p>
          </div>
        </div>
      ) : (
        <div style={{ padding: '5rem 5% 3rem', background: 'linear-gradient(to bottom, rgba(0,113,227,0.06), transparent)', borderBottom: '1px solid rgba(0,0,0,0.05)', textAlign: 'center', marginBottom: '2rem', width: '100%' }}>
          <h1 style={{ fontSize: '3.5rem', fontWeight: 800, color: 'var(--text-main)', letterSpacing: '-0.04em' }}>
            {search ? `Search: "${search}"` : 'All Products'}
          </h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '1.2rem', marginTop: '0.5rem', fontWeight: 500 }}>Found {total} items</p>
        </div>
      )}

      <div className="container">
        <div className="notice-box-inline mb-4" style={{ maxWidth: '800px', margin: '20px auto', background: 'rgba(255,204,0,0.15)', border: '1px solid rgba(255,204,0,0.5)', padding: '15px', borderRadius: '12px', textAlign: 'center' }}>
          <strong><span style={{ fontSize: '1.1rem', verticalAlign: 'middle', marginRight: '5px' }}>⚠️</span> SAFETY NOTICE:</strong>{' '}
          <span>All transactions should be made in person. Sending money online without meeting the seller is at your own risk. CampusMarketplace will not be held accountable for online transactions.</span>
        </div>

        <div className="glass filter-bar" style={{ marginBottom: '2rem' }}>
          <form onSubmit={handleSearch} style={{ display: 'flex', gap: '0.75rem', width: '100%', flexWrap: 'wrap', alignItems: 'center' }}>
            <div ref={searchBoxRef} style={{ flex: 1, minWidth: '180px', position: 'relative' }}>
              <input
                type="text"
                name="search"
                className="form-control"
                autoComplete="off"
                placeholder="Search products..."
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                onFocus={() => setSuggestionsOpen(suggestions.length > 0)}
                style={{ width: '100%' }}
              />
              {suggestionsOpen && suggestions.length > 0 && (
                <div style={{ position: 'absolute', top: 'calc(100% + 4px)', left: 0, right: 0, background: 'var(--card-bg)', border: '1px solid var(--border)', borderRadius: '12px', boxShadow: '0 8px 24px rgba(0,0,0,0.15)', zIndex: 9999, maxHeight: '280px', overflowY: 'auto' }}>
                  {suggestions.map((suggestion, index) => (
                    <button
                      key={suggestion.id ?? index}
                      type="button"
                      onClick={() => handleSuggestionSelect(suggestion)}
                      style={{ display: 'flex', width: '100%', gap: '0.75rem', alignItems: 'center', padding: '10px 14px', border: 'none', background: 'transparent', cursor: 'pointer', borderBottom: index === suggestions.length - 1 ? 'none' : '1px solid rgba(0,0,0,0.05)', textAlign: 'left' }}
                    >
                      {suggestion.image ? (
                        <img src={assetUrl(suggestion.image)} alt={suggestion.title} style={{ width: '44px', height: '44px', objectFit: 'cover', borderRadius: '10px', flexShrink: 0 }} />
                      ) : (
                        <div style={{ width: '44px', height: '44px', borderRadius: '10px', background: 'rgba(0,113,227,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#0071e3', fontWeight: 700, flexShrink: 0 }}>
                          {suggestion.title.slice(0, 1).toUpperCase()}
                        </div>
                      )}
                      <div style={{ minWidth: 0 }}>
                        <div style={{ fontWeight: 700, color: 'var(--text-main)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{suggestion.title}</div>
                        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', fontSize: '0.78rem', color: 'var(--text-muted)' }}>
                          {suggestion.category ? <span>{suggestion.category}</span> : null}
                          <span>₵{Number(suggestion.price).toFixed(2)}</span>
                        </div>
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
            <select name="category" className="form-control" value={selectedCategory} onChange={(event) => setSelectedCategory(event.target.value)} style={{ maxWidth: '160px' }}>
              <option value="">All Categories</option>
              {availableCategories.map((name) => <option key={name} value={name}>{name}</option>)}
            </select>
            <input type="number" name="min_price" className="form-control" placeholder="Min ₵" value={minPriceInput} onChange={(event) => setMinPriceInput(event.target.value)} style={{ width: '100px' }} />
            <input type="number" name="max_price" className="form-control" placeholder="Max ₵" value={maxPriceInput} onChange={(event) => setMaxPriceInput(event.target.value)} style={{ width: '100px' }} />
            <button type="submit" className="btn btn-primary">Search</button>
          </form>
        </div>

        {stableAds.length > 0 && (
          <div style={{ marginTop: '2rem', marginBottom: '1.5rem', position: 'relative' }}>
            <style>{`
              .home-ad-slider {
                overflow: hidden;
                border-radius: 24px;
              }
              .home-ad-track {
                display: flex;
                width: 100%;
                transition: transform 0.55s ease;
                will-change: transform;
              }
              .home-ad-slide {
                flex: 0 0 100%;
                width: 100%;
              }
              .home-ad-banner-img {
                height: 200px;
              }
              .home-ad-dot {
                width: 7px;
                height: 7px;
                border-radius: 999px;
                border: none;
                background: rgba(255,255,255,0.4);
                transition: all 0.2s ease;
                cursor: pointer;
              }
              .home-ad-dot.active {
                width: 18px;
                background: #fff;
              }
              @media (min-width: 768px) {
                .home-ad-banner-img { height: 280px; }
                .ad-image-container:hover { transform: scale(1.005); filter: brightness(1.05); }
              }
            `}</style>
            <div
              className="home-ad-slider"
              onMouseEnter={() => setIsAdCarouselPaused(true)}
              onMouseLeave={() => setIsAdCarouselPaused(false)}
            >
              <div
                className="home-ad-track"
                style={{ transform: `translateX(-${activeAdIndex * 100}%)` }}
              >
                {stableAds.map((ad, index) => {
                  const adImage = ad.image_url || ad.image_path || '';

                  return (
                    <div key={ad.id ?? `ad-${index}`} className="home-ad-slide">
                      <a
                        href={ad.link_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={() => { if (ad.id) ads.click(ad.id).catch(() => {}); }}
                        className="fade-in ad-item-link"
                        style={{ textDecoration: 'none', display: 'block' }}
                      >
                        <div className="ad-image-container" style={{ borderRadius: '24px', overflow: 'hidden', border: '1px solid rgba(0,0,0,0.05)', position: 'relative', transition: 'all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1)', boxShadow: '0 10px 30px rgba(0,0,0,0.08)' }}>
                          {adImage ? (
                            <img src={assetUrl(adImage)} alt={ad.title} className="home-ad-banner-img" loading="lazy" style={{ width: '100%', objectFit: 'cover', display: 'block' }} />
                          ) : (
                            <div style={{ background: 'linear-gradient(135deg, #0071e3, #34aaff)', color: '#fff', padding: '2.5rem', textAlign: 'center', minHeight: '160px', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
                              <p style={{ fontSize: '0.7rem', letterSpacing: '0.15em', textTransform: 'uppercase', opacity: 0.8, marginBottom: '0.5rem', fontWeight: 700 }}>Sponsored Content</p>
                              <p style={{ fontSize: '1.5rem', fontWeight: 800, letterSpacing: '-0.02em' }}>{ad.title}</p>
                            </div>
                          )}
                          <div style={{ position: 'absolute', inset: 'auto 0 0 0', padding: '1rem 1.1rem', background: 'linear-gradient(to top, rgba(0,0,0,0.68), rgba(0,0,0,0.08))', color: '#fff' }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem' }}>
                              <div style={{ minWidth: 0 }}>
                                <p style={{ fontSize: '0.68rem', letterSpacing: '0.14em', textTransform: 'uppercase', opacity: 0.78, margin: '0 0 0.35rem', fontWeight: 700 }}>Sponsored</p>
                                <p style={{ fontSize: '1rem', fontWeight: 800, letterSpacing: '-0.02em', margin: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{ad.title}</p>
                              </div>
                              <span style={{ background: 'rgba(255,255,255,0.14)', color: '#fff', fontSize: '0.62rem', padding: '4px 10px', borderRadius: '999px', letterSpacing: '0.08em', fontWeight: 700, border: '1px solid rgba(255,255,255,0.15)', flexShrink: 0 }}>AD</span>
                            </div>
                          </div>
                        </div>
                      </a>
                    </div>
                  );
                })}
              </div>
            </div>
            {stableAds.length > 1 && (
              <div style={{ position: 'absolute', bottom: '16px', left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: '7px', zIndex: 5, background: 'rgba(0,0,0,0.5)', padding: '5px 10px', borderRadius: '999px' }}>
                {stableAds.map((ad, index) => (
                  <button
                    key={ad.id ?? `ad-dot-${index}`}
                    type="button"
                    className={`home-ad-dot ${index === activeAdIndex ? 'active' : ''}`}
                    aria-label={`Go to ad ${index + 1}`}
                    onClick={() => setActiveAdIndex(index)}
                  />
                ))}
              </div>
            )}
          </div>
        )}

        <h2 className="mb-2" style={{ fontSize: '1.3rem' }}>Approved Listings <span className="text-muted" style={{ fontSize: '0.9rem' }}>({total} items)</span></h2>

        {isDefaultHome && stableAiRecs.length > 0 && (
          <div style={{ marginBottom: '2rem', position: 'relative' }}>
            <h3 className="mb-3" style={{ fontSize: '1.4rem', fontWeight: 800, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#ai-grad)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <defs><linearGradient id="ai-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stopColor="#0071e3" /><stop offset="100%" stopColor="#34aaff" /></linearGradient></defs>
                  <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83" />
                </svg>
                Recommendations
              </span>
              <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)', fontWeight: 600 }}>Swipe →</span>
            </h3>

            <div className="horizontal-scroll-container" style={{ scrollSnapType: 'x mandatory', WebkitOverflowScrolling: 'touch' }}>
              {stableAiRecs.map((item, index) => (
                <a key={item.id ?? `rec-${index}`} href={productUrl(item.id)} className="scroll-card glass fade-in" style={{ scrollSnapAlign: 'start', minWidth: '160px', textDecoration: 'none' }}>
                  <div className="product-img-wrap" style={{ width: '100%', aspectRatio: '4/3', maxHeight: '140px', borderRadius: '12px', overflow: 'hidden' }}>
                    {item.main_image ? (
                      <img src={assetUrl(item.main_image)} alt={item.title} className="product-img" loading="lazy" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    ) : (
                      <div className="product-img" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#555', background: 'rgba(0,0,0,0.1)' }}>No Image</div>
                    )}
                  </div>
                  <div className="product-body" style={{ padding: '10px 6px' }}>
                    <p className="product-title" style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', fontSize: '0.8rem', fontWeight: 700, margin: 0, color: 'var(--text-main)' }}>{item.title}</p>
                    <p className="product-price" style={{ fontSize: '0.9rem', fontWeight: 800, color: 'var(--primary)', marginTop: '2px' }}>₵{Number(item.price).toFixed(2)}</p>
                  </div>
                </a>
              ))}
            </div>
          </div>
        )}

        {loading ? (
          <p className="text-center">Loading products...</p>
        ) : stableProducts.length > 0 ? (
          <div className="product-grid">
            {stableProducts.map((product, index) => {
              const promo = product.promo_tag ? product.promo_tag.trim() : '';
              const originalPrice = Number(product.original_price_before_discount || 0);
              const currentPrice = Number(product.price);
              const discountPercent = product.discount_percent || (originalPrice > currentPrice ? Math.round(100 - ((currentPrice / originalPrice) * 100)) : 0);
              const isBoosted = Boolean(product.boosted_until && new Date(product.boosted_until).getTime() > Date.now());
              const quantity = Number(product.quantity ?? 0);

              return (
                <a key={product.id ?? `product-${index}`} href={productUrl(product.id)} className="glass product-card fade-in" style={{ flexDirection: 'column', textDecoration: 'none', color: 'inherit' }}>
                  <div className="product-img-wrap" style={{ width: '100%', aspectRatio: '1 / 1', borderRadius: '14px', overflow: 'hidden' }}>
                    {product.main_image ? (
                      <img src={assetUrl(product.main_image)} alt={product.title} className="product-img" loading="lazy" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                    ) : (
                      <div className="product-img" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#555', background: 'rgba(0,0,0,0.3)' }}>No Image</div>
                    )}

                    {isBoosted && (
                      <>
                        <span className="boosted-badge">⚡ Boosted</span>
                        <span className="featured-badge">⭐ Featured</span>
                      </>
                    )}

                    {promo && (
                      <span className="boosted-badge" style={{ top: '8px', bottom: 'auto', left: '8px', background: 'rgba(0,0,0,0.85)', color: '#fff', fontSize: '0.6rem', padding: '0.3rem 0.6rem', borderRadius: '8px', fontWeight: 600, boxShadow: '0 2px 8px rgba(0,0,0,0.2)', border: '1px solid rgba(255,255,255,0.15)', display: 'flex', alignItems: 'center', gap: '4px', letterSpacing: '0.02em' }}>
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#facc15" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" /></svg>
                        {promo.replace(/[⚡⏳🎓📦🏷️]\s*/g, '')}
                      </span>
                    )}
                  </div>

                  <div className="product-body">
                    <p className="product-title" style={{ fontSize: '0.75rem' }}>{product.title}</p>
                    {originalPrice > currentPrice ? (
                      <p className="product-price">
                        <span style={{ textDecoration: 'line-through', opacity: 0.5, fontSize: '0.75rem', fontWeight: 400 }}>₵{originalPrice.toFixed(2)}</span>
                        ₵{currentPrice.toFixed(2)}
                        {discountPercent > 0 && (
                          <span style={{ background: 'rgba(239,68,68,0.12)', color: '#ef4444', fontSize: '0.55rem', fontWeight: 700, padding: '0.15rem 0.35rem', borderRadius: '4px', marginLeft: '2px' }}>
                            −{discountPercent}%
                          </span>
                        )}
                      </p>
                    ) : (
                      <p className="product-price">₵{currentPrice.toFixed(2)}</p>
                    )}
                    <p className="product-meta">
                      By {product.seller_name}
                      <SellerBadgePill badge={product.seller_badge} tier={product.seller_tier} verified={product.seller_verified} />
                    </p>

                    {quantity <= 0 ? (
                      <span style={{ display: 'block', fontSize: '0.65rem', color: '#ef4444', fontWeight: 700, marginTop: '0.2rem' }}>🚫 Out of Stock</span>
                    ) : quantity <= 5 ? (
                      <span style={{ display: 'block', fontSize: '0.65rem', color: '#ff9500', fontWeight: 700, marginTop: '0.2rem' }}>🔥 Only {quantity} left!</span>
                    ) : null}

                    {quantity > 0 && (
                      <button
                        type="button"
                        className="quick-add-btn"
                        onClick={(event) => {
                          event.preventDefault();
                          event.stopPropagation();
                          const image = product.main_image ? assetUrl(product.main_image) : '';
                          (window as any).cmCart?.add(product.id, product.title, currentPrice, image);
                          const button = event.currentTarget;
                          button.textContent = '✓ Added';
                          window.setTimeout(() => {
                            button.textContent = '+ Add';
                          }, 1500);
                        }}
                      >
                        + Add
                      </button>
                    )}
                  </div>
                </a>
              );
            })}
          </div>
        ) : (
          <p className="text-muted">No products found matching your criteria.</p>
        )}

        {totalPages > 1 && (
          <div className="flex gap-1 mt-3" style={{ justifyContent: 'center' }}>
            {Array.from({ length: totalPages }, (_, index) => index + 1).map((pageNumber) => (
              <button
                key={pageNumber}
                onClick={() => setSearchParams(buildParams({ search, category, min_price: minPrice, max_price: maxPrice, page: pageNumber.toString() }))}
                className={`btn ${pageNumber === page ? 'btn-primary' : 'btn-outline'} btn-sm`}
              >
                {pageNumber}
              </button>
            ))}
          </div>
        )}
      </div>
    </>
  );
}

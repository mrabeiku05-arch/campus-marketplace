/**
 * Footer — Converted from includes/footer.php
 * Preserves footer layout, AI assistant chat widget, side cart drawer, and terms modal.
 */
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ai, settings } from '../../services/api';
import PricingSimple from '../ui/pricing-blocks';
import { buildLegacyUrl } from '../../utils/legacyAuth';
import { useAuth } from '../../contexts/AuthContext';

function TermsModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [scrolledToBottom, setScrolledToBottom] = useState(false);
  const [progressPercent, setProgressPercent] = useState(0);
  const [tiers, setTiers] = useState<any[]>([]);

  useEffect(() => {
    if (open) {
      settings.tiers()
        .then(res => setTiers(res.tiers || []))
        .catch(console.error);
    }
  }, [open]);

  const handleScroll = (e: React.UIEvent<HTMLDivElement>) => {
    const el = e.currentTarget;
    const percent = (el.scrollTop / (el.scrollHeight - el.clientHeight)) * 100;
    setProgressPercent(percent);
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 5) {
      setScrolledToBottom(true);
    }
  };

  if (!open) return null;

  return (
    <div className="modal-overlay open" style={{display:'flex', position:'fixed', top:0, left:0, width:'100%', height:'100%', background:'rgba(0,0,0,0.85)', zIndex:1000000, alignItems:'center', justifyContent:'center', padding:'20px'}}>
      <div className="glass" style={{width:'100%', maxWidth:'850px', height:'85vh', borderRadius:'32px', display:'flex', flexDirection:'column', overflow:'hidden', position:'relative', boxShadow:'0 30px 100px rgba(0,0,0,0.3)', animation:'modalSlideUp 0.4s cubic-bezier(0.19, 1, 0.22, 1)'}}>
        <div style={{padding:'1.5rem 2rem', borderBottom:'1px solid var(--border)', display:'flex', justifyContent:'space-between', alignItems:'center'}}>
          <h3 style={{margin:0, fontSize:'1.2rem', fontWeight:800}}>Terms & Conditions</h3>
          <button onClick={onClose} style={{background:'rgba(0,0,0,0.05)', border:'none', width:'36px', height:'36px', borderRadius:'50%', cursor:'pointer', fontSize:'1.5rem', display:'flex', alignItems:'center', justifyContent:'center'}}>&times;</button>
        </div>
        <div style={{width:'100%', height:'4px', background:'rgba(0,113,227,0.1)', position:'relative', overflow:'hidden'}}>
          <div style={{position:'absolute', top:0, left:0, height:'100%', width:`${progressPercent}%`, background:'#0071e3', transition:'width 0.1s'}}></div>
        </div>
        <div onScroll={handleScroll} style={{flex:1, overflowY:'auto', padding:'2rem 2.5rem', fontSize:'0.95rem', lineHeight:1.8, color:'var(--text-main)', scrollBehavior:'smooth'}}>
          <h2 style={{fontSize: '1.4rem', marginBottom: '1.5rem'}}>Campus Marketplace Platform</h2>
          <p style={{color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: '2rem'}}>Last Updated: March 29, 2026</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>1. INTRODUCTION</h3>
          <p>Welcome to Campus Marketplace. By accessing or using this platform, you agree to comply with and be bound by these Terms and Conditions. If you do not agree, you must not use this platform.</p>
          <p>This platform connects buyers and sellers within the campus community for the exchange of goods and services.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>2. USER ELIGIBILITY</h3>
          <ul>
              <li>Users must provide accurate information during registration.</li>
              <li>Users must belong to the campus community or have valid access.</li>
              <li>Each user is responsible for maintaining the confidentiality of their account.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>3. ACCOUNT REGISTRATION</h3>
          <p>By creating an account, you agree to:</p>
          <ul>
              <li>Provide truthful personal details (including faculty, department, and residence if required)</li>
              <li>Keep login credentials secure</li>
              <li>Accept responsibility for all activities under your account</li>
          </ul>
          <p>We reserve the right to suspend or terminate accounts that provide false or misleading information.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>4. BUYER RESPONSIBILITIES</h3>
          <p>As a buyer, you agree to:</p>
          <ul>
              <li>Only place orders for items you genuinely intend to purchase</li>
              <li>Communicate respectfully with sellers</li>
              <li>Confirm when an item has been received</li>
              <li>Submit a review after successful purchase (mandatory before further browsing)</li>
          </ul>
          <p>Failure to confirm delivery or provide accurate feedback may result in account restrictions.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>5. SELLER RESPONSIBILITIES</h3>
          <p>As a seller, you agree to:</p>
          <ul>
              <li>Upload accurate product information and images</li>
              <li>Maintain honest communication with buyers</li>
              <li>Confirm when an item has been sold</li>
              <li>Deliver items as agreed with the buyer</li>
              <li>Respect platform limits (e.g., product upload limits for basic/premium accounts)</li>
          </ul>
          <p>Misleading listings or failure to deliver may result in penalties or account suspension.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>6. ORDER PROCESS & CONFIRMATION</h3>
          <p>Orders follow a strict process:</p>
          <ol>
              <li>Buyer marks item as “Ordered”</li>
              <li>Seller confirms item as “Sold”</li>
              <li>Buyer confirms item as “Received”</li>
          </ol>
          <p>An order is only considered:</p>
          <ul>
              <li><strong>“Sold”</strong> after seller confirmation</li>
              <li><strong>“Completed”</strong> after buyer confirmation</li>
          </ul>
          <p>Both confirmations are required to finalize a transaction.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>7. PAYMENT TERMS</h3>
          <ul>
              <li>All transactions are conducted as <strong>Pay on Delivery (POD)</strong> unless both parties agree otherwise.</li>
              <li>The platform does not directly process payments.</li>
              <li>Buyers and sellers must mutually agree on payment terms.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>8. MESSAGING & COMMUNICATION</h3>
          <ul>
              <li>The platform provides a messaging system between buyers and sellers.</li>
              <li>Users must communicate respectfully and professionally.</li>
              <li>All messages may be logged and monitored for safety and dispute resolution.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>9. REVIEWS & RATINGS</h3>
          <ul>
              <li>Buyers are required to leave a review after confirming delivery.</li>
              <li>Reviews must be honest and not abusive.</li>
              <li>Fake or misleading reviews are strictly prohibited.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>10. ADMIN RIGHTS & CONTROL</h3>
          <p>The platform administrator has full authority to:</p>
          <ul>
              <li>Monitor all transactions and communications</li>
              <li>Approve or reject profile updates</li>
              <li>Approve premium account requests</li>
              <li>Adjust pricing for premium features and ads</li>
              <li>Resolve disputes between users</li>
              <li>Suspend or terminate accounts for violations</li>
          </ul>
          <p>All administrative decisions are final.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>11. PREMIUM & PAID FEATURES</h3>
          <ul>
              <li>Sellers may request premium accounts for enhanced features.</li>
              <li>Admin approval is required before activation.</li>
              <li>Fees may apply and are subject to change by the admin.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>12. VACATION MODE</h3>
          <ul>
              <li>Sellers may request to activate vacation mode.</li>
              <li>Admin approval is required.</li>
              <li>While active, seller listings may be hidden or paused.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>13. PROHIBITED ACTIVITIES</h3>
          <p>Users must NOT:</p>
          <ul>
              <li>Post false or misleading product information</li>
              <li>Attempt fraud or deceive other users</li>
              <li>Abuse the messaging system</li>
              <li>Bypass platform rules or restrictions</li>
              <li>Use the platform for illegal activities</li>
          </ul>
          <p>Violations may result in immediate suspension or permanent ban.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>14. DISPUTES</h3>
          <ul>
              <li>Any disputes between buyers and sellers may be reviewed by the admin.</li>
              <li>The platform may use message logs and transaction data to resolve disputes.</li>
              <li>Users must cooperate during investigations.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>15. LIMITATION OF LIABILITY</h3>
          <p>The platform acts only as an intermediary between buyers and sellers. We are not responsible for:</p>
          <ul>
              <li>Product quality</li>
              <li>Delivery issues</li>
              <li>Payment disputes</li>
          </ul>
          <p>Users engage in transactions at their own risk.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>16. DATA & PRIVACY</h3>
          <ul>
              <li>User data is collected to improve platform functionality.</li>
              <li>Data will not be shared without consent, except when required for legal or administrative purposes.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>17. MODIFICATIONS TO TERMS</h3>
          <ul>
              <li>These Terms may be updated at any time.</li>
              <li>Continued use of the platform constitutes acceptance of updated Terms.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>18. TERMINATION</h3>
          <p>We reserve the right to suspend or terminate accounts at any time and remove listings that violate policies.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>19. CONTACT INFORMATION</h3>
          <p>For support or inquiries, contact: 📞 0506589823</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>20. SHOP SHARING & EXTERNAL LINKS</h3>
          <ul>
              <li>Sellers are provided with a <strong>unique Global Shop Link</strong> in their dashboard.</li>
              <li>We encourage sellers to share this link on external platforms such as WhatsApp (Status and Chats), Facebook, Instagram, and other social media to showcase their products to potential buyers outside the platform.</li>
              <li>This link serves as a direct gateway for customers to view a seller's full catalog on the Campus Marketplace.</li>
              <li>Any misuse of links for spamming or unauthorized data collection is strictly prohibited.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>21. ACCOUNT TIERS & SELLER RULES</h3>
          <p>Users may choose from {tiers.length || 3} account levels, each with specific system-enforced limits and benefits:</p>
          <div style={{marginBottom:'1.5rem'}}>
            {tiers.length > 0 ? (
              <PricingSimple tiers={tiers} showCta={false} />
            ) : (
              <div style={{color:'var(--text-muted)'}}>Loading tier data...</div>
            )}
          </div>
          <p><strong>Admin Rights:</strong> Admin may adjust product limits, fees, badges, and ads boost benefits at any time. All changes continuously pull from the system architecture.</p>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>22. RICH MEDIA & VOICE MESSAGING</h3>
          <ul>
              <li>The platform allows users to send <strong>images, videos, and voice notes</strong>.</li>
              <li>Users are strictly prohibited from sending offensive, explicit, or harassing media.</li>
              <li>Voice notes and videos are subject to the same monitoring and recording standards as text messages.</li>
              <li>The platform is not responsible for data usage incurred during the playback or recording of media.</li>
              <li>Any abuse of the voice/video messaging system for harassment will result in an immediate and permanent ban.</li>
          </ul>

          <h3 style={{fontSize: '1.1rem', fontWeight: 700, marginTop: '2rem', marginBottom: '0.75rem'}}>23. ACCEPTANCE</h3>

          <p>By using this platform, you confirm that you have read, understood, and agreed to these Terms and Conditions.</p>
          <div style={{height: '50px'}}></div>
        </div>
        <div style={{padding:'1.5rem 2rem', borderTop:'1px solid var(--border)', textAlign:'center'}}>
          {scrolledToBottom ? (
            <button onClick={onClose} className="btn btn-primary" style={{width:'100%', padding:'1.1rem', fontSize:'1rem', fontWeight:700, boxShadow:'0 10px 20px rgba(0,113,227,0.2)'}}>I Agree & Continue</button>
          ) : (
            <button disabled className="btn btn-primary" style={{width:'100%', padding:'1.1rem', fontSize:'1rem', fontWeight:700, opacity:0.5, cursor:'not-allowed'}}>Please scroll to the bottom to acknowledge the terms</button>
          )}
        </div>
      </div>
    </div>
  );
}

function AIAssistant() {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<{text: string; sender: 'user' | 'ai'}[]>([
    { text: "Hi there! I'm the Campus Marketplace Assistant. I can help with buying, selling, safety tips, and navigating the site. Ask me anything!", sender: 'ai' }
  ]);
  const [input, setInput] = useState('');
  const [typing, setTyping] = useState(false);

  const sendMessage = async () => {
    if (!input.trim()) return;
    const msg = input.trim();
    
    // Format history for Gemini: alternating user and model roles.
    // Skip the first default welcome message to save tokens.
    const history = messages.slice(1).map(m => ({
      role: m.sender === 'ai' ? 'model' : 'user',
      parts: [{ text: m.text }]
    }));

    setMessages(prev => [...prev, { text: msg, sender: 'user' }]);
    setInput('');
    setTyping(true);

    try {
      const data = await ai.chat(msg, history);
      setMessages(prev => [...prev, { text: data.response, sender: 'ai' }]);
    } catch {
      setMessages(prev => [...prev, { text: "I'm having trouble connecting right now, let's chat soon!", sender: 'ai' }]);
    } finally {
      setTyping(false);
    }
  };

  return (
    <div id="ai-assistant-widget" style={{position:'fixed', bottom:'20px', right:'20px', zIndex:9999, fontFamily:"'Inter', sans-serif"}}>
      <button onClick={() => setOpen(!open)} style={{width:'56px', height:'56px', borderRadius:'50%', background:'linear-gradient(135deg, #0071e3, #34aaff)', border:'none', boxShadow:'0 6px 20px rgba(0,113,227,0.4)', color:'white', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center', transition:'all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275)', transform: open ? 'scale(0.85) rotate(90deg)' : 'scale(1)'}}>
        {open ? (
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        ) : (
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        )}
      </button>

      {open && (
        <div style={{position:'absolute', bottom:'72px', right:0, width:'360px', maxWidth:'90vw', height:'460px', borderRadius:'20px', display:'flex', flexDirection:'column', overflow:'hidden', boxShadow:'0 20px 48px rgba(0,0,0,0.2)', border:'1px solid var(--border)', background:'var(--card-bg)'}}>
          <div style={{background:'#0071e3', padding:'0.85rem 1rem', color:'white', display:'flex', justifyContent:'space-between', alignItems:'center'}}>
            <div style={{display:'flex', alignItems:'center', gap:'8px'}}>
              <div style={{width:'32px', height:'32px', background:'rgba(255,255,255,0.2)', borderRadius:'10px', display:'flex', alignItems:'center', justifyContent:'center'}}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div>
                <h4 style={{margin:0, fontSize:'0.9rem', fontWeight:600}}>Campus Assistant</h4>
                <span style={{fontSize:'0.7rem', opacity:0.8}}>AI-Powered Help</span>
              </div>
            </div>
            <button onClick={() => setOpen(false)} style={{background:'none', border:'none', color:'white', fontSize:'1.3rem', cursor:'pointer', lineHeight:1, opacity:0.8}}>&times;</button>
          </div>
          <div style={{flex:1, padding:'1rem', overflowY:'auto', display:'flex', flexDirection:'column', gap:'0.75rem', fontSize:'0.85rem'}}>
            {messages.map((m, i) => (
              <div key={i} style={{display:'flex', flexDirection:'column', maxWidth:'85%', alignItems: m.sender === 'user' ? 'flex-end' : 'flex-start', alignSelf: m.sender === 'user' ? 'flex-end' : 'flex-start'}}>
                <div style={{background: m.sender === 'user' ? '#0071e3' : 'var(--card-bg)', color: m.sender === 'user' ? 'white' : 'var(--text-main)', padding:'0.7rem 1rem', borderRadius: m.sender === 'user' ? '14px 14px 2px 14px' : '14px 14px 14px 2px', border: m.sender === 'ai' ? '1px solid var(--border)' : 'none', fontSize:'0.85rem', lineHeight:1.5}}>{m.text}</div>
              </div>
            ))}
            {typing && <div style={{color:'var(--text-muted)', fontSize:'0.85rem', padding:'0.7rem 1rem', background:'var(--card-bg)', border:'1px solid var(--border)', borderRadius:'14px 14px 14px 2px', maxWidth:'85%'}}>typing...</div>}
          </div>
          <div style={{padding:'0.75rem', borderTop:'1px solid var(--border)', display:'flex', gap:'8px', background:'var(--card-bg)'}}>
            <input type="text" value={input} onChange={e => setInput(e.target.value)} onKeyPress={e => e.key === 'Enter' && sendMessage()} placeholder="Ask a question..." style={{flex:1, padding:'0.7rem 1rem', borderRadius:'999px', border:'1px solid var(--border)', background:'var(--bg)', color:'var(--text-main)', fontSize:'0.85rem', outline:'none'}} />
            <button onClick={sendMessage} style={{background:'#0071e3', color:'white', border:'none', padding:'0 1rem', borderRadius:'999px', cursor:'pointer', fontWeight:600, fontSize:'0.84rem'}}>Send</button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function Footer() {
  const { isLoggedIn, isAdmin, isSeller } = useAuth();
  const [termsOpen, setTermsOpen] = useState(false);

  // Expose openTermsModal globally for backward compatibility
  React.useEffect(() => {
    (window as any).openTermsModal = () => setTermsOpen(true);
  }, []);

  const accountHomeUrl = isAdmin ? buildLegacyUrl('/admin/') : buildLegacyUrl('/dashboard.php');
  const accountHomeLabel = isAdmin ? 'Admin Panel' : 'Dashboard';
  const showSellLink = !isLoggedIn || (isSeller && !isAdmin);

  return (
    <>

      <footer style={{background:'var(--card-bg)', padding:'1.5rem 1rem 0.5rem', color:'var(--text-main)', borderTop:'1px solid var(--border)', marginTop:'2rem', borderRadius:'24px 24px 0 0'}}>
        <div className="container footer-grid" style={{marginBottom:'1rem'}}>
          <div>
            <h3 style={{fontSize:'1rem', fontWeight:800, marginBottom:'0.4rem', display:'flex', alignItems:'center', gap:'4px'}}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              Campus Marketplace
            </h3>
            <p style={{color:'var(--text-muted)', fontSize:'0.7rem', lineHeight:1.4}}>Everything you need on campus.<br/>Connect. Buy. Sell easily.</p>
          </div>
          <div>
            <h4 style={{fontWeight:700, marginBottom:'0.5rem', fontSize:'0.75rem', textTransform:'uppercase', color:'var(--text-muted)'}}>Navigation</h4>
            <ul style={{listStyle:'none', padding:0, display:'flex', flexDirection:'column', gap:'0.25rem'}}>
              <li><Link to="/" style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>Home</Link></li>
              <li><a href={buildLegacyUrl('/about.php')} style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>About</a></li>
              <li><a href={accountHomeUrl} style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>{accountHomeLabel}</a></li>
              {showSellLink && (
                <li><a href={buildLegacyUrl('/add_product.php')} style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>Sell</a></li>
              )}
            </ul>
          </div>
          <div>
            <h4 style={{fontWeight:700, marginBottom:'0.5rem', fontSize:'0.75rem', textTransform:'uppercase', color:'var(--text-muted)'}}>Contact</h4>
            <ul style={{listStyle:'none', padding:0, display:'flex', flexDirection:'column', gap:'0.25rem'}}>
              <li style={{display:'flex', alignItems:'center', gap:'0.4rem', fontSize:'0.75rem'}}>TTU Campus</li>
              <li style={{display:'flex', alignItems:'center', gap:'0.4rem', fontSize:'0.75rem'}}>0506589823</li>
            </ul>
          </div>
          <div>
            <h4 style={{fontWeight:700, marginBottom:'0.5rem', fontSize:'0.75rem', textTransform:'uppercase', color:'var(--text-muted)'}}>Support</h4>
            <ul style={{listStyle:'none', padding:0, display:'flex', flexDirection:'column', gap:'0.25rem'}}>
              <li><a href={buildLegacyUrl('/about.php#how-it-works')} style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>How it works</a></li>
              <li><a href={buildLegacyUrl('/about.php#safe-trading')} style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>Safety</a></li>
              <li><a href="#" onClick={(e) => { e.preventDefault(); setTermsOpen(true); }} style={{color:'var(--text-main)', textDecoration:'none', fontSize:'0.75rem'}}>Terms & Conditions</a></li>
            </ul>
          </div>
        </div>
        <div style={{textAlign:'center', paddingTop:'1rem', borderTop:'1px solid var(--border)', color:'var(--text-muted)', fontSize:'0.7rem'}}>
          &copy; {new Date().getFullYear()} Campus Marketplace
        </div>
      </footer>

      <TermsModal open={termsOpen} onClose={() => setTermsOpen(false)} />
      <AIAssistant />
    </>
  );
}

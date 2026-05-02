"use client"

import { useState } from "react";
import { Eye, EyeOff, Mail, Lock, User, LucideIcon } from "lucide-react";
import { useAuth } from "../../contexts/AuthContext";
import { redirectToLegacyDashboard } from "../../utils/legacyAuth";

// ── Google Icon ──
const GoogleIcon = ({ className }: { className?: string }) => (
  <svg className={className} viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
  </svg>
);

// ── Sub-components ──

const InputField = ({
  id, type, label, placeholder, value, onChange, icon: Icon, required = false,
}: {
  id: string; type: string; label: string; placeholder: string;
  value: string; onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  icon: LucideIcon; required?: boolean;
}) => (
  <div className="space-y-1.5">
    <label htmlFor={id} className="text-sm font-medium text-foreground">{label}</label>
    <div className="relative">
      <Icon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
      <input
        id={id} type={type} placeholder={placeholder} value={value} onChange={onChange} required={required}
        className="w-full h-11 pl-10 pr-3 rounded-md border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background transition-all duration-300 text-sm"
      />
    </div>
  </div>
);

const PasswordField = ({
  id, label, placeholder, value, onChange, showPassword, onTogglePassword, required = false,
}: {
  id: string; label: string; placeholder: string; value: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  showPassword: boolean; onTogglePassword: () => void; required?: boolean;
}) => (
  <div className="space-y-1.5">
    <label htmlFor={id} className="text-sm font-medium text-foreground">{label}</label>
    <div className="relative">
      <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
      <input
        id={id} type={showPassword ? "text" : "password"} placeholder={placeholder}
        value={value} onChange={onChange} required={required}
        className="w-full h-11 pl-10 pr-10 rounded-md border border-input bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background transition-all duration-300 text-sm"
      />
      <button type="button" onClick={onTogglePassword}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
        aria-label={showPassword ? "Hide password" : "Show password"}>
        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
      </button>
    </div>
  </div>
);

const Divider = ({ text }: { text: string }) => (
  <div className="relative">
    <div className="absolute inset-0 flex items-center">
      <div className="w-full border-t border-border" />
    </div>
    <div className="relative flex justify-center text-xs uppercase">
      <span className="bg-card px-2 text-muted-foreground">{text}</span>
    </div>
  </div>
);

const ErrorAlert = ({ message }: { message: string }) => (
  <div className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2.5 text-sm text-destructive">
    <svg className="h-4 w-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span>{message}</span>
  </div>
);

// ── Animated gradient hero panel ──
const HeroPanel = ({ title, description, icon }: { title: string; description: string; icon: React.ReactNode }) => (
  <div className="hidden lg:flex flex-1 relative overflow-hidden">
    <div className="absolute inset-0 bg-slate-900" />
    {/* Animated blobs */}
    <div className="absolute top-0 -left-4 w-72 h-72 bg-purple-500/10 rounded-full mix-blend-screen filter opacity-70 animate-blob" />
    <div className="absolute top-0 -right-4 w-72 h-72 bg-cyan-500/10 rounded-full mix-blend-screen filter opacity-70 animate-blob animation-delay-2000" />
    <div className="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-500/10 rounded-full mix-blend-screen filter opacity-70 animate-blob animation-delay-4000" />
    {/* Wave */}
    <div className="absolute inset-0 opacity-10">
      <svg className="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 1440 560">
        <defs>
          <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#a855f7" stopOpacity="0.3"/>
            <stop offset="100%" stopColor="#06b6d4" stopOpacity="0.1"/>
          </linearGradient>
        </defs>
        <path fill="url(#grad1)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,218.7C672,235,768,245,864,234.7C960,224,1056,192,1152,186.7C1248,181,1344,203,1392,213.3L1440,224L1440,560L1392,560C1344,560,1248,560,1152,560C1056,560,960,560,864,560C768,560,672,560,576,560C480,560,384,560,288,560C192,560,96,560,48,560L0,560Z"/>
      </svg>
    </div>
    <div className="relative z-10 flex items-center justify-center p-8 lg:p-12 w-full">
      <div className="text-center space-y-6 max-w-md">
        <div className="inline-flex rounded-full p-3 bg-white/10 text-white mb-4">
          {icon}
        </div>
        <h2 className="text-3xl lg:text-4xl font-bold text-white">{title}</h2>
        <p className="text-lg text-white/80">{description}</p>
        <div className="flex justify-center gap-2 pt-4">
          {[0,1,2].map(i => (
            <div key={i} className={`w-2 h-2 rounded-full ${i === 2 ? 'bg-white' : i === 1 ? 'bg-white/60' : 'bg-white/30'}`} />
          ))}
        </div>
      </div>
    </div>
  </div>
);

// ============================================================================
// SIGN IN PAGE
// ============================================================================

export const SignInPage = () => {
  const { login } = useAuth();
  const [showPassword, setShowPassword] = useState(false);
  const [identifier, setIdentifier] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    if (!identifier || !password) { setError("Please fill in all fields."); return; }
    setLoading(true);
    const result = await login(identifier, password) as any;
    if (result.success) {
      await redirectToLegacyDashboard(result.user);
    } else {
      setError(result.error || "Invalid email/username or password.");
      setLoading(false);
    }
  };

  const handleGoogleSignIn = () => {
    const base = window.location.pathname.startsWith('/marketplace/') ? '/marketplace' : '';
    window.location.href = `${window.location.origin}${base}/google_signin.php`;
  };

  return (
    <div className="min-h-screen flex flex-col lg:flex-row w-full">
      {/* Form side */}
      <div className="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-background">
        <div className="w-full max-w-md space-y-8">
          {/* Header */}
          <div className="text-center space-y-2">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/10 border border-primary/20 mb-2">
              <svg className="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
              </svg>
            </div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">Welcome back</h1>
            <p className="text-muted-foreground">Access your safe campus marketplace</p>
          </div>

          {/* Card */}
          <div className="bg-card border border-border rounded-2xl p-6 sm:p-8 shadow-sm space-y-5">
            {error && <ErrorAlert message={error} />}

            <form onSubmit={handleSubmit} className="space-y-5">
              <InputField
                id="login_id" type="text" label="Email or Username"
                placeholder="Enter your identifier"
                value={identifier} onChange={e => setIdentifier(e.target.value)}
                icon={Mail} required
              />
              <PasswordField
                id="password" label="Password" placeholder="••••••••"
                value={password} onChange={e => setPassword(e.target.value)}
                showPassword={showPassword} onTogglePassword={() => setShowPassword(v => !v)} required
              />

              <div className="flex items-center justify-end text-sm">
                <a href="forgot_password.php" className="text-primary hover:opacity-80 font-medium transition-opacity">
                  Forgot password?
                </a>
              </div>

              <button type="submit" disabled={loading}
                className="w-full h-11 rounded-md bg-primary text-primary-foreground font-semibold shadow-lg hover:opacity-90 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                {loading ? (
                  <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                ) : null}
                {loading ? "Signing in…" : "Sign in"}
              </button>

              <Divider text="Or continue with" />

              <button type="button" onClick={handleGoogleSignIn}
                className="w-full h-11 rounded-md border border-border bg-background hover:bg-secondary text-foreground font-medium transition-all duration-300 flex items-center justify-center gap-2 text-sm">
                <GoogleIcon />
                Continue with Google
              </button>
            </form>

            <p className="text-center text-sm text-muted-foreground pt-2 border-t border-border">
              Don't have an account?{" "}
              <a href="register.php" className="text-primary hover:opacity-80 font-semibold transition-opacity">Join now</a>
            </p>
          </div>
        </div>
      </div>

      {/* Hero side */}
      <HeroPanel
        title="Your Campus Marketplace"
        description="Buy, sell, and connect safely with fellow students. Your data is protected and your transactions are secure."
        icon={
          <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="1.5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        }
      />
    </div>
  );
};

// ============================================================================
// SIGN UP PAGE
// ============================================================================

const FACULTIES = [
  "Agriculture","Arts","Built Environment","Education","Engineering","Health Sciences",
  "Home Science","Integrated Development Studies","Law","Pharmaceutical Sciences",
  "Renewable Natural Resources","Science","Social Sciences","Other"
];

const LEVELS = ["100","200","300","400","Postgraduate"];

const HALLS = [
  "Unity Hall","Independence Hall","Osei Tutu Hall","Queen Elizabeth II Hall",
  "Bompeh Hall","Brunei Hall","Flair Hall","Africa Hall","International Students Hostel","Other"
];

export const SignUpPage = () => {
  const { register } = useAuth();
  const [mode, setMode] = useState<'buyer' | 'seller'>('buyer');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const [form, setForm] = useState({
    username: "", email: "", password: "",
    faculty: "", department: "", level: "", hall_residence: "", phone: "",
    referral_code: "", terms: false,
  });

  const set = (field: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [field]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");

    // Client-side validation matching register.php
    if (!form.terms) { setError("You must accept the Terms & Conditions."); return; }
    if (!form.username || !form.email || !form.password) { setError("Please fill in all required fields."); return; }
    const pwRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]).{12,}$/;
    if (!pwRegex.test(form.password)) {
      setError("Password must be at least 12 characters and include uppercase, lowercase, a number, and a special character.");
      return;
    }
    if (!form.faculty) { setError("Please select your faculty."); return; }
    if (mode === 'seller' && (!form.department || !form.level || !form.phone)) {
      setError("Sellers must fill in department, level, and phone."); return;
    }

    setLoading(true);
    const fd = new FormData();
    fd.append('mode', mode);
    fd.append('username', form.username);
    fd.append('email', form.email);
    fd.append('password', form.password);
    fd.append('faculty', form.faculty);
    fd.append('department', form.department);
    fd.append('level', form.level);
    fd.append('hall_residence', form.hall_residence);
    fd.append('phone', form.phone);
    fd.append('referral_code', form.referral_code);
    fd.append('terms', '1');

    const result = await register(fd) as any;
    if (result.success) {
      await redirectToLegacyDashboard(result.user);
    } else {
      setError(result.error || "Registration failed. Please try again.");
      setLoading(false);
    }
  };

  const handleGoogleSignIn = () => {
    const base = window.location.pathname.startsWith('/marketplace/') ? '/marketplace' : '';
    window.location.href = `${window.location.origin}${base}/google_signin.php`;
  };

  return (
    <div className="min-h-screen flex flex-col lg:flex-row w-full">
      {/* Hero side (left on register) */}
      <HeroPanel
        title="Join Campus Marketplace"
        description="Connect with thousands of students. Buy, sell, and grow your campus business with ease."
        icon={
          <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="1.5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <line x1="19" y1="8" x2="19" y2="14"/>
            <line x1="22" y1="11" x2="16" y2="11"/>
          </svg>
        }
      />

      {/* Form side */}
      <div className="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-background">
        <div className="w-full max-w-lg space-y-6">
          {/* Header */}
          <div className="text-center space-y-2">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary/10 border border-primary/20 mb-2">
              <svg className="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <line x1="19" y1="8" x2="19" y2="14"/>
                <line x1="22" y1="11" x2="16" y2="11"/>
              </svg>
            </div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">Create account</h1>
            <p className="text-muted-foreground">Join your campus marketplace today</p>
          </div>

          {/* Role toggle */}
          <div className="flex rounded-xl border border-border overflow-hidden bg-secondary/40">
            {(['buyer', 'seller'] as const).map(r => (
              <button key={r} type="button" onClick={() => setMode(r)}
                className={`flex-1 py-2.5 text-sm font-semibold transition-all duration-200 ${mode === r ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>
                {r === 'buyer' ? '🛒 Buyer' : '🏪 Seller'}
              </button>
            ))}
          </div>

          {/* Card */}
          <div className="bg-card border border-border rounded-2xl p-6 sm:p-8 shadow-sm space-y-5">
            {error && <ErrorAlert message={error} />}

            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Hidden honeypot */}
              <input type="text" name="website" style={{display:'none'}} tabIndex={-1} autoComplete="off" />

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <InputField id="username" type="text" label="Username" placeholder="e.g. john_doe"
                  value={form.username} onChange={set('username')} icon={User} required />
                <InputField id="email" type="email" label="Email" placeholder="name@example.com"
                  value={form.email} onChange={set('email')} icon={Mail} required />
              </div>

              <PasswordField id="password" label="Password" placeholder="Min. 12 chars, upper, lower, number, symbol"
                value={form.password} onChange={set('password')}
                showPassword={showPassword} onTogglePassword={() => setShowPassword(v => !v)} required />

              {/* Faculty */}
              <div className="space-y-1.5">
                <label htmlFor="faculty" className="text-sm font-medium text-foreground">Faculty <span className="text-destructive">*</span></label>
                <select id="faculty" value={form.faculty} onChange={set('faculty')} required
                  className="w-full h-11 px-3 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background transition-all">
                  <option value="">Select your faculty</option>
                  {FACULTIES.map(f => <option key={f} value={f}>{f}</option>)}
                </select>
              </div>

              {/* Seller-only fields */}
              {mode === 'seller' && (
                <div className="space-y-4 rounded-xl border border-border/60 bg-secondary/20 p-4">
                  <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Seller Details</p>

                  <InputField id="department" type="text" label="Department" placeholder="e.g. Computer Science"
                    value={form.department} onChange={set('department')} icon={User} required />

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <label htmlFor="level" className="text-sm font-medium text-foreground">Level <span className="text-destructive">*</span></label>
                      <select id="level" value={form.level} onChange={set('level')} required
                        className="w-full h-11 px-3 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 transition-all">
                        <option value="">Select level</option>
                        {LEVELS.map(l => <option key={l} value={l}>{l}</option>)}
                      </select>
                    </div>
                    <InputField id="phone" type="tel" label="Phone" placeholder="0XX XXX XXXX"
                      value={form.phone} onChange={set('phone')} icon={User} required />
                  </div>

                  <div className="space-y-1.5">
                    <label htmlFor="hall_residence" className="text-sm font-medium text-foreground">Hall of Residence</label>
                    <select id="hall_residence" value={form.hall_residence} onChange={set('hall_residence')}
                      className="w-full h-11 px-3 rounded-md border border-input bg-background text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 transition-all">
                      <option value="">Select hall (optional)</option>
                      {HALLS.map(h => <option key={h} value={h}>{h}</option>)}
                    </select>
                  </div>
                </div>
              )}

              {/* Referral */}
              <InputField id="referral_code" type="text" label="Referral Code (optional)" placeholder="Enter code if you have one"
                value={form.referral_code} onChange={set('referral_code')} icon={User} />

              {/* Terms */}
              <label htmlFor="terms" className="flex items-start gap-2.5 cursor-pointer group">
                <input id="terms" type="checkbox" checked={form.terms}
                  onChange={e => setForm(f => ({ ...f, terms: e.target.checked }))}
                  className="mt-0.5 w-4 h-4 rounded border-input text-primary focus:ring-primary focus:ring-offset-background shrink-0" />
                <span className="text-sm text-muted-foreground">
                  I agree to the{" "}
                  <a href="/" className="text-primary hover:opacity-80 font-medium transition-opacity">Terms &amp; Conditions</a>
                  {" "}of Campus Marketplace
                </span>
              </label>

              <button type="submit" disabled={loading}
                className="w-full h-11 rounded-md bg-primary text-primary-foreground font-semibold shadow-lg hover:opacity-90 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                {loading ? (
                  <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                ) : null}
                {loading ? "Creating account…" : `Create ${mode} account`}
              </button>

              <Divider text="Or continue with" />

              <button type="button" onClick={handleGoogleSignIn}
                className="w-full h-11 rounded-md border border-border bg-background hover:bg-secondary text-foreground font-medium transition-all duration-300 flex items-center justify-center gap-2 text-sm">
                <GoogleIcon />
                Continue with Google
              </button>
            </form>

            <p className="text-center text-sm text-muted-foreground pt-2 border-t border-border">
              Already have an account?{" "}
              <a href="login.php" className="text-primary hover:opacity-80 font-semibold transition-opacity">Sign in</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SignInPage;

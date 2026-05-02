import React from 'react';
import { 
  User, 
  Store, 
  ShieldCheck, 
  Bell, 
  CreditCard, 
  Lock,
  ChevronRight,
  BadgeCheck,
  Zap,
  Moon,
  LogOut
} from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { assetUrl } from '../../utils/assetUrl';

export default function Settings() {
  const { user } = useAuth();

  const sections = [
    {
      title: 'Account',
      items: [
        { id: 'profile', label: 'Profile Information', icon: User, desc: 'Manage your name, bio and profile picture' },
        { id: 'store', label: 'Store Information', icon: Store, desc: 'Update your shop name and description' },
        { id: 'security', label: 'Security & Password', icon: Lock, desc: 'Keep your account secure with 2FA' },
      ]
    },
    {
      title: 'Store',
      items: [
        { id: 'tier', label: 'Subscription Plan', icon: Zap, desc: 'Currently on Basic Plan', badge: 'Basic', badgeColor: 'bg-purple-50 text-purple-600' },
        { id: 'vacation', label: 'Vacation Mode', icon: Moon, desc: 'Temporarily hide your listings', toggle: true },
        { id: 'verify', label: 'Verification Status', icon: BadgeCheck, desc: 'Your account is verified', badge: 'Verified', badgeColor: 'bg-green-50 text-green-600' },
      ]
    },
    {
      title: 'Support',
      items: [
        { id: 'help', label: 'Help Center', icon: ShieldCheck, desc: 'Guides and documentation' },
        { id: 'contact', label: 'Contact Admin', icon: Bell, desc: 'Get in touch with support' },
      ]
    }
  ];

  return (
    <div className="max-w-3xl mx-auto space-y-10 animate-in fade-in slide-in-from-bottom-4 duration-500 pb-20">
      {/* Profile Header */}
      <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm flex flex-col md:flex-row items-center gap-6">
        <div className="w-24 h-24 rounded-full bg-gray-100 border-4 border-white shadow-xl overflow-hidden shrink-0">
          {user?.profile_pic ? (
            <img src={assetUrl(user.profile_pic)} alt={user.username} className="w-full h-full object-cover" />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-400 font-bold text-2xl uppercase">
              {user?.username?.charAt(0)}
            </div>
          )}
        </div>
        <div className="flex-1 text-center md:text-left min-w-0">
          <div className="flex items-center justify-center md:justify-start gap-2 mb-1">
            <h3 className="text-2xl font-black text-gray-900 tracking-tight">{user?.username}</h3>
            <BadgeCheck size={20} className="text-[#30d158]" />
          </div>
          <p className="text-sm text-gray-500 font-medium mb-4">Member since {new Date().getFullYear()}</p>
          <div className="flex flex-wrap justify-center md:justify-start gap-2">
            <button className="bg-gray-900 text-white px-5 py-2 rounded-xl text-xs font-bold hover:bg-black transition-all">
              Change Photo
            </button>
            <button className="bg-white border border-gray-200 text-gray-700 px-5 py-2 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all">
              Remove
            </button>
          </div>
        </div>
      </div>

      {/* Settings Sections */}
      <div className="space-y-12">
        {sections.map((section, idx) => (
          <div key={idx}>
            <h4 className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-6 px-4">{section.title}</h4>
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm divide-y divide-gray-50 overflow-hidden">
              {section.items.map((item) => (
                <button 
                  key={item.id}
                  className="w-full p-5 flex items-center gap-4 text-left hover:bg-gray-50/50 transition-all group"
                >
                  <div className="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400 group-hover:text-[#7c3aed] group-hover:bg-purple-50 transition-all">
                    <item.icon size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-bold text-gray-900 mb-0.5">{item.label}</p>
                    <p className="text-xs text-gray-500 font-medium truncate">{item.desc}</p>
                  </div>
                  {item.badge && (
                    <span className={`px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${item.badgeColor}`}>
                      {item.badge}
                    </span>
                  )}
                  {item.toggle && (
                    <div className="w-10 h-5 bg-gray-200 rounded-full relative p-1 cursor-pointer">
                      <div className="w-3 h-3 bg-white rounded-full shadow-sm"></div>
                    </div>
                  )}
                  {!item.toggle && <ChevronRight size={18} className="text-gray-300 group-hover:translate-x-1 transition-transform" />}
                </button>
              ))}
            </div>
          </div>
        ))}
      </div>

      {/* Danger Zone */}
      <div className="pt-8 border-t border-gray-100">
        <button className="w-full p-5 rounded-3xl bg-red-50 text-red-600 flex items-center justify-center gap-2 font-bold text-sm hover:bg-red-100 transition-all">
          <LogOut size={18} />
          Sign Out of Account
        </button>
      </div>
    </div>
  );
}

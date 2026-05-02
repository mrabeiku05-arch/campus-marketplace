import React from 'react';
import { 
  LayoutDashboard, 
  Package, 
  ShoppingCart, 
  BarChart3, 
  Wallet, 
  MessageSquare, 
  Settings, 
  LogOut,
  BadgeCheck
} from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { assetUrl } from '../../utils/assetUrl';

interface SidebarProps {
  activeTab: string;
  onTabChange: (tab: string) => void;
}

export default function Sidebar({ activeTab, onTabChange }: SidebarProps) {
  const { user, logout } = useAuth();

  const menuItems = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard },
    { id: 'inventory', label: 'Inventory', icon: Package },
    { id: 'orders', label: 'Orders', icon: ShoppingCart },
    { id: 'analytics', label: 'Analytics', icon: BarChart3 },
    { id: 'wallet', label: 'Wallet', icon: Wallet },
    { id: 'messages', label: 'Messages', icon: MessageSquare },
    { id: 'settings', label: 'Settings', icon: Settings },
  ];

  return (
    <aside className="w-64 bg-[#111111] border-r border-gray-800 flex flex-col z-50">
      {/* Brand / Logo */}
      <div className="p-6">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 bg-[#7c3aed] rounded-lg flex items-center justify-center">
            <div className="w-4 h-4 border-2 border-white rounded-sm transform rotate-45"></div>
          </div>
          <span className="text-white font-bold text-lg tracking-tight">CampusMarketplace</span>
        </div>
        <p className="text-gray-500 text-xs mt-1 font-medium px-1">Seller Workspace</p>
      </div>

      {/* Seller Profile Summary */}
      <div className="px-4 mb-6">
        <div className="bg-[#1a1a1a] rounded-xl p-4 border border-gray-800">
          <div className="flex items-center gap-3 mb-3">
            <div className="w-10 h-10 rounded-full bg-gray-700 overflow-hidden border border-gray-600">
              {user?.profile_pic ? (
                <img src={assetUrl(user.profile_pic)} alt={user.username} className="w-full h-full object-cover" />
              ) : (
                <div className="w-full h-full flex items-center justify-center text-white font-bold uppercase">
                  {user?.username?.charAt(0)}
                </div>
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-white text-sm font-bold truncate">{user?.username}</p>
              <div className="flex items-center gap-1">
                <BadgeCheck size={12} className="text-[#30d158]" />
                <span className="text-[10px] text-[#30d158] font-black uppercase tracking-wider">Verified</span>
              </div>
            </div>
          </div>
          <div className="flex items-center justify-between pt-2 border-t border-gray-800">
            <span className="text-[10px] text-gray-500 font-bold uppercase tracking-widest">{user?.seller_tier || 'Basic'} Plan</span>
            <div className="w-1.5 h-1.5 rounded-full bg-[#30d158]"></div>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 space-y-1 overflow-y-auto custom-scrollbar">
        {menuItems.map((item) => (
          <button
            key={item.id}
            onClick={() => onTabChange(item.id)}
            className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 ${
              activeTab === item.id
                ? 'bg-[#7c3aed] text-white shadow-lg shadow-[#7c3aed]/20'
                : 'text-gray-400 hover:text-white hover:bg-gray-800/50'
            }`}
          >
            <item.icon size={18} strokeWidth={activeTab === item.id ? 2.5 : 2} />
            {item.label}
            {item.id === 'messages' && (
              <span className="ml-auto w-5 h-5 bg-[#7c3aed] text-white text-[10px] rounded-full flex items-center justify-center">2</span>
            )}
          </button>
        ))}
      </nav>

      {/* Footer / Wallet Summary */}
      <div className="p-4 mt-auto">
        <div className="bg-[#1a1a1a] rounded-xl p-4 border border-gray-800 mb-4">
          <p className="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Wallet Balance</p>
          <p className="text-[#30d158] text-xl font-black">GHS {user?.balance || '0.00'}</p>
          <button 
            onClick={() => onTabChange('wallet')}
            className="w-full mt-3 py-2 bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold rounded-lg transition-colors"
          >
            Withdraw
          </button>
        </div>

        <button 
          onClick={logout}
          className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-400 hover:text-red-400 hover:bg-red-400/5 transition-all"
        >
          <LogOut size={18} />
          Logout
        </button>
      </div>
    </aside>
  );
}

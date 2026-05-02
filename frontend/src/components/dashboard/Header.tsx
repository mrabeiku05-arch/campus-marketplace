import React from 'react';
import { Bell, Search, Plus, User } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { assetUrl } from '../../utils/assetUrl';

interface HeaderProps {
  activeTab: string;
}

export default function DashboardHeader({ activeTab }: HeaderProps) {
  const { user } = useAuth();

  const getTitle = () => {
    return activeTab.charAt(0).toUpperCase() + activeTab.slice(1);
  };

  return (
    <header className="h-16 bg-white border-b border-gray-100 px-8 flex items-center justify-between z-40 shadow-sm">
      <div className="flex items-center gap-4">
        <h1 className="text-xl font-black text-gray-900 tracking-tight">{getTitle()}</h1>
        <div className="h-4 w-px bg-gray-200 mx-2"></div>
        <p className="text-sm text-gray-500 font-medium hidden sm:block">
          Welcome back, <span className="text-gray-900 font-bold">{user?.username}</span>! Here's what's happening today.
        </p>
      </div>

      <div className="flex items-center gap-4">
        {/* Search */}
        <div className="hidden lg:flex items-center bg-gray-50 border border-gray-200 rounded-full px-4 py-1.5 focus-within:border-[#7c3aed] focus-within:ring-2 focus-within:ring-[#7c3aed]/10 transition-all">
          <Search size={16} className="text-gray-400" />
          <input 
            type="text" 
            placeholder="Search dashboard..." 
            className="bg-transparent border-none outline-none text-sm px-2 w-48 text-gray-700 placeholder:text-gray-400"
          />
        </div>

        {/* Action Button */}
        <button className="bg-[#7c3aed] text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-[#6d28d9] transition-all shadow-md shadow-[#7c3aed]/20 active:scale-95">
          <Plus size={18} />
          <span className="hidden sm:inline">Add Product</span>
        </button>

        {/* Notifications */}
        <button className="p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-colors relative">
          <Bell size={20} />
          <span className="absolute top-1 right-1 w-4 h-4 bg-[#7c3aed] border-2 border-white rounded-full flex items-center justify-center text-[8px] text-white font-black">1</span>
        </button>

        {/* Profile */}
        <div className="w-8 h-8 rounded-full bg-gray-100 overflow-hidden border border-gray-200 ml-2">
          {user?.profile_pic ? (
            <img src={assetUrl(user.profile_pic)} alt={user.username} className="w-full h-full object-cover" />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-500 text-xs font-bold uppercase">
              {user?.username?.charAt(0)}
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

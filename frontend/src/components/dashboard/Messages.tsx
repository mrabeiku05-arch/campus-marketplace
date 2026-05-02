import { useEffect, useState } from 'react';
import { 
  Search, 
  MessageSquare, 
  Send, 
  Phone, 
  Video,
  Info,
  ShieldCheck,
  Plus
} from 'lucide-react';
import { messages as messagesApi } from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';
import { assetUrl } from '../../utils/assetUrl';

export default function Messages() {
  useAuth();
  const [conversations, setConversations] = useState<any[]>([]);
  const [activeThread, setActiveThread] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    messagesApi.conversations()
      .then(res => {
        setConversations(res.conversations || []);
        if (res.conversations?.length > 0) setActiveThread(res.conversations[0]);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="h-[calc(100vh-12rem)] bg-white rounded-3xl border border-gray-100 shadow-sm flex overflow-hidden animate-in fade-in duration-500">
      {/* Sidebar: Conversations List */}
      <div className="w-80 border-r border-gray-50 flex flex-col">
        <div className="p-6 border-b border-gray-50">
          <h3 className="text-lg font-black text-gray-900 mb-4">Messages</h3>
          <div className="relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input 
              type="text" 
              placeholder="Search chats..." 
              className="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm outline-none focus:border-[#7c3aed]"
            />
          </div>
        </div>

        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="p-8 text-center">
              <div className="w-6 h-6 border-3 border-[#7c3aed] border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
              <p className="text-xs text-gray-400 font-medium">Loading chats...</p>
            </div>
          ) : conversations.length > 0 ? (
            <div className="divide-y divide-gray-50">
              {conversations.map((conv) => (
                <button 
                  key={conv.user_id}
                  onClick={() => setActiveThread(conv)}
                  className={`w-full p-4 flex gap-3 text-left transition-all ${
                    activeThread?.user_id === conv.user_id ? 'bg-purple-50/50' : 'hover:bg-gray-50'
                  }`}
                >
                  <div className="w-12 h-12 rounded-full bg-gray-100 border border-gray-200 overflow-hidden shrink-0 relative">
                    {conv.profile_pic ? (
                      <img src={assetUrl(conv.profile_pic)} alt={conv.username} className="w-full h-full object-cover" />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center text-gray-400 font-bold uppercase">
                        {conv.username?.charAt(0)}
                      </div>
                    )}
                    {conv.online && <div className="absolute bottom-0 right-0 w-3 h-3 bg-[#30d158] border-2 border-white rounded-full"></div>}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex justify-between items-baseline mb-1">
                      <p className="text-sm font-bold text-gray-900 truncate">{conv.username}</p>
                      <span className="text-[10px] text-gray-400 font-bold">{conv.time || '12:45 PM'}</span>
                    </div>
                    <p className="text-xs text-gray-500 font-medium truncate">{conv.last_message}</p>
                  </div>
                  {conv.unread > 0 && (
                    <div className="w-4 h-4 bg-[#7c3aed] text-white text-[8px] font-black rounded-full flex items-center justify-center">
                      {conv.unread}
                    </div>
                  )}
                </button>
              ))}
            </div>
          ) : (
            <div className="p-12 text-center">
              <MessageSquare size={32} className="mx-auto text-gray-200 mb-2" />
              <p className="text-xs text-gray-400 font-medium">No active conversations</p>
            </div>
          )}
        </div>
      </div>

      {/* Main: Chat View */}
      {activeThread ? (
        <div className="flex-1 flex flex-col bg-gray-50/30">
          {/* Chat Header */}
          <div className="h-16 bg-white border-b border-gray-50 px-6 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-full bg-gray-100 border border-gray-200 overflow-hidden">
                {activeThread.profile_pic ? (
                  <img src={assetUrl(activeThread.profile_pic)} alt={activeThread.username} className="w-full h-full object-cover" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-gray-400 font-bold">
                    {activeThread.username?.charAt(0)}
                  </div>
                )}
              </div>
              <div>
                <p className="text-sm font-black text-gray-900">{activeThread.username}</p>
                <p className="text-[10px] text-[#30d158] font-black uppercase tracking-widest">Online</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <button className="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all"><Phone size={18} /></button>
              <button className="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all"><Video size={18} /></button>
              <div className="w-px h-4 bg-gray-200 mx-2"></div>
              <button className="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-all"><Info size={18} /></button>
            </div>
          </div>

          {/* Messages Area */}
          <div className="flex-1 overflow-y-auto p-6 space-y-4">
            <div className="flex justify-center mb-8">
              <div className="bg-blue-50 text-blue-600 px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 border border-blue-100">
                <ShieldCheck size={14} />
                End-to-end encrypted conversation
              </div>
            </div>

            {/* Mock messages */}
            <div className="flex gap-3 max-w-[70%]">
              <div className="w-8 h-8 rounded-full bg-gray-100 overflow-hidden shrink-0 mt-auto">
                {activeThread.profile_pic ? (
                  <img src={assetUrl(activeThread.profile_pic)} alt={activeThread.username} className="w-full h-full object-cover" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-gray-400 font-bold text-[10px]">
                    {activeThread.username?.charAt(0)}
                  </div>
                )}
              </div>
              <div className="bg-white p-4 rounded-2xl rounded-bl-none border border-gray-100 shadow-sm">
                <p className="text-sm text-gray-800 font-medium">Hello! Is the EAGEAT Laptop Stand still available for negotiation?</p>
                <p className="text-[9px] text-gray-400 font-bold mt-2 text-right">10:45 AM</p>
              </div>
            </div>

            <div className="flex gap-3 max-w-[70%] ml-auto justify-end">
              <div className="bg-[#7c3aed] p-4 rounded-2xl rounded-br-none shadow-lg shadow-[#7c3aed]/10">
                <p className="text-sm text-white font-medium">Yes, it is. The current price is fixed but I can offer a small student discount if you're on campus.</p>
                <p className="text-[9px] text-white/60 font-bold mt-2 text-right">10:48 AM</p>
              </div>
            </div>
          </div>

          {/* Input Area */}
          <div className="p-6 bg-white border-t border-gray-50">
            <div className="flex items-center gap-4 bg-gray-50 border border-gray-200 rounded-2xl px-4 py-2 focus-within:border-[#7c3aed] focus-within:ring-2 focus-within:ring-[#7c3aed]/10 transition-all">
              <button className="p-2 text-gray-400 hover:text-gray-900 transition-colors"><Plus size={20} /></button>
              <input 
                type="text" 
                placeholder="Write a message..." 
                className="flex-1 bg-transparent border-none outline-none text-sm py-2 text-gray-700 placeholder:text-gray-400"
              />
              <button className="bg-[#7c3aed] text-white p-2.5 rounded-xl hover:bg-[#6d28d9] transition-all active:scale-90">
                <Send size={18} />
              </button>
            </div>
          </div>
        </div>
      ) : (
        <div className="flex-1 flex flex-col items-center justify-center text-center p-20">
          <div className="w-20 h-20 bg-gray-50 text-gray-200 rounded-full flex items-center justify-center mb-6">
            <MessageSquare size={40} />
          </div>
          <h3 className="text-xl font-black text-gray-900 mb-2">Select a conversation</h3>
          <p className="text-sm text-gray-500 font-medium max-w-xs">Choose a chat from the sidebar to start messaging your customers or admins.</p>
        </div>
      )}
    </div>
  );
}

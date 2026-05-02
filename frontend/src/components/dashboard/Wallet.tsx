import React, { useState, useEffect } from 'react';
import { 
  Wallet as WalletIcon, 
  ArrowUpRight, 
  ArrowDownRight, 
  History,
  Download,
  CreditCard,
  TrendingUp,
  Clock
} from 'lucide-react';
import { payments as paymentsApi } from '../../services/api';

export default function Wallet() {
  const [transactions, setTransactions] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    paymentsApi.transactions()
      .then(res => setTransactions(res.transactions || []))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      {/* Financial Overview */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 bg-white p-8 rounded-3xl border border-gray-100 shadow-sm bg-gradient-to-br from-white to-gray-50/50 relative overflow-hidden">
          <div className="absolute top-0 right-0 w-64 h-64 bg-[#7c3aed]/5 rounded-full -mr-32 -mt-32 blur-3xl"></div>
          
          <div className="relative z-10">
            <div className="flex items-center gap-3 mb-8">
              <div className="w-10 h-10 bg-[#7c3aed] text-white rounded-xl flex items-center justify-center shadow-lg shadow-[#7c3aed]/20">
                <WalletIcon size={20} />
              </div>
              <h3 className="text-lg font-black text-gray-900">Wallet Balance</h3>
            </div>

            <div className="flex flex-col md:flex-row md:items-end justify-between gap-8">
              <div>
                <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Available Funds</p>
                <h2 className="text-5xl font-black text-gray-900 tracking-tighter">GHS 0.00</h2>
              </div>
              <div className="flex gap-3">
                <button className="bg-[#7c3aed] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#6d28d9] transition-all shadow-lg shadow-[#7c3aed]/20 active:scale-95">
                  Withdraw Funds
                </button>
                <button className="bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-50 transition-all active:scale-95">
                  Settings
                </button>
              </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 gap-8 mt-12 pt-8 border-t border-gray-100">
              <div>
                <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Total Earnings</p>
                <p className="text-xl font-black text-gray-900">GHS 120.50</p>
              </div>
              <div>
                <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">This Month</p>
                <p className="text-xl font-black text-gray-900">GHS 45.00</p>
              </div>
              <div className="hidden md:block">
                <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Pending Clearance</p>
                <p className="text-xl font-black text-orange-500">GHS 15.00</p>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-[#111] p-8 rounded-3xl text-white shadow-xl shadow-gray-200 relative overflow-hidden">
          <div className="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>
          <h3 className="text-lg font-black mb-6 relative z-10">Payment Methods</h3>
          <div className="space-y-4 relative z-10">
            <div className="p-4 bg-white/5 border border-white/10 rounded-2xl flex items-center gap-4">
              <div className="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                <CreditCard size={20} className="text-[#7c3aed]" />
              </div>
              <div>
                <p className="text-sm font-bold">Mobile Money</p>
                <p className="text-[10px] text-gray-500 font-medium">MTN • *** 456</p>
              </div>
              <div className="ml-auto w-2 h-2 rounded-full bg-[#30d158]"></div>
            </div>
            <button className="w-full py-3 border border-dashed border-white/20 rounded-2xl text-xs font-bold text-gray-400 hover:border-white/40 hover:text-white transition-all">
              + Add New Method
            </button>
          </div>
        </div>
      </div>

      {/* Transaction History */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="p-6 border-b border-gray-50 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <History size={18} className="text-gray-400" />
            <h3 className="text-lg font-black text-gray-900">Transaction History</h3>
          </div>
          <button className="p-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-all">
            <Download size={18} />
          </button>
        </div>

        {loading ? (
          <div className="p-12 text-center">
            <div className="w-8 h-8 border-4 border-[#7c3aed] border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p className="text-gray-400 font-medium">Loading transactions...</p>
          </div>
        ) : transactions.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-gray-50/50">
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Transaction</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Reference</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {transactions.map((tx) => (
                  <tr key={tx.id} className="hover:bg-gray-50/50 transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-4">
                        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${
                          tx.type === 'sale' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-600'
                        }`}>
                          {tx.type === 'sale' ? <ArrowUpRight size={18} /> : <ArrowDownRight size={18} />}
                        </div>
                        <div>
                          <p className="text-sm font-bold text-gray-900">{tx.description}</p>
                          <p className="text-[10px] text-gray-400 font-medium">{new Date(tx.created_at).toLocaleDateString()}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-sm font-mono text-gray-500">{tx.reference}</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${
                        tx.status === 'completed' ? 'bg-green-50 text-green-600' : 'bg-orange-50 text-orange-600'
                      }`}>
                        {tx.status === 'completed' ? <CheckCircle2 size={12} /> : <Clock size={12} />}
                        {tx.status}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <p className={`text-sm font-black ${tx.type === 'sale' ? 'text-green-600' : 'text-gray-900'}`}>
                        {tx.type === 'sale' ? '+' : '-'} ₵{tx.amount}
                      </p>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="p-20 text-center">
            <History size={48} className="mx-auto text-gray-200 mb-4" />
            <h4 className="text-lg font-bold text-gray-900 mb-1">No transactions yet</h4>
            <p className="text-gray-400 text-sm">Your financial activity will appear here.</p>
          </div>
        )}
      </div>
    </div>
  );
}

function CheckCircle2({ size }: { size: number }) {
  return <TrendingUp size={size} />;
}

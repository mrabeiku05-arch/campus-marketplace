import { useEffect, useState } from 'react';
import { 
  Search, 
  Filter, 
  MoreVertical, 
  ExternalLink,
  Clock,
  CheckCircle2,
  Truck,
  ShoppingCart
} from 'lucide-react';
import { orders as ordersApi } from '../../services/api';

export default function Orders() {
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');

  useEffect(() => {
    ordersApi.list()
      .then(res => {
        // Assume ordersApi.list() returns { orders: [] }
        setOrders(res.orders || []);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const filteredOrders = orders.filter(o => {
    const matchesSearch = o.product_title.toLowerCase().includes(search.toLowerCase()) || 
                          o.id.toString().includes(search);
    const matchesFilter = filter === 'all' || o.status === filter;
    return matchesSearch && matchesFilter;
  });

  const getStatusStyle = (status: string) => {
    switch (status) {
      case 'completed': return 'bg-green-50 text-green-600';
      case 'delivered': return 'bg-blue-50 text-blue-600';
      case 'seller_seen': return 'bg-purple-50 text-purple-600';
      case 'ordered': return 'bg-orange-50 text-orange-600';
      default: return 'bg-gray-100 text-gray-600';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed': return <CheckCircle2 size={14} />;
      case 'delivered': return <Truck size={14} />;
      case 'seller_seen': return <Clock size={14} />;
      case 'ordered': return <Clock size={14} />;
      default: return null;
    }
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      {/* Filters & Actions */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex bg-white p-1 rounded-xl border border-gray-100 shadow-sm w-fit">
          {['all', 'ordered', 'seller_seen', 'delivered', 'completed'].map((f) => (
            <button
              key={f}
              onClick={() => setFilter(f)}
              className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
                filter === f ? 'bg-[#7c3aed] text-white' : 'text-gray-500 hover:text-gray-900'
              }`}
            >
              {f.charAt(0).toUpperCase() + f.slice(1).replace('_', ' ')}
            </button>
          ))}
        </div>
        <div className="flex items-center gap-3">
          <div className="relative flex-1 md:w-64">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input 
              type="text" 
              placeholder="Search orders..." 
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-white border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm outline-none focus:border-[#7c3aed] transition-all"
            />
          </div>
          <button className="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:bg-gray-50 transition-all">
            <Filter size={18} />
          </button>
        </div>
      </div>

      {/* Orders Table */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center">
            <div className="w-8 h-8 border-4 border-[#7c3aed] border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p className="text-gray-400 font-medium">Loading orders...</p>
          </div>
        ) : filteredOrders.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-gray-50/50">
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Order ID</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Product</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Buyer</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Amount</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {filteredOrders.map((o) => (
                  <tr key={o.id} className="hover:bg-gray-50/50 transition-colors">
                    <td className="px-6 py-4">
                      <p className="text-sm font-black text-gray-900">#ORDER-{o.id}</p>
                      <p className="text-[10px] text-gray-400 font-medium">{new Date(o.created_at).toLocaleDateString()}</p>
                    </td>
                    <td className="px-6 py-4">
                      <p className="text-sm font-bold text-gray-900 truncate max-w-[200px]">{o.product_title}</p>
                    </td>
                    <td className="px-6 py-4">
                      <p className="text-sm font-bold text-gray-900">{o.buyer_name || 'Anonymous'}</p>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${getStatusStyle(o.status)}`}>
                        {getStatusIcon(o.status)}
                        {o.status.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <p className="text-sm font-black text-gray-900">₵{o.price || o.product_price}</p>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button className="text-xs font-bold text-[#7c3aed] hover:underline flex items-center gap-1">
                          Manage
                          <ExternalLink size={12} />
                        </button>
                        <button className="p-1.5 text-gray-400 hover:bg-gray-100 rounded-lg transition-all">
                          <MoreVertical size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="p-20 text-center">
            <ShoppingCart size={48} className="mx-auto text-gray-200 mb-4" />
            <h4 className="text-lg font-bold text-gray-900 mb-1">No orders found</h4>
            <p className="text-gray-400 text-sm">When customers buy your products, they will appear here.</p>
          </div>
        )}
      </div>
    </div>
  );
}

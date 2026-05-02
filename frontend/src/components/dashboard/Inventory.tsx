import React, { useState, useEffect } from 'react';
import { 
  Plus, 
  Search, 
  Filter, 
  MoreVertical, 
  Edit, 
  Trash2, 
  Pause, 
  Play,
  Package,
  AlertTriangle
} from 'lucide-react';
import { products as productsApi } from '../../services/api';
import { assetUrl } from '../../utils/assetUrl';

export default function Inventory() {
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  useEffect(() => {
    productsApi.myProducts()
      .then(res => setProducts(res.products || []))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const filteredProducts = products.filter(p => 
    p.title.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div className="p-3 rounded-xl bg-blue-50 text-blue-600">
            <Package size={20} />
          </div>
          <div>
            <p className="text-2xl font-black text-gray-900">{products.length}</p>
            <p className="text-xs text-gray-500 font-bold uppercase tracking-wider">Total Products</p>
          </div>
        </div>
        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div className="p-3 rounded-xl bg-purple-50 text-purple-600">
            <Filter size={20} />
          </div>
          <div>
            <p className="text-2xl font-black text-gray-900">15</p>
            <p className="text-xs text-gray-500 font-bold uppercase tracking-wider">Inventory Slots</p>
          </div>
        </div>
        <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
          <div className="p-3 rounded-xl bg-orange-50 text-orange-600">
            <AlertTriangle size={20} />
          </div>
          <div>
            <p className="text-2xl font-black text-gray-900">
              {products.filter(p => p.quantity > 0 && p.quantity <= 5).length}
            </p>
            <p className="text-xs text-gray-500 font-bold uppercase tracking-wider">Low Stock Items</p>
          </div>
        </div>
      </div>

      {/* Product Command Center */}
      <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div className="p-6 border-b border-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="relative flex-1 max-w-md">
            <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input 
              type="text" 
              placeholder="Search inventory..." 
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm outline-none focus:border-[#7c3aed] transition-all"
            />
          </div>
          <div className="flex items-center gap-3">
            <button className="p-2 border border-gray-200 rounded-xl text-gray-500 hover:bg-gray-50 transition-all">
              <Filter size={18} />
            </button>
            <button className="bg-[#7c3aed] text-white px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-[#6d28d9] transition-all">
              <Plus size={18} />
              Add Product
            </button>
          </div>
        </div>

        {loading ? (
          <div className="p-12 text-center">
            <div className="w-8 h-8 border-4 border-[#7c3aed] border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p className="text-gray-400 font-medium">Loading inventory...</p>
          </div>
        ) : filteredProducts.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-gray-50/50">
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Product</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Price</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Stock</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Views</th>
                  <th className="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {filteredProducts.map((p) => (
                  <tr key={p.id} className="hover:bg-gray-50/50 transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden border border-gray-200 shrink-0">
                          {p.main_image ? (
                            <img src={assetUrl(p.main_image)} alt={p.title} className="w-full h-full object-cover" />
                          ) : (
                            <div className="w-full h-full flex items-center justify-center text-gray-300">
                              <Package size={16} />
                            </div>
                          )}
                        </div>
                        <div className="min-w-0">
                          <p className="text-sm font-bold text-gray-900 truncate">{p.title}</p>
                          <p className="text-[10px] text-gray-400 font-medium">{p.category}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider ${
                        p.status === 'approved' ? 'bg-green-50 text-green-600' :
                        p.status === 'pending' ? 'bg-orange-50 text-orange-600' :
                        'bg-gray-100 text-gray-600'
                      }`}>
                        {p.status}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <p className="text-sm font-bold text-gray-900">₵{p.price}</p>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <p className={`text-sm font-bold ${p.quantity <= 5 ? 'text-orange-600' : 'text-gray-900'}`}>
                          {p.quantity}
                        </p>
                        {p.quantity <= 5 && <AlertTriangle size={12} className="text-orange-500" />}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <p className="text-sm font-bold text-gray-900">{p.views || 0}</p>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button className="p-2 text-gray-400 hover:text-[#7c3aed] hover:bg-purple-50 rounded-lg transition-all">
                          <Edit size={16} />
                        </button>
                        <button className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                          <Trash2 size={16} />
                        </button>
                        <button className="p-2 text-gray-400 hover:bg-gray-100 rounded-lg transition-all">
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
            <Package size={48} className="mx-auto text-gray-200 mb-4" />
            <h4 className="text-lg font-bold text-gray-900 mb-1">No products found</h4>
            <p className="text-gray-400 text-sm">Start by adding your first product to the marketplace.</p>
            <button className="mt-6 bg-[#7c3aed] text-white px-6 py-2.5 rounded-xl font-bold inline-flex items-center gap-2">
              <Plus size={18} />
              Add Product
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

import React from 'react';
import { 
  TrendingUp, 
  TrendingDown, 
  Wallet, 
  Package, 
  ShoppingCart, 
  Eye,
  CheckCircle2,
  AlertCircle,
  MessageCircle,
  Clock
} from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Filler,
  Legend,
} from 'chart.js';
import { Line } from 'react-chartjs-2';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Filler,
  Legend
);

export default function Overview() {
  const { user } = useAuth();

  const stats = [
    { label: 'Total Revenue', value: `GHS ${user?.balance || '0.00'}`, icon: Wallet, color: 'bg-blue-50 text-blue-600', trend: '+ 0%', trendDir: 'up' },
    { label: 'Active Listings', value: '4', icon: Package, color: 'bg-green-50 text-green-600', trend: '+ 0%', trendDir: 'up' },
    { label: 'Items Sold', value: '1', icon: ShoppingCart, color: 'bg-orange-50 text-orange-600', trend: '- 0%', trendDir: 'down' },
    { label: 'Total Views', value: '45', icon: Eye, color: 'bg-indigo-50 text-indigo-600', trend: '+ 0%', trendDir: 'up' },
  ];

  const chartData = {
    labels: ['Apr 26', 'Apr 27', 'Apr 28', 'Apr 29', 'Apr 30', 'May 01', 'May 02'],
    datasets: [
      {
        label: 'Revenue (GHS)',
        data: [0, 0, 0, 0, 0, 0, 0],
        borderColor: '#7c3aed',
        backgroundColor: 'rgba(124, 58, 237, 0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#7c3aed',
      },
    ],
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#111',
        titleFont: { size: 12, weight: 'bold' as const },
        bodyFont: { size: 12 },
        padding: 12,
        cornerRadius: 8,
        displayColors: false,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#f3f4f6', drawTicks: false },
        border: { display: false },
        ticks: { color: '#9ca3af', font: { size: 10 } },
      },
      x: {
        grid: { display: false },
        border: { display: false },
        ticks: { color: '#9ca3af', font: { size: 10 } },
      },
    },
  };

  const activities = [
    { id: 1, type: 'order', text: 'Order #10 completed', subtext: 'EAGEAT Laptop Stand', time: '11:38 AM', icon: CheckCircle2, color: 'text-green-500' },
    { id: 2, type: 'approval', text: 'Product approved', subtext: 'Wireless Mouse', time: '10:21 AM', icon: CheckCircle2, color: 'text-green-500' },
    { id: 3, type: 'alert', text: 'Low stock alert', subtext: '2 products running low', time: '09:15 AM', icon: AlertCircle, color: 'text-orange-500' },
    { id: 4, type: 'message', text: 'New message from Admin', subtext: 'Regarding your store', time: 'Yesterday', icon: MessageCircle, color: 'text-blue-500' },
  ];

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      {/* KPI Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, i) => (
          <div key={i} className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-center justify-between mb-4">
              <div className={`p-3 rounded-xl ${stat.color}`}>
                <stat.icon size={20} />
              </div>
              <div className={`flex items-center gap-1 text-xs font-bold ${stat.trendDir === 'up' ? 'text-green-500' : 'text-red-500'}`}>
                {stat.trendDir === 'up' ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
                {stat.trend}
              </div>
            </div>
            <p className="text-sm text-gray-500 font-bold uppercase tracking-wider mb-1">{stat.label}</p>
            <h3 className="text-2xl font-black text-gray-900">{stat.value}</h3>
            <p className="text-[10px] text-gray-400 font-medium mt-1">from last 7 days</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Performance Chart */}
        <div className="lg:col-span-2 bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h3 className="text-lg font-black text-gray-900">Weekly Performance</h3>
              <p className="text-sm text-gray-500 font-medium">Your store revenue and views trend</p>
            </div>
            <select className="bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-xs font-bold outline-none focus:border-[#7c3aed]">
              <option>Last 7 Days</option>
              <option>Last 30 Days</option>
            </select>
          </div>
          <div className="h-[300px] w-full">
            <Line data={chartData} options={chartOptions} />
          </div>
          <div className="grid grid-cols-4 gap-4 mt-8 pt-8 border-t border-gray-50 text-center">
            <div>
              <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Total Revenue</p>
              <p className="text-sm font-black text-gray-900">GHS 0.00</p>
            </div>
            <div>
              <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Views</p>
              <p className="text-sm font-black text-gray-900">45</p>
            </div>
            <div>
              <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Orders</p>
              <p className="text-sm font-black text-gray-900">1</p>
            </div>
            <div>
              <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Conv. Rate</p>
              <p className="text-sm font-black text-gray-900">2.22%</p>
            </div>
          </div>
        </div>

        {/* Activity Feed */}
        <div className="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
          <div className="flex items-center justify-between mb-8">
            <h3 className="text-lg font-black text-gray-900">Recent Activity</h3>
            <button className="text-[#7c3aed] text-xs font-bold hover:underline">View all</button>
          </div>
          <div className="space-y-6">
            {activities.map((activity) => (
              <div key={activity.id} className="flex gap-4">
                <div className={`mt-1 shrink-0 ${activity.color}`}>
                  <activity.icon size={18} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-bold text-gray-900">{activity.text}</p>
                  <p className="text-xs text-gray-500 font-medium truncate">{activity.subtext}</p>
                </div>
                <div className="text-[10px] text-gray-400 font-bold whitespace-nowrap">
                  {activity.time}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

import React from 'react';
import { 
  BarChart3, 
  TrendingUp, 
  Users, 
  Eye, 
  ShoppingCart,
  ArrowUpRight,
  ArrowDownRight
} from 'lucide-react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
} from 'chart.js';
import { Bar, Line, Pie } from 'react-chartjs-2';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement,
  ArcElement,
  Title,
  Tooltip,
  Legend
);

export default function Analytics() {
  const lineData = {
    labels: ['Apr 26', 'Apr 27', 'Apr 28', 'Apr 29', 'Apr 30', 'May 01', 'May 02'],
    datasets: [
      {
        label: 'Views',
        data: [5, 12, 8, 15, 7, 20, 45],
        borderColor: '#7c3aed',
        backgroundColor: '#7c3aed',
        tension: 0.4,
      },
      {
        label: 'Sales',
        data: [0, 1, 0, 0, 1, 0, 1],
        borderColor: '#30d158',
        backgroundColor: '#30d158',
        tension: 0.4,
      },
    ],
  };

  const barData = {
    labels: ['Laptops', 'Phones', 'Books', 'Hostels', 'Fashion'],
    datasets: [
      {
        label: 'Revenue by Category',
        data: [1200, 800, 300, 2500, 450],
        backgroundColor: '#7c3aed',
        borderRadius: 8,
      },
    ],
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' as const, labels: { boxWidth: 8, usePointStyle: true, font: { size: 10, weight: 'bold' as const } } },
    },
    scales: {
      y: { grid: { color: '#f3f4f6', drawTicks: false }, border: { display: false }, ticks: { font: { size: 10 } } },
      x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } },
    },
  };

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      {/* Analytics Hero */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <MetricCard label="Conversion Rate" value="2.22%" trend="+0.4%" dir="up" />
        <MetricCard label="Avg. Order Value" value="₵85.00" trend="+12%" dir="up" />
        <MetricCard label="Customer Growth" value="+12" trend="+8%" dir="up" />
        <MetricCard label="Return Rate" value="0.5%" trend="-2%" dir="down" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Growth Chart */}
        <div className="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h3 className="text-lg font-black text-gray-900">Traffic & Conversion</h3>
              <p className="text-sm text-gray-500 font-medium">Daily views vs orders completed</p>
            </div>
          </div>
          <div className="h-[300px]">
            <Line data={lineData} options={options} />
          </div>
        </div>

        {/* Category Performance */}
        <div className="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h3 className="text-lg font-black text-gray-900">Revenue Distribution</h3>
              <p className="text-sm text-gray-500 font-medium">Earnings broken down by category</p>
            </div>
          </div>
          <div className="h-[300px]">
            <Bar data={barData} options={options} />
          </div>
        </div>
      </div>
    </div>
  );
}

function MetricCard({ label, value, trend, dir }: { label: string, value: string, trend: string, dir: 'up' | 'down' }) {
  return (
    <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
      <p className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">{label}</p>
      <div className="flex items-end justify-between">
        <h3 className="text-2xl font-black text-gray-900">{value}</h3>
        <div className={`flex items-center gap-0.5 text-xs font-bold ${dir === 'up' ? 'text-green-500' : 'text-red-500'}`}>
          {dir === 'up' ? <ArrowUpRight size={14} /> : <ArrowDownRight size={14} />}
          {trend}
        </div>
      </div>
    </div>
  );
}

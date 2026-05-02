import { Suspense, lazy } from 'react';
import { Navigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Sidebar from '../components/dashboard/Sidebar';
import DashboardHeader from '../components/dashboard/Header';

// Lazy load tab components
const Overview = lazy(() => import('../components/dashboard/Overview'));
const Inventory = lazy(() => import('../components/dashboard/Inventory'));
const Orders = lazy(() => import('../components/dashboard/Orders'));
const Analytics = lazy(() => import('../components/dashboard/Analytics'));
const Wallet = lazy(() => import('../components/dashboard/Wallet'));
const Messages = lazy(() => import('../components/dashboard/Messages'));
const Settings = lazy(() => import('../components/dashboard/Settings'));

export default function Dashboard() {
  const { user, isLoggedIn, loading } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTab = searchParams.get('tab') || 'overview';

  // Ensure only authorized sellers/admins can access
  if (loading) return null;
  if (!isLoggedIn) return <Navigate to="/login" replace />;
  if (user?.role !== 'seller' && user?.role !== 'admin') {
    return <Navigate to="/" replace />;
  }

  const handleTabChange = (tab: string) => {
    setSearchParams({ tab });
  };

  const renderTabContent = () => {
    switch (activeTab) {
      case 'overview': return <Overview />;
      case 'inventory': return <Inventory />;
      case 'orders': return <Orders />;
      case 'analytics': return <Analytics />;
      case 'wallet': return <Wallet />;
      case 'messages': return <Messages />;
      case 'settings': return <Settings />;
      default: return <Overview />;
    }
  };

  return (
    <div className="flex h-screen bg-[#f9fafb] text-[#1a1a1a] font-sans overflow-hidden">
      {/* Sidebar */}
      <Sidebar activeTab={activeTab} onTabChange={handleTabChange} />

      {/* Main Workspace */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Header */}
        <DashboardHeader activeTab={activeTab} />

        {/* Dynamic Content Area */}
        <main className="flex-1 overflow-y-auto">
          <Suspense fallback={
            <div className="flex items-center justify-center h-full">
              <div className="w-8 h-8 border-4 border-[#7c3aed] border-t-transparent rounded-full animate-spin"></div>
            </div>
          }>
            <div className="max-w-[1400px] mx-auto p-8">
              {renderTabContent()}
            </div>
          </Suspense>
        </main>
      </div>
    </div>
  );
}

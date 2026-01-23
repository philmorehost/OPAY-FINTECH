
import React from 'react';
import { Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { AppProvider, useApp } from './store';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Airtime from './pages/Airtime';
import Data from './pages/Data';
import AdminDashboard from './pages/AdminDashboard';
import Support from './pages/Support';
import Transactions from './pages/Transactions';
import Rewards from './pages/Rewards';
import Profile from './pages/Profile';
import BulkSMS from './pages/BulkSMS';
import Crypto from './pages/Crypto';
import VirtualCards from './pages/VirtualCards';
import BankTransfer from './pages/BankTransfer';
import ReferralDashboard from './pages/ReferralDashboard';
import CableTV from './pages/CableTV';
import Electricity from './pages/Electricity';
import Betting from './pages/Betting';
import ExamPin from './pages/ExamPin';
import LoginSettings from './pages/LoginSettings';
import NetworkStatus from './components/NetworkStatus';
import AddMoney from './pages/AddMoney';
import GiftCards from './pages/GiftCards';
import Services from './pages/Services';

const ProtectedRoute: React.FC<{ children: React.ReactNode; role?: 'user' | 'admin' }> = ({ children, role }) => {
  const { currentUser } = useApp();
  if (!currentUser) return <Navigate to="/login" />;
  if (role && currentUser.role !== role) return <Navigate to="/dashboard" />;
  if (currentUser.isSuspended) return <div className="p-10 text-center font-bold text-red-500">ACCOUNT SUSPENDED. CONTACT SUPPORT.</div>;
  return <>{children}</>;
};

const App: React.FC = () => {
  return (
    <AppProvider>
      <NetworkStatus />
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/dashboard" element={<ProtectedRoute role="user"><Dashboard /></ProtectedRoute>} />
        <Route path="/services" element={<ProtectedRoute role="user"><Services /></ProtectedRoute>} />
        <Route path="/add-money" element={<ProtectedRoute role="user"><AddMoney /></ProtectedRoute>} />
        <Route path="/airtime" element={<ProtectedRoute role="user"><Airtime /></ProtectedRoute>} />
        <Route path="/data" element={<ProtectedRoute role="user"><Data /></ProtectedRoute>} />
        <Route path="/sms" element={<ProtectedRoute role="user"><BulkSMS /></ProtectedRoute>} />
        <Route path="/gift-cards" element={<ProtectedRoute role="user"><GiftCards /></ProtectedRoute>} />
        <Route path="/crypto" element={<ProtectedRoute role="user"><Crypto /></ProtectedRoute>} />
        <Route path="/vcard" element={<ProtectedRoute role="user"><VirtualCards /></ProtectedRoute>} />
        <Route path="/transfer" element={<ProtectedRoute role="user"><BankTransfer /></ProtectedRoute>} />
        <Route path="/cable" element={<ProtectedRoute role="user"><CableTV /></ProtectedRoute>} />
        <Route path="/electric" element={<ProtectedRoute role="user"><Electricity /></ProtectedRoute>} />
        <Route path="/betting" element={<ProtectedRoute role="user"><Betting /></ProtectedRoute>} />
        <Route path="/exam" element={<ProtectedRoute role="user"><ExamPin /></ProtectedRoute>} />
        <Route path="/referrals" element={<ProtectedRoute role="user"><ReferralDashboard /></ProtectedRoute>} />
        <Route path="/support" element={<ProtectedRoute role="user"><Support /></ProtectedRoute>} />
        <Route path="/profile" element={<ProtectedRoute role="user"><Profile /></ProtectedRoute>} />
        <Route path="/login-settings" element={<ProtectedRoute role="user"><LoginSettings /></ProtectedRoute>} />
        <Route path="/transactions" element={<ProtectedRoute role="user"><Transactions /></ProtectedRoute>} />
        <Route path="/rewards" element={<ProtectedRoute role="user"><Rewards /></ProtectedRoute>} />
        <Route path="/admin/*" element={<ProtectedRoute role="admin"><AdminDashboard /></ProtectedRoute>} />
        <Route path="/" element={<Navigate to="/login" />} />
      </Routes>
    </AppProvider>
  );
};

export default App;

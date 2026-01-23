
import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency } from '../utils';
import { ArrowLeft, Users, TrendingUp, DollarSign, Share2, Copy, BarChart3 } from 'lucide-react';

const ReferralDashboard: React.FC = () => {
  const { currentUser, settings } = useApp();
  const navigate = useNavigate();

  if (!currentUser) return null;

  const referralCode = `OPAY-${currentUser.username.toUpperCase()}`;

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 border-b">
        <ArrowLeft className="text-gray-900" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Refer & Earn</h1>
      </div>

      <div className="p-6 space-y-6">
        <div className="bg-gradient-to-br from-indigo-600 to-blue-700 p-8 rounded-[40px] text-white shadow-2xl shadow-blue-100">
          <div className="text-xs font-black uppercase tracking-widest opacity-70 mb-2">Total Earnings</div>
          <div className="text-4xl font-black mb-6">{formatCurrency(currentUser.referralEarnings)}</div>
          <div className="grid grid-cols-2 gap-4">
            <div className="bg-white/10 p-4 rounded-2xl border border-white/10 backdrop-blur-sm">
              <div className="text-[10px] font-black uppercase opacity-60">Invited</div>
              <div className="text-xl font-black">{currentUser.referralCount}</div>
            </div>
            <div className="bg-white/10 p-4 rounded-2xl border border-white/10 backdrop-blur-sm">
              <div className="text-[10px] font-black uppercase opacity-60">Rate</div>
              <div className="text-xl font-black">2.4%</div>
            </div>
          </div>
        </div>

        <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
          <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Your Referral Code</h3>
          <div className="flex gap-3">
            <div className="flex-1 bg-gray-50 p-5 rounded-2xl border-2 border-dashed border-gray-200 text-center font-black text-lg tracking-widest text-gray-700">
              {referralCode}
            </div>
            <button className="p-5 bg-opay-green text-white rounded-2xl shadow-xl shadow-green-100 active:scale-95 transition-all">
              <Copy size={24} />
            </button>
          </div>
          <button className="w-full bg-gray-900 text-white py-5 rounded-2xl font-black uppercase tracking-widest shadow-xl flex items-center justify-center gap-3">
            <Share2 size={18} /> Invite Friends
          </button>
        </div>

        <div className="space-y-4">
          <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">How it works</h3>
          <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
            <div className="flex gap-5 items-start">
              <div className="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 font-black border border-blue-100 shrink-0">1</div>
              <div>
                <div className="text-sm font-black text-gray-900">Share your link</div>
                <p className="text-[10px] text-gray-400 font-bold mt-1">Friends join using your unique code.</p>
              </div>
            </div>
            <div className="flex gap-5 items-start">
              <div className="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center text-green-600 font-black border border-green-100 shrink-0">2</div>
              <div>
                <div className="text-sm font-black text-gray-900">They Fund & Transact</div>
                <p className="text-[10px] text-gray-400 font-bold mt-1">When they complete their first NGN 1000 transaction.</p>
              </div>
            </div>
            <div className="flex gap-5 items-start">
              <div className="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-orange-600 font-black border border-orange-100 shrink-0">3</div>
              <div>
                <div className="text-sm font-black text-gray-900">Earn ₦500 each</div>
                <p className="text-[10px] text-gray-400 font-bold mt-1">Both you and your friend get instant rewards.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReferralDashboard;

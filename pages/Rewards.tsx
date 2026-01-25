
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency } from '../utils';
import { ArrowLeft, Coins, TrendingUp, CheckCircle2, History } from 'lucide-react';

const Rewards: React.FC = () => {
  const { currentUser, setUsers, settings } = useApp();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);

  if (!currentUser) return null;

  const handleConvert = () => {
    if (currentUser.bonusCoins < settings.conversionRate) {
      alert(`Minimum coins to convert is ${settings.conversionRate}`);
      return;
    }

    setLoading(true);
    setTimeout(() => {
      const nairaValue = Math.floor(currentUser.bonusCoins / settings.conversionRate);
      const coinsToDeduct = nairaValue * settings.conversionRate;

      setUsers(prev => prev.map(u => u.id === currentUser.id ? {
        ...u,
        bonusCoins: u.bonusCoins - coinsToDeduct,
        walletBalance: u.walletBalance + nairaValue
      } : u));

      setLoading(false);
      alert(`Successfully converted ${coinsToDeduct} coins to ${formatCurrency(nairaValue)}!`);
    }, 1000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-billpay-green p-8 text-white rounded-b-[40px] shadow-lg">
        <div className="flex items-center gap-4 mb-10">
          <ArrowLeft onClick={() => navigate('/dashboard')} className="cursor-pointer text-white" />
          <h1 className="text-lg font-black uppercase tracking-widest">Rewards</h1>
        </div>

        <div className="flex flex-col items-center py-8">
          <div className="w-28 h-28 bg-white/20 rounded-[35px] flex items-center justify-center relative mb-6 backdrop-blur-sm border border-white/20">
            <Coins size={56} className="text-yellow-300 drop-shadow-lg" />
            <div className="absolute -top-3 -right-3 bg-yellow-400 text-billpay-green px-3 py-1 rounded-full text-[10px] font-black shadow-xl border-2 border-white">BOOST</div>
          </div>
          <div className="text-5xl font-black mb-2 tracking-tighter text-white">{currentUser.bonusCoins}</div>
          <div className="text-xs opacity-90 font-black uppercase tracking-widest">Reward Coins</div>
        </div>
      </div>

      <div className="p-4 flex-1 -mt-10">
        <div className="bg-white p-8 rounded-[40px] shadow-2xl space-y-8 border border-gray-50">
          <div className="flex justify-between items-center p-6 bg-gray-50 rounded-3xl border border-gray-100">
            <div>
              <div className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Cash Value</div>
              <div className="text-2xl font-black text-gray-900">{formatCurrency(currentUser.bonusCoins / settings.conversionRate)}</div>
            </div>
            <button 
              onClick={handleConvert}
              disabled={loading || currentUser.bonusCoins < settings.conversionRate}
              className={`bg-billpay-green text-white px-8 py-4 rounded-2xl font-black text-xs shadow-lg shadow-green-100 active:scale-95 transition-transform ${loading || currentUser.bonusCoins < settings.conversionRate ? 'opacity-50 grayscale' : ''}`}
            >
              {loading ? 'CONVERTING...' : 'CASH OUT'}
            </button>
          </div>

          <div className="h-px bg-gray-100" />

          <div className="space-y-5">
            <h3 className="text-xs font-black text-gray-900 flex items-center gap-3 uppercase tracking-widest">
              <div className="w-1.5 h-6 bg-billpay-green rounded-full" /> Earn More
            </h3>
            <div className="space-y-4">
              <div className="flex justify-between items-center p-5 bg-white rounded-3xl border border-gray-100 shadow-sm">
                <div className="flex gap-4 items-center">
                  <div className="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 border border-blue-100">
                    <CheckCircle2 size={24} />
                  </div>
                  <div>
                    <div className="text-xs font-black text-gray-800 uppercase tracking-tight">Daily Purchase</div>
                    <div className="text-[10px] text-gray-500 font-bold">Buy any service today</div>
                  </div>
                </div>
                <div className="text-sm font-black text-billpay-green">+20</div>
              </div>
              
              <div className="flex justify-between items-center p-5 bg-white rounded-3xl border border-gray-100 shadow-sm">
                <div className="flex gap-4 items-center">
                  <div className="w-12 h-12 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-600 border border-purple-100">
                    <History size={24} />
                  </div>
                  <div>
                    <div className="text-xs font-black text-gray-800 uppercase tracking-tight">7-Day Streak</div>
                    <div className="text-[10px] text-gray-500 font-bold">Keep it up for a week</div>
                  </div>
                </div>
                <div className="text-sm font-black text-billpay-green">+100</div>
              </div>
            </div>
          </div>
        </div>

        <div className="mt-8 px-6">
          <div className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Conversion Rules</div>
          <p className="text-[11px] text-gray-500 leading-relaxed font-bold italic">
            Rate: <span className="text-billpay-green font-black">{settings.conversionRate} Coins = ₦1</span>. 
            Minimum payout: {settings.conversionRate} coins.
          </p>
        </div>
      </div>
    </div>
  );
};

export default Rewards;

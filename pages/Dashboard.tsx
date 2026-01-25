
import React from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency } from '../utils';
import { 
  Phone, Wifi, Tv, Zap, CreditCard, TrendingUp, History, Gift, 
  User as UserIcon, Search, MessageSquare, ArrowRightLeft, 
  Coins, ShieldCheck, LayoutGrid, MessageCircle, Bitcoin, 
  BarChart3, Settings as SettingsIcon, Bell
} from 'lucide-react';

const Dashboard: React.FC = () => {
  const { currentUser, settings } = useApp();
  const navigate = useNavigate();
  const location = useLocation();

  if (!currentUser) return null;

  const services = [
    { icon: <Phone size={24} className="text-blue-500" />, label: 'Airtime', path: '/airtime' },
    { icon: <Wifi size={24} className="text-orange-500" />, label: 'Data', path: '/data' },
    { icon: <MessageCircle size={24} className="text-emerald-500" />, label: 'Bulk SMS', path: '/sms' },
    { icon: <Tv size={24} className="text-red-500" />, label: 'Cable TV', path: '/cable' },
    { icon: <Zap size={24} className="text-yellow-500" />, label: 'Electricity', path: '/electric' },
    { icon: <TrendingUp size={24} className="text-green-500" />, label: 'Betting', path: '/betting' },
    { icon: <Bitcoin size={24} className="text-orange-600" />, label: 'Crypto', path: '/crypto' },
    { icon: <ArrowRightLeft size={24} className="text-indigo-500" />, label: 'Transfer', path: '/transfer' },
    { icon: <CreditCard size={24} className="text-pink-500" />, label: 'Card', path: '/vcard' },
    { icon: <ShieldCheck size={24} className="text-purple-500" />, label: 'Exam PIN', path: '/exam' },
    { icon: <BarChart3 size={24} className="text-cyan-600" />, label: 'Referrals', path: '/referrals' },
    { icon: <Gift size={24} className="text-pink-600" />, label: 'Gift Cards', path: '/gift-cards' },
  ];

  return (
    <div className="flex flex-col min-h-screen bg-gray-50 max-w-md mx-auto relative shadow-2xl">
      {/* Header */}
      <div className="bg-billpay-green p-6 text-white rounded-b-[40px] shadow-lg">
        <div className="flex justify-between items-center mb-6">
          <div className="flex items-center gap-3" onClick={() => navigate('/profile')}>
            <div className="w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/20">
              <UserIcon size={20} className="text-white" />
            </div>
            <div className="flex flex-col">
              <span className="font-bold text-sm">Hi, {currentUser.fullName.split(' ')[0]}</span>
              <span className="text-[10px] font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-full w-fit">Tier {currentUser.tier}</span>
            </div>
          </div>
          <div className="flex gap-3">
            <Bell size={20} className="text-white/80" />
            <MessageSquare size={20} onClick={() => navigate('/support')} className="cursor-pointer text-white/80" />
            <SettingsIcon size={20} className="text-white/80" onClick={() => navigate('/login-settings')} />
          </div>
        </div>

        <div className="bg-black/10 p-6 rounded-3xl backdrop-blur-md mb-2 border border-white/10 shadow-inner">
          <div className="flex justify-between items-start mb-2">
            <div className="text-[10px] font-black text-white/70 uppercase tracking-widest">Available Balance</div>
            <ArrowRightLeft size={16} className="text-white/40" />
          </div>
          <div className="text-3xl font-black flex items-center gap-1 text-white">
            {formatCurrency(currentUser.walletBalance)}
            <div className="text-[10px] font-black bg-white/20 text-white px-2 py-1 rounded-full ml-2 border border-white/10">DETAILS</div>
          </div>
          <div className="mt-6 flex gap-3">
            <button 
              onClick={() => navigate('/add-money')}
              className="flex-1 bg-white text-gray-900 py-3.5 rounded-2xl font-black text-xs shadow-xl active:scale-95 transition-all"
            >
              ADD MONEY
            </button>
            <button 
              onClick={() => navigate('/transfer')}
              className="flex-1 bg-black/20 text-white py-3.5 rounded-2xl font-black text-xs border border-white/10 active:scale-95 transition-all"
            >
              TRANSFER
            </button>
          </div>
        </div>
      </div>

      {/* Rewards & Daily Streak */}
      <div className="px-4 -mt-6 mb-6">
        <div 
          onClick={() => navigate('/rewards')}
          className="bg-white p-5 rounded-3xl shadow-xl flex justify-between items-center cursor-pointer active:bg-gray-50 border border-gray-100/50"
        >
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-yellow-400 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-100">
              <Coins className="text-white" size={24} />
            </div>
            <div>
              <div className="text-xs font-black text-gray-900 uppercase tracking-tight">Daily Cashback</div>
              <div className="text-[10px] text-gray-400 font-bold">Earn 20 coins for today's check-in</div>
            </div>
          </div>
          <div className="text-right">
            <div className="text-sm font-black text-billpay-green">{currentUser.bonusCoins}</div>
            <div className="text-[9px] text-gray-400 font-black uppercase">COINS</div>
          </div>
        </div>
      </div>

      {/* Services Grid */}
      <div className="px-4 py-2 flex-1 pb-24">
        <div className="bg-white p-6 rounded-[32px] shadow-sm grid grid-cols-4 gap-y-10 border border-gray-100">
          {services.map((service, idx) => (
            <div 
              key={idx} 
              className="flex flex-col items-center gap-2.5 cursor-pointer group"
              onClick={() => navigate(service.path)}
            >
              <div className="w-14 h-14 bg-gray-50 group-active:scale-90 rounded-2xl flex items-center justify-center shadow-sm border border-gray-100 transition-all">
                {service.icon}
              </div>
              <span className="text-[10px] font-black text-gray-600 uppercase tracking-tight text-center">{service.label}</span>
            </div>
          ))}
        </div>

        {/* Promotions Carousel - Dynamic from Settings */}
        <div className="mt-10">
          <div className="flex justify-between items-center px-2 mb-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Offers for you</h3>
            <span className="text-[10px] font-black text-billpay-green uppercase">See all</span>
          </div>
          <div className="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
            {settings.offers.length === 0 ? (
               <div className="min-w-[280px] h-36 bg-gray-100 rounded-[32px] flex items-center justify-center text-gray-400 text-[10px] font-black uppercase tracking-widest">
                  No active offers
               </div>
            ) : (
              settings.offers.map((offer) => (
                <div 
                  key={offer.id} 
                  style={{ background: `linear-gradient(to bottom right, ${offer.gradientFrom}, ${offer.gradientTo})`, color: offer.textColor }}
                  className="min-w-[280px] h-36 rounded-[32px] p-6 relative overflow-hidden flex flex-col justify-center shadow-xl shadow-gray-200"
                >
                  <div className="text-lg font-black leading-tight max-w-[180px]">{offer.title}</div>
                  <div className="text-[10px] font-medium opacity-80 mt-2">{offer.description}</div>
                  <div className="text-[10px] font-black uppercase opacity-60 mt-2 tracking-widest">{offer.label}</div>
                  <div className="absolute -right-8 -bottom-8 w-28 h-28 bg-white/10 rounded-full"></div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {/* Bottom Navigation */}
      <div className="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-xl border-t border-gray-100 flex justify-around items-center py-4 px-6 shadow-[0_-10px_40px_rgba(0,0,0,0.05)] max-w-md mx-auto z-50 rounded-t-[32px]">
        <div onClick={() => navigate('/services')} className={`flex flex-col items-center gap-1.5 cursor-pointer ${location.pathname === '/services' ? 'text-billpay-green font-black' : 'text-gray-400'}`}>
          <div className={`p-1.5 ${location.pathname === '/services' ? 'bg-billpay-green/10 rounded-xl' : ''}`}>
            <LayoutGrid size={22} strokeWidth={2.5} />
          </div>
          <span className="text-[9px] font-black uppercase">Services</span>
        </div>
        <div onClick={() => navigate('/dashboard')} className={`flex flex-col items-center gap-1.5 cursor-pointer ${location.pathname === '/dashboard' ? 'text-billpay-green font-black' : 'text-gray-400'}`}>
          <div className={`p-1.5 ${location.pathname === '/dashboard' ? 'bg-billpay-green/10 rounded-xl' : ''}`}>
             <History size={22} className="rotate-90" />
          </div>
          <span className="text-[9px] font-black uppercase">Home</span>
        </div>
        <div onClick={() => navigate('/rewards')} className={`flex flex-col items-center gap-1.5 cursor-pointer ${location.pathname === '/rewards' ? 'text-billpay-green font-black' : 'text-gray-400'}`}>
          <div className={`p-1.5 ${location.pathname === '/rewards' ? 'bg-billpay-green/10 rounded-xl' : ''}`}>
            <Gift size={22} />
          </div>
          <span className="text-[9px] font-bold uppercase">Reward</span>
        </div>
        <div onClick={() => navigate('/transactions')} className={`flex flex-col items-center gap-1.5 cursor-pointer ${location.pathname === '/transactions' ? 'text-billpay-green font-black' : 'text-gray-400'}`}>
          <div className={`p-1.5 ${location.pathname === '/transactions' ? 'bg-billpay-green/10 rounded-xl' : ''}`}>
            <History size={22} />
          </div>
          <span className="text-[9px] font-bold uppercase">History</span>
        </div>
        <div onClick={() => navigate('/profile')} className={`flex flex-col items-center gap-1.5 cursor-pointer ${location.pathname === '/profile' ? 'text-billpay-green font-black' : 'text-gray-400'}`}>
          <div className={`p-1.5 ${location.pathname === '/profile' ? 'bg-billpay-green/10 rounded-xl' : ''}`}>
            <UserIcon size={22} />
          </div>
          <span className="text-[9px] font-bold uppercase">Me</span>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;

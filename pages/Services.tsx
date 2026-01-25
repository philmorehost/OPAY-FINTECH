
import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { 
  Phone, Wifi, Tv, Zap, CreditCard, TrendingUp, History, Gift, 
  User as UserIcon, MessageSquare, ArrowRightLeft, 
  ShieldCheck, LayoutGrid, MessageCircle, Bitcoin, 
  BarChart3, ArrowLeft
} from 'lucide-react';

const Services: React.FC = () => {
  const navigate = useNavigate();

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
    <div className="flex flex-col min-h-screen bg-white max-w-md mx-auto relative shadow-2xl">
      <div className="p-6 border-b border-gray-100 flex items-center gap-4 sticky top-0 bg-white z-10">
        <ArrowLeft size={24} className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-xl font-black text-gray-900">All Services</h1>
      </div>

      <div className="p-6 grid grid-cols-4 gap-y-10">
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

      {/* Footer Copy from Dashboard */}
      <div className="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-xl border-t border-gray-100 flex justify-around items-center py-4 px-6 shadow-[0_-10px_40px_rgba(0,0,0,0.05)] max-w-md mx-auto z-50 rounded-t-[32px]">
        <div className="flex flex-col items-center text-billpay-green gap-1.5" onClick={() => navigate('/services')}>
          <div className="p-1.5 bg-billpay-green/10 rounded-xl">
            <LayoutGrid size={22} strokeWidth={2.5} />
          </div>
          <span className="text-[9px] font-black uppercase">Services</span>
        </div>
        <div onClick={() => navigate('/dashboard')} className="flex flex-col items-center text-gray-400 gap-1.5 cursor-pointer transition-colors hover:text-gray-600">
          <History size={22} className="rotate-90" />
          <span className="text-[9px] font-bold uppercase">Home</span>
        </div>
        <div onClick={() => navigate('/rewards')} className="flex flex-col items-center text-gray-400 gap-1.5 cursor-pointer transition-colors hover:text-gray-600">
          <Gift size={22} />
          <span className="text-[9px] font-bold uppercase">Reward</span>
        </div>
        <div onClick={() => navigate('/transactions')} className="flex flex-col items-center text-gray-400 gap-1.5 cursor-pointer transition-colors hover:text-gray-600">
          <History size={22} />
          <span className="text-[9px] font-bold uppercase">History</span>
        </div>
        <div onClick={() => navigate('/profile')} className="flex flex-col items-center text-gray-400 gap-1.5 cursor-pointer transition-colors hover:text-gray-600">
          <UserIcon size={22} />
          <span className="text-[9px] font-bold uppercase">Me</span>
        </div>
      </div>
    </div>
  );
};

export default Services;

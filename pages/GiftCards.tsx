
import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  ArrowLeft, Gift, Search, Info, AlertCircle, CheckCircle2, 
  ChevronRight, UploadCloud, Send, RefreshCcw, History, 
  DollarSign, ShoppingCart, Repeat, X, ShieldCheck
} from 'lucide-react';

const GiftCards: React.FC = () => {
  const { currentUser, setUsers, setTransactions, giftCardRequests, setGiftCardRequests, settings } = useApp();
  const navigate = useNavigate();

  const [activeTab, setActiveTab] = useState<'buy' | 'sell' | 'history'>('buy');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCard, setSelectedCard] = useState<any>(null);
  const [amount, setAmount] = useState('');
  const [cardCode, setCardCode] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  // Simulated Noones API Live Rates
  const [rates, setRates] = useState({
    Amazon: 1520,
    iTunes: 1480,
    Steam: 1550,
    GooglePlay: 1450,
    VanillaVisa: 1600,
    Nordstrom: 1300,
    Sephora: 1350,
    RazorGold: 1580
  });

  const giftCards = useMemo(() => [
    { name: 'Amazon', color: 'bg-orange-500', logo: 'Am' },
    { name: 'iTunes', color: 'bg-pink-600', logo: 'iT' },
    { name: 'Steam', color: 'bg-blue-900', logo: 'St' },
    { name: 'GooglePlay', color: 'bg-green-500', logo: 'GP' },
    { name: 'VanillaVisa', color: 'bg-gray-800', logo: 'Vi' },
    { name: 'Nordstrom', color: 'bg-black', logo: 'Nd' },
    { name: 'Sephora', color: 'bg-red-500', logo: 'Se' },
    { name: 'RazorGold', color: 'bg-yellow-500', logo: 'Rg' },
  ], []);

  useEffect(() => {
    // Simulate API Polling for Noones.com rates
    const interval = setInterval(() => {
      setRates(prev => {
        const newRates = { ...prev };
        Object.keys(newRates).forEach(key => {
          // @ts-ignore
          newRates[key] += (Math.random() - 0.5) * 10;
        });
        return newRates;
      });
    }, 10000);
    return () => clearInterval(interval);
  }, []);

  const filteredCards = giftCards.filter(c => c.name.toLowerCase().includes(searchTerm.toLowerCase()));

  const handleAction = () => {
    if (!currentUser || !selectedCard || !amount) return;
    const numAmount = parseFloat(amount);
    const rate = rates[selectedCard.name as keyof typeof rates];
    const nairaValue = numAmount * rate;

    if (activeTab === 'buy') {
      if (currentUser.walletBalance < nairaValue) {
        setStatus({ type: 'error', text: 'Insufficient wallet balance for this purchase.' });
        return;
      }

      setIsLoading(true);
      setTimeout(() => {
        const requestId = generateId();
        const request: any = {
          id: requestId,
          userId: currentUser.id,
          cardBrand: selectedCard.name,
          amount: numAmount,
          nairaAmount: nairaValue,
          type: 'buy',
          status: 'successful',
          date: new Date().toISOString(),
          rate: rate
        };

        const tx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Gift Card Purchase',
          amount: nairaValue,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Bought $${numAmount} ${selectedCard.name} Gift Card. Rate: ₦${rate.toFixed(2)}/$`,
          recipient: selectedCard.name
        };

        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - nairaValue } : u));
        setTransactions(prev => [tx, ...prev]);
        setGiftCardRequests(prev => [request, ...prev]);
        setIsLoading(false);
        setStatus({ type: 'success', text: `Purchase successful! Check your history for the code.` });
        setSelectedCard(null);
        setAmount('');
      }, 2000);
    } 
    else if (activeTab === 'sell') {
      if (!cardCode) {
        setStatus({ type: 'error', text: 'Please enter the gift card code or upload image.' });
        return;
      }

      setIsLoading(true);
      setTimeout(() => {
        const requestId = generateId();
        const request: any = {
          id: requestId,
          userId: currentUser.id,
          cardBrand: selectedCard.name,
          amount: numAmount,
          nairaAmount: nairaValue,
          type: 'sell',
          code: cardCode,
          status: 'pending',
          date: new Date().toISOString(),
          rate: rate
        };

        setGiftCardRequests(prev => [request, ...prev]);
        setIsLoading(false);
        setStatus({ type: 'success', text: `Card submitted! Admin will verify and credit your wallet shortly.` });
        setSelectedCard(null);
        setAmount('');
        setCardCode('');
      }, 2500);
    }
  };

  const myHistory = giftCardRequests.filter(r => r.userId === currentUser?.id).sort((a,b) => new Date(b.date).getTime() - new Date(a.date).getTime());

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10">
      <div className="bg-white p-4 flex items-center justify-between sticky top-0 z-10 border-b">
        <div className="flex items-center gap-4">
          <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
          <h1 className="text-lg font-black text-gray-900">Gift Cards</h1>
        </div>
        <div className="flex items-center gap-1 bg-orange-50 px-2 py-1 rounded-full border border-orange-100">
           <span className="text-[8px] font-black text-orange-600 uppercase">Synced Noones</span>
        </div>
      </div>

      <div className="p-4 space-y-6 flex-1">
        {status && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${status.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {status.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-[11px] font-bold flex-1">{status.text}</span>
            <button onClick={() => setStatus(null)}><X size={16} /></button>
          </div>
        )}

        <div className="flex bg-gray-100 p-1.5 rounded-3xl">
          <button 
            onClick={() => { setActiveTab('buy'); setSelectedCard(null); setStatus(null); }}
            className={`flex-1 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 ${activeTab === 'buy' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-400'}`}
          >
            <ShoppingCart size={14} /> Buy Card
          </button>
          <button 
            onClick={() => { setActiveTab('sell'); setSelectedCard(null); setStatus(null); }}
            className={`flex-1 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 ${activeTab === 'sell' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-400'}`}
          >
            <Repeat size={14} /> Sell Card
          </button>
          <button 
            onClick={() => { setActiveTab('history'); setStatus(null); }}
            className={`flex-1 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 ${activeTab === 'history' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-400'}`}
          >
            <History size={14} /> History
          </button>
        </div>

        {activeTab !== 'history' ? (
          <div className="space-y-6 animate-fade-in">
             <div className="relative">
                <input 
                  type="text" 
                  placeholder="Search brands (e.g. Amazon)"
                  className="w-full pl-11 pr-4 py-4 bg-white rounded-2xl shadow-sm border border-gray-100 outline-none font-bold text-xs"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={18} />
             </div>

             <div className="grid grid-cols-4 gap-4">
                {filteredCards.map(card => (
                  <div 
                    key={card.name} 
                    onClick={() => { setSelectedCard(card); setAmount(''); setCardCode(''); }}
                    className={`flex flex-col items-center gap-2 transition-all cursor-pointer ${selectedCard?.name === card.name ? 'scale-110' : 'opacity-60'}`}
                  >
                     <div className={`w-14 h-14 rounded-2xl flex items-center justify-center text-white font-black text-xs shadow-md ${card.color}`}>
                        {card.logo}
                     </div>
                     <span className="text-[9px] font-black uppercase text-gray-600 text-center tracking-tighter">{card.name}</span>
                  </div>
                ))}
             </div>

             {selectedCard && (
               <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6 animate-slide-up">
                  <div className="flex items-center justify-between border-b border-gray-50 pb-4">
                     <div className="flex items-center gap-4">
                        <div className={`w-12 h-12 rounded-2xl ${selectedCard.color} flex items-center justify-center text-white font-black`}>
                           {selectedCard.logo}
                        </div>
                        <div>
                           <h3 className="text-lg font-black text-gray-900">{selectedCard.name}</h3>
                           <div className="text-[10px] font-black text-opay-green uppercase">Rate: ₦{rates[selectedCard.name as keyof typeof rates].toFixed(2)}/$</div>
                        </div>
                     </div>
                     <button onClick={() => setSelectedCard(null)} className="p-2 bg-gray-50 rounded-full text-gray-400"><X size={18} /></button>
                  </div>

                  <div className="space-y-4">
                     <div>
                        <label className="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1 mb-2 block">Amount (USD)</label>
                        <div className="relative">
                           <input 
                              type="number"
                              placeholder="0.00"
                              className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-3xl font-black text-2xl"
                              value={amount}
                              onChange={(e) => setAmount(e.target.value)}
                           />
                           <span className="absolute right-5 top-1/2 -translate-y-1/2 text-sm font-black text-gray-300">USD</span>
                        </div>
                     </div>

                     {activeTab === 'sell' && (
                        <div className="animate-fade-in">
                           <label className="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1 mb-2 block">Card Code / PIN</label>
                           <textarea 
                              className="w-full p-4 bg-gray-50 border-none outline-none rounded-3xl font-bold text-sm min-h-[100px]"
                              placeholder="Type code here or separate by comma..."
                              value={cardCode}
                              onChange={(e) => setCardCode(e.target.value)}
                           />
                           <div className="mt-3 bg-blue-50 p-4 rounded-2xl border border-blue-100 flex items-center gap-3 cursor-pointer">
                              <UploadCloud size={20} className="text-blue-500" />
                              <span className="text-[10px] font-black text-blue-700 uppercase">Upload Card Image (Optional)</span>
                           </div>
                        </div>
                     )}

                     {amount && (
                        <div className="bg-gray-50 p-6 rounded-3xl space-y-3">
                           <div className="flex justify-between text-[10px] font-black">
                              <span className="text-gray-400 uppercase tracking-widest">{activeTab === 'buy' ? 'You Pay' : 'You Receive'}</span>
                              <span className="text-gray-900">{formatCurrency(parseFloat(amount) * rates[selectedCard.name as keyof typeof rates])}</span>
                           </div>
                           <div className="flex justify-between text-[10px] font-black">
                              <span className="text-gray-400 uppercase tracking-widest">Fees</span>
                              <span className="text-opay-green">₦0.00 (Promo)</span>
                           </div>
                        </div>
                     )}
                  </div>

                  <button
                    onClick={handleAction}
                    disabled={isLoading || !amount || (activeTab === 'sell' && !cardCode)}
                    className={`w-full font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-2 ${activeTab === 'buy' ? 'bg-opay-green text-white' : 'bg-gray-900 text-white'}`}
                  >
                    {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : 
                      activeTab === 'buy' ? `BUY ${selectedCard.name.toUpperCase()} CARD` : `SELL ${selectedCard.name.toUpperCase()} CARD`
                    }
                  </button>
               </div>
             )}
          </div>
        ) : (
          <div className="space-y-4 animate-fade-in pb-10">
             {myHistory.length === 0 ? (
                <div className="py-32 flex flex-col items-center justify-center text-center space-y-6">
                   <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center text-gray-300">
                      <History size={32} />
                   </div>
                   <p className="text-[10px] font-black text-gray-400 uppercase tracking-widest">No history yet</p>
                </div>
             ) : (
                myHistory.map(req => (
                   <div key={req.id} className="bg-white p-5 rounded-[32px] border border-gray-100 shadow-sm space-y-4">
                      <div className="flex justify-between items-start">
                         <div className="flex items-center gap-3">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-white font-black text-[10px] ${giftCards.find(c => c.name === req.cardBrand)?.color}`}>
                               {req.cardBrand.substring(0,2)}
                            </div>
                            <div>
                               <div className="text-xs font-black text-gray-800 uppercase">{req.cardBrand} - {req.type}</div>
                               <div className="text-[9px] text-gray-400 font-bold">{new Date(req.date).toLocaleString()}</div>
                            </div>
                         </div>
                         <span className={`px-2 py-0.5 rounded-full text-[8px] font-black uppercase ${
                            req.status === 'successful' ? 'bg-green-50 text-green-600' : 
                            req.status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'
                         }`}>
                            {req.status}
                         </span>
                      </div>
                      <div className="flex justify-between items-center bg-gray-50 p-4 rounded-2xl">
                         <div>
                            <span className="text-[8px] font-black text-gray-400 uppercase block">Amount</span>
                            <span className="text-sm font-black text-gray-800">${req.amount}</span>
                         </div>
                         <div className="text-right">
                            <span className="text-[8px] font-black text-gray-400 uppercase block">Value</span>
                            <span className="text-sm font-black text-opay-green">{formatCurrency(req.nairaAmount)}</span>
                         </div>
                      </div>
                   </div>
                ))
             )}
          </div>
        )}

        {activeTab !== 'history' && !selectedCard && (
           <div className="bg-gray-900 p-8 rounded-[40px] text-white space-y-6 relative overflow-hidden shadow-xl">
              <div className="absolute -right-8 -top-8 w-28 h-28 bg-opay-green/20 rounded-full blur-3xl" />
              <div className="flex items-center gap-3">
                 <ShieldCheck className="text-opay-green" />
                 <h4 className="text-[10px] font-black uppercase tracking-widest text-white/70">Verified Trading</h4>
              </div>
              <p className="text-xs font-bold leading-relaxed">
                 O-Pay gift card portal is powered by <span className="text-opay-green">Noones.com</span>. All rates are updated in real-time. Buy codes instantly or sell for lightning fast wallet credit.
              </p>
           </div>
        )}
      </div>
    </div>
  );
};

export default GiftCards;

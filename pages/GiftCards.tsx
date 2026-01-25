
import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { 
  ArrowLeft, Gift, Search, Info, AlertCircle, CheckCircle2, 
  ChevronRight, UploadCloud, Send, RefreshCcw, History, 
  DollarSign, ShoppingCart, Repeat, X, ShieldCheck
} from 'lucide-react';

const GiftCards: React.FC = () => {
  const { currentUser, setUsers, setTransactions, setGiftCardRequests, settings } = useApp();
  const navigate = useNavigate();

  const [activeTab, setActiveTab] = useState<'buy' | 'sell' | 'history'>('buy');
  const [selectedCard, setSelectedCard] = useState<any>(null);
  const [amount, setAmount] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const giftCards = useMemo(() => [
    { name: 'Amazon', color: 'bg-orange-500' },
    { name: 'iTunes', color: 'bg-pink-600' },
    { name: 'Steam', color: 'bg-blue-900' }
  ], []);

  const handleAction = async () => {
    if (!currentUser || !selectedCard || !amount) return;
    const numAmount = parseFloat(amount);
    const rate = 1520;
    const nairaValue = numAmount * rate;

    if (currentUser.walletBalance < nairaValue) {
      setStatus({ type: 'error', text: 'Insufficient balance.' });
      return;
    }

    setIsLoading(true);
    setTimeout(async () => {
      const ref = generateId();
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - nairaValue } : u));
      
      const tx: any = { id: ref, userId: currentUser.id, type: 'Gift Card', amount: nairaValue, status: 'successful', date: new Date().toISOString(), details: `${selectedCard.name} $${numAmount}`, recipient: selectedCard.name };
      setTransactions(prev => [tx, ...prev]);

      // LIVE EMAIL NOTIFICATION
      try {
        await sendNotificationEmail(settings, currentUser.email, 'Gift Card Receipt', currentUser.fullName, {
          'Brand': selectedCard.name,
          'Amount': `$${numAmount}`,
          'Total Paid': nairaValue,
          'Reference': ref
        });
      } catch (err) { console.warn(err); }

      setIsLoading(false);
      setStatus({ type: 'success', text: `Success!` });
    }, 2000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      <div className="bg-white p-4 flex items-center border-b shadow-sm">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900 ml-4">Gift Cards</h1>
      </div>
      <div className="p-4 space-y-6">
        <div className="bg-white p-8 rounded-[40px] space-y-6">
           <select className="w-full p-4 bg-gray-50 rounded-2xl font-black" onChange={e => setSelectedCard({name: e.target.value})}>
              <option value="">Select Brand</option>
              {giftCards.map(c => <option key={c.name} value={c.name}>{c.name}</option>)}
           </select>
           <input type="number" placeholder="Dollar Amount" className="w-full p-4 bg-gray-50 rounded-2xl font-black" value={amount} onChange={e => setAmount(e.target.value)} />
           <button onClick={handleAction} disabled={isLoading || !amount} className="w-full bg-billpay-green text-white py-5 rounded-2xl font-black">{isLoading ? 'Purchasing...' : 'BUY NOW'}</button>
        </div>
      </div>
    </div>
  );
};

export default GiftCards;

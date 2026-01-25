
import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { ArrowLeft, TrendingUp, User, ShieldCheck, CheckCircle2, AlertCircle, ChevronDown, RotateCcw, X, Percent } from 'lucide-react';

const Betting: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();

  const [providerId, setProviderId] = useState('');
  const [bettingId, setBettingId] = useState('');
  const [amount, setAmount] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const activeProviders = useMemo(() => settings.bettingProviders.filter(p => p.enabled), [settings.bettingProviders]);
  const selectedProvider = useMemo(() => activeProviders.find(p => p.id === providerId), [activeProviders, providerId]);

  const numAmount = parseFloat(amount) || 0;
  const discount = selectedProvider ? (numAmount * (selectedProvider.discountPercent / 100)) : 0;
  const totalToPay = numAmount - discount;

  const handleFunding = async () => {
    if (!currentUser || !amount || !providerId || !bettingId) return;

    if (currentUser.walletBalance < totalToPay) {
      setMessage({ type: 'error', text: 'Insufficient wallet balance' });
      return;
    }

    setIsLoading(true);
    const originalBalance = currentUser.walletBalance;
    
    // Debit user
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - totalToPay } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - totalToPay });

    setTimeout(async () => {
      const ref = generateId();
      const successTx: any = {
        id: ref,
        userId: currentUser.id,
        type: 'Betting',
        amount: totalToPay,
        status: 'successful',
        date: new Date().toISOString(),
        details: `${selectedProvider?.name} Wallet Funding. Discount Applied: ${formatCurrency(discount)}`,
        recipient: bettingId,
        provider: selectedProvider?.name
      };

      setTransactions(prev => [successTx, ...prev]);
      
      // LIVE EMAIL NOTIFICATION
      try {
        await sendNotificationEmail(settings, currentUser.email, 'Betting Receipt', currentUser.fullName, {
          'Platform': selectedProvider?.name || 'Betting',
          'Betting ID': bettingId,
          'Face Amount': formatCurrency(numAmount),
          'Discount': formatCurrency(discount),
          'Total Paid': formatCurrency(totalToPay),
          'Ref': ref,
          'Date': new Date().toLocaleString()
        });
      } catch (err) {
        console.warn("Email delivery failed.");
      }

      setIsLoading(false);
      setMessage({ type: 'success', text: `Wallet funded successfully!` });
      setBettingId(''); setAmount(''); setProviderId('');
    }, 2000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      <div className="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-10">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Betting Wallet</h1>
      </div>

      <div className="p-4 space-y-6 overflow-y-auto scrollbar-hide">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold flex-1">{message.text}</span>
            <X size={16} className="cursor-pointer opacity-50" onClick={() => setMessage(null)} />
          </div>
        )}

        <div className="bg-white p-8 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
          <div className="flex justify-center">
             <div className="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center text-green-600">
               <TrendingUp size={32} />
             </div>
          </div>

          <div className="space-y-6">
            <div>
              <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Select Provider</label>
              <div className="relative">
                <select 
                  className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-bold appearance-none"
                  value={providerId}
                  onChange={(e) => setProviderId(e.target.value)}
                >
                  <option value="">Choose Platform</option>
                  {activeProviders.map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
                <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none" size={20} />
              </div>
            </div>

            <div>
              <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">User ID / Phone</label>
              <div className="relative">
                <input 
                  type="text"
                  placeholder="Enter Betting ID"
                  className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-black text-lg tracking-widest"
                  value={bettingId}
                  onChange={(e) => setBettingId(e.target.value)}
                />
                <User className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-300" size={20} />
              </div>
            </div>

            <div>
              <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Amount (₦)</label>
              <div className="relative">
                <input 
                  type="number"
                  placeholder="Min ₦100"
                  className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-billpay-green outline-none font-black text-xl"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                />
                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-xl">₦</span>
              </div>
            </div>
          </div>

          {selectedProvider && selectedProvider.discountPercent > 0 && numAmount > 0 && (
            <div className="p-5 bg-green-50 rounded-3xl border border-green-100 flex justify-between items-center animate-slide-down">
               <div className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-white rounded-xl flex items-center justify-center text-green-600 shadow-sm">
                    <Percent size={14} />
                  </div>
                  <div className="flex flex-col">
                    <span className="text-[10px] font-black text-green-700 uppercase">Cashback Active</span>
                    <span className="text-[8px] font-bold text-green-600 opacity-60 uppercase">{selectedProvider.discountPercent}% Discount</span>
                  </div>
               </div>
               <div className="text-right">
                  <div className="text-xs font-black text-green-700">-{formatCurrency(discount)}</div>
               </div>
            </div>
          )}

          <div className="bg-gray-900 p-6 rounded-[32px] text-white space-y-4">
             <div className="flex justify-between items-center">
                <span className="text-[10px] font-black uppercase tracking-widest opacity-40">Wallet Debit</span>
                <span className="text-xl font-black text-billpay-green">{formatCurrency(totalToPay)}</span>
             </div>
             <div className="flex justify-between items-center">
                <span className="text-[10px] font-black uppercase tracking-widest opacity-40">Provider Topup</span>
                <span className="text-sm font-bold opacity-80">{formatCurrency(numAmount)}</span>
             </div>
          </div>

          <button
            onClick={handleFunding}
            disabled={isLoading || !amount || !providerId || !bettingId}
            className="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
          >
            {isLoading ? <RotateCcw className="animate-spin" size={20} /> : <><ShieldCheck size={18} /> FUND WALLET</>}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Betting;

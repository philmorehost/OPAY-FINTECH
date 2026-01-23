
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { ArrowLeft, TrendingUp, User, ShieldAlert, CheckCircle2, AlertCircle, ChevronDown, RotateCcw } from 'lucide-react';

const Betting: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings, transactions } = useApp();
  const navigate = useNavigate();

  const [provider, setProvider] = useState('');
  const [bettingId, setBettingId] = useState('');
  const [customerName, setCustomerName] = useState('');
  const [amount, setAmount] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const providers = [
    { id: 'msport', name: 'MSport' },
    { id: 'naijabet', name: 'NaijaBet' },
    { id: 'nairabet', name: 'NairaBet' },
    { id: 'bet9ja-agent', name: 'Bet9ja Agent' },
    { id: 'betland', name: 'Betland' },
    { id: 'betlion', name: 'Betlion' },
    { id: 'supabet', name: 'SupaBet' },
    { id: 'bet9ja', name: 'Bet9ja' },
    { id: 'bangbet', name: 'BangBet' },
    { id: 'betking', name: 'BetKing' },
    { id: '1xbet', name: '1xBet' },
    { id: 'betway', name: 'Betway' },
    { id: 'merrybet', name: 'MerryBet' },
    { id: 'mlotto', name: 'MLotto' },
    { id: 'western-lotto', name: 'Western Lotto' },
    { id: 'hallabet', name: 'HallaBet' },
    { id: 'green-lotto', name: 'Green Lotto' }
  ].sort((a, b) => a.name.localeCompare(b.name));

  const checkAndApplyLoyaltyBonus = (user: any) => {
    const today = new Date().toISOString().split('T')[0];
    if (user.lastPurchaseDate !== today) {
      const updatedUser = {
        ...user,
        bonusCoins: user.bonusCoins + settings.bonusPerDay,
        lastPurchaseDate: today
      };
      setUsers(prev => prev.map(u => u.id === user.id ? updatedUser : u));
      setCurrentUser(updatedUser);
      return true;
    }
    return false;
  };

  const validateSecurity = (targetId: string) => {
    const today = new Date().toISOString().split('T')[0];
    const userTxsToday = transactions.filter(
      (tx) => tx.userId === currentUser?.id && tx.date.startsWith(today) && tx.recipient === targetId && tx.type === 'Betting' && tx.status !== 'failed'
    );
    return userTxsToday.length < settings.maxDailyTxPerId;
  };

  const handleVerifyId = () => {
    if (bettingId.length >= 6 && provider) {
      setIsVerifying(true);
      setMessage(null);
      setTimeout(() => {
        setCustomerName('KABIRU OLAMIDE USMAN');
        setIsVerifying(false);
      }, 1000);
    }
  };

  const handleFunding = () => {
    if (!currentUser || !amount || !provider || !bettingId) return;
    const numAmount = parseFloat(amount);

    if (numAmount < 100) {
      setMessage({ type: 'error', text: 'Minimum funding amount is ₦100' });
      return;
    }

    if (currentUser.walletBalance < numAmount) {
      setMessage({ type: 'error', text: 'Insufficient wallet balance' });
      return;
    }

    // 1. Debit First
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - numAmount } : u));
    setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance - numAmount });
    setIsLoading(true);

    setTimeout(() => {
      const isSecurityFail = !validateSecurity(bettingId);
      const isRandomFail = Math.random() < 0.05; 
      const selectedProviderName = providers.find(p => p.id === provider)?.name || provider;

      if (isSecurityFail || isRandomFail) {
        // 2. Automated Refund
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + numAmount } : u));
        setCurrentUser(prev => prev ? { ...prev, walletBalance: prev.walletBalance + numAmount } : null);
        
        const failTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Betting',
          amount: numAmount,
          status: 'failed',
          date: new Date().toISOString(),
          details: isSecurityFail ? `Security Trigger: Daily limit exceeded for Betting ID ${bettingId}` : `Automated Refund: ${selectedProviderName} Gateway Timeout.`,
          recipient: bettingId,
          provider: selectedProviderName,
          refunded: true
        };
        setTransactions(prev => [failTx, ...prev]);
        setIsLoading(false);
        setMessage({ type: 'error', text: 'Betting wallet funding failed. Amount has been automatically refunded to your wallet.' });
      } else {
        const earned = checkAndApplyLoyaltyBonus(currentUser);
        const successTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Betting',
          amount: numAmount,
          status: 'successful',
          date: new Date().toISOString(),
          details: `${selectedProviderName} Wallet Funding for ID: ${bettingId}`,
          recipient: bettingId,
          provider: selectedProviderName
        };

        setTransactions(prev => [successTx, ...prev]);
        setIsLoading(false);
        setMessage({ type: 'success', text: `Wallet funded successfully for ${customerName}! ${earned ? `+${settings.bonusPerDay} Coins awarded.` : ''}` });
        setBettingId('');
        setCustomerName('');
        setAmount('');
      }
    }, 2000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Betting Wallet</h1>
      </div>

      <div className="p-4 flex-1 space-y-6 pb-20">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold">{message.text}</span>
          </div>
        )}

        <div className="bg-white p-6 rounded-[32px] shadow-sm space-y-6 border border-gray-100">
          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Select Provider</label>
            <div className="relative">
              <select
                className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-sm appearance-none cursor-pointer"
                value={provider}
                onChange={(e) => { setProvider(e.target.value); setCustomerName(''); }}
              >
                <option value="" disabled>Choose Betting Provider</option>
                {providers.map(p => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
              <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" size={18} />
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Customer ID</label>
            <div className="relative">
              <input
                type="tel"
                placeholder="Enter betting ID"
                className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg tracking-widest"
                value={bettingId}
                onChange={(e) => setBettingId(e.target.value.replace(/\D/g, ''))}
                onBlur={handleVerifyId}
              />
              {isVerifying && <div className="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 border-2 border-opay-green border-t-transparent rounded-full animate-spin" />}
            </div>
            {customerName && (
              <div className="mt-2 flex items-center gap-2 px-2 animate-fade-in">
                <User size={12} className="text-opay-green" />
                <span className="text-[10px] font-black text-opay-green uppercase tracking-tight">{customerName}</span>
              </div>
            )}
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Amount (₦)</label>
            <div className="grid grid-cols-4 gap-2 mb-4">
              {[500, 1000, 2000, 5000].map(amt => (
                <button
                  key={amt}
                  onClick={() => setAmount(amt.toString())}
                  className={`py-2 rounded-xl text-[10px] font-black border transition-all ${amount === amt.toString() ? 'border-opay-green bg-green-50 text-opay-green' : 'border-gray-100 text-gray-500'}`}
                >
                  ₦{amt}
                </button>
              ))}
            </div>
            <div className="relative">
              <input
                type="number"
                placeholder="Enter Custom Amount"
                className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-xl"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-xl pointer-events-none">₦</span>
            </div>
          </div>

          <button
            onClick={handleFunding}
            disabled={isLoading || !amount || !customerName || !provider}
            className="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
          >
            {isLoading ? 'Automated Processing...' : `Fund Wallet (${formatCurrency(parseFloat(amount || '0'))})`}
          </button>
        </div>

        <div className="bg-red-50 p-5 rounded-3xl border border-red-100 flex gap-4">
           <RotateCcw className="text-red-600 shrink-0" size={20} />
           <div className="text-[10px] font-bold text-red-700 leading-relaxed">
             Safe Guarantee: All funding requests are debited first. If the betting provider gateway reports a timeout or error, the system performs an <span className="font-black">Instant Automated Refund</span> to your balance.
           </div>
        </div>
      </div>
    </div>
  );
};

export default Betting;

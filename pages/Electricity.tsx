
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { ArrowLeft, Zap, User, Landmark, CheckCircle2, AlertCircle, ShieldAlert, RotateCcw } from 'lucide-react';

const Electricity: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings, transactions } = useApp();
  const navigate = useNavigate();

  const [provider, setProvider] = useState('');
  const [meterNumber, setMeterNumber] = useState('');
  const [meterType, setMeterType] = useState('Prepaid');
  const [customerName, setCustomerName] = useState('');
  const [amount, setAmount] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const discos = [
    { id: 'ikedc', label: 'Ikeja Electric', logo: 'IK' },
    { id: 'ekedc', label: 'Eko Electric', logo: 'EK' },
    { id: 'aedc', label: 'Abuja Electric', logo: 'AE' },
    { id: 'kedco', label: 'Kano Electric', logo: 'KE' },
    { id: 'phed', label: 'Port Harcourt Electric', logo: 'PH' },
    { id: 'ibedc', label: 'Ibadan Electric', logo: 'IB' },
    { id: 'eedc', label: 'Enugu Electric', logo: 'EE' },
    { id: 'jedc', label: 'Jos Electric', logo: 'JE' },
    { id: 'yedc', label: 'Yola Electric', logo: 'YE' },
    { id: 'bedc', label: 'Benin Electric', logo: 'BE' },
    { id: 'aba', label: 'Aba Electric', logo: 'AB' },
    { id: 'kaedco', label: 'Kaduna Electric', logo: 'KA' },
  ].sort((a, b) => a.label.localeCompare(b.label));

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
      (tx) => tx.userId === currentUser?.id && tx.date.startsWith(today) && tx.recipient === targetId && tx.status !== 'failed'
    );
    return userTxsToday.length < settings.maxDailyTxPerId;
  };

  const handleVerifyMeter = () => {
    if (meterNumber.length >= 10 && provider) {
      setIsVerifying(true);
      setTimeout(() => {
        setCustomerName('SULEIMAN PETER GOMWALK');
        setIsVerifying(false);
      }, 1000);
    }
  };

  const handlePurchase = () => {
    if (!currentUser || !amount || !provider || !meterNumber) return;
    const numAmount = parseFloat(amount);

    if (numAmount < 500) {
      setMessage({ type: 'error', text: 'Minimum electricity payment is ₦500' });
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
      const isSecurityFail = !validateSecurity(meterNumber);
      const isRandomFail = Math.random() < 0.05; 
      const selectedDisco = discos.find(d => d.id === provider);

      if (isSecurityFail || isRandomFail) {
        // 2. Automated Refund
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + numAmount } : u));
        setCurrentUser(prev => prev ? { ...prev, walletBalance: prev.walletBalance + numAmount } : null);
        
        const failTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Electricity',
          amount: numAmount,
          status: 'failed',
          date: new Date().toISOString(),
          details: isSecurityFail ? `Security Trigger: Daily limit exceeded for ${meterNumber}` : `Automated Refund: ${selectedDisco?.label} Processing Timeout.`,
          recipient: meterNumber,
          provider: selectedDisco?.label || provider,
          refunded: true
        };
        setTransactions(prev => [failTx, ...prev]);
        setIsLoading(false);
        setMessage({ type: 'error', text: 'Electricity payment failed. Amount has been automatically refunded to your wallet.' });
      } else {
        const earned = checkAndApplyLoyaltyBonus(currentUser);
        const token = Math.floor(Math.random() * 900000000000 + 100000000000).toString().match(/.{1,4}/g)?.join('-');
        
        const successTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Electricity',
          amount: numAmount,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Electricity Token for ${meterNumber}. Token: ${token}`,
          recipient: meterNumber,
          provider: selectedDisco?.label || provider
        };

        setTransactions(prev => [successTx, ...prev]);
        setIsLoading(false);
        setMessage({ type: 'success', text: `Success! ${earned ? `+${settings.bonusPerDay} Coins awarded. ` : ''}Token: ${token}` });
        setMeterNumber('');
        setCustomerName('');
        setAmount('');
      }
    }, 2500);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Electricity Bill</h1>
      </div>

      <div className="p-4 flex-1 space-y-6 pb-20">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold">{message.text}</span>
          </div>
        )}

        <div className="bg-white p-6 rounded-3xl shadow-sm space-y-6 border border-gray-100">
          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest">Select Provider</label>
            <div className="flex overflow-x-auto gap-3 pb-2 scrollbar-hide">
              {discos.map(disco => (
                <button
                  key={disco.id}
                  onClick={() => { setProvider(disco.id); setCustomerName(''); }}
                  className={`flex flex-col items-center gap-2 p-3 min-w-[90px] rounded-2xl border-2 transition-all active:scale-95 ${provider === disco.id ? 'border-opay-green bg-green-50 shadow-sm' : 'border-transparent bg-gray-50 opacity-60'}`}
                >
                  <div className="w-10 h-10 rounded-full bg-yellow-400 flex items-center justify-center text-white text-xs font-black shadow-inner border-2 border-white">
                    {disco.logo}
                  </div>
                  <span className="text-[8px] font-black text-gray-800 uppercase tracking-tighter text-center leading-tight">{disco.label}</span>
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Meter Type</label>
            <div className="flex bg-gray-100 p-1.5 rounded-2xl">
              <button 
                onClick={() => setMeterType('Prepaid')}
                className={`flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all ${meterType === 'Prepaid' ? 'bg-white shadow-md text-opay-green' : 'text-gray-500'}`}
              >
                Prepaid
              </button>
              <button 
                onClick={() => setMeterType('Postpaid')}
                className={`flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all ${meterType === 'Postpaid' ? 'bg-white shadow-md text-opay-green' : 'text-gray-500'}`}
              >
                Postpaid
              </button>
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Meter Number</label>
            <div className="relative">
              <input
                type="tel"
                placeholder="Enter 11-digit meter number"
                className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg tracking-widest"
                value={meterNumber}
                onChange={(e) => setMeterNumber(e.target.value.replace(/\D/g, ''))}
                onBlur={handleVerifyMeter}
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
            <div className="relative">
              <input
                type="number"
                placeholder="Min ₦500"
                className="w-full p-4 pl-10 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-xl"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-xl">₦</span>
            </div>
          </div>

          <button
            onClick={handlePurchase}
            disabled={isLoading || !amount || !customerName}
            className="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
          >
            {isLoading ? 'Automated Processing...' : `Pay ${formatCurrency(parseFloat(amount || '0'))}`}
          </button>
        </div>

        <div className="bg-red-50 p-5 rounded-3xl border border-red-100 flex gap-4">
           <RotateCcw className="text-red-600 shrink-0" size={20} />
           <div className="text-[10px] font-bold text-red-700 leading-relaxed">
             Debit-First Policy: Meters are secured via immediate debit. If a Token generation failure is detected from Disco server, the system triggers an <span className="font-black">Instant Automated Refund</span> to your balance.
           </div>
        </div>
      </div>
    </div>
  );
};

export default Electricity;

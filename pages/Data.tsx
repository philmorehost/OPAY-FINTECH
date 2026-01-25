
import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { detectNetwork, formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { Transaction, DataProduct } from '../types';
import { 
  ArrowLeft, User, Wifi, CheckCircle2, AlertCircle, 
  RotateCcw, Trash2, X, ShieldCheck
} from 'lucide-react';

const Data: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings, transactions } = useApp();
  const navigate = useNavigate();
  
  const [isBulk, setIsBulk] = useState(false);
  const [phoneNumber, setPhoneNumber] = useState('');
  const [bulkNumbers, setBulkNumbers] = useState('');
  const [networkId, setNetworkId] = useState('');
  const [override, setOverride] = useState(false);
  const [selectedPlanId, setSelectedPlanId] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error' | 'info', text: string } | null>(null);

  const availableNetworks = useMemo(() => settings.dataNetworks, [settings.dataNetworks]);

  const filteredPlans = useMemo(() => {
    if (!networkId) return [];
    return settings.dataProducts.filter(p => p.networkId === networkId && p.enabled);
  }, [settings.dataProducts, networkId]);

  const selectedPlan = useMemo(() => {
    return settings.dataProducts.find(p => p.id === selectedPlanId);
  }, [settings.dataProducts, selectedPlanId]);

  useEffect(() => {
    if (!isBulk && !override && phoneNumber.length >= 4) {
      const detectedName = detectNetwork(phoneNumber);
      const networkMatch = availableNetworks.find(n => n.name.toLowerCase() === detectedName.toLowerCase());
      if (networkMatch) {
        setNetworkId(networkMatch.id);
      }
    }
  }, [phoneNumber, override, isBulk, availableNetworks]);

  const bulkStats = useMemo(() => {
    const raw = bulkNumbers.split(/[,\n]/).map(n => n.trim()).filter(n => n.length >= 10);
    const unique: string[] = Array.from(new Set(raw));
    return { total: unique.length, unique };
  }, [bulkNumbers]);

  const validateSecurity = (targetId: string) => {
    const today = new Date().toISOString().split('T')[0];
    const userTxsToday = transactions.filter(
      (tx) => tx.userId === currentUser?.id && tx.date.startsWith(today) && tx.recipient === targetId && tx.status !== 'failed'
    );
    return userTxsToday.length < settings.maxDailyTxPerId;
  };

  const handlePurchase = async () => {
    if (!currentUser || !selectedPlan) return;
    setMessage(null);
    
    const recipients = isBulk ? bulkStats.unique : [phoneNumber];
    if (recipients.length === 0 || (!isBulk && phoneNumber.length < 10)) {
      setMessage({ type: 'error', text: 'Enter a valid recipient' });
      return;
    }

    const totalCost = recipients.length * selectedPlan.userPrice;
    if (currentUser.walletBalance < totalCost) {
      setMessage({ type: 'error', text: `Insufficient balance: ${formatCurrency(totalCost)} required` });
      return;
    }

    setIsLoading(true);
    const originalBalance = currentUser.walletBalance;
    const currentNetworkName = availableNetworks.find(n => n.id === selectedPlan.networkId)?.name || 'Data';

    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - totalCost } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - totalCost });
    
    let successCount = 0;
    let refundTotal = 0;
    const newTxs: Transaction[] = [];

    for (const num of recipients) {
      if (!validateSecurity(num)) {
        refundTotal += selectedPlan.userPrice;
        newTxs.push({ id: generateId(), userId: currentUser.id, type: 'Data', amount: selectedPlan.userPrice, status: 'failed', date: new Date().toISOString(), details: `Security limit exceeded for ${num}`, recipient: num, provider: currentNetworkName, refunded: true });
        continue;
      }

      const success = Math.random() > 0.05; 
      if (success) {
        successCount++;
        newTxs.push({ id: generateId(), userId: currentUser.id, type: 'Data', amount: selectedPlan.userPrice, status: 'successful', date: new Date().toISOString(), details: `${selectedPlan.size} Plan for ${num}`, recipient: num, provider: currentNetworkName });
      } else {
        refundTotal += selectedPlan.userPrice;
        newTxs.push({ id: generateId(), userId: currentUser.id, type: 'Data', amount: selectedPlan.userPrice, status: 'failed', date: new Date().toISOString(), details: `API Provider Error. Refund Issued.`, recipient: num, provider: currentNetworkName, refunded: true });
      }
    }

    if (refundTotal > 0) {
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + refundTotal } : u));
      setCurrentUser(prev => prev ? { ...prev, walletBalance: prev.walletBalance + refundTotal } : null);
    }

    setTransactions(prev => [...newTxs, ...prev]);
    
    // LIVE EMAIL NOTIFICATION
    if (successCount > 0) {
      try {
        await sendNotificationEmail(settings, currentUser.email, 'Data Receipt', currentUser.fullName, {
          'Plan': selectedPlan.size,
          'Recipients': recipients.length > 1 ? `${recipients.length} Numbers` : recipients[0],
          'Network': currentNetworkName,
          'Total Amount': totalCost,
          'Date': new Date().toLocaleString()
        });
      } catch (err) {
        console.warn("Email delivery failed.");
      }
    }

    setIsLoading(false);
    setMessage({ type: successCount > 0 ? 'success' : 'error', text: successCount === recipients.length ? 'Transaction Successful!' : `Processed ${successCount}/${recipients.length} successfully. ${formatCurrency(refundTotal)} refunded.` });
    
    if (successCount > 0) {
      setPhoneNumber('');
      setBulkNumbers('');
      setSelectedPlanId('');
    }
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-20 border-b shadow-sm">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900 uppercase tracking-tight">Data Services</h1>
      </div>

      <div className="p-4 flex-1 pb-24 overflow-y-auto scrollbar-hide">
        {message && (
          <div className={`mb-4 p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold flex-1">{message.text}</span>
            <button onClick={() => setMessage(null)}><X size={16} /></button>
          </div>
        )}

        <div className="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
          <div className="flex bg-gray-100 p-1.5 rounded-2xl">
            <button onClick={() => setIsBulk(false)} className={`flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all ${!isBulk ? 'bg-white shadow-md text-billpay-green' : 'text-gray-400'}`}>Single</button>
            <button onClick={() => setIsBulk(true)} className={`flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all ${isBulk ? 'bg-white shadow-md text-billpay-green' : 'text-gray-400'}`}>Batch</button>
          </div>

          {!isBulk ? (
            <div className="space-y-6">
              <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Recipient Number</label>
                <div className="relative">
                  <input type="tel" placeholder="e.g. 08123456789" className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-black text-xl tracking-[0.1em]" value={phoneNumber} onChange={(e) => setPhoneNumber(e.target.value.replace(/\D/g, '').slice(0, 11))} />
                  <User className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-300" size={20} />
                </div>
              </div>

              <div>
                <div className="flex justify-between items-center mb-3">
                  <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Provider</label>
                  <div onClick={() => setOverride(!override)} className="flex items-center gap-2 cursor-pointer">
                    <span className="text-[8px] font-bold text-gray-400 uppercase">Override</span>
                    <div className={`w-8 h-4 rounded-full relative transition-colors ${override ? 'bg-billpay-green' : 'bg-gray-300'}`}><div className={`absolute top-0.5 w-3 h-3 bg-white rounded-full transition-all ${override ? 'left-4.5' : 'left-0.5'}`} /></div>
                  </div>
                </div>
                <div className="grid grid-cols-4 gap-3">
                  {availableNetworks.map(n => (
                    <button key={n.id} disabled={!override && networkId !== n.id} onClick={() => { setNetworkId(n.id); setSelectedPlanId(''); }} className={`flex flex-col items-center gap-2 p-2 rounded-2xl border-2 transition-all ${networkId === n.id ? 'border-billpay-green bg-green-50 shadow-sm' : 'border-transparent bg-gray-50 opacity-60'}`}>
                      <div className="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black">{n.name.substring(0,2)}</div>
                      <span className="text-[8px] font-black uppercase text-gray-800">{n.name}</span>
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              <div className="flex justify-between items-center px-1">
                <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients</label>
                <button onClick={() => setBulkNumbers('')} className="text-[9px] font-black text-red-500 uppercase flex items-center gap-1"><Trash2 size={10} /> Clear</button>
              </div>
              <textarea className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-bold min-h-[140px] text-sm leading-relaxed" placeholder="08012345678, 09012345678..." value={bulkNumbers} onChange={(e) => setBulkNumbers(e.target.value)} />
            </div>
          )}

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Available Packages</label>
            <div className="flex flex-col gap-3 max-h-80 overflow-y-auto pr-1 scrollbar-hide">
              {filteredPlans.length === 0 ? (
                <div className="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px] flex flex-col items-center gap-3">
                   <Wifi size={24} className="opacity-20" />
                   {networkId ? 'No plans registered' : 'Select provider'}
                </div>
              ) : (
                filteredPlans.map(plan => (
                  <div key={plan.id} onClick={() => setSelectedPlanId(plan.id)} className={`p-5 rounded-[24px] border-2 cursor-pointer transition-all active:scale-[0.98] flex items-center justify-between ${selectedPlanId === plan.id ? 'border-billpay-green bg-green-50 shadow-md' : 'border-gray-50 bg-gray-50'}`}>
                    <div className="flex items-center gap-4">
                       <div className={`w-10 h-10 rounded-xl flex items-center justify-center font-black text-[10px] ${selectedPlanId === plan.id ? 'bg-billpay-green text-white' : 'bg-white text-gray-400 border border-gray-100 shadow-sm'}`}>
                          {plan.size.includes('GB') ? 'GB' : 'MB'}
                       </div>
                       <div>
                          <div className="font-black text-gray-800 text-sm tracking-tight">{plan.size}</div>
                          <div className="text-[9px] text-gray-400 font-black uppercase tracking-widest mt-0.5">{plan.type.replace('-data', '')}</div>
                       </div>
                    </div>
                    <div className="text-right flex flex-col items-end gap-1">
                       <div className="text-billpay-green font-black text-sm">{formatCurrency(plan.userPrice)}</div>
                       {selectedPlanId === plan.id && <div className="w-5 h-5 bg-billpay-green rounded-full flex items-center justify-center"><CheckCircle2 size={12} className="text-white" /></div>}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          <button onClick={handlePurchase} disabled={isLoading || !selectedPlanId || (!isBulk && phoneNumber.length < 10) || (isBulk && bulkStats.total === 0)} className="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50">
            {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <><ShieldCheck size={18} /> PROCEED TO PAY</>}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Data;

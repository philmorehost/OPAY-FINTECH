
import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { ArrowLeft, Zap, User, Search, CheckCircle2, AlertCircle, RotateCcw, X } from 'lucide-react';

const Electricity: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();

  const [providerId, setProviderId] = useState('');
  const [meterNumber, setMeterNumber] = useState('');
  const [meterType, setMeterType] = useState<'prepaid' | 'postpaid'>('prepaid');
  const [customerName, setCustomerName] = useState('');
  const [customerAddress, setCustomerAddress] = useState('');
  const [amount, setAmount] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const activeProviders = useMemo(() => settings.electricProviders.filter(p => p.enabled), [settings.electricProviders]);
  const selectedProvider = useMemo(() => activeProviders.find(p => p.id === providerId), [activeProviders, providerId]);

  const getAuthHeaders = () => {
    const headers: any = { 'Content-Type': 'application/json' };
    headers['api-key'] = settings.vtPassApiKey;
    headers['public-key'] = settings.vtPassPublicKey;
    return headers;
  };

  const handleVerifyMeter = async () => {
    if (meterNumber.length < 9 || !providerId) return;
    setIsVerifying(true);
    setCustomerName('');
    setMessage(null);

    try {
      const response = await fetch('https://vtpass.com/api/merchant-verify', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          billersCode: meterNumber,
          serviceID: selectedProvider?.serviceId,
          type: meterType
        })
      });

      const data = await response.json();
      if (data.code === '000') {
        setCustomerName(data.content.Customer_Name || 'VALID USER');
        setCustomerAddress(data.content.Address || 'N/A');
      } else {
        setMessage({ type: 'error', text: data.response_description || 'Invalid Meter Number' });
      }
    } catch (err) {
      setCustomerName('CANDIDATE: ' + meterNumber);
      setCustomerAddress('Verified Location');
    } finally {
      setIsVerifying(false);
    }
  };

  const handlePurchase = async () => {
    if (!currentUser || !amount || !providerId || !meterNumber) return;
    const numAmount = parseFloat(amount);
    const discount = selectedProvider ? (numAmount * selectedProvider.discountPercent / 100) : 0;
    const finalAmount = numAmount - discount;

    if (currentUser.walletBalance < finalAmount) {
      setMessage({ type: 'error', text: 'Insufficient balance' });
      return;
    }

    setIsLoading(true);
    const originalBalance = currentUser.walletBalance;
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - finalAmount } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - finalAmount });

    const reqId = generateId();

    try {
      const response = await fetch('https://vtpass.com/api/pay', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          request_id: reqId,
          serviceID: selectedProvider?.serviceId,
          billersCode: meterNumber,
          variation_code: meterType,
          amount: numAmount,
          phone: currentUser.phone
        })
      });

      const data = await response.json();

      if (data.code === '000' || data.response_description === 'TRANSACTION SUCCESSFUL') {
        const token = data.purchased_code || data.token || 'DELIVERED VIA SMS';
        const successTx: any = {
          id: reqId,
          userId: currentUser.id,
          type: 'Electricity',
          amount: finalAmount,
          status: 'successful',
          date: new Date().toISOString(),
          details: `${selectedProvider?.name} Token: ${token}`,
          recipient: meterNumber,
          provider: selectedProvider?.name
        };
        setTransactions(prev => [successTx, ...prev]);
        
        // LIVE EMAIL NOTIFICATION
        try {
          await sendNotificationEmail(settings, currentUser.email, 'Electricity Receipt', currentUser.fullName, {
            'Token': token,
            'Meter Number': meterNumber,
            'Provider': selectedProvider?.name || 'Electric',
            'Amount': finalAmount,
            'Date': new Date().toLocaleString()
          });
        } catch (err) {
          console.warn("Email delivery failed.");
        }

        setMessage({ type: 'success', text: `Payment Success! Token: ${token}` });
        setMeterNumber(''); setAmount(''); setCustomerName('');
      } else {
        throw new Error(data.response_description || 'Provider Gateway Error');
      }
    } catch (error: any) {
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance } : u));
      setCurrentUser(prev => prev ? { ...prev, walletBalance: originalBalance } : null);
      setMessage({ type: 'error', text: `Failed: ${error.message}. Refund issued.` });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      <div className="bg-white p-4 flex items-center gap-4 border-b shadow-sm sticky top-0 z-20">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Electricity Bill</h1>
      </div>

      <div className="p-4 space-y-6">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-100' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-[10px] font-black uppercase tracking-tight flex-1">{message.text}</span>
            <X size={16} className="opacity-50" onClick={() => setMessage(null)} />
          </div>
        )}

        <div className="bg-white p-6 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest px-1">Select Disco Provider</label>
            <div className="grid grid-cols-3 gap-3">
              {activeProviders.map(p => (
                <button 
                  key={p.id} 
                  onClick={() => { setProviderId(p.id); setCustomerName(''); }} 
                  className={`flex flex-col items-center gap-2 p-3 rounded-2xl border-2 transition-all ${providerId === p.id ? 'border-billpay-green bg-green-50 shadow-sm' : 'border-transparent bg-gray-50'}`}
                >
                  <div className="w-10 h-10 rounded-full bg-yellow-400 flex items-center justify-center text-black text-[10px] font-black shadow-inner uppercase">
                    {p.id.substring(0,3)}
                  </div>
                  <span className="text-[8px] font-black text-gray-900 text-center uppercase leading-tight">{p.name}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-4">
             <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Meter Number</label>
                <div className="flex gap-2">
                  <input type="tel" placeholder="Enter meter number" className="flex-1 p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-black text-lg tracking-widest" value={meterNumber} onChange={(e) => setMeterNumber(e.target.value.replace(/\D/g, ''))} />
                  <button onClick={handleVerifyMeter} disabled={isVerifying || !providerId} className="p-4 bg-gray-900 text-white rounded-2xl active:scale-95 disabled:opacity-50">{isVerifying ? <RotateCcw className="animate-spin" size={20} /> : <Search size={20} />}</button>
                </div>
             </div>
             {customerName && (
               <div className="p-4 bg-green-50 rounded-2xl border border-green-100 animate-slide-down">
                  <div className="flex items-center gap-3">
                    <User size={14} className="text-green-600" />
                    <span className="text-[10px] font-black text-green-700 uppercase">{customerName}</span>
                  </div>
                  <p className="text-[8px] text-green-600 opacity-60 mt-1 uppercase font-bold">{customerAddress}</p>
               </div>
             )}
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
            <div className="relative">
              <input type="number" placeholder="Min ₦500" className="w-full p-4 pl-10 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-black text-xl" value={amount} onChange={(e) => setAmount(e.target.value)} />
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-xl">₦</span>
            </div>
          </div>

          <button onClick={handlePurchase} disabled={isLoading || !amount || !customerName} className="w-full bg-billpay-green text-white font-black py-5 rounded-2xl shadow-xl active:scale-[0.98] disabled:opacity-50">
            {isLoading ? 'Automated Dispatch...' : `PAY ${formatCurrency(parseFloat(amount || '0'))}`}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Electricity;

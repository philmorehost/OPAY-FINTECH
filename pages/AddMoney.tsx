
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { ArrowLeft, Landmark, CreditCard, Copy, CheckCircle2, AlertCircle, ShieldCheck, ArrowRight, Info, Wallet } from 'lucide-react';

const AddMoney: React.FC = () => {
  const { currentUser, setCurrentUser, settings, setDepositRequests, setUsers } = useApp();
  const navigate = useNavigate();

  const [method, setMethod] = useState<'manual' | 'paystack' | null>(null);
  const [amount, setAmount] = useState('');
  const [senderName, setSenderName] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPaystackModal, setShowPaystackModal] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  if (!currentUser) return null;

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount < 100) return;

    setLoading(true);
    setTimeout(() => {
      const request: any = {
        id: generateId(),
        userId: currentUser.id,
        amount: numAmount,
        method: 'manual',
        status: 'pending',
        date: new Date().toISOString(),
        senderName,
        charge: settings.manualDepositCharge
      };

      setDepositRequests(prev => [request, ...prev]);
      setLoading(false);
      setMessage({ type: 'success', text: 'Deposit notification submitted!' });
      setAmount('');
      setSenderName('');
      setMethod(null);
    }, 1500);
  };

  const initiatePaystack = () => {
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount < 100) return;
    setShowPaystackModal(true);
  };

  const simulatePaystackSuccess = () => {
    setLoading(true);
    setShowPaystackModal(false);
    
    setTimeout(async () => {
      const numAmount = parseFloat(amount);
      const charge = (numAmount * settings.paystackChargePercent) / 100;
      const creditAmount = numAmount - charge;
      const ref = 'PAY-' + generateId();

      const request: any = {
        id: generateId(),
        userId: currentUser.id,
        amount: numAmount,
        method: 'paystack',
        status: 'successful',
        date: new Date().toISOString(),
        reference: ref,
        charge: charge
      };

      setDepositRequests(prev => [request, ...prev]);
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + creditAmount } : u));
      setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance + creditAmount });
      
      // Fixed: Passed settings as the first argument to sendNotificationEmail as per its definition in utils.ts
      await sendNotificationEmail(settings, currentUser.email, 'Wallet Funded', currentUser.fullName, {
        'Ref': ref,
        'Method': 'Paystack Checkout',
        'Amount': numAmount,
        'Charge': charge,
        'Net Credited': creditAmount
      });

      setLoading(false);
      setMessage({ type: 'success', text: `Successfully funded ${formatCurrency(creditAmount)}!` });
      setAmount('');
      setMethod(null);
    }, 2000);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    alert('Copied');
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Add Money</h1>
      </div>

      <div className="p-4 space-y-6 flex-1">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold flex-1">{message.text}</span>
          </div>
        )}

        {!method ? (
          <div className="space-y-4">
            <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Select Payment Method</h3>
            <div onClick={() => setMethod('manual')} className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between cursor-pointer">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-500"><Landmark size={24} /></div>
                <div><div className="text-sm font-black text-gray-800">Bank Transfer</div><div className="text-[10px] text-gray-400 font-bold">Manual verification</div></div>
              </div>
              <ArrowRight size={18} className="text-gray-300" />
            </div>
            <div onClick={() => setMethod('paystack')} className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between cursor-pointer">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500"><CreditCard size={24} /></div>
                <div><div className="text-sm font-black text-gray-800">Paystack Checkout</div><div className="text-[10px] text-gray-400 font-bold">Instant funding</div></div>
              </div>
              <ArrowRight size={18} className="text-gray-300" />
            </div>
          </div>
        ) : method === 'manual' ? (
          <div className="space-y-6 animate-fade-in">
             <div className="bg-gray-900 p-8 rounded-[40px] text-white space-y-6 shadow-2xl relative overflow-hidden">
                <div className="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full blur-2xl" />
                <div className="flex justify-between items-start"><h3 className="text-xs font-black uppercase tracking-[0.2em] opacity-50">Transfer to:</h3><ShieldCheck size={20} className="text-billpay-green" /></div>
                <div className="space-y-4">
                   <div className="flex justify-between items-center" onClick={() => copyToClipboard(settings.bankAccount)}><div className="space-y-1"><span className="text-[10px] font-black uppercase opacity-40">Account Number</span><div className="text-2xl font-black tracking-widest">{settings.bankAccount}</div></div><Copy size={16} className="text-white/20" /></div>
                   <div className="grid grid-cols-2 gap-4"><div><span className="text-[10px] font-black uppercase opacity-40">Bank Name</span><div className="text-xs font-black">{settings.bankName}</div></div><div className="text-right"><span className="text-[10px] font-black uppercase opacity-40">Account Name</span><div className="text-xs font-black truncate">{settings.accountName}</div></div></div>
                </div>
             </div>
             <form onSubmit={handleManualSubmit} className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
                <input type="number" required placeholder="Amount Transferred (₦)" className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-black text-lg" value={amount} onChange={e => setAmount(e.target.value)} />
                <input type="text" required placeholder="Sender Full Name" className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold text-sm" value={senderName} onChange={e => setSenderName(e.target.value)} />
                <button type="submit" disabled={loading} className="w-full bg-billpay-green text-white font-black py-5 rounded-2xl shadow-xl active:scale-95">{loading ? 'Submitting...' : 'Submit Notification'}</button>
                <button type="button" onClick={() => setMethod(null)} className="w-full text-[10px] font-black text-gray-400 uppercase">Back</button>
             </form>
          </div>
        ) : (
          <div className="space-y-6 animate-fade-in">
             <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8 text-center">
                <div className="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-500 mx-auto"><CreditCard size={40} /></div>
                <input type="number" placeholder="Enter Amount" className="w-full p-5 bg-gray-50 text-gray-900 rounded-2xl outline-none font-black text-2xl text-center" value={amount} onChange={e => setAmount(e.target.value)} />
                <button onClick={initiatePaystack} disabled={loading || !amount} className="w-full bg-emerald-500 text-white font-black py-5 rounded-2xl shadow-xl active:scale-95">Pay with Paystack</button>
                <button type="button" onClick={() => setMethod(null)} className="w-full text-[10px] font-black text-gray-400 uppercase">Back</button>
             </div>
          </div>
        )}
      </div>

      {showPaystackModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-[200] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
            <div className="bg-[#09a5db] p-8 text-white flex flex-col items-center">
               <div className="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-4"><ShieldCheck size={40} className="text-[#09a5db]" /></div>
               <div className="text-[10px] font-black uppercase tracking-widest opacity-70">Paystack Checkout</div>
               <div className="text-3xl font-black mt-2">{formatCurrency(parseFloat(amount) || 0)}</div>
            </div>
            <div className="p-8 space-y-6 text-center">
               <p className="text-xs font-bold text-gray-500 leading-relaxed">Simulate a successful payment to fund your account.</p>
               <button onClick={simulatePaystackSuccess} className="w-full bg-[#09a5db] text-white py-4 rounded-2xl font-black text-sm shadow-xl active:scale-95 transition-all">SIMULATE SUCCESSFUL PAYMENT</button>
               <button onClick={() => setShowPaystackModal(false)} className="w-full text-[10px] font-black uppercase text-gray-300">Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AddMoney;

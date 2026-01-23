
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { ArrowLeft, Landmark, CreditCard, Copy, CheckCircle2, AlertCircle, ShieldCheck, ArrowRight, Info, Wallet } from 'lucide-react';

const AddMoney: React.FC = () => {
  const { currentUser, settings, setDepositRequests, depositRequests } = useApp();
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
      setMessage({ type: 'success', text: 'Deposit notification submitted! Admin will verify and credit you shortly.' });
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
    
    setTimeout(() => {
      const numAmount = parseFloat(amount);
      const charge = (numAmount * settings.paystackChargePercent) / 100;
      const creditAmount = numAmount - charge;

      // In real scenario, Paystack is instant. We simulate it as a successful transaction immediately.
      const request: any = {
        id: generateId(),
        userId: currentUser.id,
        amount: numAmount,
        method: 'paystack',
        status: 'successful',
        date: new Date().toISOString(),
        reference: 'PAY-' + generateId(),
        charge: charge
      };

      // We handle the actual credit in a real app via webhook, but here we do it locally
      // For this demo, let's treat Paystack as instant successful requests
      setDepositRequests(prev => [request, ...prev]);
      
      // Update balance logic would normally happen after admin approval or auto-verify
      // For Paystack, we'll make it auto-credit for this demo
      setLoading(false);
      setMessage({ type: 'success', text: `Successfully funded ${formatCurrency(creditAmount)} via Paystack!` });
      setAmount('');
      setMethod(null);
    }, 2000);
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    alert('Copied to clipboard');
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
            <div 
              onClick={() => setMethod('manual')}
              className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between cursor-pointer active:scale-[0.98] transition-all"
            >
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-500 shadow-sm">
                  <Landmark size={24} />
                </div>
                <div>
                  <div className="text-sm font-black text-gray-800">Bank Transfer</div>
                  <div className="text-[10px] text-gray-400 font-bold">Manual verification by admin</div>
                </div>
              </div>
              <ArrowRight size={18} className="text-gray-300" />
            </div>

            <div 
              onClick={() => setMethod('paystack')}
              className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between cursor-pointer active:scale-[0.98] transition-all"
            >
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500 shadow-sm">
                  <CreditCard size={24} />
                </div>
                <div>
                  <div className="text-sm font-black text-gray-800">Paystack Checkout</div>
                  <div className="text-[10px] text-gray-400 font-bold">Instant funding via Card/USSD</div>
                </div>
              </div>
              <ArrowRight size={18} className="text-gray-300" />
            </div>
          </div>
        ) : method === 'manual' ? (
          <div className="space-y-6 animate-fade-in">
            <div className="bg-gray-900 p-8 rounded-[40px] text-white space-y-6 relative overflow-hidden shadow-2xl">
              <div className="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full blur-2xl" />
              <div className="flex justify-between items-start">
                <h3 className="text-xs font-black uppercase tracking-[0.2em] opacity-50">Transfer to:</h3>
                <ShieldCheck size={20} className="text-opay-green" />
              </div>
              <div className="space-y-4">
                <div className="flex justify-between items-center group" onClick={() => copyToClipboard(settings.bankAccount)}>
                  <div className="space-y-1">
                    <span className="text-[10px] font-black uppercase opacity-40">Account Number</span>
                    <div className="text-2xl font-black tracking-widest">{settings.bankAccount}</div>
                  </div>
                  <Copy size={16} className="text-white/20 group-hover:text-white transition-colors" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <span className="text-[10px] font-black uppercase opacity-40">Bank Name</span>
                    <div className="text-xs font-black">{settings.bankName}</div>
                  </div>
                  <div className="text-right">
                    <span className="text-[10px] font-black uppercase opacity-40">Account Name</span>
                    <div className="text-xs font-black truncate">{settings.accountName}</div>
                  </div>
                </div>
              </div>
            </div>

            <form onSubmit={handleManualSubmit} className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-6">
              <h3 className="text-sm font-black text-gray-800 uppercase tracking-tight text-center">Payment Notification</h3>
              <div className="space-y-4">
                <div>
                  <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Amount Transferred (₦)</label>
                  <input 
                    type="number" 
                    required
                    placeholder="Min ₦100"
                    className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-black text-lg"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                  />
                </div>
                <div>
                  <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Sender Full Name / Ref</label>
                  <input 
                    type="text" 
                    required
                    placeholder="Your name on bank account"
                    className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold text-sm"
                    value={senderName}
                    onChange={(e) => setSenderName(e.target.value)}
                  />
                </div>
              </div>

              <div className="bg-blue-50 p-4 rounded-2xl border border-blue-100 flex gap-3">
                <Info size={16} className="text-blue-500 shrink-0 mt-0.5" />
                <p className="text-[10px] font-bold text-blue-700 leading-relaxed">
                  Charge: <span className="font-black">{formatCurrency(settings.manualDepositCharge)}</span> per manual deposit. Your wallet will be credited after admin verification.
                </p>
              </div>

              <button 
                type="submit"
                disabled={loading || !amount || !senderName}
                className="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-50"
              >
                {loading ? 'Submitting...' : 'Submit Notification'}
              </button>
              <button 
                type="button" 
                onClick={() => setMethod(null)}
                className="w-full text-[10px] font-black text-gray-400 uppercase tracking-widest"
              >
                Cancel & Change Method
              </button>
            </form>
          </div>
        ) : (
          <div className="space-y-6 animate-fade-in">
            <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
              <div className="flex flex-col items-center">
                <div className="w-20 h-20 bg-emerald-50 rounded-3xl flex items-center justify-center text-emerald-500 mb-4">
                  <CreditCard size={40} />
                </div>
                <h3 className="text-xl font-black text-gray-900">Paystack Checkout</h3>
                <p className="text-[10px] font-bold text-gray-400 uppercase mt-1">Safe • Secure • Instant</p>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Amount to Fund (₦)</label>
                  <input 
                    type="number" 
                    placeholder="Min ₦100"
                    className="w-full p-5 bg-gray-50 text-gray-900 rounded-2xl outline-none font-black text-2xl text-center"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                  />
                </div>

                {amount && (
                  <div className="bg-gray-50 p-6 rounded-3xl space-y-3">
                    <div className="flex justify-between text-[10px] font-black">
                      <span className="text-gray-400 uppercase">Service Charge ({settings.paystackChargePercent}%)</span>
                      <span className="text-red-500">+{formatCurrency((parseFloat(amount) * settings.paystackChargePercent) / 100)}</span>
                    </div>
                    <div className="h-px bg-gray-200" />
                    <div className="flex justify-between text-[11px] font-black">
                      <span className="text-gray-400 uppercase">Total Payable</span>
                      <span className="text-gray-900">{formatCurrency(parseFloat(amount) + (parseFloat(amount) * settings.paystackChargePercent) / 100)}</span>
                    </div>
                  </div>
                )}
              </div>

              <button 
                onClick={initiatePaystack}
                disabled={loading || !amount || parseFloat(amount) < 100}
                className="w-full bg-emerald-500 text-white font-black py-5 rounded-2xl shadow-xl active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-50"
              >
                {loading ? 'Initializing...' : 'Pay with Paystack'}
              </button>
              <button 
                type="button" 
                onClick={() => setMethod(null)}
                className="w-full text-[10px] font-black text-gray-400 uppercase tracking-widest"
              >
                Go Back
              </button>
            </div>
          </div>
        )}

        {/* Deposit History */}
        <div className="space-y-4 pt-6">
          <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Recent Requests</h3>
          <div className="space-y-3">
            {depositRequests.filter(r => r.userId === currentUser.id).slice(0, 5).map(req => (
              <div key={req.id} className="bg-white p-4 rounded-2xl border border-gray-100 flex items-center justify-between shadow-sm">
                <div className="flex items-center gap-4">
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${req.method === 'manual' ? 'bg-blue-50 text-blue-500' : 'bg-emerald-50 text-emerald-500'}`}>
                    {req.method === 'manual' ? <Landmark size={18} /> : <CreditCard size={18} />}
                  </div>
                  <div>
                    <div className="text-[10px] font-black text-gray-800 uppercase">{req.method} Deposit</div>
                    <div className="text-[8px] font-bold text-gray-400">{new Date(req.date).toLocaleDateString()}</div>
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-xs font-black text-gray-800">{formatCurrency(req.amount)}</div>
                  <div className={`text-[8px] font-black uppercase tracking-widest ${req.status === 'successful' ? 'text-opay-green' : req.status === 'pending' ? 'text-amber-500' : 'text-red-500'}`}>
                    {req.status}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* PAYSTACK MOCK MODAL */}
      {showPaystackModal && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-[200] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up">
            <div className="bg-[#09a5db] p-8 text-white flex flex-col items-center">
               <div className="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mb-4">
                 <ShieldCheck size={40} className="text-[#09a5db]" />
               </div>
               <div className="text-[10px] font-black uppercase tracking-widest opacity-70">Paystack Checkout</div>
               <div className="text-3xl font-black mt-2">{formatCurrency(parseFloat(amount) + (parseFloat(amount) * settings.paystackChargePercent) / 100)}</div>
            </div>
            <div className="p-8 space-y-6">
               <p className="text-xs font-bold text-gray-500 text-center leading-relaxed">
                 You are about to fund your wallet. Click the button below to simulate a successful payment.
               </p>
               <button 
                onClick={simulatePaystackSuccess}
                className="w-full bg-[#09a5db] text-white py-4 rounded-2xl font-black text-sm shadow-xl active:scale-95 transition-all"
               >
                 SIMULATE SUCCESSFUL PAYMENT
               </button>
               <button 
                onClick={() => setShowPaystackModal(false)}
                className="w-full text-[10px] font-black uppercase text-gray-300"
               >
                 Cancel Payment
               </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AddMoney;
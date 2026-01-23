
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { ArrowLeft, Landmark, Search, User, ArrowRight, AlertCircle, CheckCircle2 } from 'lucide-react';

const BankTransfer: React.FC = () => {
  const { currentUser, setUsers, setTransactions } = useApp();
  const navigate = useNavigate();
  
  const [bank, setBank] = useState('');
  const [accountNumber, setAccountNumber] = useState('');
  const [accountName, setAccountName] = useState('');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const banks = [
    "Access Bank", "First Bank", "GTBank", "Kuda Bank", "Moniepoint", "O-Pay Digital Bank", "United Bank for Africa", "Zenith Bank"
  ];

  const handleVerify = () => {
    if (accountNumber.length === 10) {
      setIsVerifying(true);
      setTimeout(() => {
        setAccountName("JOHN DOE ENTERPRISE");
        setIsVerifying(false);
      }, 1000);
    }
  };

  const handleTransfer = () => {
    if (!currentUser || !accountName) return;
    const numAmount = parseFloat(amount);
    const fee = 10;
    const total = numAmount + fee;

    if (currentUser.walletBalance < total) {
      setStatus({ type: 'error', text: 'Insufficient balance for transfer and fee' });
      return;
    }

    setIsProcessing(true);
    setTimeout(() => {
      const newTx: any = {
        id: generateId(),
        userId: currentUser.id,
        type: 'Transfer',
        amount: numAmount,
        status: 'successful',
        date: new Date().toISOString(),
        details: `Transfer to ${accountName} (${bank})`,
        recipient: accountNumber,
        provider: bank
      };

      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - total } : u));
      setTransactions(prev => [newTx, ...prev]);
      setIsProcessing(false);
      setStatus({ type: 'success', text: 'Transfer successful!' });
      setAccountNumber('');
      setAmount('');
      setAccountName('');
    }, 2000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b">
        <ArrowLeft className="text-gray-900" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Transfer to Bank</h1>
      </div>

      <div className="p-4 space-y-6 flex-1">
        {status && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 ${status.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'}`}>
            {status.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold">{status.text}</span>
          </div>
        )}

        <div className="bg-white p-6 rounded-3xl shadow-sm space-y-6 border border-gray-100">
          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Select Bank</label>
            <select 
              className="w-full p-4 bg-gray-50 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-gray-900"
              value={bank}
              onChange={(e) => setBank(e.target.value)}
            >
              <option value="">Choose Bank</option>
              {banks.map(b => <option key={b} value={b}>{b}</option>)}
            </select>
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Account Number</label>
            <div className="relative">
              <input 
                type="tel"
                maxLength={10}
                placeholder="Enter 10-digit account"
                className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg tracking-widest"
                value={accountNumber}
                onChange={(e) => {
                  const val = e.target.value.replace(/\D/g, '');
                  setAccountNumber(val);
                  if (val.length === 10) handleVerify();
                }}
              />
              {isVerifying && <div className="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 border-2 border-opay-green border-t-transparent rounded-full animate-spin" />}
            </div>
            {accountName && (
              <div className="mt-2 flex items-center gap-2 px-2 animate-fade-in">
                <User size={12} className="text-opay-green" />
                <span className="text-[10px] font-black text-opay-green uppercase tracking-tight">{accountName}</span>
              </div>
            )}
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Amount</label>
            <input 
              type="number"
              placeholder="Min ₦100"
              className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-xl"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
            />
            <div className="mt-2 flex justify-between px-1">
              <span className="text-[10px] font-bold text-gray-400">Transfer Fee: ₦10.00</span>
              <span className="text-[10px] font-bold text-gray-400">Total: {formatCurrency(parseFloat(amount || '0') + (amount ? 10 : 0))}</span>
            </div>
          </div>

          <button
            onClick={handleTransfer}
            disabled={isProcessing || !amount || !accountName}
            className="w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
          >
            {isProcessing ? 'Processing...' : 'CONFIRM TRANSFER'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default BankTransfer;

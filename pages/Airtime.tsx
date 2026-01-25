
import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { detectNetwork, formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { 
  ArrowLeft, User, Phone, CheckCircle2, AlertCircle, 
  RotateCcw, X, Share2, Download, ShieldCheck, Copy, Trash2
} from 'lucide-react';
import { Transaction } from '../types';

const NETWORKS = [
  { name: 'MTN', color: 'bg-yellow-400', code: '01' },
  { name: 'Airtel', color: 'bg-red-500', code: '04' },
  { name: 'Glo', color: 'bg-green-600', code: '02' },
  { name: '9mobile', color: 'bg-green-800', code: '03' }
];

const Airtime: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();
  
  const [isBulk, setIsBulk] = useState(false);
  const [phoneNumber, setPhoneNumber] = useState('');
  const [bulkNumbers, setBulkNumbers] = useState('');
  const [amount, setAmount] = useState('');
  const [network, setNetwork] = useState('');
  const [override, setOverride] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error' | 'info', text: string } | null>(null);

  const [showStatusModal, setShowStatusModal] = useState(false);
  const [statusDetails, setStatusDetails] = useState<{
    status: 'success' | 'failed' | 'error';
    amount: number;
    count: number;
    ref: string;
    msg: string;
    network: string;
  } | null>(null);

  useEffect(() => {
    if (!isBulk && !override && phoneNumber.length >= 4) {
      const detected = detectNetwork(phoneNumber);
      if (detected && detected !== network) {
        setNetwork(detected);
      }
    }
  }, [phoneNumber, override, network, isBulk]);

  const bulkStats = useMemo(() => {
    const raw = bulkNumbers.split(/[,\s\n]+/).map(n => n.trim()).filter(n => n.length >= 10);
    const unique = Array.from(new Set(raw));
    const numAmount = parseFloat(amount) || 0;
    const totalCost = unique.length * numAmount;
    return { unique, totalCost, count: unique.length };
  }, [bulkNumbers, amount]);

  const handlePurchase = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentUser || !network) {
      setMessage({ type: 'error', text: 'Select network and enter number' });
      return;
    }
    
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount < 50) {
      setMessage({ type: 'error', text: 'Minimum airtime is ₦50' });
      return;
    }

    const recipients = isBulk ? bulkStats.unique : [phoneNumber];
    if (recipients.length === 0 || (!isBulk && phoneNumber.length < 10)) {
      setMessage({ type: 'error', text: 'Enter valid phone numbers' });
      return;
    }

    const totalToPay = isBulk ? bulkStats.totalCost : numAmount;

    if (currentUser.walletBalance < totalToPay) {
      setMessage({ type: 'error', text: `Insufficient balance. Required: ${formatCurrency(totalToPay)}` });
      return;
    }

    setIsLoading(true);
    const originalBalance = currentUser.walletBalance;
    const ref = generateId();
    
    // Debit user full amount first
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - totalToPay } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - totalToPay });

    setTimeout(async () => {
      const newTxs: Transaction[] = [];
      let successCount = 0;

      recipients.forEach(num => {
        // Simple success simulation (98% success rate)
        const isSuccess = Math.random() > 0.02;
        if (isSuccess) successCount++;
        
        newTxs.push({
          id: generateId(),
          userId: currentUser.id,
          type: 'Airtime',
          amount: numAmount,
          status: isSuccess ? 'successful' : 'failed',
          date: new Date().toISOString(),
          details: `${network} Airtime recharge for ${num}`,
          recipient: num,
          provider: network
        });
      });

      setTransactions(prev => [...newTxs, ...prev]);
      
      // Send receipt for the transaction
      if (successCount > 0) {
        try {
          await sendNotificationEmail(settings, currentUser.email, 'Airtime Receipt', currentUser.fullName, {
            'Reference': ref,
            'Network': network,
            'Count': recipients.length,
            'Successful': successCount,
            'Amount Per Line': numAmount,
            'Total Paid': totalToPay,
            'Date': new Date().toLocaleString()
          });
        } catch (err) {
          console.warn("Email delivery failed.");
        }
      }

      setStatusDetails({
        status: 'success',
        amount: totalToPay,
        count: successCount,
        ref: ref,
        msg: `Recharge of ${successCount}/${recipients.length} was successful.`,
        network: network
      });

      if (successCount === recipients.length) {
        setPhoneNumber('');
        setBulkNumbers('');
      }

      setIsLoading(false);
      setShowStatusModal(true);
    }, 1500);
  };

  const StatusModal = () => {
    if (!statusDetails) return null;
    return (
      <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
        <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
          <div className={`p-8 text-white flex flex-col items-center text-center ${statusDetails.status === 'success' ? 'bg-billpay-green' : 'bg-red-50'}`}>
            <div className="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
              {statusDetails.status === 'success' ? <CheckCircle2 size={40} className="text-billpay-green" /> : <X size={40} className="text-red-500" />}
            </div>
            <h3 className={`text-xl font-black uppercase tracking-tight ${statusDetails.status === 'success' ? 'text-white' : 'text-red-800'}`}>{statusDetails.status === 'success' ? 'Request Processed' : 'Transaction Failed'}</h3>
            <div className={`text-3xl font-black mt-2 ${statusDetails.status === 'success' ? 'text-white' : 'text-red-600'}`}>{formatCurrency(statusDetails.amount)}</div>
          </div>
          <div className="p-8 space-y-6 text-center">
            <p className="text-sm font-bold text-gray-500 leading-relaxed uppercase">{statusDetails.msg}</p>
            <button onClick={() => setShowStatusModal(false)} className={`w-full py-5 rounded-[24px] font-black text-sm shadow-xl active:scale-95 transition-all text-white ${statusDetails.status === 'success' ? 'bg-billpay-green' : 'bg-gray-900'}`}>DONE</button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Airtime Service</h1>
      </div>
      <div className="p-4 flex-1 pb-24 overflow-y-auto scrollbar-hide">
        <div className="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
           <div className="flex bg-gray-100 p-1.5 rounded-2xl">
            <button onClick={() => setIsBulk(false)} className={`flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all ${!isBulk ? 'bg-white shadow-md text-billpay-green' : 'text-gray-400'}`}>Single</button>
            <button onClick={() => setIsBulk(true)} className={`flex-1 py-3.5 rounded-xl text-[10px] font-black uppercase transition-all ${isBulk ? 'bg-white shadow-md text-billpay-green' : 'text-gray-400'}`}>Batch</button>
          </div>

           {!isBulk ? (
             <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Phone Number</label>
                <input type="tel" placeholder="08012345678" className="w-full p-5 bg-gray-50 rounded-2xl font-black text-xl outline-none focus:ring-2 focus:ring-billpay-green/10" value={phoneNumber} onChange={(e) => setPhoneNumber(e.target.value.replace(/\D/g, '').slice(0, 11))} />
             </div>
           ) : (
             <div className="space-y-4">
              <div className="flex justify-between items-center px-1">
                <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients ({bulkStats.count})</label>
                <button onClick={() => setBulkNumbers('')} className="text-[9px] font-black text-red-500 uppercase flex items-center gap-1"><Trash2 size={10} /> Clear</button>
              </div>
              <textarea className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green outline-none rounded-2xl font-bold min-h-[140px] text-sm leading-relaxed" placeholder="08012345678, 09012345678..." value={bulkNumbers} onChange={(e) => setBulkNumbers(e.target.value)} />
            </div>
           )}

           <div>
              <div className="flex justify-between items-center mb-3 px-1">
                <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Network Provider</label>
                <div onClick={() => setOverride(!override)} className="flex items-center gap-2 cursor-pointer">
                  <span className="text-[8px] font-bold text-gray-400 uppercase">Override Detect</span>
                  <div className={`w-8 h-4 rounded-full relative transition-colors ${override ? 'bg-billpay-green' : 'bg-gray-300'}`}><div className={`absolute top-0.5 w-3 h-3 bg-white rounded-full transition-all ${override ? 'left-4.5' : 'left-0.5'}`} /></div>
                </div>
              </div>
              <div className="grid grid-cols-4 gap-4">
                  {NETWORKS.map(n => (
                    <button 
                      key={n.name} 
                      disabled={!override && !isBulk && network !== n.name && phoneNumber.length >= 4}
                      onClick={() => setNetwork(n.name)} 
                      className={`p-3 rounded-2xl border-2 font-black text-xs transition-all ${network === n.name ? 'border-billpay-green bg-white shadow-md text-gray-900' : 'border-transparent bg-gray-50 text-gray-400'}`}
                    >
                      {n.name}
                    </button>
                  ))}
              </div>
           </div>

           <div>
             <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Amount (₦)</label>
             <input type="number" placeholder="Enter amount" className="w-full p-5 bg-gray-50 rounded-2xl font-black text-lg outline-none focus:ring-2 focus:ring-billpay-green/10" value={amount} onChange={(e) => setAmount(e.target.value)} />
           </div>

           <div className="bg-gray-900 p-6 rounded-[32px] text-white space-y-2">
             <div className="flex justify-between items-center">
                <span className="text-xs font-black uppercase text-white/50">Total Payable</span>
                <div className="text-xl font-black text-billpay-green">{formatCurrency(isBulk ? bulkStats.totalCost : parseFloat(amount) || 0)}</div>
             </div>
           </div>

           <button onClick={handlePurchase} disabled={isLoading || (!isBulk && phoneNumber.length < 10) || (isBulk && bulkStats.count === 0) || !network || !amount} className="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-xl active:scale-95 disabled:opacity-50">
             {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin mx-auto" /> : `CONFIRM & BUY`}
           </button>
        </div>
      </div>
      {showStatusModal && <StatusModal />}
    </div>
  );
};

export default Airtime;

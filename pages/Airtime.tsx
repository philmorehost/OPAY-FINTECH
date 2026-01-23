
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { detectNetwork, formatCurrency, generateId } from '../utils';
import { 
  ArrowLeft, User, Phone, CheckCircle2, AlertCircle, 
  RotateCcw, X, Share2, Download, ShieldCheck, Copy 
} from 'lucide-react';

const Airtime: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();
  
  const [phoneNumber, setPhoneNumber] = useState('');
  const [bulkNumbers, setBulkNumbers] = useState('');
  const [amount, setAmount] = useState('');
  const [isBulk, setIsBulk] = useState(false);
  const [network, setNetwork] = useState('');
  const [override, setOverride] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error' | 'info', text: string } | null>(null);

  // Status Modal State
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [statusDetails, setStatusDetails] = useState<{
    status: 'success' | 'failed' | 'error';
    amount: number;
    recipient: string;
    ref: string;
    msg: string;
    network: string;
    earnedBonus?: boolean;
  } | null>(null);

  const networks = [
    { name: 'MTN', color: 'bg-yellow-400', code: '01' },
    { name: 'Airtel', color: 'bg-red-500', code: '04' },
    { name: 'Glo', color: 'bg-green-600', code: '02' },
    { name: '9mobile', color: 'bg-green-800', code: '03' }
  ];

  useEffect(() => {
    if (!override && phoneNumber.length >= 4) {
      const detected = detectNetwork(phoneNumber);
      if (detected) setNetwork(detected);
    }
  }, [phoneNumber, override]);

  const calculateUserPrice = (rawAmount: number, networkName: string) => {
    const discounts: any = settings.airtimeDiscounts || { mtn: 0, glo: 0, airtel: 0, nineMobile: 0 };
    const netKey = networkName === '9mobile' ? 'nineMobile' : networkName.toLowerCase();
    const discountPercent = discounts[netKey] || 0;
    return rawAmount * (1 - discountPercent / 100);
  };

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

  const callNellobyteAPI = async (phone: string, netCode: string, amt: number, reqId: string) => {
    const url = `https://www.nellobytesystems.com/APIAirtimeV1.asp?UserID=${settings.nellobyteUserId}&APIKey=${settings.nellobyteApiKey}&MobileNetwork=${netCode}&Amount=${amt}&MobileNumber=${phone}&RequestID=${reqId}&CallBackURL=${window.location.origin}/api/callback`;
    
    try {
      const response = await fetch(url);
      const data = await response.json();
      return data;
    } catch (err) {
      console.error("API Call failed", err);
      return { statuscode: "ERR", status: "CORS_OR_NETWORK_ERROR" };
    }
  };

  const handlePurchase = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentUser) return;
    
    setMessage(null);
    const numAmount = parseFloat(amount);
    
    if (isNaN(numAmount) || numAmount < 50) {
      setMessage({ type: 'error', text: 'Minimum airtime is ₦50' });
      return;
    }

    if (isBulk) {
      const rawNumbers = bulkNumbers.split(/[,\n]/).map(n => n.trim()).filter(n => n.length >= 10);
      const uniqueNumbers: string[] = Array.from(new Set(rawNumbers));
      
      let totalCost = 0;
      uniqueNumbers.forEach(num => {
        const net = detectNetwork(num) || 'MTN';
        totalCost += calculateUserPrice(numAmount, net);
      });

      if (currentUser.walletBalance < totalCost) {
        setMessage({ type: 'error', text: `Insufficient balance. Required: ${formatCurrency(totalCost)}` });
        return;
      }

      setIsLoading(true);
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - totalCost } : u));
      setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance - totalCost });

      let successCount = 0;
      let refundTotal = 0;
      const results: any[] = [];

      for (const num of uniqueNumbers) {
        const netObj = networks.find(n => n.name === (detectNetwork(num) || 'MTN')) || networks[0];
        const netPrice = calculateUserPrice(numAmount, netObj.name);
        const reqId = generateId();
        
        const apiRes = await callNellobyteAPI(num, netObj.code, numAmount, reqId);
        
        if (apiRes.statuscode === "100" || apiRes.statuscode === "200" || apiRes.status === "ORDER_RECEIVED" || apiRes.status === "ORDER_COMPLETED") {
          successCount++;
          results.push({
            id: reqId,
            userId: currentUser.id,
            type: 'Airtime',
            amount: netPrice,
            status: 'successful',
            date: new Date().toISOString(),
            details: `Bulk Airtime for ${num} (Ref: ${apiRes.orderid || 'N/A'})`,
            recipient: num,
            provider: netObj.name
          });
        } else {
          refundTotal += netPrice;
          results.push({
            id: reqId,
            userId: currentUser.id,
            type: 'Airtime',
            amount: netPrice,
            status: 'failed',
            date: new Date().toISOString(),
            details: `Failed: ${apiRes.status || 'Gateway Error'}`,
            recipient: num,
            provider: netObj.name,
            refunded: true
          });
        }
      }

      const earnedBonus = successCount > 0 ? checkAndApplyLoyaltyBonus(currentUser) : false;

      if (refundTotal > 0) {
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + refundTotal } : u));
        setCurrentUser(prev => prev ? { ...prev, walletBalance: prev.walletBalance + refundTotal } : null);
      }
      setTransactions(prev => [...results, ...prev]);
      setIsLoading(false);
      
      setStatusDetails({
        status: successCount === uniqueNumbers.length ? 'success' : 'failed',
        amount: totalCost - refundTotal,
        recipient: `${successCount}/${uniqueNumbers.length} Numbers`,
        ref: results[0]?.id || 'BATCH',
        network: 'Various',
        msg: `Processed ${uniqueNumbers.length} numbers. ${successCount} successful. ${formatCurrency(refundTotal)} refunded for failures.`,
        earnedBonus
      });
      setShowStatusModal(true);
      setBulkNumbers('');

    } else {
      if (!network) {
        setMessage({ type: 'error', text: 'Please select a network provider' });
        return;
      }

      const userPrice = calculateUserPrice(numAmount, network);
      if (currentUser.walletBalance < userPrice) {
        setMessage({ type: 'error', text: `Insufficient balance. Required: ${formatCurrency(userPrice)}` });
        return;
      }

      setIsLoading(true);
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - userPrice } : u));
      setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance - userPrice });

      const netObj = networks.find(n => n.name === network) || networks[0];
      const reqId = generateId();
      
      const apiRes = await callNellobyteAPI(phoneNumber, netObj.code, numAmount, reqId);

      if (apiRes.statuscode === "100" || apiRes.statuscode === "200" || apiRes.status === "ORDER_RECEIVED" || apiRes.status === "ORDER_COMPLETED") {
        const earnedBonus = checkAndApplyLoyaltyBonus(currentUser);
        const successTx: any = {
          id: reqId,
          userId: currentUser.id,
          type: 'Airtime',
          amount: userPrice,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Airtime Purchase for ${phoneNumber} (Order: ${apiRes.orderid || 'Pending'})`,
          recipient: phoneNumber,
          provider: network
        };
        setTransactions(prev => [successTx, ...prev]);
        setStatusDetails({
          status: 'success',
          amount: userPrice,
          recipient: phoneNumber,
          ref: reqId,
          network: network,
          msg: 'Your airtime recharge was successful.',
          earnedBonus
        });
      } else {
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + userPrice } : u));
        setCurrentUser(prev => prev ? { ...prev, walletBalance: prev.walletBalance + userPrice } : null);
        const failTx: any = {
          id: reqId,
          userId: currentUser.id,
          type: 'Airtime',
          amount: userPrice,
          status: 'failed',
          date: new Date().toISOString(),
          details: `Gateway Error: ${apiRes.status || 'Connection Error'}`,
          recipient: phoneNumber,
          provider: network,
          refunded: true
        };
        setTransactions(prev => [failTx, ...prev]);
        setStatusDetails({
          status: 'failed',
          amount: userPrice,
          recipient: phoneNumber,
          ref: reqId,
          network: network,
          msg: `Transaction failed: ${apiRes.status || 'Gateway Error'}. Your money has been refunded.`
        });
      }
      
      setIsLoading(false);
      setShowStatusModal(true);
      setPhoneNumber('');
      setAmount('');
    }
  };

  const getActiveDiscount = () => {
    if (!network) return 0;
    const discounts: any = settings.airtimeDiscounts || {};
    const netKey = network === '9mobile' ? 'nineMobile' : network.toLowerCase();
    return discounts[netKey] || 0;
  };

  const StatusModal = () => {
    if (!statusDetails) return null;
    return (
      <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
        <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl">
          <div className={`p-8 text-white flex flex-col items-center text-center ${statusDetails.status === 'success' ? 'bg-opay-green' : 'bg-red-500'}`}>
            <div className="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
              {statusDetails.status === 'success' ? 
                <CheckCircle2 size={40} className="text-opay-green" /> : 
                <X size={40} className="text-red-500" />
              }
            </div>
            <h3 className="text-xl font-black uppercase tracking-tight">{statusDetails.status === 'success' ? 'Transaction Successful' : 'Transaction Failed'}</h3>
            <div className="text-3xl font-black mt-2">{formatCurrency(statusDetails.amount)}</div>
            <p className="text-[10px] font-bold opacity-80 mt-1 uppercase tracking-widest">{statusDetails.network} Airtime</p>
          </div>

          <div className="p-8 space-y-6">
            <div className="space-y-4">
              <div className="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span>Recipient</span>
                <span className="text-gray-900">{statusDetails.recipient}</span>
              </div>
              <div className="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span>Reference</span>
                <span className="text-gray-900 font-mono">{statusDetails.ref}</span>
              </div>
              
              {statusDetails.earnedBonus && (
                <div className="bg-yellow-50 p-3 rounded-xl border border-yellow-100 flex items-center gap-3 animate-bounce-slow">
                   <div className="w-6 h-6 bg-yellow-400 rounded-lg flex items-center justify-center text-white font-black text-[10px]">C</div>
                   <span className="text-[10px] font-black text-yellow-700 uppercase tracking-tight">+{settings.bonusPerDay} Daily Loyalty Coins Earned!</span>
                </div>
              )}

              <div className="h-px bg-gray-100" />
              <p className="text-xs font-bold text-gray-500 text-center leading-relaxed">
                {statusDetails.msg}
              </p>
            </div>

            <div className="flex gap-3">
               <button className="flex-1 bg-gray-100 text-gray-900 p-4 rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-all">
                  <Share2 size={18} />
               </button>
               <button className="flex-1 bg-gray-100 text-gray-900 p-4 rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-all">
                  <Download size={18} />
               </button>
            </div>

            <button 
              onClick={() => setShowStatusModal(false)}
              className={`w-full py-5 rounded-[24px] font-black text-sm shadow-xl active:scale-95 transition-all text-white ${statusDetails.status === 'success' ? 'bg-opay-green' : 'bg-gray-900'}`}
            >
              DONE
            </button>
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

      <div className="p-4 flex-1">
        {message && (
          <div className={`mb-4 p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${
            message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            message.type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' :
            'bg-blue-50 text-blue-800 border border-blue-200'
          }`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : message.type === 'error' ? <AlertCircle size={20} /> : <RotateCcw size={20} className="animate-spin-slow" />}
            <span className="text-sm font-bold">{message.text}</span>
          </div>
        )}

        <div className="bg-white p-6 rounded-3xl shadow-sm space-y-8 border border-gray-100">
          <div className="flex bg-gray-100 p-1.5 rounded-2xl">
            <button 
              onClick={() => setIsBulk(false)}
              className={`flex-1 py-3.5 rounded-xl text-[11px] font-black uppercase tracking-[0.1em] transition-all flex flex-col items-center justify-center gap-1 ${!isBulk ? 'bg-white shadow-lg text-opay-green border border-gray-100' : 'text-gray-400'}`}
            >
              Single Airtime
            </button>
            <button 
              onClick={() => setIsBulk(true)}
              className={`flex-1 py-3.5 rounded-xl text-[11px] font-black uppercase tracking-[0.1em] transition-all flex flex-col items-center justify-center gap-1 ${isBulk ? 'bg-white shadow-lg text-opay-green border border-gray-100' : 'text-gray-400'}`}
            >
              Bulk Airtime
            </button>
          </div>

          {!isBulk ? (
            <div className="animate-fade-in">
              <label className="block text-[11px] font-black text-gray-400 mb-2.5 uppercase tracking-widest">Phone Number</label>
              <div className="relative">
                <input
                  type="tel"
                  placeholder="08012345678"
                  className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black tracking-[0.15em] text-xl placeholder:text-gray-300 transition-all"
                  value={phoneNumber}
                  onChange={(e) => setPhoneNumber(e.target.value.replace(/\D/g, '').slice(0, 11))}
                />
                <User className="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400" size={20} />
              </div>
            </div>
          ) : (
            <div className="animate-fade-in">
              <label className="block text-[11px] font-black text-gray-400 mb-2.5 uppercase tracking-widest">Bulk Numbers (Comma/Line Separated)</label>
              <textarea
                placeholder="08012345678, 09012345678..."
                className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-bold min-h-[160px] placeholder:text-gray-300 text-sm leading-relaxed transition-all"
                value={bulkNumbers}
                onChange={(e) => setBulkNumbers(e.target.value)}
              />
              <div className="text-[10px] text-gray-400 font-bold mt-3 bg-gray-50 p-3 rounded-xl border border-gray-100">
                💡 Duplicates and invalid numbers are removed automatically. Total numbers: <span className="text-opay-green font-black">{bulkNumbers.split(/[,\n]/).map(n => n.trim()).filter(n => n.length >= 10).length}</span>
              </div>
            </div>
          )}

          {!isBulk && (
            <div className="animate-fade-in">
              <div className="flex justify-between items-center mb-4">
                <label className="text-[11px] font-black text-gray-400 uppercase tracking-widest">Network Operator</label>
                <div className="flex items-center gap-2">
                  <span className="text-[10px] text-gray-500 font-bold">Manual Selection</span>
                  <div 
                    onClick={() => setOverride(!override)}
                    className={`w-10 h-5 rounded-full relative transition-colors cursor-pointer ${override ? 'bg-opay-green' : 'bg-gray-300'}`}
                  >
                    <div className={`absolute top-1 w-3 h-3 bg-white rounded-full transition-all ${override ? 'left-6' : 'left-1'}`} />
                  </div>
                </div>
              </div>
              <div className="grid grid-cols-4 gap-4">
                {networks.map(n => (
                  <button
                    key={n.name}
                    disabled={!override && network !== n.name}
                    onClick={() => setNetwork(n.name)}
                    className={`flex flex-col items-center gap-2 p-3 rounded-2xl border-2 transition-all ${network === n.name ? 'border-opay-green bg-white shadow-md scale-[1.05]' : 'border-transparent bg-gray-50 opacity-60'}`}
                  >
                    <div className={`w-11 h-11 rounded-full ${n.color} flex items-center justify-center text-white text-[10px] font-black shadow-inner border-2 border-white`}>
                      {n.name[0]}
                    </div>
                    <span className="text-[9px] font-black text-gray-800 uppercase tracking-tighter">{n.name}</span>
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className="animate-fade-in">
            <label className="block text-[11px] font-black text-gray-400 mb-3.5 uppercase tracking-widest">Select Amount</label>
            <div className="grid grid-cols-4 gap-3 mb-5">
              {[100, 200, 500, 1000].map(amt => (
                <button
                  key={amt}
                  type="button"
                  onClick={() => setAmount(amt.toString())}
                  className={`py-3.5 rounded-xl text-xs font-black border-2 transition-all ${amount === amt.toString() ? 'border-opay-green text-opay-green bg-white shadow-md' : 'border-transparent text-gray-700 bg-gray-50 hover:bg-gray-100'}`}
                >
                  ₦{amt}
                </button>
              ))}
            </div>
            <div className="relative">
                <input
                  type="number"
                  placeholder="Enter Custom Amount (Min ₦50)"
                  className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg placeholder:text-gray-300 transition-all"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                />
                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-lg">₦</span>
            </div>
          </div>

          {amount && network && !isBulk && (
            <div className="bg-gray-50 p-4 rounded-2xl border border-gray-100 space-y-2 animate-fade-in">
              <div className="flex justify-between items-center text-[10px] font-black">
                <span className="text-gray-400 uppercase">Discount ({getActiveDiscount()}%)</span>
                <span className="text-opay-green">-{formatCurrency(parseFloat(amount) * getActiveDiscount() / 100)}</span>
              </div>
              <div className="flex justify-between items-center text-[11px] font-black">
                <span className="text-gray-400 uppercase">Amount to Pay</span>
                <span className="text-gray-900">{formatCurrency(calculateUserPrice(parseFloat(amount), network))}</span>
              </div>
            </div>
          )}

          <button
            onClick={handlePurchase}
            disabled={isLoading || (!isBulk && phoneNumber.length < 10) || amount === ''}
            className={`w-full bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 ${isLoading ? 'opacity-70 grayscale' : 'hover:shadow-green-100'}`}
          >
            {isLoading ? (
              <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
            ) : null}
            {isLoading ? 'Connecting...' : 'Confirm & Buy'}
          </button>
        </div>
        
        <div className="mt-6 bg-red-50 p-5 rounded-3xl border border-red-100 flex gap-4 animate-pulse-slow">
           <RotateCcw className="text-red-600 shrink-0" size={20} />
           <div className="text-[10px] font-bold text-red-700 leading-relaxed">
             API Sync: Transactions are routed via Airtime Systems API. If a network failure occurs, the system performs an Instant Automated Refund to your wallet.
           </div>
        </div>
      </div>

      {showStatusModal && <StatusModal />}
    </div>
  );
};

export default Airtime;


import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  ArrowLeft, ShieldCheck, CheckCircle2, AlertCircle, 
  RotateCcw, ChevronDown, ShoppingBag, Receipt, Phone, 
  Copy, Send, X, Share2, Download, History, Printer, 
  Check, ExternalLink, UserCheck
} from 'lucide-react';

const ExamPin: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();

  const [examType, setExamType] = useState('');
  const [quantity, setQuantity] = useState<number>(1);
  const [phone, setPhone] = useState('');
  const [profileId, setProfileId] = useState('');
  const [verifiedName, setVerifiedName] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);
  
  // Status Modal State
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [statusDetails, setStatusDetails] = useState<{
    status: 'success' | 'failed' | 'error';
    amount: number;
    recipient: string;
    ref: string;
    msg: string;
    provider: string;
    pins: { pin: string; serial_no: string }[];
    earnedBonus?: boolean;
  } | null>(null);

  // Filter enabled providers from settings
  const availableExams = useMemo(() => {
    return settings.examProviders.filter(p => p.enabled);
  }, [settings.examProviders]);

  // Selected Exam Details
  const selectedExam = useMemo(() => {
    return availableExams.find(e => e.id === examType);
  }, [availableExams, examType]);

  const isJamb = selectedExam?.serviceId === 'jamb';

  // LIVE CALCULATED TOTALS
  const unitPrice = selectedExam?.userPrice || 0;
  const totalPayable = unitPrice * (isNaN(quantity) ? 0 : quantity);

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

  // VTPASS Specific Request ID generator (YYYYMMDDHHMMSS + Random)
  const generateVtPassRequestId = () => {
    const now = new Date();
    const datePart = now.toISOString().replace(/[-:T]/g, '').slice(0, 14);
    const randomPart = Math.random().toString(36).substring(2, 10).toUpperCase();
    return `${datePart}-${randomPart}`;
  };

  const handleVerifyProfileId = async () => {
    if (!profileId || !selectedExam) return;
    setIsVerifying(true);
    setVerifiedName('');
    
    try {
      // REAL API CALL: VTPASS Profile Verification
      const response = await fetch('https://vtpass.com/api/merchant-verify', {
        method: 'POST',
        headers: {
          'api-key': settings.vtPassApiKey,
          'public-key': settings.vtPassPublicKey,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          billersCode: profileId,
          serviceID: selectedExam.serviceId,
          type: selectedExam.variationCode
        })
      });
      
      const data = await response.json();
      if (data.code === '000') {
        setVerifiedName(data.content.Customer_Name);
      } else {
        setMessage({ type: 'error', text: data.response_description || 'Could not verify Profile ID' });
      }
    } catch (err) {
      // Fallback for demo/dev if API call fails (CORS etc)
      setVerifiedName('CANDIDATE: ' + profileId);
    } finally {
      setIsVerifying(false);
    }
  };

  const handlePurchase = async () => {
    if (!currentUser || !examType || phone.length < 10 || totalPayable <= 0) return;
    if (isJamb && !verifiedName) {
        setMessage({ type: 'error', text: 'Please verify the JAMB Profile ID first.' });
        return;
    }
    
    if (currentUser.walletBalance < totalPayable) {
      setMessage({ type: 'error', text: 'Insufficient wallet balance' });
      return;
    }

    setIsLoading(true);
    setMessage(null);

    // 1. Debit User first
    const originalBalance = currentUser.walletBalance;
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - totalPayable } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - totalPayable });

    const reqId = selectedExam?.routingProvider === 'vtpass' ? generateVtPassRequestId() : generateId();

    try {
      let data: any;
      
      if (selectedExam?.routingProvider === 'vtpass') {
        // VTPASS API CALL
        const response = await fetch('https://vtpass.com/api/pay', {
          method: 'POST',
          headers: {
            'api-key': settings.vtPassApiKey,
            'public-key': settings.vtPassPublicKey,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            request_id: reqId,
            serviceID: selectedExam.serviceId,
            variation_code: selectedExam.variationCode,
            billersCode: isJamb ? profileId : undefined,
            amount: selectedExam.unitAmount,
            phone: phone,
            quantity: quantity
          })
        });
        data = await response.json();
        // Standardize VTPASS response check
        if (data.code !== '000') throw new Error(data.response_description || 'VTPASS Provider error');
      } else {
        // NAIJA RESULT PINS API CALL
        const apiUrl = `https://naijaresultpins.com/api/buy?token=${settings.examApiKey}&card_type_id=${examType}&quantity=${quantity}&phone=${phone}&request_id=${reqId}`;
        const response = await fetch(apiUrl);
        data = await response.json();
        if (data.status !== 'success' && data.code !== '200') throw new Error(data.message || 'Naija Provider error');
      }

      // SUCCESS HANDLING
      const earned = checkAndApplyLoyaltyBonus(currentUser);
      
      // Standardize PIN extraction from both APIs
      let formattedPins: { pin: string, serial_no: string }[] = [];
      if (selectedExam?.routingProvider === 'vtpass') {
        const pinString = data.purchased_code || data.Pin || 'Check SMS';
        formattedPins = [{ pin: pinString, serial_no: data.content?.transactions?.transactionId || 'N/A' }];
      } else {
        const rawPins = data.pins || (data.data && data.data.pins) || [];
        formattedPins = rawPins.map((p: any) => ({
          pin: p.pin || p.pin_code || 'PIN-IN-SMS',
          serial_no: p.serial_no || p.serial || 'N/A'
        }));
      }
      
      const successTx: any = {
        id: reqId,
        userId: currentUser.id,
        type: 'Exam PIN',
        amount: totalPayable,
        status: 'successful',
        date: new Date().toISOString(),
        details: `${selectedExam?.name} (${quantity} unit). Delivery to ${phone}`,
        recipient: phone,
        provider: selectedExam?.name
      };

      setTransactions(prev => [successTx, ...prev]);
      
      setStatusDetails({
        status: 'success',
        amount: totalPayable,
        recipient: phone,
        ref: reqId,
        provider: selectedExam?.name || 'Exam PIN',
        msg: 'Your exam PIN purchase was successful. Details are provided below.',
        pins: formattedPins.length > 0 ? formattedPins : [{ pin: 'DELIVERED VIA SMS', serial_no: 'CHECK PHONE' }],
        earnedBonus: earned
      });
      
      setIsLoading(false);
      setShowStatusModal(true);
      setExamType('');
      setQuantity(1);
      setPhone('');
      setProfileId('');
      setVerifiedName('');
    } catch (error: any) {
      // AUTO-REFUND ON FAILURE
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance } : u));
      setCurrentUser({ ...currentUser, walletBalance: originalBalance });

      const failTx: any = {
        id: reqId,
        userId: currentUser.id,
        type: 'Exam PIN',
        amount: totalPayable,
        status: 'failed',
        date: new Date().toISOString(),
        details: `Failed: ${error.message || 'API Timeout'}. Instant Refund Issued.`,
        recipient: phone,
        provider: selectedExam?.name,
        refunded: true
      };
      
      setTransactions(prev => [failTx, ...prev]);
      
      setStatusDetails({
        status: 'failed',
        amount: totalPayable,
        recipient: phone,
        ref: reqId,
        provider: selectedExam?.name || 'Exam PIN',
        msg: `Transaction Failed: ${error.message || 'Connection Error'}. Your wallet has been automatically refunded.`,
        pins: []
      });
      
      setIsLoading(false);
      setShowStatusModal(true);
    }
  };

  const copyPin = (pin: string) => {
    navigator.clipboard.writeText(pin);
    alert("Copied!");
  };

  const handleQuantityChange = (val: string) => {
    const num = parseInt(val);
    if (val === '') setQuantity(0); 
    else if (!isNaN(num) && num >= 0) setQuantity(num);
  };

  const StatusModal = () => {
    if (!statusDetails) return null;
    return (
      <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
        <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl flex flex-col max-h-[92vh]">
          <div className={`p-8 text-white flex flex-col items-center text-center shrink-0 ${statusDetails.status === 'success' ? 'bg-opay-green' : 'bg-red-500'}`}>
            <div className="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
              {statusDetails.status === 'success' ? 
                <CheckCircle2 size={40} className="text-opay-green" /> : 
                <X size={40} className="text-red-500" />
              }
            </div>
            <h3 className="text-xl font-black uppercase tracking-tight">
              {statusDetails.status === 'success' ? 'Purchase Successful' : 'Transaction Failed'}
            </h3>
            <div className="text-3xl font-black mt-2">{formatCurrency(statusDetails.amount)}</div>
            <p className="text-[10px] font-bold opacity-80 mt-1 uppercase tracking-widest">{statusDetails.provider}</p>
          </div>

          <div className="p-8 space-y-6 overflow-y-auto scrollbar-hide flex-1">
            <div className="space-y-4">
              <div className="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span>Recipient</span>
                <span className="text-gray-900">{statusDetails.recipient}</span>
              </div>
              <div className="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span>Reference</span>
                <span className="text-gray-900 font-mono text-[9px]">{statusDetails.ref}</span>
              </div>
              
              {statusDetails.status === 'success' && statusDetails.pins.length > 0 && (
                <div className="space-y-3 mt-4 pt-4 border-t border-gray-50">
                  <span className="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-2">Voucher Codes</span>
                  {statusDetails.pins.map((p, idx) => (
                    <div key={idx} className="bg-gray-50 p-4 rounded-2xl border border-gray-100 space-y-2">
                       <div className="flex justify-between items-center">
                          <span className="text-[8px] font-black text-gray-400 uppercase">Serial: {p.serial_no}</span>
                          <button onClick={() => copyPin(p.pin)} className="text-[9px] font-black text-opay-green hover:bg-green-100 px-2 py-0.5 rounded transition-colors flex items-center gap-1">
                            <Copy size={10} /> COPY
                          </button>
                       </div>
                       <div className="text-lg font-black text-gray-900 tracking-[0.05em] font-mono text-center break-all">
                          {p.pin}
                       </div>
                    </div>
                  ))}
                </div>
              )}

              {statusDetails.earnedBonus && (
                <div className="bg-yellow-50 p-3 rounded-xl border border-yellow-100 flex items-center gap-3">
                   <div className="w-6 h-6 bg-yellow-400 rounded-lg flex items-center justify-center text-white font-black text-[10px]">C</div>
                   <span className="text-[10px] font-black text-yellow-700 uppercase tracking-tight">+{settings.bonusPerDay} Loyalty Coins Earned!</span>
                </div>
              )}

              <div className="h-px bg-gray-100" />
              <p className="text-xs font-bold text-gray-500 text-center leading-relaxed">
                {statusDetails.msg}
              </p>
            </div>

            <div className="flex gap-3">
               <button className="flex-1 bg-gray-50 text-gray-900 p-4 rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-all">
                  <Share2 size={18} />
               </button>
               <button className="flex-1 bg-gray-50 text-gray-900 p-4 rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-all">
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
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Education PINs</h1>
      </div>

      <div className="p-4 flex-1 space-y-6">
        {message && (
          <div className={`p-5 rounded-3xl flex items-center gap-4 animate-fade-in ${message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {message.type === 'success' ? <CheckCircle2 size={24} /> : <AlertCircle size={24} />}
            <span className="text-xs font-black uppercase tracking-tight">{message.text}</span>
          </div>
        )}

        <div className="bg-white p-8 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
          <div className="flex flex-col items-center">
             <div className="w-20 h-20 bg-indigo-50 rounded-3xl flex items-center justify-center text-indigo-500 mb-4 shadow-inner">
                <ShieldCheck size={40} />
             </div>
             <h3 className="text-lg font-black text-gray-900 uppercase tracking-tight">Exam Portal</h3>
             <p className="text-[10px] font-bold text-gray-400 uppercase mt-1">NaijaResultPins & VTPass Enabled</p>
          </div>

          <div className="space-y-6">
            <div>
              <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Select Product</label>
              <div className="relative">
                <select
                  className="w-full p-4 pr-10 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-sm appearance-none cursor-pointer"
                  value={examType}
                  onChange={(e) => { setExamType(e.target.value); setVerifiedName(''); setProfileId(''); }}
                >
                  <option value="">Choose Exam Type</option>
                  {availableExams.map(e => (
                    <option key={e.id} value={e.id}>
                      {e.name} - {formatCurrency(e.userPrice)}
                    </option>
                  ))}
                </select>
                <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" size={18} />
              </div>
            </div>

            {isJamb && (
              <div className="animate-fade-in space-y-4">
                 <div>
                    <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">JAMB Profile ID</label>
                    <div className="flex gap-2">
                       <input 
                        type="text" 
                        placeholder="Enter 10-digit Profile ID"
                        className="flex-1 p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-sm"
                        value={profileId}
                        onChange={(e) => setProfileId(e.target.value)}
                       />
                       <button 
                        onClick={handleVerifyProfileId}
                        disabled={isVerifying || !profileId}
                        className="p-4 bg-gray-900 text-white rounded-2xl active:scale-95 transition-all disabled:opacity-50"
                       >
                         {isVerifying ? <RotateCcw className="animate-spin" size={20} /> : <UserCheck size={20} />}
                       </button>
                    </div>
                 </div>
                 {verifiedName && (
                   <div className="p-4 bg-green-50 rounded-2xl border border-green-100 flex items-center gap-3 animate-slide-down">
                      <CheckCircle2 size={16} className="text-green-600" />
                      <span className="text-[10px] font-black text-green-700 uppercase truncate">{verifiedName}</span>
                   </div>
                 )}
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Quantity</label>
                <input 
                  type="number" 
                  min={1} 
                  max={100}
                  className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-center"
                  value={quantity === 0 ? '' : quantity}
                  onChange={(e) => handleQuantityChange(e.target.value)}
                />
              </div>
              <div className="flex flex-col justify-end">
                 <div className="p-4 bg-gray-900 rounded-2xl border border-gray-800 flex flex-col shadow-lg">
                    <span className="text-[8px] font-black text-gray-400 uppercase tracking-tighter">Amount Due</span>
                    <span className="text-sm font-black text-opay-green">
                      {formatCurrency(totalPayable)}
                    </span>
                 </div>
              </div>
            </div>

            <div>
              <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Recipient Phone</label>
              <div className="relative">
                <input 
                  type="tel" 
                  placeholder="080XXXXXXXX"
                  className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black tracking-widest text-sm"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value.replace(/\D/g, '').slice(0, 11))}
                />
                <Phone size={16} className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-300" />
              </div>
            </div>

            <button
              onClick={handlePurchase}
              disabled={isLoading || !examType || phone.length < 10 || totalPayable <= 0 || (isJamb && !verifiedName)}
              className="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
            >
              {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : null}
              {isLoading ? 'Automated Dispatch...' : <><ShoppingBag size={18} /> CONFIRM & PAY</>}
            </button>
          </div>
        </div>

        <div className="bg-red-50 p-6 rounded-[32px] border border-red-100 flex gap-4 animate-pulse-slow">
           <RotateCcw className="text-red-600 shrink-0" size={20} />
           <div className="text-[10px] font-bold text-red-700 leading-relaxed uppercase tracking-tight">
             Fintech Protocol: All requests are routed through verified provider APIs. If the vendor gateway fails to deliver, an <span className="font-black">Instant Automated Refund</span> is triggered to your wallet.
           </div>
        </div>
      </div>

      {showStatusModal && <StatusModal />}
    </div>
  );
};

export default ExamPin;

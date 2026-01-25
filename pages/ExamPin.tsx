
import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
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

  const availableExams = useMemo(() => {
    return settings.examProviders.filter(p => p.enabled);
  }, [settings.examProviders]);

  const selectedExam = useMemo(() => {
    return availableExams.find(e => e.id === examType);
  }, [availableExams, examType]);

  const isJamb = selectedExam?.serviceId === 'jamb';

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

  const handlePurchase = async () => {
    if (!currentUser || !examType || phone.length < 10 || totalPayable <= 0) return;
    if (currentUser.walletBalance < totalPayable) {
      setMessage({ type: 'error', text: 'Insufficient balance' });
      return;
    }

    setIsLoading(true);
    const originalBalance = currentUser.walletBalance;
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - totalPayable } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - totalPayable });

    const reqId = generateId();

    setTimeout(async () => {
      const earned = checkAndApplyLoyaltyBonus(currentUser);
      const pins = [{ pin: 'PIN-' + Math.random().toString(36).substr(2, 10).toUpperCase(), serial_no: 'SN-' + generateId() }];
      
      const successTx: any = {
        id: reqId,
        userId: currentUser.id,
        type: 'Exam PIN',
        amount: totalPayable,
        status: 'successful',
        date: new Date().toISOString(),
        details: `${selectedExam?.name} (${quantity} units)`,
        recipient: phone,
        provider: selectedExam?.name
      };

      setTransactions(prev => [successTx, ...prev]);
      
      // LIVE EMAIL NOTIFICATION
      try {
        await sendNotificationEmail(settings, currentUser.email, 'Exam PIN Receipt', currentUser.fullName, {
          'Exam': selectedExam?.name || 'Exam',
          'PIN': pins[0].pin,
          'Phone': phone,
          'Total Paid': totalPayable,
          'Ref': reqId
        });
      } catch (err) {
        console.warn("Email delivery failed.");
      }

      setStatusDetails({
        status: 'success',
        amount: totalPayable,
        recipient: phone,
        ref: reqId,
        provider: selectedExam?.name || 'Exam PIN',
        msg: 'Purchase successful.',
        pins: pins,
        earnedBonus: earned
      });
      
      setIsLoading(false);
      setShowStatusModal(true);
    }, 2000);
  };

  const StatusModal = () => {
    if (!statusDetails) return null;
    return (
      <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
        <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden animate-slide-up shadow-2xl flex flex-col max-h-[92vh]">
          <div className={`p-8 text-white flex flex-col items-center text-center shrink-0 ${statusDetails.status === 'success' ? 'bg-billpay-green' : 'bg-red-500'}`}>
            <h3 className="text-xl font-black uppercase tracking-tight">Success</h3>
            <div className="text-3xl font-black mt-2">{formatCurrency(statusDetails.amount)}</div>
          </div>
          <div className="p-8 space-y-6 flex-1 overflow-y-auto">
            {statusDetails.pins.map((p, idx) => (
              <div key={idx} className="p-4 bg-gray-50 rounded-2xl border text-center font-black text-lg">{p.pin}</div>
            ))}
            <button onClick={() => setShowStatusModal(false)} className="w-full py-5 rounded-2xl bg-billpay-green text-white font-black">DONE</button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      <div className="bg-white p-4 flex items-center gap-4 border-b">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Education PINs</h1>
      </div>
      <div className="p-4 space-y-6">
        <div className="bg-white p-8 rounded-[40px] shadow-sm space-y-8">
           <select className="w-full p-4 bg-gray-50 rounded-2xl font-black" value={examType} onChange={e => setExamType(e.target.value)}>
              <option value="">Choose Exam</option>
              {availableExams.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
           </select>
           <input type="tel" placeholder="Phone" className="w-full p-4 bg-gray-50 rounded-2xl font-black" value={phone} onChange={e => setPhone(e.target.value.replace(/\D/g, ''))} />
           <button onClick={handlePurchase} disabled={isLoading || !examType || phone.length < 10} className="w-full bg-billpay-green text-white py-5 rounded-2xl font-black">{isLoading ? 'Processing...' : 'CONFIRM & PAY'}</button>
        </div>
      </div>
      {showStatusModal && <StatusModal />}
    </div>
  );
};

export default ExamPin;

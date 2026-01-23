
import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  ArrowLeft, User, Search, CheckCircle2, AlertCircle, 
  RotateCcw, X, ShieldCheck
} from 'lucide-react';

const CableTV: React.FC = () => {
  const { currentUser, setCurrentUser, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();

  const [providerId, setProviderId] = useState('');
  const [smartcardNumber, setSmartcardNumber] = useState('');
  const [subType, setSubType] = useState<'renew' | 'change'>('renew');
  const [customerName, setCustomerName] = useState('');
  const [currentBouquet, setCurrentBouquet] = useState('');
  const [renewalAmount, setRenewalAmount] = useState<number>(0);
  const [selectedVariationCode, setSelectedVariationCode] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error' | 'info', text: string } | null>(null);

  const activeProviders = useMemo(() => settings.cableProviders.filter(p => p.enabled), [settings.cableProviders]);
  const selectedProvider = useMemo(() => activeProviders.find(p => p.id === providerId), [activeProviders, providerId]);

  const generateVtRequestId = () => {
    const now = new Date();
    const datePart = now.toISOString().replace(/[-:T]/g, '').slice(0, 14);
    const randomPart = Math.random().toString(36).substring(2, 10).toUpperCase();
    return `${datePart}-${randomPart}`;
  };

  const getAuthHeaders = () => {
    const headers: any = { 'Content-Type': 'application/json' };
    if (settings.vtPassEmail && settings.vtPassPassword) {
      headers['Authorization'] = 'Basic ' + btoa(`${settings.vtPassEmail}:${settings.vtPassPassword}`);
    } else {
      headers['api-key'] = settings.vtPassApiKey;
      headers['public-key'] = settings.vtPassPublicKey;
    }
    return headers;
  };

  const handleVerify = async () => {
    if (!smartcardNumber || !providerId) {
      setMessage({ type: 'error', text: 'Select provider and enter number' });
      return;
    }
    
    setIsVerifying(true);
    setCustomerName('');
    setMessage(null);

    try {
      const response = await fetch('https://vtpass.com/api/merchant-verify', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          billersCode: smartcardNumber,
          serviceID: selectedProvider?.serviceId
        })
      });

      const data = await response.json();
      
      if (data.code === '000') {
        setCustomerName(data.content.Customer_Name || 'VALID CUSTOMER');
        setCurrentBouquet(data.content.Current_Bouquet || 'Active Package');
        setRenewalAmount(parseFloat(data.content.Renewal_Amount || '0'));
      } else {
        setMessage({ 
          type: 'error', 
          text: data.response_description || 'Invalid card number or IUC' 
        });
      }
    } catch (err) {
      setMessage({ type: 'error', text: 'Network Error: Check API credentials in Admin.' });
    } finally {
      setIsVerifying(false);
    }
  };

  const getFinalPrice = (rawAmount: number) => {
    if (!selectedProvider) return rawAmount;
    return rawAmount * (1 - selectedProvider.discountPercent / 100);
  };

  const selectedVariation = useMemo(() => {
    return selectedProvider?.variations.find(v => v.variation_code === selectedVariationCode);
  }, [selectedProvider, selectedVariationCode]);

  const totalToPay = subType === 'renew' ? getFinalPrice(renewalAmount) : getFinalPrice(parseFloat(selectedVariation?.variation_amount || '0'));

  const handlePurchase = async () => {
    if (!currentUser || !customerName || totalToPay <= 0) return;

    if (currentUser.walletBalance < totalToPay) {
      setMessage({ type: 'error', text: 'Insufficient wallet balance' });
      return;
    }

    setIsLoading(true);
    const originalBalance = currentUser.walletBalance;
    
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance - totalToPay } : u));
    setCurrentUser({ ...currentUser, walletBalance: originalBalance - totalToPay });

    const reqId = generateVtRequestId();

    try {
      const body: any = {
        request_id: reqId,
        serviceID: selectedProvider?.serviceId,
        billersCode: smartcardNumber,
        phone: currentUser.phone,
        subscription_type: subType,
        amount: subType === 'renew' ? renewalAmount : parseFloat(selectedVariation?.variation_amount || '0'),
        quantity: 1
      };
      
      if (subType === 'change') {
        body.variation_code = selectedVariationCode;
      }

      const response = await fetch('https://vtpass.com/api/pay', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(body)
      });

      const data = await response.json();

      if (data.code === '000') {
        const successTx: any = {
          id: reqId,
          userId: currentUser.id,
          type: 'Cable TV',
          amount: totalToPay,
          status: 'successful',
          date: new Date().toISOString(),
          details: `${selectedProvider?.name} ${subType.toUpperCase()}: ${subType === 'renew' ? currentBouquet : selectedVariation?.name} for ${smartcardNumber}`,
          recipient: smartcardNumber,
          provider: selectedProvider?.name
        };
        setTransactions(prev => [successTx, ...prev]);
        setMessage({ type: 'success', text: `Subscription successful for ${customerName}!` });
        setSmartcardNumber('');
        setCustomerName('');
        setSelectedVariationCode('');
      } else {
        throw new Error(data.response_description || 'Provider rejected transaction');
      }
    } catch (error: any) {
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: originalBalance } : u));
      setCurrentUser(prev => prev ? { ...prev, walletBalance: originalBalance } : null);
      
      const failTx: any = {
        id: reqId,
        userId: currentUser.id,
        type: 'Cable TV',
        amount: totalToPay,
        status: 'failed',
        date: new Date().toISOString(),
        details: `API Error: ${error.message}. Wallet Refunded.`,
        recipient: smartcardNumber,
        provider: selectedProvider?.name,
        refunded: true
      };
      setTransactions(prev => [failTx, ...prev]);
      setMessage({ type: 'error', text: `Activation failed: ${error.message}. Refund issued.` });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-10 border-b shadow-sm">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900 uppercase tracking-tight">Cable TV</h1>
      </div>

      <div className="p-4 flex-1 space-y-6 pb-24 overflow-y-auto scrollbar-hide">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${
            message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            message.type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-blue-50 text-blue-800'
          }`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold flex-1">{message.text}</span>
            <X size={16} className="cursor-pointer opacity-50" onClick={() => setMessage(null)} />
          </div>
        )}

        <div className="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-3 uppercase tracking-widest ml-1">Select Provider</label>
            <div className="grid grid-cols-4 gap-3">
              {activeProviders.map(p => (
                <button
                  key={p.id}
                  onClick={() => { setProviderId(p.id); setCustomerName(''); setSelectedVariationCode(''); }}
                  className={`flex flex-col items-center gap-2 p-3 rounded-2xl border-2 transition-all ${providerId === p.id ? 'border-opay-green bg-green-50' : 'border-transparent bg-gray-50 opacity-60'}`}
                >
                  <div className="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white text-[10px] font-black shadow-inner">
                    {p.name.substring(0,2)}
                  </div>
                  <span className="text-[8px] font-black text-gray-800 uppercase tracking-tighter">{p.name}</span>
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Card / IUC Number</label>
            <div className="flex gap-2">
              <input
                type="tel"
                placeholder="Enter IUC number"
                className="flex-1 p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-lg tracking-widest"
                value={smartcardNumber}
                onChange={(e) => setSmartcardNumber(e.target.value.replace(/\D/g, '').slice(0, 11))}
              />
              <button 
                onClick={handleVerify}
                disabled={isVerifying || !smartcardNumber || !providerId}
                className="p-4 bg-gray-900 text-white rounded-2xl active:scale-95 transition-all disabled:opacity-50"
              >
                {isVerifying ? <RotateCcw className="animate-spin" size={20} /> : <Search size={20} />}
              </button>
            </div>
            
            {customerName && (
              <div className="mt-4 p-4 bg-green-50 rounded-2xl border border-green-100 animate-slide-down flex items-center gap-3">
                <div className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm">
                   <User size={14} className="text-opay-green" />
                </div>
                <div className="flex flex-col">
                   <span className="text-[10px] font-black text-green-700 uppercase">{customerName}</span>
                   <span className="text-[8px] font-bold text-green-600 opacity-60 uppercase">Current: {currentBouquet}</span>
                </div>
              </div>
            )}
          </div>

          {customerName && (
            <div className="space-y-6 animate-fade-in">
              <div className="flex bg-gray-100 p-1.5 rounded-2xl">
                <button 
                  onClick={() => setSubType('renew')}
                  className={`flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all ${subType === 'renew' ? 'bg-white shadow-md text-opay-green' : 'text-gray-50'}`}
                >
                  Renew
                </button>
                <button 
                  onClick={() => setSubType('change')}
                  className={`flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all ${subType === 'change' ? 'bg-white shadow-md text-opay-green' : 'text-gray-50'}`}
                >
                  Change
                </button>
              </div>

              {subType === 'change' ? (
                <div className="space-y-4">
                   <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Available Bouquets</label>
                   <div className="flex flex-col gap-3 max-h-80 overflow-y-auto pr-1 scrollbar-hide">
                      {selectedProvider?.variations.map(v => (
                        <div 
                          key={v.variation_code}
                          onClick={() => setSelectedVariationCode(v.variation_code)}
                          className={`p-5 rounded-[24px] border-2 flex justify-between items-center cursor-pointer transition-all active:scale-[0.98] ${selectedVariationCode === v.variation_code ? 'border-opay-green bg-green-50 shadow-md' : 'border-gray-50 bg-gray-50'}`}
                        >
                           <div className="flex items-center gap-4">
                              <div className={`w-10 h-10 rounded-xl flex items-center justify-center font-black text-[10px] ${selectedVariationCode === v.variation_code ? 'bg-opay-green text-white' : 'bg-white text-gray-400 border border-gray-100 shadow-sm'}`}>
                                 {selectedProvider.name.substring(0,1)}
                              </div>
                              <span className="text-[11px] font-black text-gray-800 uppercase leading-tight pr-4">{v.name}</span>
                           </div>
                           <div className="text-right shrink-0">
                              <div className="text-xs font-black text-opay-green">{formatCurrency(getFinalPrice(parseFloat(v.variation_amount)))}</div>
                           </div>
                        </div>
                      ))}
                      {(!selectedProvider?.variations || selectedProvider.variations.length === 0) && (
                        <div className="py-12 text-center text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100 rounded-[32px]">No synced packages found. Update credentials or Sync in Admin.</div>
                      )}
                   </div>
                </div>
              ) : (
                <div className="bg-gray-50 p-6 rounded-3xl border border-gray-100 flex justify-between items-center">
                   <div>
                      <span className="text-[8px] font-black text-gray-400 uppercase block mb-1">Total Payable</span>
                      <span className="text-lg font-black text-gray-900">{formatCurrency(totalToPay)}</span>
                   </div>
                   <div className="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500 shadow-sm border border-indigo-100">
                      <RotateCcw size={24} />
                   </div>
                </div>
              )}

              <button
                onClick={handlePurchase}
                disabled={isLoading || (subType === 'change' && !selectedVariationCode) || totalToPay <= 0}
                className="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
              >
                {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <><ShieldCheck size={18} /> PAY {formatCurrency(totalToPay)}</>}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CableTV;

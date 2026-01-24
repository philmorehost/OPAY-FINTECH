
import React, { useState, useMemo, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { generateId, formatCurrency } from '../utils';
import { 
  ArrowLeft, MessageCircle, Send, Users, AlertCircle, CheckCircle2, 
  BookOpen, Plus, Search, Trash2, ShieldCheck, Clock, UserPlus, 
  UserCheck, ChevronRight, X, Info, UploadCloud, Copy, CheckSquare, Square, RefreshCcw
} from 'lucide-react';

const BulkSMS: React.FC = () => {
  const { 
    currentUser, setCurrentUser, setUsers, setTransactions, 
    smsSenderIds, setSmsSenderIds, 
    phoneBook, setPhoneBook, settings
  } = useApp();
  const navigate = useNavigate();
  
  const [activeTab, setActiveTab] = useState<'compose' | 'ids' | 'contacts'>('compose');
  
  const [numbers, setNumbers] = useState('');
  const [message, setMessage] = useState('');
  const [senderId, setSenderId] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [showContactPicker, setShowContactPicker] = useState(false);
  const [selectedContactsForSMS, setSelectedContactsForSMS] = useState<string[]>([]);

  const [regSenderId, setRegSenderId] = useState('');
  const [regSample, setRegSample] = useState('');
  const [isRegistering, setIsRegistering] = useState(false);

  const [contactName, setContactName] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [isSavingContact, setIsSavingContact] = useState(false);
  const [contactSearch, setContactSearch] = useState('');
  const [showBulkContactImport, setShowBulkContactImport] = useState(false);
  const [bulkContactData, setBulkContactData] = useState('');

  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const charLimit = 160;
  const pages = Math.ceil(message.length / charLimit) || 1;

  const stats = useMemo(() => {
    const raw = numbers.split(/[,\s\n]+/).map(n => n.trim()).filter(n => n.length >= 10);
    const unique = Array.from(new Set(raw));
    const duplicates = raw.length - unique.length;
    const totalCost = unique.length * pages * settings.smsRate;
    return { unique, duplicates, totalCost, rawCount: raw.length };
  }, [numbers, pages, settings.smsRate]);

  // FIXED: Removed smsSenderIds from deps. Using functional update to avoid recreation loops.
  useEffect(() => {
    if (!currentUser || !settings.kudiSmsToken) return;
    
    const checkAllStatuses = async () => {
      setSmsSenderIds(prev => {
        const pendingIds = prev.filter(id => id.userId === currentUser.id && id.status === 'pending');
        if (pendingIds.length === 0) return prev;

        // Note: Realistically you'd batch these or check one by one. 
        // For simulation, we'll mark some as approved if found in an actual check
        // In a real app, this logic would trigger an async call for EACH pending ID.
        return prev; 
      });
    };

    const interval = setInterval(checkAllStatuses, 30000);
    return () => clearInterval(interval);
  }, [settings.kudiSmsToken, currentUser]);

  if (!currentUser) return null;

  const handleSend = async () => {
    if (!senderId) { setStatus({ type: 'error', text: 'Select an approved Sender ID' }); return; }
    if (stats.unique.length === 0) { setStatus({ type: 'error', text: 'Enter valid recipients' }); return; }
    if (currentUser.walletBalance < stats.totalCost) { setStatus({ type: 'error', text: `Insufficient balance: ${formatCurrency(stats.totalCost)}` }); return; }

    setIsSending(true);
    const recipients = stats.unique.join(',');
    const url = `https://my.kudisms.net/api/sms?token=${settings.kudiSmsToken}&senderID=${senderId}&recipients=${recipients}&message=${encodeURIComponent(message)}&gateway=2`;

    try {
      const response = await fetch(url);
      const data = await response.json();

      if (data.status === 'success' || data.error_code === '000') {
        const newTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Bulk SMS',
          amount: stats.totalCost,
          status: 'successful',
          date: new Date().toISOString(),
          details: `KudiSms: ${stats.unique.length} recipients. Sender: ${senderId}`,
          recipient: `${stats.unique.length} numbers`
        };

        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - stats.totalCost } : u));
        setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance - stats.totalCost });
        setTransactions(prev => [newTx, ...prev]);
        setStatus({ type: 'success', text: `Sent successfully! Total recipients: ${stats.unique.length}` });
        setNumbers('');
        setMessage('');
      } else {
        throw new Error(data.msg || 'API Provider Error');
      }
    } catch (e: any) {
      setStatus({ type: 'error', text: e.message || 'Connection failed' });
    } finally {
      setIsSending(false);
    }
  };

  const handleRegisterId = async (e: React.FormEvent) => {
    e.preventDefault();
    if (regSenderId.length > 11) { setStatus({ type: 'error', text: 'Max 11 chars' }); return; }
    
    setIsRegistering(true);
    try {
      const formData = new FormData();
      formData.append('token', settings.kudiSmsToken);
      formData.append('senderID', regSenderId);
      formData.append('message', regSample);

      const response = await fetch('https://my.kudisms.net/api/senderID', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();

      if (data.status === 'success' || data.error_code === '000') {
        const newReq: any = {
          id: generateId(),
          userId: currentUser.id,
          name: regSenderId.toUpperCase(),
          sampleMessage: regSample,
          status: 'pending',
          createdAt: new Date().toISOString()
        };
        setSmsSenderIds(prev => [newReq, ...prev]);
        setRegSenderId('');
        setRegSample('');
        setStatus({ type: 'success', text: 'Sender ID submitted to KudiSMS.' });
      } else {
        throw new Error(data.msg || 'Provider registration failed');
      }
    } catch (e: any) {
      setStatus({ type: 'error', text: e.message });
    } finally {
      setIsRegistering(false);
    }
  };

  const handleSaveContact = (e: React.FormEvent) => {
    e.preventDefault();
    if (!contactName || contactPhone.length < 10) return;
    setIsSavingContact(true);
    setTimeout(() => {
      const newContact: any = { id: generateId(), userId: currentUser.id, name: contactName, phone: contactPhone, createdAt: new Date().toISOString() };
      setPhoneBook(prev => [newContact, ...prev]);
      setIsSavingContact(false);
      setContactName('');
      setContactPhone('');
      setStatus({ type: 'success', text: 'Saved.' });
    }, 500);
  };

  const handleBulkContactImport = () => {
    const lines = bulkContactData.split('\n').filter(l => l.trim().length > 0);
    const newContacts: any[] = [];
    lines.forEach(line => {
      const parts = line.split(/[,\t|]/);
      if (parts.length >= 2) {
        const name = parts[0].trim();
        const phone = parts[1].replace(/\D/g, '').trim();
        if (phone.length >= 10) { newContacts.push({ id: generateId(), userId: currentUser.id, name, phone, createdAt: new Date().toISOString() }); }
      }
    });
    if (newContacts.length > 0) {
      setPhoneBook(prev => [...newContacts, ...prev]);
      setStatus({ type: 'success', text: `Imported ${newContacts.length}` });
      setBulkContactData('');
      setShowBulkContactImport(false);
    }
  };

  const myApprovedIds = smsSenderIds.filter(id => id.userId === currentUser.id && id.status === 'approved');
  const myContacts = phoneBook.filter(c => c.userId === currentUser.id);
  const filteredContacts = myContacts.filter(c => c.name.toLowerCase().includes(contactSearch.toLowerCase()) || c.phone.includes(contactSearch));

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 sticky top-0 z-40 border-b">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">Bulk SMS Hub</h1>
      </div>

      <div className="p-4 space-y-6 flex-1 pb-24 overflow-y-auto scrollbar-hide">
        {status && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${status.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {status.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold flex-1">{status.text}</span>
            <X size={18} className="cursor-pointer opacity-50" onClick={() => setStatus(null)} />
          </div>
        )}

        <div className="flex bg-gray-200 p-1.5 rounded-[24px]">
          {['compose', 'ids', 'contacts'].map(tab => (
            <button key={tab} onClick={() => setActiveTab(tab as any)} className={`flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all ${activeTab === tab ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'}`}>{tab}</button>
          ))}
        </div>

        {activeTab === 'compose' && (
          <div className="space-y-6 animate-fade-in">
            <div className="bg-gradient-to-br from-opay-green to-emerald-600 p-6 rounded-[32px] text-white shadow-lg relative overflow-hidden">
               <div className="flex justify-between items-start mb-4">
                 <div>
                    <div className="text-[10px] font-black uppercase tracking-widest opacity-70">Current Rate</div>
                    <div className="text-2xl font-black">{formatCurrency(settings.smsRate)}<span className="text-xs font-bold opacity-60"> / SMS</span></div>
                 </div>
                 <div className="bg-white/20 p-2 rounded-xl backdrop-blur-md"><Info size={16} /></div>
               </div>
               <div className="grid grid-cols-2 gap-4">
                 <div className="bg-white/10 p-3 rounded-2xl border border-white/10 backdrop-blur-sm"><div className="text-[8px] font-black uppercase opacity-60">Char Limit</div><div className="text-xs font-black">160 Characters</div></div>
                 <div className="bg-white/10 p-3 rounded-2xl border border-white/10 backdrop-blur-sm"><div className="text-[8px] font-black uppercase opacity-60">Gateway</div><div className="text-xs font-black">KudiSms v2</div></div>
               </div>
            </div>

            <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
              <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Approved Sender ID</label>
                {myApprovedIds.length === 0 ? (
                  <div className="p-4 bg-orange-50 rounded-2xl border border-orange-100 text-[10px] font-bold text-orange-600">Register a Sender ID first in the next tab.</div>
                ) : (
                  <select className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-bold" value={senderId} onChange={(e) => setSenderId(e.target.value)}>
                    <option value="">Select Sender ID</option>
                    {myApprovedIds.map(id => <option key={id.id} value={id.name}>{id.name}</option>)}
                  </select>
                )}
              </div>
              <div>
                <div className="flex justify-between items-center mb-2 px-1">
                  <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients ({stats.unique.length})</label>
                  <button onClick={() => setShowContactPicker(true)} className="text-[10px] font-black text-opay-green uppercase flex items-center gap-1 bg-green-50 px-3 py-1.5 rounded-xl"><BookOpen size={12} /> Contact Book</button>
                </div>
                <textarea className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-medium min-h-[120px] text-sm" placeholder="Numbers separated by comma..." value={numbers} onChange={(e) => setNumbers(e.target.value)} />
              </div>
              <div>
                <div className="flex justify-between items-center mb-2 px-1"><label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Message</label><span className="text-[9px] font-black px-2 py-0.5 rounded-full bg-gray-100">{pages} Pages</span></div>
                <textarea className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-medium min-h-[150px] text-sm" placeholder="Message content..." value={message} onChange={(e) => setMessage(e.target.value)} />
              </div>
              <div className="bg-gray-900 p-6 rounded-[32px] text-white space-y-2">
                 <div className="flex justify-between items-center"><span className="text-sm font-black uppercase text-white/50">Total Cost</span><div className="text-xl font-black text-opay-green">{formatCurrency(stats.totalCost)}</div></div>
              </div>
              <button onClick={handleSend} disabled={isSending || !message || stats.unique.length === 0 || !senderId} className="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50">
                {isSending ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <><Send size={18} /> BROADCAST SMS</>}
              </button>
            </div>
          </div>
        )}

        {activeTab === 'ids' && (
          <div className="space-y-8 animate-fade-in">
            <form onSubmit={handleRegisterId} className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
              <h3 className="text-xs font-black text-gray-800 uppercase tracking-widest flex items-center gap-2"><Plus size={16} className="text-opay-green" /> Automated Sender ID Registration</h3>
              <div>
                <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Proposed ID (Max 11 Chars)</label>
                <input type="text" maxLength={11} required placeholder="e.g. OPAY CLONE" className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold uppercase transition-all" value={regSenderId} onChange={(e) => setRegSenderId(e.target.value)} />
              </div>
              <div>
                <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Sample Message</label>
                <textarea required placeholder="Sample message for approval..." className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-medium min-h-[120px] text-sm" value={regSample} onChange={(e) => setRegSample(e.target.value)} />
              </div>
              <button type="submit" disabled={isRegistering || regSenderId.length === 0} className="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl active:scale-95 transition-all flex items-center justify-center gap-2">
                {isRegistering ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : 'REGISTER ON KUDISMS'}
              </button>
            </form>
            <div className="space-y-3">
                {smsSenderIds.filter(id => id.userId === currentUser.id).map(id => (
                  <div key={id.id} className="bg-white p-5 rounded-[24px] border border-gray-100 flex items-center justify-between">
                    <div><div className="text-sm font-black text-gray-800 uppercase">{id.name}</div><div className="text-[9px] text-gray-400 font-bold uppercase flex items-center gap-2"><Clock size={10} /> {new Date(id.createdAt).toLocaleDateString()}</div></div>
                    <span className={`px-3 py-1 rounded-full text-[9px] font-black uppercase ${id.status === 'approved' ? 'bg-green-50 text-green-600' : id.status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'}`}>{id.status}</span>
                  </div>
                ))}
            </div>
          </div>
        )}

        {activeTab === 'contacts' && (
          <div className="space-y-8 animate-fade-in">
            <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
              <div className="flex justify-between items-center"><h3 className="text-xs font-black text-gray-800 uppercase tracking-widest">Phone Book</h3><button onClick={() => setShowBulkContactImport(true)} className="text-[10px] font-black text-opay-green uppercase flex items-center gap-1 bg-green-50 px-3 py-1.5 rounded-xl"><UploadCloud size={14} /> Import</button></div>
              <form onSubmit={handleSaveContact} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <input type="text" required placeholder="Name" className="w-full p-4 bg-gray-50 rounded-2xl font-bold text-xs" value={contactName} onChange={(e) => setContactName(e.target.value)} />
                  <input type="tel" required placeholder="Phone" className="w-full p-4 bg-gray-50 rounded-2xl font-bold text-xs" value={contactPhone} onChange={(e) => setContactPhone(e.target.value.replace(/\D/g, '').slice(0,11))} />
                </div>
                <button type="submit" disabled={isSavingContact} className="w-full bg-opay-green text-white font-black py-4 rounded-2xl">ADD CONTACT</button>
              </form>
            </div>
          </div>
        )}
      </div>

      {showContactPicker && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-end">
          <div className="w-full max-w-md mx-auto bg-white rounded-t-[40px] p-8 animate-slide-up max-h-[80vh] flex flex-col">
            <div className="flex justify-between items-center mb-6"><h3 className="text-xl font-black">Pick Contacts</h3><button onClick={() => setShowContactPicker(false)} className="p-2 bg-gray-50 rounded-full"><X size={20} /></button></div>
            <div className="flex-1 overflow-y-auto space-y-2 scrollbar-hide">
              {filteredContacts.map(c => (
                <div key={c.id} onClick={() => setSelectedContactsForSMS(prev => prev.includes(c.phone) ? prev.filter(p => p !== c.phone) : [...prev, c.phone])} className={`p-4 rounded-[24px] flex items-center gap-4 cursor-pointer border-2 transition-all ${selectedContactsForSMS.includes(c.phone) ? 'border-opay-green bg-green-50' : 'border-transparent bg-gray-50'}`}>
                  {selectedContactsForSMS.includes(c.phone) ? <CheckSquare size={20} className="text-opay-green" /> : <Square size={20} className="text-gray-300" />}
                  <div><div className="text-xs font-black">{c.name}</div><div className="text-[9px] text-gray-400">{c.phone}</div></div>
                </div>
              ))}
            </div>
            <button onClick={() => { setNumbers(prev => (prev ? prev + ',' : '') + selectedContactsForSMS.join(',')); setSelectedContactsForSMS([]); setShowContactPicker(false); }} className="w-full bg-opay-green text-white py-5 rounded-3xl font-black mt-6">ADD SELECTED</button>
          </div>
        </div>
      )}
    </div>
  );
};

export default BulkSMS;

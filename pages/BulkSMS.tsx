
import React, { useState, useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { generateId, formatCurrency } from '../utils';
import { 
  ArrowLeft, MessageCircle, Send, Users, AlertCircle, CheckCircle2, 
  BookOpen, Plus, Search, Trash2, ShieldCheck, Clock, UserPlus, 
  UserCheck, ChevronRight, X, Info, UploadCloud, Copy, CheckSquare, Square
} from 'lucide-react';

const BulkSMS: React.FC = () => {
  const { 
    currentUser, setCurrentUser, setUsers, setTransactions, 
    smsSenderIds, setSmsSenderIds, 
    phoneBook, setPhoneBook, settings
  } = useApp();
  const navigate = useNavigate();
  
  const [activeTab, setActiveTab] = useState<'compose' | 'ids' | 'contacts'>('compose');
  
  // Compose State
  const [numbers, setNumbers] = useState('');
  const [message, setMessage] = useState('');
  const [senderId, setSenderId] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [showContactPicker, setShowContactPicker] = useState(false);
  const [selectedContactsForSMS, setSelectedContactsForSMS] = useState<string[]>([]);

  // Registration State
  const [regSenderId, setRegSenderId] = useState('');
  const [regSample, setRegSample] = useState('');
  const [isRegistering, setIsRegistering] = useState(false);

  // Contact State
  const [contactName, setContactName] = useState('');
  const [contactPhone, setContactPhone] = useState('');
  const [isSavingContact, setIsSavingContact] = useState(false);
  const [contactSearch, setContactSearch] = useState('');
  const [showBulkContactImport, setShowBulkContactImport] = useState(false);
  const [bulkContactData, setBulkContactData] = useState('');

  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const charLimit = 160;
  const pages = Math.ceil(message.length / charLimit) || 1;

  // Pricing & Number Calculation
  const stats = useMemo(() => {
    const raw = numbers.split(/[,\s\n]+/).map(n => n.trim()).filter(n => n.length >= 10);
    const unique = Array.from(new Set(raw));
    const duplicates = raw.length - unique.length;
    const totalCost = unique.length * pages * settings.smsRate;
    return { unique, duplicates, totalCost, rawCount: raw.length };
  }, [numbers, pages, settings.smsRate]);

  if (!currentUser) return null;

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

  const myApprovedIds = smsSenderIds.filter(id => id.userId === currentUser.id && id.status === 'approved');
  const myContacts = phoneBook.filter(c => c.userId === currentUser.id);

  const filteredContacts = myContacts.filter(c => 
    c.name.toLowerCase().includes(contactSearch.toLowerCase()) || 
    c.phone.includes(contactSearch)
  );

  const handleSend = () => {
    if (!senderId) {
      setStatus({ type: 'error', text: 'Please select an approved Sender ID' });
      return;
    }
    
    if (stats.unique.length === 0) {
      setStatus({ type: 'error', text: 'Please enter at least one valid recipient' });
      return;
    }

    if (currentUser.walletBalance < stats.totalCost) {
      setStatus({ type: 'error', text: `Insufficient balance. Required: ${formatCurrency(stats.totalCost)}` });
      return;
    }

    setIsSending(true);
    setTimeout(() => {
      const earned = checkAndApplyLoyaltyBonus(currentUser);
      const newTx: any = {
        id: generateId(),
        userId: currentUser.id,
        type: 'Bulk SMS',
        amount: stats.totalCost,
        status: 'successful',
        date: new Date().toISOString(),
        details: `Sent ${stats.unique.length} messages via Sender ID: ${senderId}. Pages: ${pages}`,
        recipient: `${stats.unique.length} recipients`
      };

      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - stats.totalCost } : u));
      setCurrentUser(prev => prev ? { ...prev, walletBalance: prev.walletBalance - stats.totalCost } : null);
      setTransactions(prev => [newTx, ...prev]);
      setIsSending(false);
      setStatus({ type: 'success', text: `SMS processing complete for ${stats.unique.length} numbers. ${earned ? `+${settings.bonusPerDay} Coins Earned!` : ''}` });
      setNumbers('');
      setMessage('');
      setSenderId('');
    }, 2000);
  };

  const handleRegisterId = (e: React.FormEvent) => {
    e.preventDefault();
    if (regSenderId.length > 11) {
      setStatus({ type: 'error', text: 'Sender ID cannot exceed 11 characters.' });
      return;
    }
    
    setIsRegistering(true);
    setTimeout(() => {
      const newReq: any = {
        id: generateId(),
        userId: currentUser.id,
        name: regSenderId.toUpperCase(),
        sampleMessage: regSample,
        status: 'pending',
        createdAt: new Date().toISOString()
      };
      setSmsSenderIds(prev => [newReq, ...prev]);
      setIsRegistering(false);
      setRegSenderId('');
      setRegSample('');
      setStatus({ type: 'success', text: 'Sender ID registration submitted. You will be notified via email upon approval.' });
    }, 1500);
  };

  const handleSaveContact = (e: React.FormEvent) => {
    e.preventDefault();
    if (!contactName || contactPhone.length < 10) return;
    
    setIsSavingContact(true);
    setTimeout(() => {
      const newContact: any = {
        id: generateId(),
        userId: currentUser.id,
        name: contactName,
        phone: contactPhone,
        createdAt: new Date().toISOString()
      };
      setPhoneBook(prev => [newContact, ...prev]);
      setIsSavingContact(false);
      setContactName('');
      setContactPhone('');
      setStatus({ type: 'success', text: 'Contact saved to phone book.' });
    }, 1000);
  };

  const handleBulkContactImport = () => {
    const lines = bulkContactData.split('\n').filter(l => l.trim().length > 0);
    const newContacts: any[] = [];
    
    lines.forEach(line => {
      const parts = line.split(/[,\t|]/);
      if (parts.length >= 2) {
        const name = parts[0].trim();
        const phone = parts[1].replace(/\D/g, '').trim();
        if (phone.length >= 10) {
          newContacts.push({
            id: generateId(),
            userId: currentUser.id,
            name,
            phone,
            createdAt: new Date().toISOString()
          });
        }
      }
    });

    if (newContacts.length > 0) {
      setPhoneBook(prev => [...newContacts, ...prev]);
      setStatus({ type: 'success', text: `Imported ${newContacts.length} contacts successfully.` });
      setBulkContactData('');
      setShowBulkContactImport(false);
    }
  };

  const deleteContact = (id: string) => {
    if (window.confirm('Delete this contact?')) {
      setPhoneBook(prev => prev.filter(c => c.id !== id));
    }
  };

  const toggleContactSelection = (phone: string) => {
    setSelectedContactsForSMS(prev => 
      prev.includes(phone) ? prev.filter(p => p !== phone) : [...prev, phone]
    );
  };

  const addSelectedToCompose = () => {
    const current = numbers.trim();
    const joined = selectedContactsForSMS.join(', ');
    if (!current) {
      setNumbers(joined);
    } else {
      const separator = current.includes(',') ? ', ' : '\n';
      setNumbers(`${current}${separator}${joined}`);
    }
    setSelectedContactsForSMS([]);
    setShowContactPicker(false);
  };

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
          <button onClick={() => setActiveTab('compose')} className={`flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all ${activeTab === 'compose' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'}`}>Compose</button>
          <button onClick={() => setActiveTab('ids')} className={`flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all ${activeTab === 'ids' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'}`}>Sender IDs</button>
          <button onClick={() => setActiveTab('contacts')} className={`flex-1 py-3.5 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all ${activeTab === 'contacts' ? 'bg-white shadow-xl text-opay-green' : 'text-gray-500'}`}>Phone Book</button>
        </div>

        {activeTab === 'compose' && (
          <div className="space-y-6 animate-fade-in">
            {/* Pricing Info Card */}
            <div className="bg-gradient-to-br from-opay-green to-emerald-600 p-6 rounded-[32px] text-white shadow-lg relative overflow-hidden">
               <div className="absolute -right-8 -top-8 w-24 h-24 bg-white/10 rounded-full blur-2xl" />
               <div className="flex justify-between items-start mb-4">
                 <div>
                    <div className="text-[10px] font-black uppercase tracking-widest opacity-70">Current Rate</div>
                    <div className="text-2xl font-black">{formatCurrency(settings.smsRate)}<span className="text-xs font-bold opacity-60"> / SMS</span></div>
                 </div>
                 <div className="bg-white/20 p-2 rounded-xl backdrop-blur-md">
                    <Info size={16} />
                 </div>
               </div>
               <div className="grid grid-cols-2 gap-4">
                 <div className="bg-white/10 p-3 rounded-2xl border border-white/10 backdrop-blur-sm">
                   <div className="text-[8px] font-black uppercase opacity-60">Char Limit</div>
                   <div className="text-xs font-black">160 Characters</div>
                 </div>
                 <div className="bg-white/10 p-3 rounded-2xl border border-white/10 backdrop-blur-sm">
                   <div className="text-[8px] font-black uppercase opacity-60">Multi-page</div>
                   <div className="text-xs font-black">Supported</div>
                 </div>
               </div>
            </div>

            <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
              <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Approved Sender ID</label>
                {myApprovedIds.length === 0 ? (
                  <div className="p-4 bg-orange-50 rounded-2xl border border-orange-100 text-[10px] font-bold text-orange-600">
                    You don't have any approved Sender IDs. Please register one first in the 'Sender IDs' tab.
                  </div>
                ) : (
                  <select 
                    className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-bold appearance-none cursor-pointer"
                    value={senderId}
                    onChange={(e) => setSenderId(e.target.value)}
                  >
                    <option value="">Select Sender ID</option>
                    {myApprovedIds.map(id => <option key={id.id} value={id.name}>{id.name}</option>)}
                  </select>
                )}
              </div>

              <div>
                <div className="flex justify-between items-center mb-2 px-1">
                  <div className="flex flex-col">
                    <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipients</label>
                    <div className="flex gap-2 mt-1">
                       <span className="text-[9px] font-black bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">{stats.unique.length} Total</span>
                       {stats.duplicates > 0 && <span className="text-[9px] font-black bg-orange-50 text-orange-600 px-2 py-0.5 rounded-full">{stats.duplicates} Duplicates Removed</span>}
                    </div>
                  </div>
                  <button onClick={() => setShowContactPicker(true)} className="text-[10px] font-black text-opay-green uppercase flex items-center gap-1 bg-green-50 px-3 py-1.5 rounded-xl border border-green-100">
                    <BookOpen size={12} /> Contact Book
                  </button>
                </div>
                <textarea 
                  className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-medium min-h-[120px] placeholder:text-gray-300 text-sm leading-relaxed"
                  placeholder="Paste numbers here (separated by comma, space or new line)"
                  value={numbers}
                  onChange={(e) => setNumbers(e.target.value)}
                />
              </div>

              <div>
                <div className="flex justify-between items-center mb-2 px-1">
                   <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Message</label>
                   <span className={`text-[9px] font-black px-2 py-0.5 rounded-full ${pages > 1 ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-500'}`}>
                     {pages} Page{pages > 1 ? 's' : ''}
                   </span>
                </div>
                <textarea 
                  className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-medium min-h-[150px] placeholder:text-gray-300 text-sm leading-relaxed"
                  placeholder="Type message content..."
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                />
                <div className="flex justify-between mt-3 px-1">
                  <span className="text-[9px] font-black text-gray-400 uppercase">{message.length} Characters Used</span>
                  <span className="text-[9px] font-black text-opay-green uppercase">₦{settings.smsRate} per page</span>
                </div>
              </div>

              <div className="bg-gray-900 p-6 rounded-[32px] text-white space-y-4 shadow-xl">
                 <h4 className="text-[10px] font-black uppercase tracking-widest opacity-40">Cost Calculation</h4>
                 <div className="flex justify-between items-center">
                    <span className="text-[10px] font-bold opacity-70">Unique Numbers</span>
                    <span className="text-xs font-black">{stats.unique.length}</span>
                 </div>
                 <div className="flex justify-between items-center">
                    <span className="text-[10px] font-bold opacity-70">Message Unit(s)</span>
                    <span className="text-xs font-black">{pages} per person</span>
                 </div>
                 <div className="h-px bg-white/10" />
                 <div className="flex justify-between items-center">
                    <span className="text-sm font-black text-white/50 uppercase">Total Amount</span>
                    <div className="text-xl font-black text-opay-green">{formatCurrency(stats.totalCost)}</div>
                 </div>
              </div>

              <button
                onClick={handleSend}
                disabled={isSending || !message || stats.unique.length === 0 || !senderId}
                className="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-3 disabled:opacity-50"
              >
                {isSending ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <><Send size={18} /> BROADCAST SMS</>}
              </button>
            </div>
          </div>
        )}

        {activeTab === 'ids' && (
          <div className="space-y-8 animate-fade-in">
            <form onSubmit={handleRegisterId} className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
              <div className="flex justify-between items-center">
                <h3 className="text-xs font-black text-gray-800 uppercase tracking-widest flex items-center gap-2">
                  <Plus size={16} className="text-opay-green" /> Register Sender ID
                </h3>
                <div className={`px-2 py-1 rounded-lg text-[9px] font-black ${regSenderId.length > 11 ? 'bg-red-50 text-red-500' : 'bg-gray-50 text-gray-400'}`}>
                  {regSenderId.length}/11
                </div>
              </div>
              
              <div>
                <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Proposed ID (Max 11 Chars)</label>
                <div className="relative">
                  <input 
                    type="text" 
                    maxLength={11}
                    required
                    placeholder="e.g. OPAY CLONE"
                    className={`w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold uppercase transition-all border-2 ${regSenderId.length > 0 && regSenderId.length <= 11 ? 'border-transparent focus:border-opay-green' : 'border-transparent'}`}
                    value={regSenderId}
                    onChange={(e) => setRegSenderId(e.target.value)}
                  />
                  {regSenderId.length === 11 && (
                    <div className="absolute right-4 top-1/2 -translate-y-1/2">
                      <AlertCircle size={16} className="text-amber-500" />
                    </div>
                  )}
                </div>
                <p className="text-[8px] font-bold text-gray-400 mt-2 px-1 leading-relaxed">Sender IDs must not exceed 11 characters including spaces. Special characters are discouraged.</p>
              </div>

              <div>
                <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Sample SMS Content</label>
                <textarea 
                  required
                  placeholder="Type or paste a sample message here..."
                  className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-medium min-h-[120px] text-sm leading-relaxed"
                  value={regSample}
                  onChange={(e) => setRegSample(e.target.value)}
                />
              </div>

              <button 
                type="submit"
                disabled={isRegistering || regSenderId.length === 0 || regSenderId.length > 11}
                className="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {isRegistering ? (
                  <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                ) : (
                  <>SUBMIT FOR APPROVAL</>
                )}
              </button>
            </form>

            <div className="space-y-4">
              <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Recent Requests</h3>
              <div className="space-y-3">
                {smsSenderIds.filter(id => id.userId === currentUser.id).length === 0 ? (
                  <div className="py-12 flex flex-col items-center gap-4 bg-white rounded-[32px] border border-gray-100 border-dashed">
                     <ShieldCheck size={32} className="text-gray-100" />
                     <p className="text-[10px] font-black text-gray-300 uppercase tracking-widest">No history found</p>
                  </div>
                ) : (
                  smsSenderIds.filter(id => id.userId === currentUser.id).map(id => (
                    <div key={id.id} className="bg-white p-5 rounded-[24px] border border-gray-100 flex items-center justify-between">
                      <div>
                        <div className="text-sm font-black text-gray-800 uppercase">{id.name}</div>
                        <div className="text-[9px] text-gray-400 font-bold mt-1 uppercase tracking-widest flex items-center gap-2">
                           <Clock size={10} /> {new Date(id.createdAt).toLocaleDateString()}
                        </div>
                      </div>
                      <span className={`px-3 py-1 rounded-full text-[9px] font-black uppercase ${
                        id.status === 'approved' ? 'bg-green-50 text-green-600' : 
                        id.status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'
                      }`}>
                        {id.status}
                      </span>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        )}

        {activeTab === 'contacts' && (
          <div className="space-y-8 animate-fade-in">
            <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
              <div className="flex justify-between items-center">
                <h3 className="text-xs font-black text-gray-800 uppercase tracking-widest">Manage Contacts</h3>
                <button 
                  onClick={() => setShowBulkContactImport(true)}
                  className="text-[10px] font-black text-opay-green uppercase flex items-center gap-1 bg-green-50 px-3 py-1.5 rounded-xl"
                >
                  <UploadCloud size={14} /> Bulk Import
                </button>
              </div>

              <form onSubmit={handleSaveContact} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Name</label>
                    <input type="text" required placeholder="Full Name" className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold text-xs" value={contactName} onChange={(e) => setContactName(e.target.value)} />
                  </div>
                  <div>
                    <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest ml-1">Phone</label>
                    <input type="tel" required placeholder="080XXXXXXXX" className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold text-xs" value={contactPhone} onChange={(e) => setContactPhone(e.target.value.replace(/\D/g, '').slice(0,11))} />
                  </div>
                </div>
                <button 
                  type="submit"
                  disabled={isSavingContact || !contactName || !contactPhone}
                  className="w-full bg-opay-green text-white font-black py-4 rounded-2xl shadow-xl active:scale-95 transition-all disabled:opacity-50"
                >
                  {isSavingContact ? 'SAVING...' : 'ADD TO PHONE BOOK'}
                </button>
              </form>
            </div>

            <div className="space-y-4">
              <div className="flex justify-between items-center px-1">
                <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Your Contacts ({myContacts.length})</h3>
              </div>
              <div className="relative">
                <input 
                  type="text"
                  placeholder="Search name or phone..."
                  className="w-full pl-11 pr-4 py-4 bg-white rounded-2xl border border-gray-100 outline-none text-xs font-bold shadow-sm focus:border-opay-green transition-all"
                  value={contactSearch}
                  onChange={(e) => setContactSearch(e.target.value)}
                />
                <Search size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
              </div>

              <div className="space-y-2 pb-10">
                {filteredContacts.length === 0 ? (
                  <div className="py-20 text-center text-gray-300 font-black text-[10px] uppercase tracking-widest border-2 border-dashed border-gray-200 rounded-[40px]">
                    No contacts found.
                  </div>
                ) : (
                  filteredContacts.map(c => (
                    <div key={c.id} className="bg-white p-4 rounded-[20px] border border-gray-50 flex items-center justify-between group shadow-sm hover:border-opay-green/30 transition-all">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-opay-green font-black text-[10px]">
                           {c.name.substring(0,2).toUpperCase()}
                        </div>
                        <div>
                          <div className="text-xs font-black text-gray-800">{c.name}</div>
                          <div className="text-[9px] text-gray-400 font-bold">{c.phone}</div>
                        </div>
                      </div>
                      <button onClick={() => deleteContact(c.id)} className="p-2 text-red-500 bg-red-50 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-red-100 transition-all" title="Delete">
                         <Trash2 size={14} />
                      </button>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Contact Picker Modal */}
      {showContactPicker && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-end">
          <div className="w-full max-w-md mx-auto bg-white rounded-t-[40px] p-8 animate-slide-up max-h-[85vh] flex flex-col shadow-2xl">
            <div className="flex justify-between items-center mb-6">
               <div className="flex flex-col">
                  <h3 className="text-xl font-black text-gray-900 leading-tight">Pick Contacts</h3>
                  <span className="text-[10px] font-bold text-gray-400 uppercase">{selectedContactsForSMS.length} Selected</span>
               </div>
               <button onClick={() => { setSelectedContactsForSMS([]); setShowContactPicker(false); }} className="p-2 bg-gray-50 rounded-full"><X size={20} className="text-gray-400" /></button>
            </div>
            
            <div className="relative mb-6">
              <input 
                type="text"
                placeholder="Search phone book..."
                className="w-full pl-11 pr-4 py-4 bg-gray-50 rounded-2xl outline-none text-xs font-bold border-2 border-transparent focus:border-opay-green transition-all"
                value={contactSearch}
                onChange={(e) => setContactSearch(e.target.value)}
              />
              <Search size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" />
            </div>

            <div className="flex-1 overflow-y-auto space-y-2 pr-1 scrollbar-hide">
              {filteredContacts.length === 0 ? (
                 <p className="text-center py-10 text-xs font-bold text-gray-400">Your phone book is empty.</p>
              ) : (
                filteredContacts.map(c => (
                  <div 
                    key={c.id} 
                    onClick={() => toggleContactSelection(c.phone)}
                    className={`p-4 rounded-[24px] flex items-center justify-between cursor-pointer transition-all border-2 ${selectedContactsForSMS.includes(c.phone) ? 'bg-green-50 border-opay-green' : 'bg-gray-50 border-transparent hover:bg-gray-100'}`}
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-opay-green font-black">
                        {selectedContactsForSMS.includes(c.phone) ? <CheckSquare size={20} /> : <Square size={20} className="opacity-20" />}
                      </div>
                      <div>
                        <div className="text-xs font-black text-gray-800">{c.name}</div>
                        <div className="text-[9px] text-gray-400 font-bold">{c.phone}</div>
                      </div>
                    </div>
                    <ChevronRight size={16} className="text-gray-300" />
                  </div>
                ))
              )}
            </div>

            <div className="pt-6 border-t border-gray-100 mt-4">
               <button 
                onClick={addSelectedToCompose}
                disabled={selectedContactsForSMS.length === 0}
                className="w-full bg-opay-green text-white py-5 rounded-[24px] font-black text-sm shadow-xl active:scale-95 transition-all disabled:opacity-50"
               >
                 ADD {selectedContactsForSMS.length} TO RECIPIENTS
               </button>
            </div>
          </div>
        </div>
      )}

      {/* Bulk Contact Import Modal */}
      {showBulkContactImport && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-sm rounded-[40px] p-8 animate-slide-up space-y-6 shadow-2xl">
            <div className="flex justify-between items-center">
               <h3 className="text-lg font-black text-gray-900">Bulk Import</h3>
               <button onClick={() => setShowBulkContactImport(false)} className="p-1 bg-gray-50 rounded-full"><X size={18} className="text-gray-400" /></button>
            </div>
            <div className="space-y-4">
               <div className="bg-blue-50 p-4 rounded-2xl border border-blue-100">
                  <p className="text-[9px] font-bold text-blue-700 leading-relaxed uppercase tracking-tight">
                    Format: <span className="font-black">Name, Phone</span> (one per line).<br/>
                    Example: John Doe, 08012345678
                  </p>
               </div>
               <textarea 
                className="w-full p-4 bg-gray-50 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-bold min-h-[200px] text-xs leading-relaxed"
                placeholder="Paste your contact list here..."
                value={bulkContactData}
                onChange={(e) => setBulkContactData(e.target.value)}
               />
               <button 
                onClick={handleBulkContactImport}
                disabled={!bulkContactData}
                className="w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest disabled:opacity-50"
               >
                 Start Importing
               </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default BulkSMS;

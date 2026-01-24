
import React, { useState, useMemo } from 'react';
import { Routes, Route, Link, useNavigate, useLocation } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  LayoutDashboard, Users, Settings as SettingsIcon, Database, 
  Mail, LogOut, CreditCard, CheckCircle, 
  Trash2, Lock, Unlock, Plus, RefreshCcw, 
  Eye, ShieldCheck, Wallet, Landmark, Check, Ban, MessageCircle, Gift, LayoutGrid, Phone, Tv, Wifi, Key, ShieldAlert, Zap, Percent, Shield, GraduationCap, ArrowUpDown, ChevronDown, Save, MessageSquare, Send, Bitcoin, TrendingUp, Globe
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { User, KYCSubmission, DepositRequest, SmsSenderId, GiftCardRequest, Offer, ExamProvider, DataProduct, CableProvider, Transaction, SupportTicket } from '../types';

interface AdminSubPageProps {
  showToast: (msg: string) => void;
}

const AdminOverview: React.FC = () => {
  const { users, giftCardRequests, smsSenderIds, tickets } = useApp();
  const stats = [
    { label: 'Total Users', value: users.length, icon: <Users className="text-blue-500" />, color: 'bg-blue-50' },
    { label: 'Platform Balance', value: formatCurrency(users.reduce((acc, u) => acc + u.walletBalance, 0)), icon: <CreditCard className="text-green-500" />, color: 'bg-green-50' },
    { label: 'Open Tickets', value: tickets.filter(t => t.status === 'open').length, icon: <MessageSquare className="text-purple-500" />, color: 'bg-purple-50' },
    { label: 'Pending SMS IDs', value: smsSenderIds.filter(r => r.status === 'pending').length, icon: <MessageCircle className="text-amber-500" />, color: 'bg-amber-50' },
  ];
  const volumeData = [
    { date: '01 May', vol: 1200000 }, { date: '02 May', vol: 1500000 }, { date: '03 May', vol: 1100000 },
    { date: '04 May', vol: 2200000 }, { date: '05 May', vol: 1800000 }, { date: '06 May', vol: 2900000 },
    { date: '07 May', vol: 2400000 },
  ];
  return (
    <div className="space-y-8 animate-fade-in">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, i) => (
          <div key={i} className="bg-white p-7 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5">
            <div className={`w-14 h-14 rounded-2xl ${stat.color} flex items-center justify-center`}>{stat.icon}</div>
            <div>
              <div className="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">{stat.label}</div>
              <div className="text-xl font-black text-gray-800 tracking-tight">{stat.value}</div>
            </div>
          </div>
        ))}
      </div>
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm overflow-hidden text-gray-900">
          <div className="flex justify-between items-center mb-8">
            <h3 className="text-sm font-black uppercase tracking-widest">Revenue Growth</h3>
            <span className="px-3 py-1 bg-green-50 text-green-600 text-[10px] font-black rounded-full">+12.5% Today</span>
          </div>
          <div className="h-[300px] w-full relative">
            <ResponsiveContainer width="100%" height="100%" minWidth={0}>
              <AreaChart data={volumeData}>
                <defs><linearGradient id="colorVol" x1="0" x2="0" y2="1"><stop offset="5%" stopColor="#00c689" stopOpacity={0.3}/><stop offset="95%" stopColor="#00c689" stopOpacity={0}/></linearGradient></defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="date" stroke="#999" fontSize={10} axisLine={false} tickLine={false} />
                <YAxis hide /><Tooltip /><Area type="monotone" dataKey="vol" stroke="#00c689" strokeWidth={3} fillOpacity={1} fill="url(#colorVol)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
        <div className="bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm text-gray-900">
          <h3 className="text-sm font-black uppercase tracking-widest mb-6">Quick Access</h3>
          <div className="grid grid-cols-2 gap-4">
            <Link to="/admin/deposits" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Wallet className="text-purple-500 mb-3" /><span className="text-[9px] font-black uppercase">Deposits</span></Link>
            <Link to="/admin/support" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><MessageSquare className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Support</span></Link>
            <Link to="/admin/users" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Users className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Users</span></Link>
            <Link to="/admin/settings" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><SettingsIcon className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Settings</span></Link>
          </div>
        </div>
      </div>
    </div>
  );
};

// --- Support Ticket Component ---

const SupportManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { tickets, setTickets, users } = useApp();
  const [selectedTicket, setSelectedTicket] = useState<SupportTicket | null>(null);
  const [replyMessage, setReplyMessage] = useState('');

  const handleSendReply = () => {
    if (!selectedTicket || !replyMessage.trim()) return;

    const updatedTickets = tickets.map(t => {
      if (t.id === selectedTicket.id) {
        return {
          ...t,
          replies: [
            ...t.replies,
            { author: 'Admin Support', message: replyMessage, date: new Date().toISOString() }
          ]
        };
      }
      return t;
    });

    setTickets(updatedTickets);
    setSelectedTicket(updatedTickets.find(t => t.id === selectedTicket.id) || null);
    setReplyMessage('');
    showToast("Reply sent successfully.");
  };

  const toggleTicketStatus = (id: string) => {
    setTickets(prev => prev.map(t => {
      if (t.id === id) return { ...t, status: t.status === 'open' ? 'closed' : 'open' };
      return t;
    }));
    showToast("Ticket status updated.");
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in text-gray-900 h-[calc(100vh-180px)]">
      <div className="bg-white rounded-[40px] border border-gray-100 shadow-sm flex flex-col overflow-hidden">
        <div className="p-6 border-b border-gray-50 flex justify-between items-center">
           <h3 className="text-sm font-black uppercase tracking-widest">Inbox</h3>
           <span className="bg-opay-green/10 text-opay-green px-3 py-1 rounded-full text-[10px] font-black">{tickets.length} TOTAL</span>
        </div>
        <div className="flex-1 overflow-y-auto scrollbar-hide divide-y divide-gray-50">
          {tickets.map(ticket => (
            <div 
              key={ticket.id} 
              onClick={() => setSelectedTicket(ticket)}
              className={`p-5 cursor-pointer transition-all hover:bg-gray-50 ${selectedTicket?.id === ticket.id ? 'bg-gray-50 border-l-4 border-opay-green' : ''}`}
            >
              <div className="flex justify-between items-start mb-1">
                <span className="text-xs font-black truncate max-w-[120px]">{ticket.subject}</span>
                <span className={`px-2 py-0.5 rounded-full text-[8px] font-black uppercase ${ticket.status === 'open' ? 'bg-amber-100 text-amber-600' : 'bg-green-100 text-green-600'}`}>
                  {ticket.status}
                </span>
              </div>
              <div className="text-[10px] text-gray-400 font-bold truncate">From: {users.find(u => u.id === ticket.userId)?.username || 'User'}</div>
              <div className="text-[9px] text-gray-300 uppercase mt-2 font-black">{new Date(ticket.createdAt).toLocaleDateString()}</div>
            </div>
          ))}
          {tickets.length === 0 && (
             <div className="p-10 text-center text-gray-300 font-black uppercase text-[10px] tracking-widest">No tickets found</div>
          )}
        </div>
      </div>

      <div className="lg:col-span-2 bg-white rounded-[40px] border border-gray-100 shadow-sm flex flex-col overflow-hidden">
        {selectedTicket ? (
          <>
            <div className="p-6 border-b border-gray-50 flex justify-between items-center">
               <div className="flex items-center gap-4">
                  <div className="w-10 h-10 bg-gray-100 rounded-2xl flex items-center justify-center text-gray-400">
                     <MessageSquare size={20} />
                  </div>
                  <div>
                    <h3 className="text-sm font-black uppercase tracking-tight">{selectedTicket.subject}</h3>
                    <span className="text-[9px] font-bold text-gray-400 uppercase">Ticket ID: {selectedTicket.id}</span>
                  </div>
               </div>
               <button 
                onClick={() => toggleTicketStatus(selectedTicket.id)}
                className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all ${selectedTicket.status === 'open' ? 'border-red-100 text-red-500 hover:bg-red-50' : 'border-green-100 text-green-500 hover:bg-green-50'}`}
               >
                 {selectedTicket.status === 'open' ? 'Close Ticket' : 'Reopen'}
               </button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-8 space-y-6 bg-gray-50/50 scrollbar-hide">
               {/* User's Original Message */}
               <div className="flex flex-col items-start max-w-[85%]">
                  <div className="bg-white p-5 rounded-[32px] rounded-tl-none shadow-sm border border-gray-100">
                     <div className="text-[11px] font-bold text-gray-700 leading-relaxed" dangerouslySetInnerHTML={{ __html: selectedTicket.message }} />
                  </div>
                  <span className="text-[9px] font-black text-gray-300 uppercase mt-2 ml-2">User • {new Date(selectedTicket.createdAt).toLocaleTimeString()}</span>
               </div>

               {/* Replies */}
               {selectedTicket.replies.map((reply, i) => (
                 <div key={i} className={`flex flex-col max-w-[85%] ${reply.author.includes('Admin') ? 'items-end ml-auto' : 'items-start'}`}>
                    <div className={`p-5 rounded-[32px] shadow-sm border ${reply.author.includes('Admin') ? 'bg-opay-green text-white border-opay-green rounded-tr-none' : 'bg-white text-gray-700 border-gray-100 rounded-tl-none'}`}>
                       <p className="text-[11px] font-bold leading-relaxed">{reply.message}</p>
                    </div>
                    <span className="text-[9px] font-black text-gray-300 uppercase mt-2 mx-2">{reply.author} • {new Date(reply.date).toLocaleTimeString()}</span>
                 </div>
               ))}
            </div>

            <div className="p-6 bg-white border-t border-gray-50 flex gap-4">
               <input 
                type="text" 
                placeholder="Type your response here..." 
                className="flex-1 bg-gray-50 rounded-2xl px-6 py-4 outline-none font-bold text-sm border-2 border-transparent focus:border-opay-green transition-all"
                value={replyMessage}
                onChange={e => setReplyMessage(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && handleSendReply()}
               />
               <button 
                onClick={handleSendReply}
                disabled={!replyMessage.trim()}
                className="bg-opay-green text-white p-4 rounded-2xl shadow-lg shadow-green-100 active:scale-95 transition-all disabled:opacity-50"
               >
                 <Send size={24} />
               </button>
            </div>
          </>
        ) : (
          <div className="flex-1 flex flex-col items-center justify-center text-center p-10">
             <div className="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-gray-100 mb-6">
                <MessageSquare size={48} />
             </div>
             <h4 className="text-sm font-black text-gray-300 uppercase tracking-widest">Select a ticket to join the conversation</h4>
          </div>
        )}
      </div>
    </div>
  );
};

// --- API Sub-Pages ---

const AirtimeApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3">
          <Phone className="text-blue-500" /> Airtime (Nellobyte)
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div>
            <label className="text-[10px] font-black text-gray-400 uppercase">User ID</label>
            <input className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-blue-500" 
              value={settings.nellobyteUserId} onChange={e => setSettings({...settings, nellobyteUserId: e.target.value})} />
          </div>
          <div>
            <label className="text-[10px] font-black text-gray-400 uppercase">API Key</label>
            <input type="password" placeholder="nellobyte key" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-blue-500" 
              value={settings.nellobyteApiKey} onChange={e => setSettings({...settings, nellobyteApiKey: e.target.value})} />
          </div>
        </div>
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3">
          <Percent className="text-blue-500" /> User Discounts (%)
        </h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
           {['mtn', 'glo', 'airtel', 'nineMobile'].map(net => (
             <div key={net} className="bg-gray-50 p-6 rounded-3xl border border-gray-100">
                <label className="text-[10px] font-black text-gray-400 uppercase block mb-3">{net === 'nineMobile' ? '9mobile' : net}</label>
                <input type="number" step="0.1" className="w-full bg-white p-3 rounded-xl outline-none font-black text-sm text-blue-600 border border-gray-100" value={settings.airtimeDiscounts[net as keyof typeof settings.airtimeDiscounts]} onChange={e => setSettings({...settings, airtimeDiscounts: { ...settings.airtimeDiscounts, [net]: parseFloat(e.target.value) || 0 }})} />
             </div>
           ))}
        </div>
      </div>
      <button onClick={() => showToast("Airtime settings saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">Save Config</button>
    </div>
  );
};

const DataApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [newDataProduct, setNewDataProduct] = useState<Partial<DataProduct>>({ networkId: '', type: 'sme-data', size: '', apiQuantityCode: '', userPrice: 0, enabled: true });
  const addDataProduct = () => {
    if (!newDataProduct.networkId || !newDataProduct.size || !newDataProduct.apiQuantityCode) { showToast("Fill all fields"); return; }
    const product: DataProduct = { id: generateId(), networkId: newDataProduct.networkId!, type: newDataProduct.type!, size: newDataProduct.size!, apiQuantityCode: newDataProduct.apiQuantityCode!, userPrice: newDataProduct.userPrice!, enabled: true };
    setSettings({ ...settings, dataProducts: [...settings.dataProducts, product] });
    setNewDataProduct({ networkId: '', type: 'sme-data', size: '', apiQuantityCode: '', userPrice: 0, enabled: true });
    showToast("Plan added.");
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Wifi className="text-emerald-500" /> Data Gifting API Token</h3>
        <input type="password" placeholder="API Token" className="w-full p-5 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 border-2 border-transparent focus:border-emerald-500" value={settings.dataGiftingApiKey} onChange={e => setSettings({...settings, dataGiftingApiKey: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Database className="text-emerald-500" /> Data Plan Management</h3>
        <div className="bg-gray-50 p-8 rounded-[32px] space-y-6 mb-10">
           <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              <select className="p-3 bg-white rounded-xl font-bold text-xs" value={newDataProduct.networkId} onChange={e => setNewDataProduct({...newDataProduct, networkId: e.target.value})}><option value="">Select Network</option>{settings.dataNetworks.map(n => <option key={n.id} value={n.id}>{n.name}</option>)}</select>
              <input placeholder="Size (1GB)" className="p-3 bg-white rounded-xl font-bold text-xs" value={newDataProduct.size} onChange={e => setNewDataProduct({...newDataProduct, size: e.target.value})} />
              <select className="p-3 bg-white rounded-xl font-bold text-xs" value={newDataProduct.type} onChange={e => setNewDataProduct({...newDataProduct, type: e.target.value})}><option value="sme-data">SME</option><option value="cg-data">CG</option><option value="direct-data">Direct</option></select>
              <input placeholder="API Code" className="p-3 bg-white rounded-xl font-bold text-xs" value={newDataProduct.apiQuantityCode} onChange={e => setNewDataProduct({...newDataProduct, apiQuantityCode: e.target.value})} />
              <input type="number" placeholder="Price (₦)" className="p-3 bg-white rounded-xl font-bold text-xs" value={newDataProduct.userPrice || ''} onChange={e => setNewDataProduct({...newDataProduct, userPrice: parseFloat(e.target.value) || 0})} />
           </div>
           <button onClick={addDataProduct} className="w-full bg-emerald-500 text-white py-4 rounded-xl font-black text-[10px] uppercase shadow-lg shadow-emerald-100 active:scale-95 transition-all">Add Plan</button>
        </div>
      </div>
    </div>
  );
};

const CableApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [isSyncing, setIsSyncing] = useState(false);
  const getAuthHeaders = () => {
    const headers: any = { 'Content-Type': 'application/json' };
    if (settings.vtPassEmail && settings.vtPassPassword) { headers['Authorization'] = 'Basic ' + btoa(`${settings.vtPassEmail}:${settings.vtPassPassword}`); }
    else { headers['api-key'] = settings.vtPassApiKey; headers['public-key'] = settings.vtPassPublicKey; }
    return headers;
  };
  const syncCableVariations = async (serviceId: string) => {
    setIsSyncing(true);
    try {
      const response = await fetch(`https://vtpass.com/api/service-variations?serviceID=${serviceId}`, { headers: getAuthHeaders() });
      const data = await response.json();
      if (data.response_description === "000") {
        setSettings(prev => ({ ...prev, cableProviders: prev.cableProviders.map(p => p.serviceId === serviceId ? { ...p, variations: data.content.variations } : p) }));
        showToast(`Synced ${serviceId.toUpperCase()}`);
      } else { showToast("Sync failed"); }
    } catch (err) { showToast("Error connecting"); }
    finally { setIsSyncing(false); }
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Key className="text-indigo-500" /> VTpass Credentials</h3>
        <div className="grid grid-cols-2 gap-8 mb-8">
           <div className="space-y-4">
              <input type="email" placeholder="Email" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold" value={settings.vtPassEmail || ''} onChange={e => setSettings({...settings, vtPassEmail: e.target.value})} />
              <input type="password" placeholder="Password" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold" value={settings.vtPassPassword || ''} onChange={e => setSettings({...settings, vtPassPassword: e.target.value})} />
           </div>
           <div className="grid grid-cols-1 gap-4">
              <input type="password" placeholder="API Key" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold" value={settings.vtPassApiKey} onChange={e => setSettings({...settings, vtPassApiKey: e.target.value})} />
              <input type="password" placeholder="Public Key" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold" value={settings.vtPassPublicKey} onChange={e => setSettings({...settings, vtPassPublicKey: e.target.value})} />
           </div>
        </div>
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Tv className="text-red-500" /> Bouquet Sync Control</h3>
        <div className="grid grid-cols-4 gap-4">
           {settings.cableProviders.map(cp => (
              <div key={cp.id} className="p-6 bg-gray-50 rounded-3xl flex flex-col items-center gap-2">
                 <span className="text-xs font-black uppercase">{cp.name}</span>
                 <button onClick={() => syncCableVariations(cp.serviceId)} className="p-3 bg-white rounded-xl shadow-sm text-opay-green"><RefreshCcw size={16} className={isSyncing ? 'animate-spin' : ''} /></button>
              </div>
           ))}
        </div>
      </div>
    </div>
  );
};

const ExamApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [newExamProduct, setNewExamProduct] = useState<Partial<ExamProvider>>({ name: '', unitAmount: 0, userPrice: 0, enabled: true, routingProvider: 'naija', serviceId: '', variationCode: '' });
  const addExamProduct = () => {
    if (!newExamProduct.name || !newExamProduct.userPrice) { showToast("Fill fields"); return; }
    const product: ExamProvider = { id: generateId(), name: newExamProduct.name!, unitAmount: newExamProduct.unitAmount || 0, userPrice: newExamProduct.userPrice!, enabled: true, availability: 'Available', routingProvider: newExamProduct.routingProvider as any, serviceId: newExamProduct.serviceId, variationCode: newExamProduct.variationCode };
    setSettings({ ...settings, examProviders: [...settings.examProviders, product] });
    setNewExamProduct({ name: '', unitAmount: 0, userPrice: 0, enabled: true, routingProvider: 'naija', serviceId: '', variationCode: '' });
    showToast("Exam PIN added.");
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><ShieldCheck className="text-purple-500" /> NaijaResultPins Key</h3>
        <input type="password" placeholder="Naija Token" className="w-full p-5 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 border-2 border-transparent focus:border-purple-500" value={settings.examApiKey} onChange={e => setSettings({...settings, examApiKey: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><GraduationCap className="text-indigo-500" /> Manage Exam PINs</h3>
        <div className="bg-gray-50 p-8 rounded-[32px] space-y-6 mb-10">
           <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <input placeholder="Exam Name" className="p-3 bg-white rounded-xl font-bold text-xs" value={newExamProduct.name} onChange={e => setNewExamProduct({...newExamProduct, name: e.target.value})} />
              <select className="p-3 bg-white rounded-xl font-bold text-xs" value={newExamProduct.routingProvider} onChange={e => setNewExamProduct({...newExamProduct, routingProvider: e.target.value as any})}><option value="naija">NaijaResult</option><option value="vtpass">VTPass</option></select>
              <input type="number" placeholder="Cost" className="p-3 bg-white rounded-xl font-bold text-xs" value={newExamProduct.unitAmount || ''} onChange={e => setNewExamProduct({...newExamProduct, unitAmount: parseFloat(e.target.value) || 0})} />
              <input type="number" placeholder="Selling" className="p-3 bg-white rounded-xl font-bold text-xs" value={newExamProduct.userPrice || ''} onChange={e => setNewExamProduct({...newExamProduct, userPrice: parseFloat(e.target.value) || 0})} />
           </div>
           <button onClick={addExamProduct} className="w-full bg-indigo-500 text-white py-3 rounded-xl font-black text-[10px] uppercase shadow-lg active:scale-95 transition-all">Add Exam</button>
        </div>
      </div>
    </div>
  );
};

const BulkSmsApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><MessageSquare className="text-blue-500" /> Kudisms API Integration</h3>
        <div className="space-y-4">
          <label className="text-[10px] font-black text-gray-400 uppercase">KudiSms Token</label>
          <input type="password" placeholder="Enter Token" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold" value={settings.kudiSmsToken} onChange={e => setSettings({...settings, kudiSmsToken: e.target.value})} />
          <p className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Used for Bulk SMS sending and automated Sender ID registration.</p>
        </div>
      </div>
      <button onClick={() => showToast("SMS config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest">Save Config</button>
    </div>
  );
};

const BettingApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><TrendingUp className="text-orange-500" /> Nellobyte Betting Credentials</h3>
        <div className="grid grid-cols-2 gap-8">
           <div><label className="text-[10px] font-black text-gray-400 uppercase">User ID</label><input className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.nellobyteUserId} onChange={e => setSettings({...settings, nellobyteUserId: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">API Key</label><input type="password" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.nellobyteApiKey} onChange={e => setSettings({...settings, nellobyteApiKey: e.target.value})} /></div>
        </div>
      </div>
      <button onClick={() => showToast("Betting config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest">Save Config</button>
    </div>
  );
};

const VirtualCardApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><CreditCard className="text-blue-600" /> Stripe Issuing Credentials</h3>
        <div className="space-y-4">
          <label className="text-[10px] font-black text-gray-400 uppercase">Stripe Secret Key</label>
          <input type="password" placeholder="sk_live_..." className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.stripeSecretKey} onChange={e => setSettings({...settings, stripeSecretKey: e.target.value})} />
          <div className="p-4 bg-blue-50 text-blue-700 text-[10px] font-bold uppercase rounded-2xl border border-blue-100">Ensure Issuing capability is enabled on your Stripe account.</div>
        </div>
      </div>
      <button onClick={() => showToast("V-Card config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest">Save Config</button>
    </div>
  );
};

const GiftCardApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Gift className="text-pink-500" /> Tremendous.com Integration</h3>
        <div className="space-y-4">
          <label className="text-[10px] font-black text-gray-400 uppercase">Tremendous API Key</label>
          <input type="password" placeholder="Bearer Key" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.tremendousApiKey} onChange={e => setSettings({...settings, tremendousApiKey: e.target.value})} />
        </div>
      </div>
      <button onClick={() => showToast("Gift Card config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest">Save Config</button>
    </div>
  );
};

const TransferApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Globe className="text-indigo-600" /> Global Transfer (Stripe)</h3>
        <div className="space-y-4">
          <label className="text-[10px] font-black text-gray-400 uppercase">Stripe Secret Key (Payouts)</label>
          <input type="password" placeholder="sk_live_..." className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.stripeSecretKey} onChange={e => setSettings({...settings, stripeSecretKey: e.target.value})} />
          <p className="text-[10px] text-gray-400 font-bold uppercase">Stripe Connect is used for automated global bank payouts.</p>
        </div>
      </div>
      <button onClick={() => showToast("Transfer config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest">Save Config</button>
    </div>
  );
};

const CryptoApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Bitcoin className="text-orange-500" /> JuicyWay API Integration</h3>
        <div className="space-y-4">
          <label className="text-[10px] font-black text-gray-400 uppercase">JuicyWay API Token</label>
          <input type="password" placeholder="API Key" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.juicywayApiKey} onChange={e => setSettings({...settings, juicywayApiKey: e.target.value})} />
          <p className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">Routes conversion, swapping, and address generation for all coins.</p>
        </div>
      </div>
      <button onClick={() => showToast("Crypto config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest">Save Config</button>
    </div>
  );
};

const ApiManagerLayout: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const [activeApi, setActiveApi] = useState<string>('airtime');
  const [showDropdown, setShowDropdown] = useState(false);

  const apiOptions = [
    { id: 'airtime', label: 'Airtime (Nellobyte)', icon: <Phone size={16} /> },
    { id: 'data', label: 'Data Gifting (v6)', icon: <Wifi size={16} /> },
    { id: 'cable', label: 'Cable TV (VTpass)', icon: <Tv size={16} /> },
    { id: 'exam', label: 'Exam PIN (NaijaResult)', icon: <GraduationCap size={16} /> },
    { id: 'sms', label: 'Bulk SMS (KudiSms)', icon: <MessageSquare size={16} /> },
    { id: 'betting', label: 'Betting (Nellobyte)', icon: <TrendingUp size={16} /> },
    { id: 'electricity', label: 'Electricity (VTpass)', icon: <Zap size={16} /> },
    { id: 'vcard', label: 'Virtual Card (Stripe)', icon: <CreditCard size={16} /> },
    { id: 'giftcards', label: 'Gift Cards (Tremendous)', icon: <Gift size={16} /> },
    { id: 'transfer', label: 'Transfer (Stripe)', icon: <Landmark size={16} /> },
    { id: 'crypto', label: 'Crypto (JuicyWay)', icon: <Bitcoin size={16} /> },
  ];

  return (
    <div className="space-y-6">
       <div className="flex justify-between items-center mb-8 bg-white p-6 rounded-[32px] shadow-sm border border-gray-100">
          <div className="flex flex-col">
             <h2 className="text-2xl font-black text-gray-900 uppercase tracking-tighter">API Gateways</h2>
             <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Independent Protocol Control</span>
          </div>
          <div className="relative">
             <button onClick={() => setShowDropdown(!showDropdown)} className="bg-gray-900 text-white px-8 py-4 rounded-2xl flex items-center gap-4 text-[11px] font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">
                {apiOptions.find(o => o.id === activeApi)?.icon} {apiOptions.find(o => o.id === activeApi)?.label} <ChevronDown size={14} className={`transition-transform ${showDropdown ? 'rotate-180' : ''}`} />
             </button>
             {showDropdown && (
                <div className="absolute right-0 mt-3 w-72 bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-y-auto max-h-80 z-50 animate-slide-up scrollbar-hide">
                   {apiOptions.map(option => (
                      <button key={option.id} onClick={() => { setActiveApi(option.id); setShowDropdown(false); }} className={`w-full flex items-center gap-4 px-6 py-4 text-[10px] font-black uppercase tracking-widest transition-colors ${activeApi === option.id ? 'bg-opay-green text-white' : 'text-gray-500 hover:bg-gray-50'}`}>{option.icon} {option.label}</button>
                   ))}
                </div>
             )}
          </div>
       </div>
       <div className="animate-fade-in pb-20">
          {activeApi === 'airtime' && <AirtimeApiSettings showToast={showToast} />}
          {activeApi === 'data' && <DataApiSettings showToast={showToast} />}
          {activeApi === 'cable' && <CableApiSettings showToast={showToast} />}
          {activeApi === 'exam' && <ExamApiSettings showToast={showToast} />}
          {activeApi === 'sms' && <BulkSmsApiSettings showToast={showToast} />}
          {activeApi === 'betting' && <BettingApiSettings showToast={showToast} />}
          {activeApi === 'electricity' && <CableApiSettings showToast={showToast} />}
          {activeApi === 'vcard' && <VirtualCardApiSettings showToast={showToast} />}
          {activeApi === 'giftcards' && <GiftCardApiSettings showToast={showToast} />}
          {activeApi === 'transfer' && <TransferApiSettings showToast={showToast} />}
          {activeApi === 'crypto' && <CryptoApiSettings showToast={showToast} />}
       </div>
    </div>
  );
};

const SettingsManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  
  return (
    <div className="space-y-10 animate-fade-in pb-20 text-gray-900">
      {/* Global System Control */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3">
          <Shield className="text-indigo-500" /> Global System Control
        </h3>
        <div className="flex items-center justify-between p-6 bg-gray-50 rounded-3xl border border-gray-100">
           <div>
              <div className="text-sm font-black text-gray-800 uppercase">Frontend Maintenance Mode</div>
              <p className="text-[10px] text-gray-400 font-bold mt-1 uppercase">If enabled, all user features will be disabled except Login.</p>
           </div>
           <div 
              onClick={() => setSettings({...settings, isMaintenanceMode: !settings.isMaintenanceMode})}
              className={`w-14 h-8 rounded-full relative transition-colors cursor-pointer ${settings.isMaintenanceMode ? 'bg-red-500' : 'bg-gray-300'}`}
            >
              <div className={`absolute top-1 w-6 h-6 bg-white rounded-full transition-all shadow-sm ${settings.isMaintenanceMode ? 'left-7' : 'left-1'}`} />
           </div>
        </div>
      </div>

      {/* Loyalty & Rewards Engine */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3">
          <Gift className="text-amber-500" /> Loyalty & Rewards Engine
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Daily Check-in (Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2" 
                value={settings.bonusPerDay} onChange={e => setSettings({...settings, bonusPerDay: parseInt(e.target.value) || 0})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Referral Reward (Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2" 
                value={settings.referralBonus} onChange={e => setSettings({...settings, referralBonus: parseInt(e.target.value) || 100})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Welcome Bonus (Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2" 
                value={settings.welcomeBonus} onChange={e => setSettings({...settings, welcomeBonus: parseInt(e.target.value) || 0})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Rate (₦1 = X Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2" 
                value={settings.conversionRate} onChange={e => setSettings({...settings, conversionRate: parseInt(e.target.value) || 20})} />
           </div>
        </div>
      </div>

      {/* Security & System Guard */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3">
          <ShieldAlert className="text-red-500" /> Security & System Guard
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Min. Wallet Deposit (₦)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2 border-2 border-transparent focus:border-red-500" 
                value={settings.minDepositAmount} onChange={e => setSettings({...settings, minDepositAmount: parseInt(e.target.value) || 100})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Min. Airtime Purchase (₦)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2 border-2 border-transparent focus:border-red-500" 
                value={settings.minAirtimePurchase} onChange={e => setSettings({...settings, minAirtimePurchase: parseInt(e.target.value) || 50})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Max Daily Tx Per ID</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold mt-2 border-2 border-transparent focus:border-red-500" 
                value={settings.maxDailyTxPerId} onChange={e => setSettings({...settings, maxDailyTxPerId: parseInt(e.target.value) || 3})} />
              <p className="text-[8px] font-bold text-gray-400 mt-2 uppercase tracking-tighter">Prevents spam on a single Meter/Phone/IUC daily</p>
           </div>
        </div>
      </div>

      {/* SMTP Server Configuration */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3">
          <Mail className="text-indigo-500" /> Email (SMTP) Configuration
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">SMTP Host</label>
              <input type="text" placeholder="smtp.gmail.com" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.smtpHost} onChange={e => setSettings({...settings, smtpHost: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">SMTP Port</label>
              <input type="text" placeholder="587" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.smtpPort} onChange={e => setSettings({...settings, smtpPort: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">SMTP User</label>
              <input type="text" placeholder="noreply@domain.com" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.smtpUser} onChange={e => setSettings({...settings, smtpUser: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">SMTP Password</label>
              <input type="password" placeholder="••••••••" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.smtpPass} onChange={e => setSettings({...settings, smtpPass: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Sender Name</label>
              <input type="text" placeholder="OPay Support" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.senderName} onChange={e => setSettings({...settings, senderName: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">From Email</label>
              <input type="email" placeholder="support@domain.com" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.fromEmail} onChange={e => setSettings({...settings, fromEmail: e.target.value})} />
           </div>
        </div>
      </div>

      <button onClick={() => showToast("Global settings saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">Save Global Settings</button>
    </div>
  );
};

const UserHub: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { users, setUsers } = useApp();
  const [searchTerm, setSearchTerm] = useState('');
  const [fundAction, setFundAction] = useState<{ userId: string, type: 'credit' | 'debit' } | null>(null);
  const [fundAmount, setFundAmount] = useState('');

  const filteredUsers = users.filter(u => 
    u.fullName.toLowerCase().includes(searchTerm.toLowerCase()) || 
    u.username.toLowerCase().includes(searchTerm.toLowerCase()) ||
    u.phone.includes(searchTerm)
  );

  const handleFund = () => {
    if (!fundAction || !fundAmount) return;
    const amt = parseFloat(fundAmount);
    setUsers(prev => prev.map(u => {
      if (u.id === fundAction.userId) {
        const newBalance = fundAction.type === 'credit' ? u.walletBalance + amt : u.walletBalance - amt;
        return { ...u, walletBalance: newBalance };
      }
      return u;
    }));
    setFundAction(null);
    setFundAmount('');
    showToast(`User wallet ${fundAction.type}ed.`);
  };

  return (
    <div className="space-y-6 animate-fade-in text-gray-900">
       <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100">
          <h3 className="text-xl font-black uppercase tracking-widest mb-6">User Directory</h3>
          <div className="relative mb-6">
             <input type="text" placeholder="Search by name, phone or username..." className="w-full p-4 pl-12 bg-gray-50 rounded-2xl outline-none font-bold text-sm" value={searchTerm} onChange={e => setSearchTerm(e.target.value)} />
             <Users className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={20} />
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
               <thead><tr className="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th className="px-8 py-4">User</th><th className="px-8 py-4">Balance</th><th className="px-8 py-4 text-right">Actions</th></tr></thead>
               <tbody className="divide-y divide-gray-50">
                  {filteredUsers.map(u => (
                     <tr key={u.id} className="hover:bg-gray-50/50">
                        <td className="px-8 py-5 flex flex-col"><span className="text-xs font-bold">{u.fullName}</span><span className="text-[9px] text-gray-400">@{u.username} • {u.phone}</span></td>
                        <td className="px-8 py-5 text-xs font-black text-opay-green">{formatCurrency(u.walletBalance)}</td>
                        <td className="px-8 py-5 text-right flex justify-end gap-2">
                          <button onClick={() => setFundAction({ userId: u.id, type: 'credit' })} className="p-2 text-indigo-500 bg-indigo-50 rounded-xl" title="Credit Wallet"><Plus size={16} /></button>
                          <button onClick={() => setFundAction({ userId: u.id, type: 'debit' })} className="p-2 text-orange-500 bg-orange-50 rounded-xl" title="Debit Wallet"><ArrowUpDown size={16} /></button>
                          <button onClick={() => setUsers(prev => prev.map(usr => usr.id === u.id ? {...usr, isSuspended: !usr.isSuspended} : usr))} className={`p-2 rounded-xl ${u.isSuspended ? 'text-green-500 bg-green-50' : 'text-red-500 bg-red-50'}`}>{u.isSuspended ? <Unlock size={16}/> : <Lock size={16}/>}</button>
                        </td>
                     </tr>
                  ))}
               </tbody>
            </table>
          </div>
       </div>

       {fundAction && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white w-full max-w-sm rounded-[40px] p-10 space-y-6 shadow-2xl animate-slide-up">
            <h3 className="text-xl font-black uppercase tracking-tight">Manual {fundAction.type}</h3>
            <input type="number" placeholder="Amount" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-black text-xl" value={fundAmount} onChange={e => setFundAmount(e.target.value)} />
            <div className="flex gap-4">
              <button onClick={() => setFundAction(null)} className="flex-1 py-4 text-[10px] font-black uppercase text-gray-400">Cancel</button>
              <button onClick={handleFund} className="flex-1 py-4 bg-gray-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest">Confirm</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const DepositManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { depositRequests, setDepositRequests, setUsers, setTransactions, users } = useApp();
  
  const handleAction = (req: DepositRequest, status: 'successful' | 'rejected') => {
    if (status === 'successful') {
      const creditAmount = req.amount - req.charge;
      setUsers(prev => prev.map(u => u.id === req.userId ? { ...u, walletBalance: u.walletBalance + creditAmount } : u));
      setTransactions(prev => [{ 
        id: generateId(), 
        userId: req.userId, 
        type: 'Deposit', 
        amount: creditAmount, 
        status: 'successful', 
        date: new Date().toISOString(), 
        details: `${req.method.toUpperCase()} Deposit Verified`, 
        recipient: 'Wallet' 
      }, ...prev]);
    }
    setDepositRequests(prev => prev.map(r => r.id === req.id ? { ...r, status } : r));
    showToast(`Deposit ${status}`);
  };

  const pendingDeposits = depositRequests.filter(r => r.status === 'pending');

  return (
    <div className="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden animate-fade-in text-gray-900">
       <div className="p-8 border-b border-gray-50 font-black uppercase tracking-widest text-xs flex justify-between items-center">
          <span>Pending Deposits</span>
          <span className="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full">{pendingDeposits.length}</span>
       </div>
       <table className="w-full text-left">
          <thead><tr className="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th className="px-8 py-4">User</th><th className="px-8 py-4">Amount</th><th className="px-8 py-4 text-right">Actions</th></tr></thead>
          <tbody className="divide-y divide-gray-50">
              {pendingDeposits.map(req => (
                <tr key={req.id} className="hover:bg-gray-50/50">
                  <td className="px-8 py-5 flex flex-col"><span className="text-xs font-bold">{users.find(u => u.id === req.userId)?.fullName}</span><span className="text-[9px] uppercase">{req.method}</span></td>
                  <td className="px-8 py-5 text-xs font-black text-indigo-600">{formatCurrency(req.amount)}</td>
                  <td className="px-8 py-5 text-right flex justify-end gap-2">
                    <button onClick={() => handleAction(req, 'successful')} className="p-2 text-green-500 bg-green-50 rounded-xl"><Check size={16}/></button>
                    <button onClick={() => handleAction(req, 'rejected')} className="p-2 text-red-500 bg-red-50 rounded-xl"><Ban size={16}/></button>
                  </td>
                </tr>
              ))}
          </tbody>
       </table>
       {pendingDeposits.length === 0 && <div className="py-20 text-center text-gray-300 font-black uppercase text-[10px] tracking-widest">No pending deposits</div>}
    </div>
  );
};

const AdminDashboard: React.FC = () => {
  const { setCurrentUser, settings } = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const [toast, setToast] = useState<string | null>(null);
  const showToast = (msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); };
  const menuItems = [
    { label: 'Overview', icon: <LayoutDashboard size={20} />, path: '/admin' },
    { label: 'Users', icon: <Users size={20} />, path: '/admin/users' },
    { label: 'Deposits', icon: <Wallet size={20} />, path: '/admin/deposits' },
    { label: 'Support', icon: <MessageSquare size={20} />, path: '/admin/support' },
    { label: 'API Manager', icon: <Database size={20} />, path: '/admin/api-manager' },
    { label: 'Settings', icon: <SettingsIcon size={20} />, path: '/admin/settings' },
  ];
  return (
    <div className={`flex min-h-screen ${settings.adminTheme === 'dark' ? 'bg-gray-950 text-white' : 'bg-gray-50 text-gray-900'}`}>
      <aside className="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100">
        <div className="p-8 flex items-center gap-3"><div className="w-10 h-10 bg-opay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg shadow-green-100">O</div><span className="font-black text-lg">Admin Hub</span></div>
        <nav className="flex-1 px-4 py-4 space-y-1">{menuItems.map(item => (
            <Link key={item.path} to={item.path} className={`flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all ${location.pathname === item.path ? 'bg-opay-green text-white shadow-lg shadow-green-100' : 'text-gray-400 hover:bg-gray-50'}`}>{item.icon} {item.label}</Link>
        ))}</nav>
        <div className="p-6 border-t border-gray-100"><button onClick={() => { setCurrentUser(null); navigate('/login'); }} className="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all"><LogOut size={20} /> Sign Out</button></div>
      </aside>
      <main className="flex-1 ml-72 p-12 overflow-y-auto">
        {toast && (<div className="fixed top-8 right-8 bg-gray-900 text-white px-8 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-3 animate-slide-down border border-white/10"><CheckCircle className="text-opay-green" size={20} /><span className="text-[11px] font-black uppercase tracking-widest">{toast}</span></div>)}
        <div className="max-w-6xl mx-auto">
          <Routes>
            <Route index element={<AdminOverview />} />
            <Route path="users" element={<UserHub showToast={showToast} />} />
            <Route path="deposits" element={<DepositManager showToast={showToast} />} />
            <Route path="support" element={<SupportManager showToast={showToast} />} />
            <Route path="api-manager" element={<ApiManagerLayout showToast={showToast} />} />
            <Route path="settings" element={<SettingsManager showToast={showToast} />} />
          </Routes>
        </div>
      </main>
    </div>
  );
};

export default AdminDashboard;

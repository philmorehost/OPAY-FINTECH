
import React, { useState, useMemo } from 'react';
import { Routes, Route, Link, useNavigate, useLocation } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  LayoutDashboard, Users, Settings as SettingsIcon, Database, 
  Mail, LogOut, CreditCard, CheckCircle, 
  Trash2, Lock, Unlock, Plus, RefreshCcw, 
  Eye, ShieldCheck, Wallet, Landmark, Check, Ban, MessageCircle, Gift, LayoutGrid, Phone, Tv, Wifi, Key, ShieldAlert, Zap, Percent, Shield, GraduationCap, ArrowUpDown, ChevronDown, ChevronRight, Save, MessageSquare, Send, Bitcoin, TrendingUp, Globe, Coins, ShieldHalf, Search, X, MoreVertical, UserMinus, UserCheck, ArrowUpRight, ArrowDownLeft
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { User, KYCSubmission, DepositRequest, SmsSenderId, GiftCardRequest, Offer, ExamProvider, DataProduct, CableProvider, Transaction, SupportTicket, ElectricProvider } from '../types';

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
    <div className="space-y-8 animate-fade-in text-gray-900">
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
        <div className="lg:col-span-2 bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm overflow-hidden">
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
        <div className="bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm">
          <h3 className="text-sm font-black uppercase tracking-widest mb-6">Quick Access</h3>
          <div className="grid grid-cols-2 gap-4">
            <Link to="/admin/deposits" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Wallet className="text-purple-500 mb-3" /><span className="text-[9px] font-black uppercase">Deposits</span></Link>
            <Link to="/admin/support" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><MessageSquare className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Support</span></Link>
            <Link to="/admin/api-manager" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Database className="text-orange-500 mb-3" /><span className="text-[9px] font-black uppercase">API Hub</span></Link>
            <Link to="/admin/settings" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><SettingsIcon className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Settings</span></Link>
          </div>
        </div>
      </div>
    </div>
  );
};

// --- User Management ---
const UserHub: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { users, setUsers } = useApp();
  const [search, setSearch] = useState('');
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [adjustAmount, setAdjustAmount] = useState('');
  const [adjustType, setAdjustType] = useState<'credit' | 'debit'>('credit');

  const filteredUsers = users.filter(u => 
    u.fullName.toLowerCase().includes(search.toLowerCase()) || 
    u.username.toLowerCase().includes(search.toLowerCase()) ||
    u.phone.includes(search)
  );

  const handleToggleSuspend = (id: string) => {
    setUsers(prev => prev.map(u => u.id === id ? { ...u, isSuspended: !u.isSuspended } : u));
    showToast("User status updated");
  };

  const handleAdjustBalance = () => {
    if (!selectedUser || !adjustAmount) return;
    const amount = parseFloat(adjustAmount);
    const multiplier = adjustType === 'credit' ? 1 : -1;
    
    setUsers(prev => prev.map(u => u.id === selectedUser.id ? { ...u, walletBalance: u.walletBalance + (amount * multiplier) } : u));
    showToast(`Successfully ${adjustType}ed ${formatCurrency(amount)}`);
    setSelectedUser(null);
    setAdjustAmount('');
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div className="flex flex-col">
          <h2 className="text-2xl font-black uppercase tracking-tighter">User Directory</h2>
          <span className="text-[10px] font-bold text-gray-400 uppercase">{users.length} Active Accounts</span>
        </div>
        <div className="relative w-64">
          <input 
            className="w-full bg-gray-50 p-3 pl-10 rounded-2xl outline-none font-bold text-xs" 
            placeholder="Search users..." 
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-300" size={16} />
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {filteredUsers.map(user => (
          <div key={user.id} className="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between group">
            <div className="flex items-center gap-4">
              <div className="w-12 h-12 bg-opay-green/10 rounded-2xl flex items-center justify-center text-opay-green font-black">
                {user.fullName[0]}
              </div>
              <div>
                <div className="text-sm font-black text-gray-800">{user.fullName}</div>
                <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">@{user.username} • Tier {user.tier}</div>
                <div className="text-[10px] text-opay-green font-black mt-1">{formatCurrency(user.walletBalance)}</div>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <button 
                onClick={() => setSelectedUser(user)}
                className="p-3 bg-blue-50 text-blue-500 rounded-xl hover:bg-blue-100 transition-colors"
                title="Adjust Balance"
              >
                <ArrowUpDown size={18} />
              </button>
              <button 
                onClick={handleToggleSuspend(user.id)}
                className={`p-3 rounded-xl transition-colors ${user.isSuspended ? 'bg-green-50 text-green-500 hover:bg-green-100' : 'bg-red-50 text-red-500 hover:bg-red-100'}`}
                title={user.isSuspended ? "Unsuspend" : "Suspend"}
              >
                {user.isSuspended ? <UserCheck size={18} /> : <UserMinus size={18} />}
              </button>
            </div>
          </div>
        ))}
      </div>

      {selectedUser && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-sm rounded-[40px] p-8 space-y-6 shadow-2xl animate-slide-up">
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-black uppercase">Adjust Balance</h3>
              <button onClick={() => setSelectedUser(null)} className="p-2 bg-gray-50 rounded-full"><X size={18} /></button>
            </div>
            <p className="text-xs text-gray-400 font-bold uppercase">Adjusting balance for <span className="text-gray-800">{selectedUser.fullName}</span></p>
            
            <div className="flex bg-gray-100 p-1 rounded-2xl">
              <button onClick={() => setAdjustType('credit')} className={`flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${adjustType === 'credit' ? 'bg-white shadow-sm text-opay-green' : 'text-gray-400'}`}>Credit</button>
              <button onClick={() => setAdjustType('debit')} className={`flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${adjustType === 'debit' ? 'bg-white shadow-sm text-red-500' : 'text-gray-400'}`}>Debit</button>
            </div>

            <div className="relative">
              <input 
                type="number" 
                placeholder="0.00" 
                className="w-full p-5 bg-gray-50 rounded-2xl outline-none font-black text-2xl"
                value={adjustAmount}
                onChange={(e) => setAdjustAmount(e.target.value)}
              />
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-lg">₦</span>
            </div>

            <button 
              onClick={handleAdjustBalance}
              className={`w-full py-5 rounded-[24px] font-black uppercase tracking-widest text-white shadow-xl ${adjustType === 'credit' ? 'bg-opay-green' : 'bg-red-500'}`}
            >
              Confirm Adjustment
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

// --- Deposit Management ---
const DepositManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { depositRequests, setDepositRequests, users, setUsers, setTransactions } = useApp();
  const [activeTab, setActiveTab] = useState<'pending' | 'processed'>('pending');

  const filtered = depositRequests.filter(r => 
    activeTab === 'pending' ? r.status === 'pending' : r.status !== 'pending'
  );

  const handleAction = (id: string, status: 'successful' | 'rejected') => {
    const req = depositRequests.find(r => r.id === id);
    if (!req) return;

    if (status === 'successful') {
      const creditAmount = req.amount - req.charge;
      setUsers(prev => prev.map(u => u.id === req.userId ? { ...u, walletBalance: u.walletBalance + creditAmount } : u));
      
      const newTx: any = {
        id: generateId(),
        userId: req.userId,
        type: 'Wallet Funding',
        amount: creditAmount,
        status: 'successful',
        date: new Date().toISOString(),
        details: `Manual Deposit Approved (Ref: ${req.id})`,
        recipient: 'Wallet'
      };
      setTransactions(prev => [newTx, ...prev]);
    }

    setDepositRequests(prev => prev.map(r => r.id === id ? { ...r, status } : r));
    showToast(`Deposit ${status}`);
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div className="flex flex-col">
          <h2 className="text-2xl font-black uppercase tracking-tighter">Deposits Hub</h2>
          <span className="text-[10px] font-bold text-gray-400 uppercase">{depositRequests.filter(r => r.status === 'pending').length} Action Required</span>
        </div>
        <div className="flex bg-gray-100 p-1 rounded-2xl">
          <button onClick={() => setActiveTab('pending')} className={`px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${activeTab === 'pending' ? 'bg-white shadow-sm text-opay-green' : 'text-gray-400'}`}>Pending</button>
          <button onClick={() => setActiveTab('processed')} className={`px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${activeTab === 'processed' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400'}`}>Processed</button>
        </div>
      </div>

      <div className="space-y-4">
        {filtered.length === 0 ? (
          <div className="py-24 bg-white rounded-[40px] border border-dashed border-gray-200 text-center text-gray-300 font-black uppercase text-xs">No records found</div>
        ) : (
          filtered.map(req => {
            const user = users.find(u => u.id === req.userId);
            return (
              <div key={req.id} className="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between">
                <div className="flex items-center gap-5">
                  <div className={`w-12 h-12 rounded-2xl flex items-center justify-center ${req.method === 'manual' ? 'bg-indigo-50 text-indigo-500' : 'bg-emerald-50 text-emerald-500'}`}>
                    {req.method === 'manual' ? <Landmark size={24} /> : <CreditCard size={24} />}
                  </div>
                  <div>
                    <div className="text-sm font-black text-gray-800">{user?.fullName || 'Unknown User'}</div>
                    <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">{req.method} • {new Date(req.date).toLocaleString()}</div>
                    {req.senderName && <div className="text-[9px] text-indigo-500 font-black uppercase mt-1">Sender: {req.senderName}</div>}
                  </div>
                </div>
                <div className="flex items-center gap-8">
                  <div className="text-right">
                    <div className="text-sm font-black text-gray-900">{formatCurrency(req.amount)}</div>
                    <div className="text-[9px] text-gray-400 font-bold uppercase">Fee: {formatCurrency(req.charge)}</div>
                  </div>
                  {req.status === 'pending' ? (
                    <div className="flex gap-2">
                      <button onClick={() => handleAction(req.id, 'successful')} className="p-3 bg-green-50 text-green-600 rounded-xl hover:bg-green-100 transition-colors"><Check size={20} /></button>
                      <button onClick={() => handleAction(req.id, 'rejected')} className="p-3 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors"><Ban size={20} /></button>
                    </div>
                  ) : (
                    <span className={`px-4 py-1.5 rounded-full text-[9px] font-black uppercase ${req.status === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>{req.status}</span>
                  )}
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};

// --- Support Hub ---
const SupportManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { tickets, setTickets, users } = useApp();
  const [selectedTicket, setSelectedTicket] = useState<SupportTicket | null>(null);
  const [reply, setReply] = useState('');

  const handleReply = () => {
    if (!selectedTicket || !reply) return;
    const updatedTickets = tickets.map(t => {
      if (t.id === selectedTicket.id) {
        return {
          ...t,
          replies: [...t.replies, { author: 'Admin', message: reply, date: new Date().toISOString() }]
        };
      }
      return t;
    });
    setTickets(updatedTickets);
    showToast("Reply sent");
    setReply('');
    setSelectedTicket(null);
  };

  const handleClose = (id: string) => {
    setTickets(prev => prev.map(t => t.id === id ? { ...t, status: 'closed' } : t));
    showToast("Ticket closed");
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div className="flex flex-col">
          <h2 className="text-2xl font-black uppercase tracking-tighter">Support Command</h2>
          <span className="text-[10px] font-bold text-gray-400 uppercase">{tickets.filter(t => t.status === 'open').length} Unresolved Issues</span>
        </div>
        <button className="p-3 bg-gray-50 text-gray-400 rounded-xl"><RefreshCcw size={20} /></button>
      </div>

      <div className="space-y-4">
        {tickets.map(ticket => {
          const user = users.find(u => u.id === ticket.userId);
          return (
            <div key={ticket.id} className="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between group cursor-pointer hover:border-opay-green transition-all" onClick={() => setSelectedTicket(ticket)}>
              <div className="flex items-center gap-5">
                <div className={`w-12 h-12 rounded-2xl flex items-center justify-center ${ticket.status === 'open' ? 'bg-amber-50 text-amber-500' : 'bg-gray-100 text-gray-400'}`}>
                  <MessageSquare size={24} />
                </div>
                <div>
                  <div className="text-sm font-black text-gray-800">{ticket.subject}</div>
                  <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">{user?.fullName || 'User'} • {new Date(ticket.createdAt).toLocaleDateString()}</div>
                </div>
              </div>
              <div className="flex items-center gap-4">
                <span className={`px-4 py-1.5 rounded-full text-[9px] font-black uppercase ${ticket.status === 'open' ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600'}`}>{ticket.status}</span>
                <ChevronRight size={18} className="text-gray-300 group-hover:text-opay-green transition-colors" />
              </div>
            </div>
          );
        })}
      </div>

      {selectedTicket && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-lg rounded-[40px] overflow-hidden shadow-2xl animate-slide-up flex flex-col max-h-[85vh]">
            <div className="p-8 border-b border-gray-50 flex justify-between items-center bg-gray-50">
              <div>
                <h3 className="text-lg font-black uppercase tracking-tight">{selectedTicket.subject}</h3>
                <p className="text-[10px] font-bold text-gray-400 uppercase">TICKET ID: {selectedTicket.id}</p>
              </div>
              <button onClick={() => setSelectedTicket(null)} className="p-2 bg-white rounded-full shadow-sm"><X size={20} /></button>
            </div>
            
            <div className="p-8 overflow-y-auto space-y-6 flex-1 scrollbar-hide">
              <div className="bg-opay-green/5 p-5 rounded-3xl border border-opay-green/10">
                <div className="text-[10px] font-black text-opay-green uppercase mb-2">Original Message</div>
                <div className="text-sm text-gray-800 leading-relaxed font-medium" dangerouslySetInnerHTML={{ __html: selectedTicket.message }} />
              </div>

              {selectedTicket.replies.map((r, i) => (
                <div key={i} className={`flex flex-col ${r.author === 'Admin' ? 'items-end' : 'items-start'}`}>
                  <div className={`max-w-[80%] p-4 rounded-2xl text-xs font-bold ${r.author === 'Admin' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-800'}`}>
                    {r.message}
                  </div>
                  <span className="text-[8px] font-black text-gray-300 uppercase mt-1">{new Date(r.date).toLocaleString()}</span>
                </div>
              ))}
            </div>

            <div className="p-8 border-t border-gray-50 space-y-4">
              <textarea 
                className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs min-h-[100px] border border-transparent focus:border-opay-green" 
                placeholder="Type your response..."
                value={reply}
                onChange={(e) => setReply(e.target.value)}
              />
              <div className="flex gap-4">
                <button 
                  onClick={() => handleClose(selectedTicket.id)}
                  className="flex-1 py-4 border-2 border-red-50 text-red-500 rounded-2xl font-black text-[10px] uppercase tracking-widest active:scale-95"
                >
                  Close Ticket
                </button>
                <button 
                  onClick={handleReply}
                  className="flex-[2] py-4 bg-opay-green text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-green-100 active:scale-95"
                >
                  Send Reply
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// --- API Hub Hub Sub-Pages (Existing but moved for clarity) ---

const AirtimeBettingSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Phone className="text-blue-500" /> Nellobyte (Airtime & Betting)</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div><label className="text-[10px] font-black text-gray-400 uppercase">User ID</label><input className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.nellobyteUserId} onChange={e => setSettings({...settings, nellobyteUserId: e.target.value})} /></div>
          <div><label className="text-[10px] font-black text-gray-400 uppercase">API Key</label><input type="password" placeholder="key" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.nellobyteApiKey} onChange={e => setSettings({...settings, nellobyteApiKey: e.target.value})} /></div>
        </div>
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Percent className="text-blue-500" /> Airtime User Discounts (%)</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
           {['mtn', 'glo', 'airtel', 'nineMobile'].map(net => (
             <div key={net} className="bg-gray-50 p-6 rounded-3xl border border-gray-100">
                <label className="text-[10px] font-black text-gray-400 uppercase mb-3 block">{net}</label>
                <input type="number" step="0.1" className="w-full bg-white p-3 rounded-xl font-black text-blue-600" value={settings.airtimeDiscounts[net as keyof typeof settings.airtimeDiscounts]} onChange={e => setSettings({...settings, airtimeDiscounts: { ...settings.airtimeDiscounts, [net]: parseFloat(e.target.value) || 0 }})} />
             </div>
           ))}
        </div>
      </div>
    </div>
  );
};

const DataApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [newData, setNewData] = useState<Partial<DataProduct>>({ networkId: '', type: 'sme-data', size: '', apiQuantityCode: '', userPrice: 0, enabled: true });
  const addData = () => {
    if (!newData.networkId || !newData.size) { showToast("Fill all fields"); return; }
    setSettings({ ...settings, dataProducts: [...settings.dataProducts, { ...newData, id: generateId() } as DataProduct] });
    setNewData({ networkId: '', type: 'sme-data', size: '', apiQuantityCode: '', userPrice: 0, enabled: true });
    showToast("Data package added.");
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Wifi className="text-emerald-500" /> Data Gifting (v6) Key</h3>
        <input type="password" placeholder="API Token" className="w-full p-5 bg-gray-50 rounded-2xl outline-none font-bold" value={settings.dataGiftingApiKey} onChange={e => setSettings({...settings, dataGiftingApiKey: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Plus className="text-emerald-500" /> Manage Plans</h3>
        <div className="bg-gray-50 p-8 rounded-[32px] space-y-6">
           <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              <select className="p-3 bg-white rounded-xl font-bold text-xs" value={newData.networkId} onChange={e => setNewData({...newData, networkId: e.target.value})}><option value="">Select Network</option>{settings.dataNetworks.map(n => <option key={n.id} value={n.id}>{n.name}</option>)}</select>
              <input placeholder="Size (1GB)" className="p-3 bg-white rounded-xl font-bold text-xs" value={newData.size} onChange={e => setNewData({...newData, size: e.target.value})} />
              <select className="p-3 bg-white rounded-xl font-bold text-xs" value={newData.type} onChange={e => setNewData({...newData, type: e.target.value})}><option value="sme-data">SME</option><option value="cg-data">CG</option><option value="direct-data">Direct</option></select>
              <input placeholder="API Code" className="p-3 bg-white rounded-xl font-bold text-xs" value={newData.apiQuantityCode} onChange={e => setNewData({...newData, apiQuantityCode: e.target.value})} />
              <input type="number" placeholder="Price (₦)" className="p-3 bg-white rounded-xl font-bold text-xs" value={newData.userPrice || ''} onChange={e => setNewData({...newData, userPrice: parseFloat(e.target.value) || 0})} />
           </div>
           <button onClick={addData} className="w-full bg-emerald-500 text-white py-4 rounded-xl font-black text-[10px] uppercase">Add Data Plan</button>
        </div>
      </div>
    </div>
  );
};

const CableTVApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [isSyncing, setIsSyncing] = useState(false);
  const syncCable = async (serviceId: string) => {
    setIsSyncing(true);
    try {
      const headers = { 'api-key': settings.vtPassApiKey, 'public-key': settings.vtPassPublicKey, 'Content-Type': 'application/json' };
      const res = await fetch(`https://vtpass.com/api/service-variations?serviceID=${serviceId}`, { headers });
      const data = await res.json();
      if (data.code === '000' || data.response_description === '000') {
        setSettings(prev => ({ ...prev, cableProviders: prev.cableProviders.map(p => p.serviceId === serviceId ? { ...p, variations: data.content.variations } : p) }));
        showToast(`Synced ${serviceId.toUpperCase()}`);
      }
    } catch (e) { showToast("Sync failed"); }
    finally { setIsSyncing(false); }
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Tv className="text-red-500" /> VTpass Credentials</h3>
        <div className="grid grid-cols-2 gap-8">
           <input type="password" placeholder="API Key" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.vtPassApiKey} onChange={e => setSettings({...settings, vtPassApiKey: e.target.value})} />
           <input type="password" placeholder="Public Key" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.vtPassPublicKey} onChange={e => setSettings({...settings, vtPassPublicKey: e.target.value})} />
        </div>
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><RefreshCcw className="text-red-500" /> Synced Packages</h3>
        <div className="grid grid-cols-4 gap-4">
           {settings.cableProviders.map(p => (
             <div key={p.id} className="p-5 bg-gray-50 rounded-[32px] flex flex-col items-center gap-3 border border-gray-100">
                <span className="text-[10px] font-black uppercase">{p.name}</span>
                <button onClick={() => syncCable(p.serviceId)} className="p-3 bg-white text-opay-green rounded-xl shadow-sm"><RefreshCcw size={16} className={isSyncing ? 'animate-spin' : ''} /></button>
             </div>
           ))}
        </div>
      </div>
    </div>
  );
};

const ElectricApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Zap className="text-yellow-500" /> VTpass Electricity Control</h3>
        <div className="grid grid-cols-1 gap-6">
           <div className="overflow-x-auto">
             <table className="w-full text-left">
                <thead><tr className="text-[10px] font-black text-gray-400 uppercase border-b border-gray-50"><th className="pb-4">Disco Provider</th><th className="pb-4">User Discount (%)</th><th className="pb-4 text-right">Status</th></tr></thead>
                <tbody className="divide-y divide-gray-50">
                   {settings.electricProviders.map(p => (
                     <tr key={p.id}>
                        <td className="py-4 font-bold">{p.name}</td>
                        <td className="py-4"><input type="number" step="0.1" className="w-20 bg-gray-100 p-2 rounded-lg font-black text-xs" value={p.discountPercent} onChange={e => setSettings({...settings, electricProviders: settings.electricProviders.map(ep => ep.id === p.id ? {...ep, discountPercent: parseFloat(e.target.value) || 0} : ep)})} /></td>
                        <td className="py-4 text-right"><button className={`px-4 py-2 rounded-xl text-[10px] font-black ${p.enabled ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'}`} onClick={() => setSettings({...settings, electricProviders: settings.electricProviders.map(ep => ep.id === p.id ? {...ep, enabled: !ep.enabled} : ep)})}>{p.enabled ? 'ENABLED' : 'DISABLED'}</button></td>
                     </tr>
                   ))}
                </tbody>
             </table>
           </div>
        </div>
      </div>
    </div>
  );
};

const ExamApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [newExam, setNewExam] = useState<Partial<ExamProvider>>({ name: '', unitAmount: 0, userPrice: 0, enabled: true, routingProvider: 'naija', serviceId: '', variationCode: '' });
  const addExam = () => {
    if (!newExam.name || !newExam.userPrice) { showToast("Fill fields"); return; }
    setSettings({ ...settings, examProviders: [...settings.examProviders, { ...newExam, id: generateId(), availability: 'Available' } as ExamProvider] });
    setNewExam({ name: '', unitAmount: 0, userPrice: 0, enabled: true, routingProvider: 'naija', serviceId: '', variationCode: '' });
    showToast("Exam PIN added.");
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><GraduationCap className="text-purple-500" /> Exam PIN (NaijaResult)</h3>
        <input type="password" placeholder="Naija API Token" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.examApiKey} onChange={e => setSettings({...settings, examApiKey: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Plus className="text-purple-500" /> Add New Exam Product</h3>
        <div className="bg-gray-50 p-8 rounded-[32px] space-y-6">
           <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <input placeholder="Exam (e.g. WAEC)" className="p-3 bg-white rounded-xl text-xs" value={newExam.name} onChange={e => setNewExam({...newExam, name: e.target.value})} />
              <select className="p-3 bg-white rounded-xl text-xs" value={newExam.routingProvider} onChange={e => setNewExam({...newExam, routingProvider: e.target.value as any})}><option value="naija">NaijaResult</option><option value="vtpass">VTPass</option></select>
              <input type="number" placeholder="Cost Price" className="p-3 bg-white rounded-xl text-xs" value={newExam.unitAmount || ''} onChange={e => setNewExam({...newExam, unitAmount: parseFloat(e.target.value) || 0})} />
              <input type="number" placeholder="Sale Price" className="p-3 bg-white rounded-xl text-xs" value={newExam.userPrice || ''} onChange={e => setNewExam({...newExam, userPrice: parseFloat(e.target.value) || 0})} />
           </div>
           <button onClick={addExam} className="w-full bg-indigo-500 text-white py-4 rounded-xl font-black text-[10px] uppercase">Register Exam PIN</button>
        </div>
      </div>
    </div>
  );
};

const BulkSmsApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings, smsSenderIds, setSmsSenderIds } = useApp();
  const handleReview = (id: string, status: 'approved' | 'rejected') => {
    setSmsSenderIds(prev => prev.map(s => s.id === id ? { ...s, status } : s));
    showToast(`Sender ID ${status}`);
  };
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><MessageSquare className="text-blue-500" /> KudiSms Gateway</h3>
        <input type="password" placeholder="KudiSms Token" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.kudiSmsToken} onChange={e => setSettings({...settings, kudiSmsToken: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><ShieldCheck className="text-blue-500" /> Manual Sender ID Review</h3>
        <div className="space-y-4">
           {smsSenderIds.filter(s => s.status === 'pending').map(s => (
             <div key={s.id} className="p-6 bg-gray-50 rounded-[32px] flex justify-between items-center border border-gray-100">
                <div><div className="text-sm font-black uppercase">{s.name}</div><p className="text-[10px] text-gray-400 font-bold italic mt-1">Sample: "{s.sampleMessage}"</p></div>
                <div className="flex gap-2">
                   <button onClick={() => handleReview(s.id, 'approved')} className="p-3 bg-green-50 text-green-600 rounded-2xl hover:bg-green-100"><Check size={18} /></button>
                   <button onClick={() => handleReview(s.id, 'rejected')} className="p-3 bg-red-50 text-red-600 rounded-2xl hover:bg-red-100"><Ban size={18} /></button>
                </div>
             </div>
           ))}
           {smsSenderIds.filter(s => s.status === 'pending').length === 0 && <div className="py-12 text-center text-gray-300 font-black uppercase text-xs">No pending requests</div>}
        </div>
      </div>
    </div>
  );
};

const GlobalGatewaySettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Globe className="text-indigo-600" /> Stripe (Cards & Transfer)</h3>
        <input type="password" placeholder="sk_live_..." className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.stripeSecretKey} onChange={e => setSettings({...settings, stripeSecretKey: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Gift className="text-pink-500" /> Tremendous (Gift Cards)</h3>
        <input type="password" placeholder="Tremendous API Key" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.tremendousApiKey} onChange={e => setSettings({...settings, tremendousApiKey: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Bitcoin className="text-orange-500" /> JuicyWay (Crypto)</h3>
        <input type="password" placeholder="JuicyWay API Token" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.juicywayApiKey} onChange={e => setSettings({...settings, juicywayApiKey: e.target.value})} />
      </div>
    </div>
  );
};

const ApiManagerLayout: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const [activeApi, setActiveApi] = useState<string>('airtime');
  const [showDropdown, setShowDropdown] = useState(false);

  const apiOptions = [
    { id: 'airtime', label: '1. Airtime & Betting (Nellobyte)', icon: <Phone size={16} /> },
    { id: 'data', label: '2. Data Hub (v6)', icon: <Wifi size={16} /> },
    { id: 'cable', label: '3. Cable TV (VTpass)', icon: <Tv size={16} /> },
    { id: 'electricity', label: '4. Electricity (VTpass)', icon: <Zap size={16} /> },
    { id: 'exam', label: '5. Education PINs (Exam)', icon: <GraduationCap size={16} /> },
    { id: 'sms', label: '6. Bulk SMS (KudiSms)', icon: <MessageSquare size={16} /> },
    { id: 'gateways', label: '7. Global Hub (Stripe/JuicyWay)', icon: <Globe size={16} /> },
  ];

  return (
    <div className="space-y-6">
       <div className="flex justify-between items-center mb-8 bg-white p-6 rounded-[32px] shadow-sm border border-gray-100">
          <div className="flex flex-col text-gray-900"><h2 className="text-2xl font-black uppercase tracking-tighter">Independent API Hub</h2><span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fintech Protocol Routing</span></div>
          <div className="relative">
             <button onClick={() => setShowDropdown(!showDropdown)} className="bg-gray-900 text-white px-8 py-4 rounded-2xl flex items-center gap-4 text-[11px] font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all">
                {apiOptions.find(o => o.id === activeApi)?.icon} {apiOptions.find(o => o.id === activeApi)?.label} <ChevronDown size={14} className={`transition-transform ${showDropdown ? 'rotate-180' : ''}`} />
             </button>
             {showDropdown && (
                <div className="absolute right-0 mt-3 w-80 bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden z-50 animate-slide-up">
                   {apiOptions.map(option => (
                      <button key={option.id} onClick={() => { setActiveApi(option.id); setShowDropdown(false); }} className={`w-full flex items-center gap-4 px-6 py-4 text-[10px] font-black uppercase tracking-widest transition-colors ${activeApi === option.id ? 'bg-opay-green text-white' : 'text-gray-500 hover:bg-gray-50'}`}>{option.icon} {option.label}</button>
                   ))}
                </div>
             )}
          </div>
       </div>
       <div className="animate-fade-in pb-20">
          {activeApi === 'airtime' && <AirtimeBettingSettings showToast={showToast} />}
          {activeApi === 'data' && <DataApiSettings showToast={showToast} />}
          {activeApi === 'cable' && <CableTVApiSettings showToast={showToast} />}
          {activeApi === 'electricity' && <ElectricApiSettings showToast={showToast} />}
          {activeApi === 'exam' && <ExamApiSettings showToast={showToast} />}
          {activeApi === 'sms' && <BulkSmsApiSettings showToast={showToast} />}
          {activeApi === 'gateways' && <GlobalGatewaySettings showToast={showToast} />}
       </div>
    </div>
  );
};

const SettingsManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-10 animate-fade-in pb-20 text-gray-900">
      {/* SMTP Configuration */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Mail className="text-indigo-500" /> SMTP Configuration</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Host</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.smtpHost} onChange={e => setSettings({...settings, smtpHost: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Port</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.smtpPort} onChange={e => setSettings({...settings, smtpPort: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">User</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.smtpUser} onChange={e => setSettings({...settings, smtpUser: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Password</label><input type="password" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.smtpPass} onChange={e => setSettings({...settings, smtpPass: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Sender Name</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.senderName} onChange={e => setSettings({...settings, senderName: e.target.value})} /></div>
        </div>
      </div>

      {/* Global System Control */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><ShieldAlert className="text-red-500" /> Global System Control</h3>
        <div className="flex items-center justify-between p-6 bg-gray-50 rounded-3xl border border-gray-100">
           <div><div className="text-sm font-black text-gray-800 uppercase">Frontend Maintenance Mode</div><p className="text-[10px] text-gray-400 font-bold uppercase">Disables all user features except login.</p></div>
           <div onClick={() => setSettings({...settings, isMaintenanceMode: !settings.isMaintenanceMode})} className={`w-14 h-8 rounded-full relative transition-colors cursor-pointer ${settings.isMaintenanceMode ? 'bg-red-500' : 'bg-gray-300'}`}><div className={`absolute top-1 w-6 h-6 bg-white rounded-full transition-all shadow-sm ${settings.isMaintenanceMode ? 'left-7' : 'left-1'}`} /></div>
        </div>
      </div>

      {/* Loyalty & Rewards Engine */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Coins className="text-yellow-500" /> Loyalty & Rewards Engine</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Daily Check-in (Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.bonusPerDay} onChange={e => setSettings({...settings, bonusPerDay: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Referral Reward (Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.referralBonus} onChange={e => setSettings({...settings, referralBonus: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Welcome Bonus (Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.welcomeBonus} onChange={e => setSettings({...settings, welcomeBonus: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Rate (₦1 = X Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.conversionRate} onChange={e => setSettings({...settings, conversionRate: parseInt(e.target.value) || 0})} /></div>
        </div>
      </div>

      {/* Security & System Guard */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><ShieldHalf className="text-opay-green" /> Security & System Guard</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Min. Wallet Deposit (₦)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.minDepositAmount} onChange={e => setSettings({...settings, minDepositAmount: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Min. Airtime Purchase (₦)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.minAirtimePurchase} onChange={e => setSettings({...settings, minAirtimePurchase: parseInt(e.target.value) || 0})} /></div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Max Daily Tx Per ID</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2" value={settings.maxDailyTxPerId} onChange={e => setSettings({...settings, maxDailyTxPerId: parseInt(e.target.value) || 0})} />
              <p className="text-[8px] font-bold text-gray-400 uppercase mt-2">Prevents spam on a single Meter/Phone/IUC daily</p>
           </div>
        </div>
      </div>

      <button onClick={() => showToast("Global config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase tracking-widest hover:scale-[1.02] active:scale-[0.98] transition-all shadow-xl shadow-gray-200">Save Config</button>
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
    { label: 'API Hub', icon: <Database size={20} />, path: '/admin/api-manager' },
    { label: 'Settings', icon: <SettingsIcon size={20} />, path: '/admin/settings' },
  ];
  return (
    <div className="flex min-h-screen bg-gray-50 text-gray-900">
      <aside className="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100">
        <div className="p-8 flex items-center gap-3"><div className="w-10 h-10 bg-opay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">O</div><span className="font-black text-lg">Admin Hub</span></div>
        <nav className="flex-1 px-4 py-4 space-y-1">{menuItems.map(item => (
            <Link key={item.path} to={item.path} className={`flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all ${location.pathname.startsWith(item.path) && (item.path !== '/admin' || location.pathname === '/admin') ? 'bg-opay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'}`}>{item.icon} {item.label}</Link>
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

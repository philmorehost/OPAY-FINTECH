
import React, { useState, useMemo, useEffect } from 'react';
import { Routes, Route, Link, useNavigate, useLocation } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendBroadcastEmail } from '../utils';
import { 
  LayoutDashboard, Users, Settings as SettingsIcon, Database, 
  Mail, LogOut, CreditCard, CheckCircle, 
  RefreshCcw, Eye, ShieldCheck, Wallet, Landmark, Check, 
  Ban, MessageCircle, Gift, Phone, Tv, Wifi, ShieldAlert, 
  Zap, Percent, Coins, GraduationCap, ArrowUpDown, ChevronDown, 
  ChevronRight, X, Search, UserMinus, UserCheck, FileText, 
  BadgeCheck, XCircle, ArrowRightLeft, LogIn, Send, Bitcoin, Globe, ShieldHalf,
  MessageSquare, Plus, TrendingUp
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { User, KYCSubmission, DataProduct, ExamProvider, Transaction, SupportTicket, BettingProvider } from '../types';

interface AdminSubPageProps {
  showToast: (msg: string) => void;
}

const VOLUME_DATA = [
  { date: '01 May', vol: 1200000 }, { date: '02 May', vol: 1500000 }, { date: '03 May', vol: 1100000 },
  { date: '04 May', vol: 2200000 }, { date: '05 May', vol: 1800000 }, { date: '06 May', vol: 2900000 },
  { date: '07 May', vol: 2400000 },
];

const AdminOverview: React.FC = () => {
  const { users, kycSubmissions, transactions } = useApp();
  const stats = [
    { label: 'Total Users', value: users.length, icon: <Users className="text-blue-500" />, color: 'bg-blue-50' },
    { label: 'Platform Balance', value: formatCurrency(users.reduce((acc, u) => acc + u.walletBalance, 0)), icon: <CreditCard className="text-green-500" />, color: 'bg-green-50' },
    { label: 'Pending KYC', value: kycSubmissions.filter(k => k.status === 'pending').length, icon: <ShieldCheck className="text-purple-500" />, color: 'bg-purple-50' },
    { label: 'Total Transactions', value: transactions.length, icon: <ArrowRightLeft className="text-amber-500" />, color: 'bg-amber-50' },
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
              <AreaChart data={VOLUME_DATA}>
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
            <Link to="/admin/deposits" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors"><Wallet className="text-purple-500 mb-3" /><span className="text-[9px] font-black uppercase">Deposits</span></Link>
            <Link to="/admin/kyc" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors"><ShieldCheck className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">KYC Review</span></Link>
            <Link to="/admin/transactions" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors"><FileText className="text-pink-500 mb-3" /><span className="text-[9px] font-black uppercase">All Tx</span></Link>
            <Link to="/admin/settings" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-billpay-green/10 transition-colors"><SettingsIcon className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Settings</span></Link>
          </div>
        </div>
      </div>
    </div>
  );
};

const UserHub: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { users, setUsers, setCurrentUser } = useApp();
  const navigate = useNavigate();
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

  const handleImpersonate = (user: User) => {
    setCurrentUser(user);
    showToast(`Logged in as ${user.fullName}`);
    navigate('/dashboard');
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

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {filteredUsers.map(user => (
          <div key={user.id} className="bg-white p-5 rounded-[32px] border border-gray-100 shadow-sm flex flex-col justify-between group hover:border-billpay-green transition-all">
            <div className="flex items-center gap-4 mb-4">
              <div className="w-12 h-12 bg-billpay-green/10 rounded-2xl flex items-center justify-center text-billpay-green font-black shrink-0">
                {user.fullName[0]}
              </div>
              <div className="min-w-0">
                <div className="text-sm font-black text-gray-800 truncate">{user.fullName}</div>
                <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight truncate">@{user.username} • T{user.tier}</div>
                <div className="text-[10px] text-billpay-green font-black mt-1">{formatCurrency(user.walletBalance)}</div>
              </div>
            </div>
            
            <div className="flex items-center justify-between pt-4 border-t border-gray-50">
              <div className="flex gap-1.5">
                <button 
                  onClick={() => handleImpersonate(user)} 
                  className="p-2.5 bg-billpay-green/10 text-billpay-green rounded-xl hover:bg-billpay-green hover:text-white transition-colors"
                  title="Login to account"
                >
                  <LogIn size={16} />
                </button>
                <button 
                  onClick={() => setSelectedUser(user)} 
                  className="p-2.5 bg-blue-50 text-blue-500 rounded-xl hover:bg-blue-500 hover:text-white transition-colors"
                  title="Adjust Balance"
                >
                  <ArrowUpDown size={16} />
                </button>
              </div>
              <button 
                onClick={() => handleToggleSuspend(user.id)} 
                className={`flex items-center gap-1.5 px-3 py-2 rounded-xl transition-all font-black text-[9px] uppercase ${user.isSuspended ? 'bg-red-50 text-red-500' : 'bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500'}`}
              >
                {user.isSuspended ? <><UserCheck size={14} /> Suspended</> : <><UserMinus size={14} /> Suspend</>}
              </button>
            </div>
          </div>
        ))}
      </div>

      {selectedUser && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-sm rounded-[40px] p-8 space-y-6 shadow-2xl animate-slide-up">
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-black uppercase tracking-tight">Adjust Balance</h3>
              <button onClick={() => setSelectedUser(null)} className="p-2 bg-gray-50 rounded-full hover:bg-gray-100"><X size={18} /></button>
            </div>
            <p className="text-xs text-gray-400 font-bold uppercase">Adjusting balance for <span className="text-gray-800">{selectedUser.fullName}</span></p>
            <div className="flex bg-gray-100 p-1 rounded-2xl">
              <button onClick={() => setAdjustType('credit')} className={`flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${adjustType === 'credit' ? 'bg-white shadow-sm text-billpay-green' : 'text-gray-400'}`}>Credit</button>
              <button onClick={() => setAdjustType('debit')} className={`flex-1 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${adjustType === 'debit' ? 'bg-white shadow-sm text-red-500' : 'text-gray-400'}`}>Debit</button>
            </div>
            <div className="relative">
              <input type="number" placeholder="0.00" className="w-full p-5 bg-gray-50 rounded-2xl outline-none font-black text-2xl" value={adjustAmount} onChange={(e) => setAdjustAmount(e.target.value)} />
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 font-black text-lg">₦</span>
            </div>
            <button onClick={handleAdjustBalance} className={`w-full py-5 rounded-[24px] font-black uppercase tracking-widest text-white shadow-xl ${adjustType === 'credit' ? 'bg-billpay-green shadow-green-100' : 'bg-red-500 shadow-red-100'}`}>Confirm Adjustment</button>
          </div>
        </div>
      )}
    </div>
  );
};

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
      const newTx: any = { id: generateId(), userId: req.userId, type: 'Wallet Funding', amount: creditAmount, status: 'successful', date: new Date().toISOString(), details: `Manual Deposit Approved`, recipient: 'Wallet' };
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
          <button onClick={() => setActiveTab('pending')} className={`px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${activeTab === 'pending' ? 'bg-white shadow-sm text-billpay-green' : 'text-gray-400'}`}>Pending</button>
          <button onClick={() => setActiveTab('processed')} className={`px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${activeTab === 'processed' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400'}`}>Processed</button>
        </div>
      </div>
      <div className="space-y-4">
        {filtered.length === 0 ? <div className="py-24 bg-white rounded-[40px] text-center text-gray-300 font-black uppercase text-xs">No records found</div> : filtered.map(req => (
              <div key={req.id} className="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between">
                <div className="flex items-center gap-5">
                  <div className={`w-12 h-12 rounded-2xl flex items-center justify-center ${req.method === 'manual' ? 'bg-indigo-50 text-indigo-500' : 'bg-emerald-50 text-emerald-500'}`}>{req.method === 'manual' ? <Landmark size={24} /> : <CreditCard size={24} />}</div>
                  <div><div className="text-sm font-black text-gray-800">{users.find(u => u.id === req.userId)?.fullName || 'Unknown'}</div><div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">{req.method} • {new Date(req.date).toLocaleString()}</div></div>
                </div>
                <div className="flex items-center gap-8">
                  <div className="text-right"><div className="text-sm font-black text-gray-900">{formatCurrency(req.amount)}</div><div className="text-[9px] text-gray-400 font-bold uppercase">Fee: {formatCurrency(req.charge)}</div></div>
                  {req.status === 'pending' ? <div className="flex gap-2"><button onClick={() => handleAction(req.id, 'successful')} className="p-3 bg-green-50 text-green-600 rounded-xl hover:bg-green-100 transition-colors"><Check size={20} /></button><button onClick={() => handleAction(req.id, 'rejected')} className="p-3 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors"><Ban size={20} /></button></div> : <span className={`px-4 py-1.5 rounded-full text-[9px] font-black uppercase ${req.status === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}>{req.status}</span>}
                </div>
              </div>
        ))}
      </div>
    </div>
  );
};

const SupportManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { tickets, setTickets, users } = useApp();
  const [selectedTicket, setSelectedTicket] = useState<SupportTicket | null>(null);
  const [reply, setReply] = useState('');

  const handleReply = () => {
    if (!selectedTicket || !reply) return;
    setTickets(prev => prev.map(t => t.id === selectedTicket.id ? { ...t, replies: [...t.replies, { author: 'Admin', message: reply, date: new Date().toISOString() }] } : t));
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
        <div className="flex flex-col"><h2 className="text-2xl font-black uppercase tracking-tighter">Support Hub</h2><span className="text-[10px] font-bold text-gray-400 uppercase">{tickets.filter(t => t.status === 'open').length} Open Tickets</span></div>
        <button className="p-3 bg-gray-50 text-gray-400 rounded-xl"><RefreshCcw size={20} /></button>
      </div>
      <div className="space-y-4">
        {tickets.map(ticket => (
          <div key={ticket.id} className="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between group cursor-pointer hover:border-billpay-green transition-all" onClick={() => setSelectedTicket(ticket)}>
            <div className="flex items-center gap-5"><div className={`w-12 h-12 rounded-2xl flex items-center justify-center ${ticket.status === 'open' ? 'bg-amber-50 text-amber-500' : 'bg-gray-100 text-gray-400'}`}><MessageSquare size={24} /></div><div><div className="text-sm font-black text-gray-800">{ticket.subject}</div><div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">{users.find(u => u.id === ticket.userId)?.fullName || 'User'} • {new Date(ticket.createdAt).toLocaleDateString()}</div></div></div>
            <div className="flex items-center gap-4"><span className={`px-4 py-1.5 rounded-full text-[9px] font-black uppercase ${ticket.status === 'open' ? 'bg-amber-600' : 'bg-green-600'}`}>{ticket.status}</span><ChevronRight size={18} className="text-gray-300" /></div>
          </div>
        ))}
      </div>
      {selectedTicket && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-lg rounded-[40px] overflow-hidden shadow-2xl animate-slide-up flex flex-col max-h-[85vh]">
            <div className="p-8 border-b border-gray-50 flex justify-between items-center bg-gray-50"><div><h3 className="text-lg font-black uppercase">{selectedTicket.subject}</h3><p className="text-[10px] font-bold text-gray-400 uppercase">ID: {selectedTicket.id}</p></div><button onClick={() => setSelectedTicket(null)} className="p-2 bg-white rounded-full shadow-sm"><X size={20} /></button></div>
            <div className="p-8 overflow-y-auto space-y-6 flex-1"><div className="bg-billpay-green/5 p-5 rounded-3xl border border-billpay-green/10 text-sm" dangerouslySetInnerHTML={{ __html: selectedTicket.message }} />{selectedTicket.replies.map((r, i) => (<div key={i} className={`flex flex-col ${r.author === 'Admin' ? 'items-end' : 'items-start'}`}><div className={`max-w-[80%] p-4 rounded-2xl text-xs font-bold ${r.author === 'Admin' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-800'}`}>{r.message}</div><span className="text-[8px] font-black text-gray-300 uppercase mt-1">{new Date(r.date).toLocaleString()}</span></div>))}</div>
            <div className="p-8 border-t border-gray-50 space-y-4"><textarea className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs min-h-[100px]" placeholder="Reply..." value={reply} onChange={(e) => setReply(e.target.value)} /><div className="flex gap-4"><button onClick={() => handleClose(selectedTicket.id)} className="flex-1 py-4 text-red-500 font-black text-[10px] uppercase">Close</button><button onClick={handleReply} className="flex-[2] py-4 bg-billpay-green text-white rounded-2xl font-black text-[10px] uppercase shadow-xl">Send</button></div></div>
          </div>
        </div>
      )}
    </div>
  );
};

const KycManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { kycSubmissions, setKycSubmissions, users, setUsers } = useApp();
  const [selectedSub, setSelectedSub] = useState<KYCSubmission | null>(null);
  const [activeTab, setActiveTab] = useState<'pending' | 'reviewed'>('pending');

  const filtered = kycSubmissions.filter(s => 
    activeTab === 'pending' ? s.status === 'pending' : s.status !== 'pending'
  );

  const handleKycAction = (id: string, status: 'verified' | 'rejected') => {
    const sub = kycSubmissions.find(s => s.id === id);
    if (!sub) return;

    setKycSubmissions(prev => prev.map(s => s.id === id ? { ...s, status } : s));
    setUsers(prev => prev.map(u => u.id === sub.userId ? { ...u, kycStatus: status, tier: status === 'verified' ? 3 : u.tier } : u));
    
    showToast(`KYC submission ${status}`);
    setSelectedSub(null);
  };

  return (
    <div className="space-y-6 animate-fade-in">
       <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-between">
          <div className="flex flex-col">
            <h2 className="text-2xl font-black uppercase tracking-tighter">KYC Desk</h2>
            <span className="text-[10px] font-bold text-gray-400 uppercase">{kycSubmissions.filter(s => s.status === 'pending').length} New Submissions</span>
          </div>
          <div className="flex bg-gray-100 p-1 rounded-2xl">
            <button onClick={() => setActiveTab('pending')} className={`px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${activeTab === 'pending' ? 'bg-white shadow-sm text-billpay-green' : 'text-gray-400'}`}>Pending</button>
            <button onClick={() => setActiveTab('reviewed')} className={`px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${activeTab === 'reviewed' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400'}`}>Reviewed</button>
          </div>
       </div>

       <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filtered.map(sub => (
            <div key={sub.id} className="bg-white p-6 rounded-[32px] border border-gray-100 shadow-sm flex items-center justify-between group">
              <div className="flex items-center gap-4">
                <div className={`w-12 h-12 rounded-2xl flex items-center justify-center ${sub.status === 'pending' ? 'bg-blue-50 text-blue-500' : sub.status === 'verified' ? 'bg-green-50 text-green-500' : 'bg-red-50 text-red-500'}`}>
                   <ShieldCheck size={24} />
                </div>
                <div>
                   <div className="text-sm font-black text-gray-800">{sub.fullName}</div>
                   <div className="text-[10px] text-gray-400 font-bold uppercase tracking-tight">{sub.idType} • {sub.idNumber}</div>
                   <div className="text-[9px] text-gray-300 font-bold uppercase mt-1">{new Date(sub.date).toLocaleDateString()}</div>
                </div>
              </div>
              <button onClick={() => setSelectedSub(sub)} className="p-3 bg-gray-50 text-gray-400 rounded-xl hover:bg-billpay-green/10 hover:text-billpay-green transition-all"><Eye size={20} /></button>
            </div>
          ))}
          {filtered.length === 0 && <div className="col-span-full py-24 bg-white rounded-[40px] text-center text-gray-300 font-black uppercase text-xs">No KYC records in this category</div>}
       </div>

       {selectedSub && (
         <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-6">
            <div className="bg-white w-full max-w-2xl rounded-[40px] shadow-2xl animate-slide-up flex flex-col max-h-[90vh]">
               <div className="p-8 border-b border-gray-50 flex justify-between items-center bg-gray-50">
                  <div>
                    <h3 className="text-lg font-black uppercase tracking-tight">Review Submission</h3>
                    <p className="text-[10px] font-bold text-gray-400 uppercase">Submission Ref: {selectedSub.id}</p>
                  </div>
                  <button onClick={() => setSelectedSub(null)} className="p-2 bg-white rounded-full shadow-sm hover:bg-gray-100"><X size={20} /></button>
               </div>
               <div className="p-8 overflow-y-auto space-y-8 flex-1 scrollbar-hide">
                  <div className="grid grid-cols-2 gap-8">
                     <div><label className="text-[9px] font-black text-gray-300 uppercase block mb-1">Full Name</label><div className="text-sm font-black text-gray-800">{selectedSub.fullName}</div></div>
                     <div><label className="text-[9px] font-black text-gray-300 uppercase block mb-1">DOB</label><div className="text-sm font-black text-gray-800">{selectedSub.dob}</div></div>
                     <div className="col-span-2"><label className="text-[9px] font-black text-gray-300 uppercase block mb-1">Address</label><div className="text-sm font-black text-gray-800">{selectedSub.address}</div></div>
                     <div><label className="text-[9px] font-black text-gray-300 uppercase block mb-1">ID Type</label><div className="text-sm font-black text-gray-800 uppercase">{selectedSub.idType}</div></div>
                     <div><label className="text-[9px] font-black text-gray-300 uppercase block mb-1">ID Number</label><div className="text-sm font-black text-gray-800">{selectedSub.idNumber}</div></div>
                  </div>
                  
                  <div className="space-y-4">
                     <label className="text-[9px] font-black text-gray-300 uppercase block">Verification Documents</label>
                     <div className="grid grid-cols-2 gap-4">
                        <div className="bg-gray-50 rounded-3xl p-4 border border-gray-100 flex flex-col items-center gap-2 aspect-video justify-center">
                           <div className="text-[10px] font-black text-gray-400 uppercase">ID Document Photo</div>
                           <FileText className="text-gray-200" size={48} />
                        </div>
                        <div className="bg-gray-50 rounded-3xl p-4 border border-gray-100 flex flex-col items-center gap-2 aspect-video justify-center">
                           <div className="text-[10px] font-black text-gray-400 uppercase">Utility Bill / Address</div>
                           <Landmark className="text-gray-200" size={48} />
                        </div>
                     </div>
                  </div>
               </div>
               
               {selectedSub.status === 'pending' && (
                 <div className="p-8 border-t border-gray-50 bg-gray-50 flex gap-4">
                    <button onClick={() => handleKycAction(selectedSub.id, 'rejected')} className="flex-1 py-5 bg-red-50 text-red-500 font-black rounded-[24px] uppercase tracking-widest flex items-center justify-center gap-2 shadow-sm"><XCircle size={18} /> Reject</button>
                    <button onClick={() => handleKycAction(selectedSub.id, 'verified')} className="flex-[2] py-5 bg-billpay-green text-white font-black rounded-[24px] uppercase tracking-widest flex items-center justify-center gap-2 shadow-xl"><BadgeCheck size={18} /> Approve KYC</button>
                 </div>
               )}
            </div>
         </div>
       )}
    </div>
  );
};

const GlobalTransactions: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { transactions, users } = useApp();
  const [search, setSearch] = useState('');
  const [filterType, setFilterType] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [dateFilter, setDateFilter] = useState('');

  const filtered = useMemo(() => {
    return transactions.filter(tx => {
      const user = users.find(u => u.id === tx.userId);
      const matchesSearch = tx.id.toLowerCase().includes(search.toLowerCase()) || 
                           tx.recipient.toLowerCase().includes(search.toLowerCase()) ||
                           (user?.fullName.toLowerCase().includes(search.toLowerCase()));
      const matchesType = filterType === 'all' || tx.type.toLowerCase().includes(filterType.toLowerCase());
      const matchesStatus = statusFilter === 'all' || tx.status === statusFilter;
      const matchesDate = !dateFilter || tx.date.startsWith(dateFilter);
      return matchesSearch && matchesType && matchesStatus && matchesDate;
    });
  }, [transactions, search, filterType, statusFilter, dateFilter, users]);

  const txTypes = useMemo(() => {
    const rawTypes = transactions.map(tx => tx.type.split(' ')[0]);
    return Array.from(new Set(rawTypes));
  }, [transactions]);

  return (
    <div className="space-y-6 animate-fade-in">
       <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
          <div className="space-y-6">
             <div className="flex flex-col">
               <h2 className="text-2xl font-black uppercase tracking-tighter">Live Audit Log</h2>
               <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{transactions.length} Total Transactions Processed</span>
             </div>
             
             <div className="grid grid-cols-1 md:grid-cols-4 gap-4 bg-gray-50 p-6 rounded-[32px] border border-gray-100">
                <div className="relative col-span-1 md:col-span-1">
                   <label className="block text-[8px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Search Context</label>
                   <div className="relative">
                      <input className="w-full bg-white p-3.5 pl-10 rounded-2xl font-bold text-xs outline-none border border-gray-100 focus:border-billpay-green" placeholder="ID / Name / Phone..." value={search} onChange={e => setSearch(e.target.value)} />
                      <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-300" size={14} />
                   </div>
                </div>
                <div className="relative">
                   <label className="block text-[8px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Service Category</label>
                   <select className="w-full bg-white p-3.5 rounded-2xl font-black text-[10px] uppercase outline-none border border-gray-100 focus:border-billpay-green appearance-none pr-10" value={filterType} onChange={e => setFilterType(e.target.value)}>
                      <option value="all">All Services</option>
                      {txTypes.map(t => <option key={t} value={t}>{t}</option>)}
                   </select>
                   <ChevronDown className="absolute right-3.5 bottom-4 text-gray-300 pointer-events-none" size={14} />
                </div>
                <div className="relative">
                   <label className="block text-[8px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Transaction Status</label>
                   <select className="w-full bg-white p-3.5 rounded-2xl font-black text-[10px] uppercase outline-none border border-gray-100 focus:border-billpay-green appearance-none pr-10" value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
                      <option value="all">Any Status</option>
                      <option value="successful">Successful</option>
                      <option value="pending">Pending</option>
                      <option value="failed">Failed</option>
                   </select>
                   <ChevronDown className="absolute right-3.5 bottom-4 text-gray-300 pointer-events-none" size={14} />
                </div>
                <div className="relative">
                   <label className="block text-[8px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Specific Date</label>
                   <div className="relative">
                      <input type="date" className="w-full bg-white p-3 rounded-2xl font-black text-[10px] uppercase outline-none border border-gray-100 focus:border-billpay-green" value={dateFilter} onChange={e => setDateFilter(e.target.value)} />
                      {dateFilter && <button onClick={() => setDateFilter('')} className="absolute right-10 top-1/2 -translate-y-1/2 text-gray-300"><X size={12} /></button>}
                   </div>
                </div>
             </div>
          </div>

          <div className="overflow-hidden border border-gray-50 rounded-[32px]">
             <table className="w-full text-left border-collapse">
                <thead>
                   <tr className="bg-gray-50 text-[9px] font-black text-gray-400 uppercase tracking-[0.15em]">
                      <th className="p-6">Transaction ID</th>
                      <th className="p-6">User</th>
                      <th className="p-6">Service</th>
                      <th className="p-6">Amount</th>
                      <th className="p-6">Status</th>
                      <th className="p-6">Date</th>
                   </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                   {filtered.map(tx => {
                     const user = users.find(u => u.id === tx.userId);
                     return (
                       <tr key={tx.id} className="hover:bg-gray-50 transition-colors group">
                          <td className="p-6 font-mono text-[10px] font-bold text-gray-400 uppercase">{tx.id}</td>
                          <td className="p-6">
                             <div className="flex flex-col">
                                <span className="text-xs font-black text-gray-800">{user?.fullName || 'Deleted'}</span>
                                <span className="text-[9px] text-gray-400 font-bold uppercase">@{user?.username || 'user'}</span>
                             </div>
                          </td>
                          <td className="p-6">
                             <span className="px-3 py-1 bg-gray-100 rounded-full text-[9px] font-black uppercase text-gray-500">{tx.type}</span>
                          </td>
                          <td className="p-6 font-black text-gray-800 text-xs">{formatCurrency(tx.amount)}</td>
                          <td className="p-6">
                             <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase ${tx.status === 'successful' ? 'bg-green-50 text-green-600' : tx.status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'}`}>
                                <div className={`w-1.5 h-1.5 rounded-full ${tx.status === 'successful' ? 'bg-green-500' : tx.status === 'pending' ? 'bg-amber-500' : 'bg-red-500'}`} />
                                {tx.status}
                             </span>
                          </td>
                          <td className="p-6 text-[10px] font-bold text-gray-400">{new Date(tx.date).toLocaleString()}</td>
                       </tr>
                     );
                   })}
                </tbody>
             </table>
             {filtered.length === 0 && <div className="py-24 text-center text-gray-300 font-black uppercase text-xs">No transactions match your filters</div>}
          </div>
       </div>
    </div>
  );
};

const EmailHub: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { users, settings } = useApp();
  const [subject, setSubject] = useState('');
  const [content, setContent] = useState('');
  const [targetTier, setTargetTier] = useState<string>('all');
  const [isSending, setIsSending] = useState(false);

  useEffect(() => {
    // @ts-ignore
    if (window.ClassicEditor && !document.querySelector('.ck-editor')) {
      // @ts-ignore
      window.ClassicEditor.create(document.querySelector('#broadcast-editor'))
        .then((editor: any) => {
          editor.model.document.on('change:data', () => {
            setContent(editor.getData());
          });
        })
        .catch((error: any) => console.error(error));
    }
  }, []);

  const handleBroadcast = async () => {
    if (!subject || !content) {
      showToast("Subject and content required.");
      return;
    }

    const targets = targetTier === 'all' 
      ? users 
      : users.filter(u => u.tier === parseInt(targetTier));
    
    if (targets.length === 0) {
      showToast("No users match this criteria.");
      return;
    }

    setIsSending(true);
    try {
      await sendBroadcastEmail(settings, targets.map(u => u.email), subject, content);
      showToast(`Broadcast sent to ${targets.length} users!`);
      setSubject('');
    } catch (e) {
      showToast("Broadcast failed.");
    } finally {
      setIsSending(false);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 flex items-center justify-between">
        <div className="flex flex-col">
          <h2 className="text-2xl font-black uppercase tracking-tighter">Email Hub</h2>
          <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Global Outreach Engine</span>
        </div>
        <Mail className="text-billpay-green" size={32} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 bg-white p-10 rounded-[40px] shadow-sm border border-gray-100 space-y-8">
           <div className="space-y-4">
              <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Email Subject</label>
              <input 
                className="w-full p-4 bg-gray-50 rounded-2xl font-bold border-2 border-transparent focus:border-billpay-green outline-none"
                placeholder="Platform Maintenance / Promotion..."
                value={subject}
                onChange={e => setSubject(e.target.value)}
              />
           </div>
           <div className="space-y-4">
              <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Broadcast Content</label>
              <div className="border border-gray-100 rounded-2xl overflow-hidden min-h-[400px]">
                <div id="broadcast-editor"></div>
              </div>
           </div>
           <button 
            onClick={handleBroadcast}
            disabled={isSending}
            className="w-full bg-billpay-green text-white py-5 rounded-3xl font-black uppercase shadow-xl flex items-center justify-center gap-3 disabled:opacity-50"
           >
              {isSending ? <RefreshCcw className="animate-spin" /> : <><Send size={18} /> INITIALIZE BROADCAST</>}
           </button>
        </div>

        <div className="space-y-6">
           <div className="bg-gray-900 p-8 rounded-[40px] text-white space-y-6 shadow-xl relative overflow-hidden">
              <div className="absolute -right-8 -top-8 w-28 h-28 bg-billpay-green/20 rounded-full blur-3xl" />
              <h3 className="text-xs font-black uppercase tracking-widest opacity-60">Distribution</h3>
              <div className="space-y-4">
                 {['all', '1', '2', '3'].map(tier => (
                   <button 
                    key={tier}
                    onClick={() => setTargetTier(tier)}
                    className={`w-full p-4 rounded-2xl border-2 transition-all text-left flex justify-between items-center ${targetTier === tier ? 'border-billpay-green bg-billpay-green/10' : 'border-white/5 bg-white/5 opacity-40'}`}
                   >
                     <span className="text-[10px] font-black uppercase tracking-widest">{tier === 'all' ? 'All Users' : `Tier ${tier} Users`}</span>
                     {targetTier === tier && <CheckCircle size={16} className="text-billpay-green" />}
                   </button>
                 ))}
              </div>
           </div>

           <div className="bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm">
              <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Mailing Tips</h3>
              <ul className="space-y-3">
                 <li className="flex gap-3 items-start"><Check className="text-billpay-green mt-0.5" size={14} /><span className="text-[10px] font-bold text-gray-500 uppercase leading-relaxed">Use placeholders like [[NAME]] for personalization.</span></li>
                 <li className="flex gap-3 items-start"><Check className="text-billpay-green mt-0.5" size={14} /><span className="text-[10px] font-bold text-gray-500 uppercase leading-relaxed">Ensure valid SMTP settings in the Settings tab.</span></li>
              </ul>
           </div>
        </div>
      </div>
    </div>
  );
};

const AirtimeBettingSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><Phone className="text-blue-500" /> Nellobyte (Airtime Gateway)</h3>
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

const BettingApiSettings: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  
  const updateProvider = (id: string, updates: Partial<BettingProvider>) => {
    setSettings(prev => ({
      ...prev,
      bettingProviders: prev.bettingProviders.map(p => p.id === id ? { ...p, ...updates } : p)
    }));
  };

  return (
    <div className="space-y-8 animate-fade-in text-gray-900">
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><TrendingUp className="text-emerald-500" /> Betting Hub Control</h3>
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="text-[10px] font-black text-gray-400 uppercase border-b border-gray-50">
                <th className="pb-4">Provider</th>
                <th className="pb-4">User Discount (%)</th>
                <th className="pb-4 text-right">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {settings.bettingProviders.map(p => (
                <tr key={p.id}>
                  <td className="py-4 font-black text-gray-800 uppercase text-xs">{p.name}</td>
                  <td className="py-4">
                    <input 
                      type="number" 
                      step="0.1" 
                      className="w-24 bg-gray-50 p-2 rounded-xl border border-gray-100 font-black text-emerald-600 text-xs text-center" 
                      value={p.discountPercent} 
                      onChange={e => updateProvider(p.id, { discountPercent: parseFloat(e.target.value) || 0 })} 
                    />
                  </td>
                  <td className="py-4 text-right">
                    <button 
                      onClick={() => updateProvider(p.id, { enabled: !p.enabled })}
                      className={`px-4 py-2 rounded-xl text-[9px] font-black transition-all ${p.enabled ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`}
                    >
                      {p.enabled ? 'ACTIVE' : 'DISABLED'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
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
                <button onClick={() => syncCable(p.serviceId)} className="p-3 bg-white text-billpay-green rounded-xl shadow-sm"><RefreshCcw size={16} className={isSyncing ? 'animate-spin' : ''} /></button>
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
                        <td className="py-4 font-black text-gray-900">{p.name}</td>
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
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><MessageSquare className="text-billpay-green" /> KudiSms Gateway</h3>
        <input type="password" placeholder="KudiSms Token" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={settings.kudiSmsToken} onChange={e => setSettings({...settings, kudiSmsToken: e.target.value})} />
      </div>
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><ShieldCheck className="text-billpay-green" /> Manual Sender ID Review</h3>
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
    { id: 'airtime', label: '1. Airtime (Nellobyte)', icon: <Phone size={16} /> },
    { id: 'betting', label: '2. Betting Control', icon: <TrendingUp size={16} /> },
    { id: 'data', label: '3. Data Hub (v6)', icon: <Wifi size={16} /> },
    { id: 'cable', label: '4. Cable TV (VTpass)', icon: <Tv size={16} /> },
    { id: 'electricity', label: '5. Electricity (VTpass)', icon: <Zap size={16} /> },
    { id: 'exam', label: '6. Education PINs (Exam)', icon: <GraduationCap size={16} /> },
    { id: 'sms', label: '7. Bulk SMS (KudiSms)', icon: <MessageSquare size={16} /> },
    { id: 'gateways', label: '8. Global Hub (Stripe/JuicyWay)', icon: <Globe size={16} /> },
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
                      <button key={option.id} onClick={() => { setActiveApi(option.id); setShowDropdown(false); }} className={`w-full flex items-center gap-4 px-6 py-4 text-[10px] font-black uppercase tracking-widest transition-colors ${activeApi === option.id ? 'bg-billpay-green text-white' : 'text-gray-500 hover:bg-gray-50'}`}>{option.icon} {option.label}</button>
                   ))}
                </div>
             )}
          </div>
       </div>
       <div className="animate-fade-in pb-20">
          {activeApi === 'airtime' && <AirtimeBettingSettings showToast={showToast} />}
          {activeApi === 'betting' && <BettingApiSettings showToast={showToast} />}
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
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Host</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.smtpHost} onChange={e => setSettings({...settings, smtpHost: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Port</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.smtpPort} onChange={e => setSettings({...settings, smtpPort: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">User</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.smtpUser} onChange={e => setSettings({...settings, smtpUser: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Password</label><input type="password" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.smtpPass} onChange={e => setSettings({...settings, smtpPass: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Sender Name</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.senderName} onChange={e => setSettings({...settings, senderName: e.target.value})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Sender Email</label><input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.fromEmail} onChange={e => setSettings({...settings, fromEmail: e.target.value})} /></div>
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
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Daily Check-in (Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.bonusPerDay} onChange={e => setSettings({...settings, bonusPerDay: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Referral Reward (Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.referralBonus} onChange={e => setSettings({...settings, referralBonus: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Welcome Bonus (Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.welcomeBonus} onChange={e => setSettings({...settings, welcomeBonus: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Rate (₦1 = X Coins)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.conversionRate} onChange={e => setSettings({...settings, conversionRate: parseInt(e.target.value) || 0})} /></div>
        </div>
      </div>

      {/* Security & System Guard */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black uppercase tracking-widest mb-8 flex items-center gap-3"><ShieldHalf className="text-billpay-green" /> Security & System Guard</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Min. Wallet Deposit (₦)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.minDepositAmount} onChange={e => setSettings({...settings, minDepositAmount: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Min. Airtime Purchase (₦)</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.minAirtimePurchase} onChange={e => setSettings({...settings, minAirtimePurchase: parseInt(e.target.value) || 0})} /></div>
           <div><label className="text-[10px] font-black text-gray-400 uppercase">Max Daily Tx Per ID</label><input type="number" className="w-full p-4 bg-gray-50 rounded-2xl font-bold mt-2 outline-none border border-transparent focus:border-billpay-green" value={settings.maxDailyTxPerId} onChange={e => setSettings({...settings, maxDailyTxPerId: parseInt(e.target.value) || 0})} /><p className="text-[8px] font-bold text-gray-400 uppercase mt-2">Prevents spam on a single Meter/Phone/IUC daily</p></div>
        </div>
      </div>
      <button onClick={() => showToast("Global config saved.")} className="w-full bg-gray-900 text-white py-5 rounded-[32px] font-black uppercase shadow-xl hover:bg-black transition-all">Save Config</button>
    </div>
  );
};

const AdminDashboard: React.FC = () => {
  const { setCurrentUser } = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const [toast, setToast] = useState<string | null>(null);
  const showToast = (msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); };
  
  const menuItems = [
    { label: 'Overview', icon: <LayoutDashboard size={20} />, path: '/admin' },
    { label: 'Users', icon: <Users size={20} />, path: '/admin/users' },
    { label: 'KYC Review', icon: <ShieldCheck size={20} />, path: '/admin/kyc' },
    { label: 'Deposits', icon: <Wallet size={20} />, path: '/admin/deposits' },
    { label: 'All Tx History', icon: <FileText size={20} />, path: '/admin/transactions' },
    { label: 'Support', icon: <MessageSquare size={20} />, path: '/admin/support' },
    { label: 'Email Hub', icon: <Mail size={20} />, path: '/admin/email-hub' },
    { label: 'API Hub', icon: <Database size={20} />, path: '/admin/api-manager' },
    { label: 'Settings', icon: <SettingsIcon size={20} />, path: '/admin/settings' },
  ];

  return (
    <div className="flex min-h-screen bg-gray-50 text-gray-900">
      <aside className="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100 overflow-y-auto scrollbar-hide">
        <div className="p-8 flex items-center gap-3"><div className="w-10 h-10 bg-billpay-green rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">B</div><span className="font-black text-lg">Admin Hub</span></div>
        <nav className="flex-1 px-4 py-4 space-y-1">{menuItems.map(item => (<Link key={item.path} to={item.path} className={`flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all ${location.pathname.startsWith(item.path) && (item.path !== '/admin' || location.pathname === '/admin') ? 'bg-billpay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'}`}>{item.icon} {item.label}</Link>))}</nav>
        <div className="p-6 border-t border-gray-100"><button onClick={() => { setCurrentUser(null); navigate('/login'); }} className="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all"><LogOut size={20} /> Sign Out</button></div>
      </aside>
      <main className="flex-1 ml-72 p-12 overflow-y-auto">
        {toast && (<div className="fixed top-8 right-8 bg-gray-900 text-white px-8 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-3 animate-slide-down border border-white/10"><CheckCircle className="text-billpay-green" size={20} /><span className="text-[11px] font-black uppercase tracking-widest">{toast}</span></div>)}
        <div className="max-w-6xl mx-auto">
          <Routes>
            <Route index element={<AdminOverview />} />
            <Route path="users" element={<UserHub showToast={showToast} />} />
            <Route path="kyc" element={<KycManager showToast={showToast} />} />
            <Route path="deposits" element={<DepositManager showToast={showToast} />} />
            <Route path="transactions" element={<GlobalTransactions showToast={showToast} />} />
            <Route path="support" element={<SupportManager showToast={showToast} />} />
            <Route path="email-hub" element={<EmailHub showToast={showToast} />} />
            <Route path="api-manager" element={<ApiManagerLayout showToast={showToast} />} />
            <Route path="settings" element={<SettingsManager showToast={showToast} />} />
          </Routes>
        </div>
      </main>
    </div>
  );
};

export default AdminDashboard;

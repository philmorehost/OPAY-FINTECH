
import React, { useState, useMemo } from 'react';
import { Routes, Route, Link, useNavigate, useLocation } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  LayoutDashboard, Users, Settings as SettingsIcon, Database, 
  Mail, LogOut, CreditCard, CheckCircle, 
  Trash2, Lock, Unlock, Plus, RefreshCcw, 
  Eye, ShieldCheck, Wallet, Landmark, Check, Ban, MessageCircle, Gift, LayoutGrid, Phone, Tv, Wifi, Key, ShieldAlert, Zap, Percent, Shield
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { User, KYCSubmission, DepositRequest, SmsSenderId, GiftCardRequest, Offer, ExamProvider, DataProduct, CableProvider } from '../types';

interface AdminSubPageProps {
  showToast: (msg: string) => void;
}

const AdminOverview: React.FC = () => {
  const { users, giftCardRequests, smsSenderIds } = useApp();
  const stats = [
    { label: 'Total Users', value: users.length, icon: <Users className="text-blue-500" />, color: 'bg-blue-50' },
    { label: 'Platform Balance', value: formatCurrency(users.reduce((acc, u) => acc + u.walletBalance, 0)), icon: <CreditCard className="text-green-500" />, color: 'bg-green-50' },
    { label: 'Pending GC', value: giftCardRequests.filter(r => r.status === 'pending').length, icon: <Gift className="text-pink-500" />, color: 'bg-pink-50' },
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
        <div className="lg:col-span-2 bg-white p-8 rounded-[40px] border border-gray-100 shadow-sm">
          <div className="flex justify-between items-center mb-8">
            <h3 className="text-sm font-black text-gray-800 uppercase tracking-widest">Revenue Growth</h3>
            <span className="px-3 py-1 bg-green-50 text-green-600 text-[10px] font-black rounded-full">+12.5% Today</span>
          </div>
          <div className="h-[300px]">
            <ResponsiveContainer width="100%" height="100%">
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
          <h3 className="text-sm font-black text-gray-800 uppercase tracking-widest mb-6">Quick Access</h3>
          <div className="grid grid-cols-2 gap-4">
            <Link to="/admin/deposits" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Wallet className="text-purple-500 mb-3" /><span className="text-[9px] font-black uppercase">Deposits</span></Link>
            <Link to="/admin/api-manager" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Database className="text-orange-500 mb-3" /><span className="text-[9px] font-black uppercase">API Manager</span></Link>
            <Link to="/admin/gift-cards" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><Gift className="text-pink-500 mb-3" /><span className="text-[9px] font-black uppercase">Gift Cards</span></Link>
            <Link to="/admin/settings" className="flex flex-col items-center p-6 bg-gray-50 rounded-3xl hover:bg-opay-green/10 transition-colors"><SettingsIcon className="text-blue-500 mb-3" /><span className="text-[9px] font-black uppercase">Settings</span></Link>
          </div>
        </div>
      </div>
    </div>
  );
};

const ApiManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  const [isSyncing, setIsSyncing] = useState(false);
  const [newDataProduct, setNewDataProduct] = useState<Partial<DataProduct>>({
    networkId: '', type: 'sme-data', size: '', apiQuantityCode: '', userPrice: 0, enabled: true
  });

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

  const syncCableVariations = async (serviceId: string) => {
    setIsSyncing(true);
    try {
      const response = await fetch(`https://vtpass.com/api/service-variations?serviceID=${serviceId}`, {
        headers: getAuthHeaders()
      });
      const data = await response.json();
      if (data.response_description === "000") {
        setSettings(prev => ({
          ...prev,
          cableProviders: prev.cableProviders.map(p => 
            p.serviceId === serviceId ? { ...p, variations: data.content.variations } : p
          )
        }));
        showToast(`Synced ${data.content.variations.length} packages for ${serviceId.toUpperCase()}`);
      } else {
        showToast(`Sync failed: ${data.response_description || 'Check Credentials'}`);
      }
    } catch (err) {
      showToast("Connection Error. Ensure VTPass allows your IP.");
    } finally { setIsSyncing(false); }
  };

  const addDataProduct = () => {
    if (!newDataProduct.networkId || !newDataProduct.size || !newDataProduct.apiQuantityCode) {
      showToast("Fill all data fields");
      return;
    }
    const product: DataProduct = {
      id: generateId(),
      networkId: newDataProduct.networkId!,
      type: newDataProduct.type!,
      size: newDataProduct.size!,
      apiQuantityCode: newDataProduct.apiQuantityCode!,
      userPrice: newDataProduct.userPrice!,
      enabled: true
    };
    setSettings({ ...settings, dataProducts: [...settings.dataProducts, product] });
    setNewDataProduct({ networkId: '', type: 'sme-data', size: '', apiQuantityCode: '', userPrice: 0, enabled: true });
    showToast("Data package added successfully.");
  };

  return (
    <div className="space-y-10 animate-fade-in pb-20">
      {/* CABLE & EXAM (VTPASS BASIC AUTH) */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Key className="text-indigo-500" /> VTPass (Cable & Exam) Basic Auth
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
           <div className="space-y-4">
              <div>
                <label className="text-[10px] font-black text-gray-400 uppercase">VTPass Login Email</label>
                <input type="email" placeholder="user@gmail.com" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-indigo-500" 
                  value={settings.vtPassEmail || ''} onChange={e => setSettings({...settings, vtPassEmail: e.target.value})} />
              </div>
              <div>
                <label className="text-[10px] font-black text-gray-400 uppercase">VTPass Login Password</label>
                <input type="password" placeholder="VTPass Pass" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-indigo-500" 
                  value={settings.vtPassPassword || ''} onChange={e => setSettings({...settings, vtPassPassword: e.target.value})} />
              </div>
           </div>
           <div className="bg-indigo-50 p-8 rounded-3xl flex flex-col justify-center border border-indigo-100">
              <span className="text-[10px] font-black text-indigo-700 uppercase tracking-widest">Authentication Protocol</span>
              <p className="text-[10px] text-indigo-600 mt-2 font-bold leading-relaxed">
                By entering your email and password, the system will prioritize <span className="font-black">Basic Authorization Headers</span> for all VTPass requests. This is the most reliable method for Cable TV and Exam PIN verification.
              </p>
           </div>
        </div>
        
        <div className="grid grid-cols-2 gap-4">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">VTPass API Key (Optional)</label>
              <input type="password" placeholder="sk_live_..." className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2" 
                value={settings.vtPassApiKey} onChange={e => setSettings({...settings, vtPassApiKey: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">VTPass Public Key (Optional)</label>
              <input type="password" placeholder="pk_live_..." className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2" 
                value={settings.vtPassPublicKey} onChange={e => setSettings({...settings, vtPassPublicKey: e.target.value})} />
           </div>
        </div>
      </div>

      {/* AIRTIME (NELLOBYTE) */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Phone className="text-blue-500" /> Airtime (Nellobyte) API
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
          <div>
            <label className="text-[10px] font-black text-gray-400 uppercase">Nellobyte User ID</label>
            <input className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-blue-500" 
              value={settings.nellobyteUserId} onChange={e => setSettings({...settings, nellobyteUserId: e.target.value})} />
          </div>
          <div>
            <label className="text-[10px] font-black text-gray-400 uppercase">Nellobyte API Key</label>
            <input type="password" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-blue-500" 
              value={settings.nellobyteApiKey} onChange={e => setSettings({...settings, nellobyteApiKey: e.target.value})} />
          </div>
        </div>
        
        <div className="grid grid-cols-4 gap-4">
           {['mtn', 'glo', 'airtel', 'nineMobile'].map(net => (
             <div key={net} className="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                <label className="text-[9px] font-black text-gray-400 uppercase block mb-2">{net === 'nineMobile' ? '9mobile' : net} Discount (%)</label>
                <input 
                  type="number" 
                  step="0.1"
                  className="w-full bg-white p-2 rounded-xl outline-none font-black text-xs text-blue-600"
                  // @ts-ignore
                  value={settings.airtimeDiscounts[net]}
                  onChange={e => setSettings({
                    ...settings,
                    airtimeDiscounts: { ...settings.airtimeDiscounts, [net]: parseFloat(e.target.value) || 0 }
                  })}
                />
             </div>
           ))}
        </div>
      </div>

      {/* DATA & EXAM (EXTRA PROVIDERS) */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
           <h3 className="text-sm font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-3">
              <Wifi className="text-emerald-500" /> Data Gifting (v6) Key
           </h3>
           <input type="password" placeholder="API Token" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 border-2 border-transparent focus:border-emerald-500" 
              value={settings.dataGiftingApiKey} onChange={e => setSettings({...settings, dataGiftingApiKey: e.target.value})} />
        </div>
        <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
           <h3 className="text-sm font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-3">
              <ShieldCheck className="text-purple-500" /> Exam (NaijaResult) Key
           </h3>
           <input type="password" placeholder="API Token" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 border-2 border-transparent focus:border-purple-500" 
              value={settings.examApiKey} onChange={e => setSettings({...settings, examApiKey: e.target.value})} />
        </div>
      </div>

      {/* DATA PRODUCT MANAGER */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Database className="text-emerald-500" /> Data Package Management
        </h3>
        <div className="bg-gray-50 p-8 rounded-[32px] space-y-6 mb-10 border border-gray-100 shadow-inner">
           <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase">Provider</label>
                <select className="w-full p-3 bg-white rounded-xl outline-none font-bold text-xs" value={newDataProduct.networkId} onChange={e => setNewDataProduct({...newDataProduct, networkId: e.target.value})}>
                   <option value="">Select Network</option>{settings.dataNetworks.map(n => <option key={n.id} value={n.id}>{n.name}</option>)}
                </select>
              </div>
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase">Size (Label)</label>
                <input placeholder="e.g. 1GB" className="w-full p-3 bg-white rounded-xl outline-none font-bold text-xs" value={newDataProduct.size} onChange={e => setNewDataProduct({...newDataProduct, size: e.target.value})} />
              </div>
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase">Type</label>
                <select className="w-full p-3 bg-white rounded-xl outline-none font-bold text-xs" value={newDataProduct.type} onChange={e => setNewDataProduct({...newDataProduct, type: e.target.value})}>
                   <option value="sme-data">SME</option>
                   <option value="cg-data">CG (Gifting)</option>
                   <option value="direct-data">Direct</option>
                </select>
              </div>
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase">API Qty Code</label>
                <input placeholder="e.g. 1000" className="w-full p-3 bg-white rounded-xl outline-none font-bold text-xs" value={newDataProduct.apiQuantityCode} onChange={e => setNewDataProduct({...newDataProduct, apiQuantityCode: e.target.value})} />
              </div>
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase">Price (₦)</label>
                <input type="number" placeholder="Price" className="w-full p-3 bg-white rounded-xl outline-none font-bold text-xs" value={newDataProduct.userPrice || ''} onChange={e => setNewDataProduct({...newDataProduct, userPrice: parseFloat(e.target.value) || 0})} />
              </div>
           </div>
           <button onClick={addDataProduct} className="w-full bg-emerald-500 text-white py-4 rounded-xl font-black text-[10px] uppercase shadow-lg shadow-emerald-100 active:scale-95 transition-all">ADD NEW DATA PLAN</button>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left">
             <thead><tr className="text-[9px] font-black text-gray-400 uppercase border-b border-gray-50"><th className="pb-4 px-4">Active Plan</th><th className="pb-4">Type</th><th className="pb-4">API Qty</th><th className="pb-4">Price</th><th className="pb-4 text-right px-4">Action</th></tr></thead>
             <tbody className="divide-y divide-gray-50">
                {settings.dataProducts.map(p => (
                  <tr key={p.id} className="hover:bg-gray-50/50 transition-colors group">
                    <td className="py-4 px-4"><div className="flex items-center gap-3"><span className="text-xs font-bold text-gray-800">{settings.dataNetworks.find(n => n.id === p.networkId)?.name} {p.size}</span></div></td>
                    <td className="py-4 text-[10px] font-black text-gray-400 uppercase">{p.type.replace('-data', '')}</td>
                    <td className="py-4 text-[10px] font-bold text-gray-600 font-mono">{p.apiQuantityCode}</td>
                    <td className="py-4 text-xs font-black text-emerald-600">{formatCurrency(p.userPrice)}</td>
                    <td className="py-4 text-right px-4"><button onClick={() => setSettings({...settings, dataProducts: settings.dataProducts.filter(dp => dp.id !== p.id)})} className="p-2 text-red-400 hover:bg-red-50 rounded-xl opacity-0 group-hover:opacity-100 transition-all"><Trash2 size={16} /></button></td>
                  </tr>
                ))}
             </tbody>
          </table>
          {settings.dataProducts.length === 0 && (
            <div className="py-20 text-center text-gray-300 font-black uppercase text-[10px] tracking-widest border-2 border-dashed border-gray-100 rounded-[32px]">No data plans registered</div>
          )}
        </div>
      </div>

      {/* CABLE SYNC */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Tv className="text-red-500" /> Cable TV Bouquets Sync
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
           {settings.cableProviders.map(cp => (
              <div key={cp.id} className="p-6 bg-gray-50 rounded-3xl border border-gray-100 flex flex-col items-center gap-4">
                 <span className="text-sm font-black text-gray-800">{cp.name}</span>
                 <span className="text-[9px] font-bold text-gray-400 uppercase">{cp.variations.length} Active Bouquets</span>
                 <button onClick={() => syncCableVariations(cp.serviceId)} disabled={isSyncing} className="p-3 bg-white text-indigo-600 rounded-2xl shadow-sm border border-indigo-100 hover:bg-indigo-50 active:scale-90 transition-all">
                    <RefreshCcw size={16} className={isSyncing ? 'animate-spin' : ''} />
                 </button>
              </div>
           ))}
        </div>
      </div>
      
      <button onClick={() => showToast("Global API Configuration Saved.")} className="w-full bg-gray-900 text-white py-6 rounded-3xl font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all mt-10">SAVE ALL CONFIGURATIONS</button>
    </div>
  );
};

const SettingsManager: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { settings, setSettings } = useApp();
  
  return (
    <div className="space-y-10 animate-fade-in pb-20">
      {/* GLOBAL SYSTEM STATUS */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
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

      {/* LOYALTY & REWARDS */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Gift className="text-amber-500" /> Loyalty & Rewards Engine
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Daily Check-in (Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-amber-500" 
                value={settings.bonusPerDay} onChange={e => setSettings({...settings, bonusPerDay: parseInt(e.target.value) || 0})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Referral Reward (Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-amber-500" 
                value={settings.referralBonus} onChange={e => setSettings({...settings, referralBonus: parseInt(e.target.value) || 100})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Welcome Bonus (Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-amber-500" 
                value={settings.welcomeBonus} onChange={e => setSettings({...settings, welcomeBonus: parseInt(e.target.value) || 0})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Conversion (₦1 = X Coins)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-amber-500" 
                value={settings.conversionRate} onChange={e => setSettings({...settings, conversionRate: parseInt(e.target.value) || 20})} />
           </div>
        </div>
      </div>

      {/* TRANSACTION LIMITS & SECURITY */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <ShieldAlert className="text-red-500" /> Security & Transaction Limits
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Min. Wallet Deposit (₦)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-red-500" 
                value={settings.minDepositAmount} onChange={e => setSettings({...settings, minDepositAmount: parseInt(e.target.value) || 100})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Min. Airtime Purchase (₦)</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-red-500" 
                value={settings.minAirtimePurchase} onChange={e => setSettings({...settings, minAirtimePurchase: parseInt(e.target.value) || 50})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Max Daily Tx Per ID</label>
              <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2 border-2 border-transparent focus:border-red-500" 
                value={settings.maxDailyTxPerId} onChange={e => setSettings({...settings, maxDailyTxPerId: parseInt(e.target.value) || 3})} />
           </div>
        </div>
      </div>

      {/* SMTP CONFIGURATION */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Mail className="text-indigo-500" /> SMTP (Email Server) Settings
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
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
              <label className="text-[10px] font-black text-gray-400 uppercase">SMTP Username</label>
              <input type="text" placeholder="user@gmail.com" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.smtpUser} onChange={e => setSettings({...settings, smtpUser: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">SMTP Password</label>
              <input type="password" placeholder="••••••••" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.smtpPass} onChange={e => setSettings({...settings, smtpPass: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Sender Name</label>
              <input type="text" placeholder="OPay Clone Admin" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.senderName} onChange={e => setSettings({...settings, senderName: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">From Email</label>
              <input type="email" placeholder="noreply@domain.com" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.fromEmail} onChange={e => setSettings({...settings, fromEmail: e.target.value})} />
           </div>
        </div>
      </div>

      {/* BANKING & PAYSTACK */}
      <div className="bg-white p-10 rounded-[40px] shadow-sm border border-gray-100">
        <h3 className="text-xl font-black text-gray-800 uppercase tracking-widest mb-8 flex items-center gap-3">
          <Landmark className="text-emerald-500" /> Manual Bank & Paystack
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 pb-8 border-b border-gray-50">
           <div className="space-y-4">
              <div>
                <label className="text-[10px] font-black text-gray-400 uppercase">Manual Bank Account Name</label>
                <input type="text" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2" 
                  value={settings.accountName} onChange={e => setSettings({...settings, accountName: e.target.value})} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                 <div>
                    <label className="text-[10px] font-black text-gray-400 uppercase">Bank Name</label>
                    <input type="text" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                      value={settings.bankName} onChange={e => setSettings({...settings, bankName: e.target.value})} />
                 </div>
                 <div>
                    <label className="text-[10px] font-black text-gray-400 uppercase">Account No.</label>
                    <input type="text" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                      value={settings.bankAccount} onChange={e => setSettings({...settings, bankAccount: e.target.value})} />
                 </div>
              </div>
           </div>
           <div className="space-y-4">
              <div>
                <label className="text-[10px] font-black text-gray-400 uppercase">Deposit Fee (₦)</label>
                <input type="number" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2" 
                  value={settings.manualDepositCharge} onChange={e => setSettings({...settings, manualDepositCharge: parseInt(e.target.value) || 0})} />
              </div>
              <div className="bg-emerald-50 p-6 rounded-3xl border border-emerald-100 flex items-center gap-3">
                 <Percent size={20} className="text-emerald-600" />
                 <p className="text-[9px] font-bold text-emerald-700 uppercase tracking-tighter leading-tight">Fixed fee charged when users report a manual bank transfer deposit.</p>
              </div>
           </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Paystack Public Key</label>
              <input type="password" placeholder="pk_live_..." className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-xs mt-2" 
                value={settings.paystackPublicKey} onChange={e => setSettings({...settings, paystackPublicKey: e.target.value})} />
           </div>
           <div>
              <label className="text-[10px] font-black text-gray-400 uppercase">Paystack Charge (%)</label>
              <input type="number" step="0.1" className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold text-gray-900 mt-2" 
                value={settings.paystackChargePercent} onChange={e => setSettings({...settings, paystackChargePercent: parseFloat(e.target.value) || 1.5})} />
           </div>
        </div>
      </div>

      <button onClick={() => showToast("Global App Settings Updated.")} className="w-full bg-gray-900 text-white py-6 rounded-3xl font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all mt-10">SAVE ALL SETTINGS</button>
    </div>
  );
};

const UserHub: React.FC<AdminSubPageProps> = ({ showToast }) => {
  const { users, setUsers } = useApp();
  const [fundAction, setFundAction] = useState<{ userId: string, type: 'credit' | 'debit' } | null>(null);
  const [fundAmount, setFundAmount] = useState('');

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
    <div className="space-y-6 animate-fade-in">
       <div className="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden">
          <div className="p-8 border-b border-gray-50 font-black uppercase tracking-widest text-xs">Members Directory</div>
          <table className="w-full text-left">
             <thead><tr className="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th className="px-8 py-4">User</th><th className="px-8 py-4">Balance</th><th className="px-8 py-4 text-right">Actions</th></tr></thead>
             <tbody className="divide-y divide-gray-50">
                {users.map(u => (
                   <tr key={u.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="px-8 py-5 flex flex-col"><span className="text-xs font-bold">{u.fullName}</span><span className="text-[9px] text-gray-400">@{u.username}</span></td>
                      <td className="px-8 py-5 text-xs font-black text-opay-green">{formatCurrency(u.walletBalance)}</td>
                      <td className="px-8 py-5 text-right flex justify-end gap-2">
                        <button onClick={() => setFundAction({ userId: u.id, type: 'credit' })} className="p-2 text-indigo-500 bg-indigo-50 rounded-xl"><Plus size={16} /></button>
                        <button onClick={() => setUsers(prev => prev.map(usr => usr.id === u.id ? {...usr, isSuspended: !usr.isSuspended} : usr))} className={`p-2 rounded-xl ${u.isSuspended ? 'text-green-500 bg-green-50' : 'text-red-500 bg-red-50'}`}>{u.isSuspended ? <Unlock size={16}/> : <Lock size={16}/>}</button>
                      </td>
                   </tr>
                ))}
             </tbody>
          </table>
       </div>

       {fundAction && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white w-full max-w-sm rounded-[40px] p-10 space-y-6 shadow-2xl animate-slide-up">
            <h3 className="text-xl font-black text-gray-900 uppercase tracking-tight">Manual {fundAction.type}</h3>
            <div className="space-y-4">
              <input type="number" placeholder="Enter Amount" className="w-full p-4 bg-gray-50 rounded-2xl border-none outline-none font-black text-xl text-gray-900" value={fundAmount} onChange={e => setFundAmount(e.target.value)} />
              <div className="flex gap-4">
                <button onClick={() => setFundAction(null)} className="flex-1 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Cancel</button>
                <button onClick={handleFund} className="flex-1 py-4 bg-gray-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest">Confirm</button>
              </div>
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
      setTransactions(prev => [{ id: generateId(), userId: req.userId, type: 'Deposit', amount: creditAmount, status: 'successful', date: new Date().toISOString(), details: `${req.method.toUpperCase()} Deposit`, recipient: 'Wallet' }, ...prev]);
    }
    setDepositRequests(prev => prev.map(r => r.id === req.id ? { ...r, status } : r));
    showToast(`Request ${status}`);
  };
  return (
    <div className="bg-white rounded-[40px] shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
       <div className="p-8 border-b border-gray-50 font-black uppercase tracking-widest text-xs">Deposits Notification</div>
       <table className="w-full text-left">
          <thead><tr className="bg-gray-50 text-[10px] font-black text-gray-400 uppercase"><th className="px-8 py-4">User</th><th className="px-8 py-4">Amount</th><th className="px-8 py-4 text-right">Actions</th></tr></thead>
          <tbody className="divide-y divide-gray-50">
             {depositRequests.filter(r => r.status === 'pending').map(req => (
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
    { label: 'API Manager', icon: <Database size={20} />, path: '/admin/api-manager' },
    { label: 'App Settings', icon: <SettingsIcon size={20} />, path: '/admin/settings' },
  ];
  return (
    <div className={`flex min-h-screen ${settings.adminTheme === 'dark' ? 'bg-gray-950 text-white' : 'bg-gray-50 text-gray-900'}`}>
      <aside className="w-72 border-r flex flex-col fixed h-full z-40 bg-white border-gray-100">
        <div className="p-8 flex items-center gap-3"><div className="w-10 h-10 bg-opay-green rounded-2xl flex items-center justify-center text-white font-black text-xl">O</div><span className="font-black text-lg">Admin Portal</span></div>
        <nav className="flex-1 px-4 py-4 space-y-1">{menuItems.map(item => (
            <Link key={item.path} to={item.path} className={`flex items-center gap-4 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all ${location.pathname === item.path ? 'bg-opay-green text-white shadow-lg' : 'text-gray-400 hover:bg-gray-50'}`}>{item.icon} {item.label}</Link>
        ))}</nav>
        <div className="p-6 border-t border-gray-100"><button onClick={() => { setCurrentUser(null); navigate('/login'); }} className="w-full flex items-center gap-4 px-6 py-4 text-red-500 font-black text-[10px] uppercase tracking-widest hover:bg-red-50 rounded-2xl transition-all"><LogOut size={20} /> Sign Out</button></div>
      </aside>
      <main className="flex-1 ml-72 p-12">
        {toast && (<div className="fixed top-8 right-8 bg-gray-900 text-white px-8 py-4 rounded-2xl shadow-2xl z-[100] flex items-center gap-3 animate-slide-down border border-white/10"><CheckCircle className="text-opay-green" size={20} /><span className="text-[11px] font-black uppercase tracking-widest">{toast}</span></div>)}
        <div className="max-w-6xl mx-auto">
          <Routes>
            <Route index element={<AdminOverview />} />
            <Route path="users" element={<UserHub showToast={showToast} />} />
            <Route path="deposits" element={<DepositManager showToast={showToast} />} />
            <Route path="api-manager" element={<ApiManager showToast={showToast} />} />
            <Route path="settings" element={<SettingsManager showToast={showToast} />} />
          </Routes>
        </div>
      </main>
    </div>
  );
};

export default AdminDashboard;


import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { 
  ArrowLeft, User as UserIcon, Shield, Camera, CheckCircle2, AlertCircle, 
  Lock, MapPin, Calendar, FileText, ChevronRight, X, 
  LogOut, CreditCard, Landmark, HelpCircle, Share2, 
  Settings as SettingsIcon, Bell, QrCode, Fingerprint, History,
  RotateCcw, ShieldCheck, Mail, Smartphone, ArrowRight
} from 'lucide-react';
import { KYCStatus, User } from '../types';
import { formatCurrency } from '../utils';

type ModalType = 'info' | 'limits' | 'pin' | 'notify' | 'kyc' | null;

const Profile: React.FC = () => {
  const { currentUser, setKycSubmissions, users, setUsers, setCurrentUser } = useApp();
  const navigate = useNavigate();
  
  const [activeModal, setActiveModal] = useState<ModalType>(null);
  const [kycStep, setKycStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  // Forms
  const [infoForm, setInfoForm] = useState({ fullName: currentUser?.fullName || '', email: currentUser?.email || '' });
  const [pinForm, setPinForm] = useState({ currentPin: '', newPin: '', confirmPin: '' });
  const [kycForm, setKycForm] = useState({ idType: 'NIN', idNumber: '', fullName: '', dob: '' });

  if (!currentUser) return null;

  const handleUpdateUser = (updates: Partial<User>) => {
    const updated = { ...currentUser, ...updates };
    setUsers(prev => prev.map(u => u.id === currentUser.id ? updated : u));
    setCurrentUser(updated);
  };

  const handleLogout = () => {
    if (window.confirm("Sign out of Billpay?")) {
      setCurrentUser(null);
      navigate('/login');
    }
  };

  const handleKycSubmit = () => {
    setIsSubmitting(true);
    setTimeout(() => {
      const newSubmission = {
        id: Math.random().toString(36).substr(2, 9).toUpperCase(),
        userId: currentUser.id,
        fullName: kycForm.fullName || currentUser.fullName,
        dob: kycForm.dob || '1995-01-01',
        address: 'Submitted via App',
        idType: kycForm.idType,
        idNumber: kycForm.idNumber,
        idImageUrl: '',
        addressImageUrl: '',
        status: 'pending' as KYCStatus,
        date: new Date().toISOString()
      };

      setKycSubmissions(prev => [newSubmission, ...prev]);
      handleUpdateUser({ kycStatus: 'pending' });
      
      setIsSubmitting(false);
      setActiveModal(null);
      setKycStep(1);
    }, 1500);
  };

  const handlePinUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (pinForm.newPin !== pinForm.confirmPin) {
      alert("PINs do not match");
      return;
    }
    if (pinForm.newPin.length !== 4) {
      alert("PIN must be 4 digits");
      return;
    }
    setIsSubmitting(true);
    setTimeout(() => {
      handleUpdateUser({ paymentPin: pinForm.newPin });
      setIsSubmitting(false);
      setActiveModal(null);
      setPinForm({ currentPin: '', newPin: '', confirmPin: '' });
      alert("Payment PIN updated successfully");
    }, 1000);
  };

  const MenuLink = ({ icon, label, sub, onClick, color = "text-gray-400" }: any) => (
    <div 
      onClick={onClick}
      className="flex items-center justify-between p-5 bg-white active:bg-gray-50 transition-colors cursor-pointer first:rounded-t-[32px] last:rounded-b-[32px] border-b border-gray-50 last:border-0"
    >
      <div className="flex items-center gap-4">
        <div className={`w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center ${color}`}>
          {icon}
        </div>
        <div className="flex flex-col">
          <span className="text-sm font-black text-gray-800 tracking-tight">{label}</span>
          {sub && <span className="text-[10px] text-gray-400 font-bold uppercase">{sub}</span>}
        </div>
      </div>
      <ChevronRight size={18} className="text-gray-300" />
    </div>
  );

  const ModalHeader = ({ title, onClose }: { title: string, onClose: () => void }) => (
    <div className="p-8 bg-gray-50 border-b border-gray-100 flex justify-between items-center shrink-0">
      <div>
        <h3 className="text-xl font-black text-gray-900 uppercase tracking-tight">{title}</h3>
      </div>
      <button onClick={onClose} className="p-2 bg-white rounded-full shadow-sm"><X size={20} className="text-gray-400" /></button>
    </div>
  );

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-24">
      {/* Header Section */}
      <div className="bg-billpay-green p-6 text-white rounded-b-[40px] shadow-lg relative overflow-hidden">
        <div className="absolute top-0 right-0 p-10 opacity-10">
          <UserIcon size={180} />
        </div>
        
        <div className="flex justify-between items-center mb-10 relative z-10">
          <ArrowLeft className="cursor-pointer" onClick={() => navigate('/dashboard')} />
          <div className="flex gap-4">
            <QrCode size={20} className="text-white/80" />
            <SettingsIcon size={20} className="text-white/80" onClick={() => navigate('/login-settings')} />
          </div>
        </div>

        <div className="flex items-center gap-5 mb-8 relative z-10">
          <div className="relative">
            <div className="w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border-4 border-white/20 shadow-xl overflow-hidden">
               <span className="text-2xl font-black">{currentUser.fullName[0]}</span>
            </div>
            <div className="absolute -bottom-1 -right-1 bg-yellow-400 p-1.5 rounded-full border-2 border-billpay-green">
              <Camera size={12} className="text-billpay-green" />
            </div>
          </div>
          <div className="flex flex-col">
            <h2 className="text-xl font-black tracking-tight">{currentUser.fullName}</h2>
            <div className="flex items-center gap-2 mt-1">
              <span className="text-[10px] font-black uppercase opacity-70">@{currentUser.username}</span>
              <div className="w-1 h-1 rounded-full bg-white/40" />
              <span className="text-[10px] font-black uppercase opacity-70">{currentUser.phone}</span>
            </div>
            <div 
              onClick={() => setActiveModal('kyc')}
              className="mt-3 flex items-center gap-1.5 px-3 py-1 bg-white text-billpay-green text-[9px] font-black rounded-full border border-white/20 shadow-sm w-fit cursor-pointer active:scale-95 transition-all"
            >
               <Shield size={10} /> TIER {currentUser.tier} • {currentUser.kycStatus === 'verified' ? 'VERIFIED' : currentUser.kycStatus === 'pending' ? 'PENDING' : 'UPGRADE'}
               <ChevronRight size={10} />
            </div>
          </div>
        </div>
      </div>

      <div className="p-4 space-y-6 -mt-4 relative z-20">
        {/* Account Group */}
        <div className="shadow-sm">
           <MenuLink 
            icon={<UserIcon size={20} />} 
            label="Personal Information" 
            sub="Update your profile details" 
            onClick={() => setActiveModal('info')} 
            color="text-blue-500"
           />
           <MenuLink 
            icon={<CreditCard size={20} />} 
            label="Bank Accounts & Cards" 
            sub="Manage your payout methods" 
            onClick={() => navigate('/vcard')} 
            color="text-purple-500"
           />
           <MenuLink 
            icon={<History size={20} />} 
            label="Transaction Limits" 
            sub={`Tier ${currentUser.tier}: ${formatCurrency(currentUser.tier === 1 ? 50000 : currentUser.tier === 2 ? 500000 : 5000000)} Daily`} 
            onClick={() => setActiveModal('limits')} 
            color="text-amber-500"
           />
        </div>

        {/* Security Group */}
        <div className="shadow-sm">
           <MenuLink 
            icon={<Lock size={20} />} 
            label="Security & Privacy" 
            sub="Password, Biometrics, Devices" 
            onClick={() => navigate('/login-settings')} 
            color="text-indigo-500"
           />
           <MenuLink 
            icon={<Fingerprint size={20} />} 
            label="Payment PIN" 
            sub={currentUser.paymentPin ? "**** Locked" : "Set 4-digit transaction PIN"} 
            onClick={() => setActiveModal('pin')} 
            color="text-emerald-500"
           />
        </div>

        {/* Growth & Support */}
        <div className="shadow-sm">
           <MenuLink 
            icon={<Share2 size={20} />} 
            label="Refer & Earn" 
            sub="Get ₦500 per friend" 
            onClick={() => navigate('/referrals')} 
            color="text-pink-500"
           />
           <MenuLink 
            icon={<HelpCircle size={20} />} 
            label="Help Center" 
            sub="FAQ & Customer Support" 
            onClick={() => navigate('/support')} 
            color="text-cyan-500"
           />
           <MenuLink 
            icon={<Bell size={20} />} 
            label="Notification Settings" 
            sub="Manage app alerts" 
            onClick={() => setActiveModal('notify')} 
            color="text-orange-500"
           />
        </div>

        <button 
          onClick={handleLogout}
          className="w-full py-5 bg-white text-red-500 font-black text-sm rounded-[32px] shadow-sm border border-gray-100 flex items-center justify-center gap-3 active:scale-[0.98] transition-all"
        >
          <LogOut size={20} /> SIGN OUT
        </button>

        <div className="text-center space-y-1 py-4">
           <p className="text-[10px] font-black text-gray-300 uppercase tracking-[0.2em]">Billpay Clone v8.4.2</p>
           <p className="text-[8px] font-bold text-gray-400 uppercase">Licensed by Central Bank of Nigeria</p>
        </div>
      </div>

      {/* Global Modals Container */}
      {activeModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-sm rounded-[40px] overflow-hidden shadow-2xl animate-slide-up flex flex-col max-h-[90vh]">
            
            {/* 1. PERSONAL INFO MODAL */}
            {activeModal === 'info' && (
              <>
                <ModalHeader title="Personal Information" onClose={() => setActiveModal(null)} />
                <div className="p-8 space-y-6 overflow-y-auto">
                   <div className="space-y-4">
                      <div>
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2 ml-1">Full Legal Name</label>
                        <input className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={infoForm.fullName} onChange={e => setInfoForm({...infoForm, fullName: e.target.value})} />
                      </div>
                      <div>
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2 ml-1">Email Address</label>
                        <input className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={infoForm.email} onChange={e => setInfoForm({...infoForm, email: e.target.value})} />
                      </div>
                   </div>
                   <button onClick={() => { handleUpdateUser(infoForm); setActiveModal(null); }} className="w-full bg-billpay-green text-white py-5 rounded-[24px] font-black shadow-xl">SAVE CHANGES</button>
                </div>
              </>
            )}

            {/* 2. TRANSACTION LIMITS MODAL */}
            {activeModal === 'limits' && (
              <>
                <ModalHeader title="Account Limits" onClose={() => setActiveModal(null)} />
                <div className="p-8 space-y-6 overflow-y-auto">
                   <div className="space-y-4">
                      {[1, 2, 3].map(t => (
                        <div key={t} className={`p-5 rounded-3xl border-2 transition-all ${currentUser.tier === t ? 'border-billpay-green bg-green-50' : 'border-gray-50 bg-white opacity-50'}`}>
                           <div className="flex justify-between items-center mb-3">
                              <span className="text-[10px] font-black uppercase tracking-widest text-gray-400">Tier {t} Benefits</span>
                              {currentUser.tier === t && <CheckCircle2 size={16} className="text-billpay-green" />}
                           </div>
                           <div className="grid grid-cols-2 gap-4">
                              <div><div className="text-[8px] font-black uppercase opacity-40">Daily Limit</div><div className="text-sm font-black">{formatCurrency(t === 1 ? 50000 : t === 2 ? 500000 : 5000000)}</div></div>
                              <div className="text-right"><div className="text-[8px] font-black uppercase opacity-40">Single Tx</div><div className="text-sm font-black">{formatCurrency(t === 1 ? 10000 : t === 2 ? 100000 : 1000000)}</div></div>
                           </div>
                        </div>
                      ))}
                   </div>
                   {currentUser.tier < 3 && (
                     <button onClick={() => setActiveModal('kyc')} className="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black uppercase shadow-xl flex items-center justify-center gap-2">
                        UPGRADE ACCOUNT <ArrowRight size={16} />
                     </button>
                   )}
                </div>
              </>
            )}

            {/* 3. PAYMENT PIN MODAL */}
            {activeModal === 'pin' && (
              <>
                <ModalHeader title="Transaction PIN" onClose={() => setActiveModal(null)} />
                <form onSubmit={handlePinUpdate} className="p-8 space-y-6 overflow-y-auto">
                   <div className="p-4 bg-blue-50 rounded-2xl border border-blue-100 text-[10px] font-bold text-blue-600 uppercase leading-relaxed">
                      You'll need this 4-digit PIN for all transfers and bill payments. Keep it secret.
                   </div>
                   <div className="space-y-4">
                      {currentUser.paymentPin && (
                        <input 
                          type="password" maxLength={4} required placeholder="Current 4-digit PIN" 
                          className="w-full p-4 bg-gray-50 rounded-2xl font-black text-center text-xl tracking-[1em]" 
                          value={pinForm.currentPin} onChange={e => setPinForm({...pinForm, currentPin: e.target.value.replace(/\D/g, '')})} 
                        />
                      )}
                      <input 
                        type="password" maxLength={4} required placeholder="New 4-digit PIN" 
                        className="w-full p-4 bg-gray-50 rounded-2xl font-black text-center text-xl tracking-[1em]" 
                        value={pinForm.newPin} onChange={e => setPinForm({...pinForm, newPin: e.target.value.replace(/\D/g, '')})} 
                      />
                      <input 
                        type="password" maxLength={4} required placeholder="Confirm New PIN" 
                        className="w-full p-4 bg-gray-50 rounded-2xl font-black text-center text-xl tracking-[1em]" 
                        value={pinForm.confirmPin} onChange={e => setPinForm({...pinForm, confirmPin: e.target.value.replace(/\D/g, '')})} 
                      />
                   </div>
                   <button type="submit" disabled={isSubmitting} className="w-full bg-billpay-green text-white py-5 rounded-[24px] font-black shadow-xl">
                      {isSubmitting ? 'SECURELY SAVING...' : 'UPDATE PIN'}
                   </button>
                </form>
              </>
            )}

            {/* 4. NOTIFICATION SETTINGS MODAL */}
            {activeModal === 'notify' && (
              <>
                <ModalHeader title="Alert Preferences" onClose={() => setActiveModal(null)} />
                <div className="p-8 space-y-4 overflow-y-auto">
                   {[
                     { id: 'loginAlertsEnabled', label: 'Login Notifications', sub: 'Receive alerts when you log in' },
                     { id: 'marketingEmailsEnabled', label: 'Marketing Emails', sub: 'Product updates and promotions' },
                     { id: 'smsAlertsEnabled', label: 'SMS Transaction Alerts', sub: 'Carrier charges may apply' },
                   ].map(item => (
                    <div key={item.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-3xl border border-gray-100">
                      <div className="flex-1 pr-4">
                        <div className="text-xs font-black text-gray-800 uppercase">{item.label}</div>
                        <div className="text-[8px] font-bold text-gray-400 uppercase mt-0.5">{item.sub}</div>
                      </div>
                      <div 
                        onClick={() => handleUpdateUser({ [item.id]: !currentUser[item.id as keyof User] })}
                        className={`w-12 h-6 rounded-full relative transition-colors cursor-pointer ${currentUser[item.id as keyof User] ? 'bg-billpay-green' : 'bg-gray-300'}`}
                      >
                        <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${currentUser[item.id as keyof User] ? 'left-7' : 'left-1'}`} />
                      </div>
                    </div>
                   ))}
                </div>
              </>
            )}

            {/* 5. KYC MODAL (Refined) */}
            {activeModal === 'kyc' && (
              <>
                <div className="p-8 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
                  <div>
                    <h3 className="text-xl font-black text-gray-900 uppercase tracking-tight">Upgrade Account</h3>
                    <div className="flex gap-1.5 mt-2">
                      {[1, 2, 3].map(step => (
                        <div key={step} className={`h-1 rounded-full transition-all ${kycStep >= step ? 'w-8 bg-billpay-green' : 'w-4 bg-gray-200'}`} />
                      ))}
                    </div>
                  </div>
                  <button onClick={() => { setActiveModal(null); setKycStep(1); }} className="p-2 bg-white rounded-full shadow-sm"><X size={20} className="text-gray-400" /></button>
                </div>

                <div className="p-8 overflow-y-auto flex-1 scrollbar-hide">
                  {kycStep === 1 && (
                    <div className="space-y-6 animate-fade-in">
                        <div className="bg-green-50 p-5 rounded-3xl border border-green-100 flex items-center gap-4">
                          <Shield className="text-billpay-green" size={32} />
                          <div>
                              <div className="text-xs font-black text-green-800 uppercase">Unlock Tier 3</div>
                              <p className="text-[10px] font-bold text-green-600 mt-0.5 leading-relaxed uppercase">Increase your daily limit to ₦5,000,000.</p>
                          </div>
                        </div>
                        <div className="space-y-3">
                          <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Select Identity Type</label>
                          {['NIN', 'Passport', 'BVN', 'Drivers License'].map(type => (
                            <div 
                              key={type} onClick={() => setKycForm({...kycForm, idType: type})}
                              className={`p-5 rounded-2xl border-2 transition-all cursor-pointer flex justify-between items-center ${kycForm.idType === type ? 'border-billpay-green bg-green-50' : 'border-gray-50 bg-gray-50'}`}
                            >
                                <span className="text-xs font-black text-gray-800 uppercase">{type}</span>
                                {kycForm.idType === type && <CheckCircle2 size={18} className="text-billpay-green" />}
                            </div>
                          ))}
                        </div>
                    </div>
                  )}

                  {kycStep === 2 && (
                    <div className="space-y-6 animate-fade-in">
                        <div className="space-y-4">
                          <div>
                              <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1 block mb-2">ID Number ({kycForm.idType})</label>
                              <input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-black text-lg" value={kycForm.idNumber} onChange={(e) => setKycForm({...kycForm, idNumber: e.target.value})} />
                          </div>
                          <div>
                              <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1 block mb-2">Legal Name</label>
                              <input type="text" className="w-full p-4 bg-gray-50 rounded-2xl font-bold" value={kycForm.fullName} onChange={(e) => setKycForm({...kycForm, fullName: e.target.value})} />
                          </div>
                        </div>
                    </div>
                  )}

                  {kycStep === 3 && (
                    <div className="space-y-6 animate-fade-in text-center">
                        <div className="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto text-blue-500 mb-2">
                          <Camera size={32} />
                        </div>
                        <h4 className="text-sm font-black text-gray-800 uppercase">Selfie Verification</h4>
                        <div className="aspect-video bg-gray-100 rounded-3xl flex items-center justify-center border-2 border-dashed border-gray-200">
                          <Camera size={40} className="text-gray-300" />
                        </div>
                    </div>
                  )}
                </div>

                <div className="p-8 border-t border-gray-50 bg-gray-50">
                  {kycStep < 3 ? (
                    <button onClick={() => setKycStep(prev => prev + 1)} className="w-full bg-billpay-green text-white py-5 rounded-[24px] font-black">CONTINUE</button>
                  ) : (
                    <button onClick={handleKycSubmit} disabled={isSubmitting} className="w-full bg-gray-900 text-white py-5 rounded-[24px] font-black flex items-center justify-center gap-3">
                        {isSubmitting ? <RotateCcw className="animate-spin" /> : <><ShieldCheck size={20} /> SUBMIT</>}
                    </button>
                  )}
                  {kycStep > 1 && <button onClick={() => setKycStep(prev => prev - 1)} className="w-full mt-4 text-[10px] font-black text-gray-400 uppercase">Back</button>}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Shared Bottom Nav Component */}
      <div className="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-xl border-t border-gray-100 flex justify-around items-center py-4 px-6 shadow-[0_-10px_40px_rgba(0,0,0,0.05)] max-w-md mx-auto z-50 rounded-t-[32px]">
        <div onClick={() => navigate('/services')} className="flex flex-col items-center gap-1.5 cursor-pointer text-gray-400">
          <div className="p-1.5">
            <Landmark size={22} strokeWidth={2.5} />
          </div>
          <span className="text-[9px] font-black uppercase">Services</span>
        </div>
        <div onClick={() => navigate('/dashboard')} className="flex flex-col items-center gap-1.5 cursor-pointer text-gray-400">
          <div className="p-1.5">
             <History size={22} className="rotate-90" />
          </div>
          <span className="text-[9px] font-black uppercase">Home</span>
        </div>
        <div onClick={() => navigate('/rewards')} className="flex flex-col items-center gap-1.5 cursor-pointer text-gray-400">
          <div className="p-1.5">
            <Shield size={22} />
          </div>
          <span className="text-[9px] font-bold uppercase">Reward</span>
        </div>
        <div onClick={() => navigate('/transactions')} className="flex flex-col items-center gap-1.5 cursor-pointer text-gray-400">
          <div className="p-1.5">
            <History size={22} />
          </div>
          <span className="text-[9px] font-bold uppercase">History</span>
        </div>
        <div onClick={() => navigate('/profile')} className="flex flex-col items-center gap-1.5 cursor-pointer text-billpay-green font-black">
          <div className="p-1.5 bg-billpay-green/10 rounded-xl">
            <UserIcon size={22} />
          </div>
          <span className="text-[9px] font-bold uppercase">Me</span>
        </div>
      </div>
    </div>
  );
};

export default Profile;


import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { ArrowLeft, User, Shield, Camera, CheckCircle2, AlertCircle, Lock, MapPin, Calendar, FileText, ChevronRight, X } from 'lucide-react';

const Profile: React.FC = () => {
  const { currentUser, setKycSubmissions, users, setUsers } = useApp();
  const navigate = useNavigate();
  
  const [isKycModalOpen, setIsKycModalOpen] = useState(false);
  const [kycStep, setKycStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Form State
  const [formData, setFormData] = useState({
    fullName: currentUser?.fullName || '',
    dob: '',
    address: '',
    idType: 'National ID',
    idNumber: '',
  });

  if (!currentUser) return null;

  const handleKycSubmit = () => {
    setIsSubmitting(true);
    setTimeout(() => {
      const newSubmission = {
        id: Math.random().toString(36).substr(2, 9).toUpperCase(),
        userId: currentUser.id,
        ...formData,
        idImageUrl: 'mock_id_card.jpg',
        addressImageUrl: 'mock_utility_bill.jpg',
        status: 'pending' as const,
        date: new Date().toISOString()
      };
      setKycSubmissions(prev => [newSubmission, ...prev]);
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, kycStatus: 'pending' } : u));
      setIsSubmitting(false);
      setIsKycModalOpen(false);
      setKycStep(1);
      alert('Verification documents submitted! Our compliance team will review them shortly.');
    }, 2000);
  };

  const getTierDetails = (tier: number) => {
    switch(tier) {
      case 1: return { daily: '₦50,000', balance: '₦300,000', perks: 'Basic services' };
      case 2: return { daily: '₦200,000', balance: '₦500,000', perks: 'Virtual cards enabled' };
      case 3: return { daily: '₦5,000,000', balance: 'Unlimited', perks: 'Full platform access' };
      default: return { daily: '₦50,000', balance: '₦300,000', perks: 'Basic services' };
    }
  };

  const tierInfo = getTierDetails(currentUser.tier);

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 border-b sticky top-0 z-20">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">My Account</h1>
      </div>

      <div className="p-6 space-y-6 pb-24">
        {/* Profile Card */}
        <div className="bg-white p-8 rounded-[40px] shadow-sm flex flex-col items-center border border-gray-100">
          <div className="relative mb-4">
            <div className="w-24 h-24 bg-opay-green/10 rounded-full flex items-center justify-center border-4 border-white shadow-lg">
              <User size={48} className="text-opay-green" />
            </div>
            <div className="absolute bottom-0 right-0 bg-white p-2 rounded-full shadow-md border border-gray-100">
              <Camera size={14} className="text-gray-400" />
            </div>
          </div>
          <h2 className="text-xl font-black text-gray-900">{currentUser.fullName}</h2>
          <p className="text-xs font-bold text-gray-400 mt-1">{currentUser.phone}</p>
          <div className="mt-4 flex gap-2">
            <div className="flex items-center gap-1.5 px-3 py-1 bg-opay-green/10 text-opay-green text-[10px] font-black rounded-full border border-opay-green/20">
               <Shield size={10} /> TIER {currentUser.tier}
            </div>
            <span className={`px-3 py-1 text-[10px] font-black rounded-full border ${
              currentUser.kycStatus === 'verified' ? 'bg-green-100 text-green-700 border-green-200' : 
              currentUser.kycStatus === 'pending' ? 'bg-amber-100 text-amber-700 border-amber-200' :
              'bg-gray-100 text-gray-500 border-gray-200'
            }`}>
              {currentUser.kycStatus === 'verified' ? 'VERIFIED' : currentUser.kycStatus.toUpperCase()}
            </span>
          </div>
        </div>

        {/* Account Limits */}
        <div className="bg-white p-7 rounded-[32px] shadow-sm space-y-5 border border-gray-100">
          <div className="flex justify-between items-center">
             <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest">Transaction Limits</h3>
             <span className="text-[10px] font-black text-opay-green">LEVEL UP <ChevronRight size={10} className="inline" /></span>
          </div>
          <div className="space-y-4">
            <div className="flex justify-between items-center">
              <div className="flex flex-col">
                <span className="text-[10px] font-bold text-gray-400 uppercase">Daily Limit</span>
                <span className="text-sm font-black text-gray-800">{tierInfo.daily}</span>
              </div>
              <div className="text-right flex flex-col">
                <span className="text-[10px] font-bold text-gray-400 uppercase">Balance Limit</span>
                <span className="text-sm font-black text-gray-800">{tierInfo.balance}</span>
              </div>
            </div>
            <div className="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
               <div className={`h-full bg-opay-green transition-all duration-1000`} style={{ width: `${(currentUser.tier / 3) * 100}%` }} />
            </div>
          </div>
          
          {currentUser.kycStatus !== 'verified' && currentUser.kycStatus !== 'pending' && (
            <button 
              onClick={() => setIsKycModalOpen(true)}
              className="w-full bg-opay-green text-white font-black py-4 rounded-2xl shadow-xl flex items-center justify-center gap-3 mt-2 active:scale-95 transition-all"
            >
              <Shield size={18} /> Upgrade to Tier 3
            </button>
          )}
          {currentUser.kycStatus === 'pending' && (
            <div className="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex gap-3">
              <AlertCircle size={18} className="text-amber-500 shrink-0" />
              <p className="text-[10px] font-bold text-amber-700">Verification in progress. Tier upgrade will be applied automatically upon approval.</p>
            </div>
          )}
        </div>

        {/* Security List */}
        <div className="bg-white rounded-[32px] shadow-sm overflow-hidden border border-gray-100">
          <div 
            className="p-5 flex items-center justify-between border-b border-gray-50 cursor-pointer hover:bg-gray-50 transition-colors"
            onClick={() => navigate('/login-settings')}
          >
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500"><Lock size={20} /></div>
              <span className="text-sm font-black text-gray-700">Login Settings</span>
            </div>
            <ChevronRight size={16} className="text-gray-300" />
          </div>
          <div className="p-5 flex items-center justify-between border-b border-gray-50 cursor-pointer hover:bg-gray-50 transition-colors" onClick={() => navigate('/support')}>
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500"><Shield size={20} /></div>
              <span className="text-sm font-black text-gray-700">Help & Support</span>
            </div>
            <ChevronRight size={16} className="text-gray-300" />
          </div>
          <div className="p-5 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors" onClick={() => navigate('/login')}>
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-500"><X size={20} /></div>
              <span className="text-sm font-black text-red-500">Log out</span>
            </div>
          </div>
        </div>
      </div>

      {/* KYC MODAL */}
      {isKycModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-end">
          <div className="w-full max-w-md mx-auto bg-white rounded-t-[40px] p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center mb-8">
               <h3 className="text-xl font-black text-gray-900">Tier 3 Upgrade</h3>
               <button onClick={() => { setIsKycModalOpen(false); setKycStep(1); }} className="p-2 bg-gray-50 rounded-full"><X size={20} className="text-gray-400" /></button>
            </div>

            {/* Progress indicator */}
            <div className="flex gap-2 mb-8">
               <div className={`h-1.5 flex-1 rounded-full transition-all ${kycStep >= 1 ? 'bg-opay-green' : 'bg-gray-100'}`} />
               <div className={`h-1.5 flex-1 rounded-full transition-all ${kycStep >= 2 ? 'bg-opay-green' : 'bg-gray-100'}`} />
               <div className={`h-1.5 flex-1 rounded-full transition-all ${kycStep >= 3 ? 'bg-opay-green' : 'bg-gray-100'}`} />
            </div>
            
            <div className="space-y-6">
              {kycStep === 1 && (
                <div className="space-y-5 animate-fade-in">
                  <div className="flex items-center gap-3 mb-2">
                    <User size={18} className="text-opay-green" />
                    <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Personal Details</span>
                  </div>
                  <div>
                    <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Full Legal Name</label>
                    <input 
                      type="text" 
                      className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                      placeholder="As seen on ID card"
                      value={formData.fullName}
                      onChange={(e) => setFormData({...formData, fullName: e.target.value})}
                    />
                  </div>
                  <div>
                    <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Date of Birth</label>
                    <div className="relative">
                       <input 
                        type="date" 
                        className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                        value={formData.dob}
                        onChange={(e) => setFormData({...formData, dob: e.target.value})}
                      />
                    </div>
                  </div>
                  <div>
                    <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Residential Address</label>
                    <textarea 
                      className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm min-h-[80px]"
                      placeholder="Current street address"
                      value={formData.address}
                      onChange={(e) => setFormData({...formData, address: e.target.value})}
                    />
                  </div>
                  <button 
                    onClick={() => setKycStep(2)}
                    disabled={!formData.fullName || !formData.dob || !formData.address}
                    className="w-full bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl flex items-center justify-center gap-3 disabled:opacity-50 mt-4"
                  >
                    Next Step <ChevronRight size={18} />
                  </button>
                </div>
              )}

              {kycStep === 2 && (
                <div className="space-y-5 animate-fade-in">
                  <div className="flex items-center gap-3 mb-2">
                    <FileText size={18} className="text-opay-green" />
                    <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Identify Verification</span>
                  </div>
                  <div>
                    <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Identity Document Type</label>
                    <select 
                      className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                      value={formData.idType}
                      onChange={(e) => setFormData({...formData, idType: e.target.value})}
                    >
                      <option>National ID (NIN)</option>
                      <option>Voters Card</option>
                      <option>Drivers License</option>
                      <option>International Passport</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Document Number</label>
                    <input 
                      type="text" 
                      className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                      placeholder="Enter ID number"
                      value={formData.idNumber}
                      onChange={(e) => setFormData({...formData, idNumber: e.target.value})}
                    />
                  </div>
                  <div className="bg-gray-50 border-2 border-dashed border-gray-200 rounded-[32px] p-8 flex flex-col items-center justify-center group cursor-pointer hover:border-opay-green transition-colors">
                    <Camera size={28} className="text-gray-300 mb-3 group-hover:text-opay-green" />
                    <span className="text-[10px] font-black text-gray-400 uppercase group-hover:text-opay-green">Upload Front of ID</span>
                  </div>
                  <div className="flex gap-3">
                    <button onClick={() => setKycStep(1)} className="flex-1 py-5 font-black text-gray-400 text-xs uppercase tracking-widest">Back</button>
                    <button 
                      onClick={() => setKycStep(3)}
                      disabled={!formData.idNumber}
                      className="flex-[2] bg-gray-900 text-white font-black py-5 rounded-2xl shadow-xl flex items-center justify-center gap-3 disabled:opacity-50"
                    >
                      Next Step <ChevronRight size={18} />
                    </button>
                  </div>
                </div>
              )}

              {kycStep === 3 && (
                <div className="space-y-6 animate-fade-in">
                  <div className="flex items-center gap-3 mb-2">
                    <MapPin size={18} className="text-opay-green" />
                    <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Proof of Address</span>
                  </div>
                  <p className="text-[10px] font-bold text-gray-400 text-center px-4">Please upload a clear photo of a utility bill (Electricity, Water, or Waste) or a recent Bank Statement showing your address.</p>
                  
                  <div className="bg-gray-50 border-2 border-dashed border-gray-200 rounded-[32px] p-12 flex flex-col items-center justify-center group cursor-pointer hover:border-opay-green transition-colors">
                    <div className="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-sm mb-4">
                       <Camera size={28} className="text-gray-200 group-hover:text-opay-green" />
                    </div>
                    <span className="text-[10px] font-black text-gray-400 uppercase group-hover:text-opay-green">Select File / Take Photo</span>
                  </div>

                  <div className="flex gap-3">
                    <button onClick={() => setKycStep(2)} className="flex-1 py-5 font-black text-gray-400 text-xs uppercase tracking-widest">Back</button>
                    <button 
                      onClick={handleKycSubmit}
                      disabled={isSubmitting}
                      className="flex-[2] bg-opay-green text-white font-black py-5 rounded-2xl shadow-xl flex items-center justify-center gap-3 disabled:opacity-50"
                    >
                      {isSubmitting ? 'Processing...' : 'Complete & Submit'}
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Profile;
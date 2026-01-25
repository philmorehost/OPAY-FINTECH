
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

  if (!currentUser) return null;

  const handleKycSubmit = () => {
    setIsSubmitting(true);
    setTimeout(() => {
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, kycStatus: 'pending' } : u));
      setIsSubmitting(false);
      setIsKycModalOpen(false);
      setKycStep(1);
      alert('Verification documents submitted!');
    }, 2000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 border-b sticky top-0 z-20">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
        <h1 className="text-lg font-black text-gray-900">My Account</h1>
      </div>

      <div className="p-6 space-y-6 pb-24">
        <div className="bg-white p-8 rounded-[40px] shadow-sm flex flex-col items-center border border-gray-100">
          <div className="relative mb-4">
            <div className="w-24 h-24 bg-billpay-green/10 rounded-full flex items-center justify-center border-4 border-white shadow-lg">
              <User size={48} className="text-billpay-green" />
            </div>
          </div>
          <h2 className="text-xl font-black text-gray-900">{currentUser.fullName}</h2>
          <div className="mt-4 flex gap-2">
            <div className="flex items-center gap-1.5 px-3 py-1 bg-billpay-green/10 text-billpay-green text-[10px] font-black rounded-full border border-billpay-green/20">
               <Shield size={10} /> TIER {currentUser.tier}
            </div>
          </div>
        </div>

        <div className="bg-white p-7 rounded-[32px] shadow-sm space-y-5 border border-gray-100">
          <button 
            onClick={() => setIsKycModalOpen(true)}
            className="w-full bg-billpay-green text-white font-black py-4 rounded-2xl shadow-xl flex items-center justify-center gap-3 mt-2 active:scale-95 transition-all"
          >
            <Shield size={18} /> Upgrade to Tier 3
          </button>
        </div>
      </div>
    </div>
  );
};

export default Profile;


import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { ArrowLeft, Lock, Fingerprint, Bell, Smartphone, ChevronRight, CheckCircle2, AlertCircle, Eye, EyeOff, Trash2 } from 'lucide-react';

const LoginSettings: React.FC = () => {
  const { currentUser, setUsers, setCurrentUser } = useApp();
  const navigate = useNavigate();

  const [currentPass, setCurrentPass] = useState('');
  const [newPass, setNewPass] = useState('');
  const [confirmPass, setConfirmPass] = useState('');
  const [showPass, setShowPass] = useState(false);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  if (!currentUser) return null;

  const showToast = (type: 'success' | 'error', text: string) => {
    setMessage({ type, text });
    setTimeout(() => setMessage(null), 3000);
  };

  const handleToggleAlerts = () => {
    const newVal = !currentUser.loginAlertsEnabled;
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, loginAlertsEnabled: newVal } : u));
    setCurrentUser({ ...currentUser, loginAlertsEnabled: newVal });
    showToast('success', `Login alerts ${newVal ? 'enabled' : 'disabled'} successfully.`);
  };

  const handleToggleBiometrics = () => {
    const newVal = !currentUser.biometricEnabled;
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, biometricEnabled: newVal } : u));
    setCurrentUser({ ...currentUser, biometricEnabled: newVal });
    showToast('success', `Biometric login ${newVal ? 'enabled' : 'disabled'} successfully.`);
  };

  const handleRevokeDevice = (deviceId: string) => {
    const deviceToRevoke = currentUser.authorizedDevices.find(d => d.id === deviceId);
    if (deviceToRevoke?.isCurrent) {
        showToast('error', "You cannot revoke your current device session.");
        return;
    }

    const updatedDevices = currentUser.authorizedDevices.filter(d => d.id !== deviceId);
    setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, authorizedDevices: updatedDevices } : u));
    setCurrentUser({ ...currentUser, authorizedDevices: updatedDevices });
    showToast('success', "Device revoked successfully.");
  };

  const handleLogoutAll = () => {
    if (window.confirm("Are you sure you want to logout from all other devices?")) {
        const currentSession = currentUser.authorizedDevices.filter(d => d.isCurrent);
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, authorizedDevices: currentSession } : u));
        setCurrentUser({ ...currentUser, authorizedDevices: currentSession });
        showToast('success', "All other sessions have been terminated.");
    }
  };

  const handleChangePassword = (e: React.FormEvent) => {
    e.preventDefault();
    setMessage(null);

    if (currentPass !== currentUser.password) {
      setMessage({ type: 'error', text: 'Current password is incorrect' });
      return;
    }

    if (newPass !== confirmPass) {
      setMessage({ type: 'error', text: 'New passwords do not match' });
      return;
    }

    if (newPass.length < 3) {
      setMessage({ type: 'error', text: 'Password is too short' });
      return;
    }

    setLoading(true);
    setTimeout(() => {
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, password: newPass } : u));
      setCurrentUser({ ...currentUser, password: newPass });
      setLoading(false);
      showToast('success', 'Password updated successfully!');
      setCurrentPass('');
      setNewPass('');
      setConfirmPass('');
    }, 1500);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center gap-4 border-b sticky top-0 z-20">
        <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/profile')} />
        <h1 className="text-lg font-black text-gray-900">Login Settings</h1>
      </div>

      <div className="p-6 space-y-6 pb-24 overflow-y-auto scrollbar-hide">
        {message && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${
            message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'
          }`}>
            {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold">{message.text}</span>
          </div>
        )}

        {/* Change Password Card */}
        <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-6">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-500">
              <Lock size={20} />
            </div>
            <h3 className="text-sm font-black text-gray-800 uppercase tracking-tight">Change Password</h3>
          </div>

          <form onSubmit={handleChangePassword} className="space-y-4">
            <div className="relative">
              <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Current Password</label>
              <input 
                type={showPass ? "text" : "password"}
                className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                placeholder="••••••••"
                value={currentPass}
                onChange={(e) => setCurrentPass(e.target.value)}
                required
              />
              <button 
                type="button" 
                onClick={() => setShowPass(!showPass)}
                className="absolute right-4 top-[38px] text-gray-400"
              >
                {showPass ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">New Password</label>
                <input 
                  type={showPass ? "text" : "password"}
                  className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                  placeholder="Minimum 3 characters"
                  value={newPass}
                  onChange={(e) => setNewPass(e.target.value)}
                  required
                />
              </div>
              <div>
                <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block ml-1">Confirm New Password</label>
                <input 
                  type={showPass ? "text" : "password"}
                  className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl border-2 border-transparent focus:border-opay-green outline-none font-bold text-sm"
                  placeholder="Repeat new password"
                  value={confirmPass}
                  onChange={(e) => setConfirmPass(e.target.value)}
                  required
                />
              </div>
            </div>

            <button 
              type="submit"
              disabled={loading || !currentPass || !newPass}
              className="w-full bg-gray-900 text-white font-black py-4 rounded-2xl shadow-xl flex items-center justify-center gap-3 active:scale-95 transition-all disabled:opacity-50"
            >
              {loading ? "Updating..." : "Update Password"}
            </button>
          </form>
        </div>

        {/* Security Preferences */}
        <div className="bg-white rounded-[32px] shadow-sm border border-gray-100 overflow-hidden">
          <div className="p-5 border-b border-gray-50 flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-500">
                <Fingerprint size={20} />
              </div>
              <div>
                <div className="text-sm font-black text-gray-700">Biometric Login</div>
                <div className="text-[10px] text-gray-400 font-bold">Use Face ID or Fingerprint</div>
              </div>
            </div>
            <div 
              onClick={handleToggleBiometrics}
              className={`w-12 h-6 rounded-full relative transition-colors cursor-pointer ${currentUser.biometricEnabled ? 'bg-opay-green' : 'bg-gray-300'}`}
            >
              <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${currentUser.biometricEnabled ? 'left-7' : 'left-1'}`} />
            </div>
          </div>

          <div className="p-5 flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500">
                <Bell size={20} />
              </div>
              <div>
                <div className="text-sm font-black text-gray-700">Login Alerts</div>
                <div className="text-[10px] text-gray-400 font-bold">Notify on new device login</div>
              </div>
            </div>
            <div 
              onClick={handleToggleAlerts}
              className={`w-12 h-6 rounded-full relative transition-colors cursor-pointer ${currentUser.loginAlertsEnabled ? 'bg-opay-green' : 'bg-gray-300'}`}
            >
              <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${currentUser.loginAlertsEnabled ? 'left-7' : 'left-1'}`} />
            </div>
          </div>
        </div>

        {/* Devices Card */}
        <div className="bg-white p-6 rounded-[32px] shadow-sm border border-gray-100 space-y-4">
          <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Authorized Devices</h3>
          <div className="space-y-4">
            {currentUser.authorizedDevices.map((device) => (
              <div key={device.id} className="flex items-center justify-between p-2 hover:bg-gray-50 rounded-2xl transition-colors group">
                <div className="flex items-center gap-4">
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${device.isCurrent ? 'bg-green-50 text-green-600' : 'bg-gray-50 text-gray-400'}`}>
                    <Smartphone size={18} />
                  </div>
                  <div>
                    <div className="text-xs font-black text-gray-800 uppercase tracking-tight">
                      {device.name} {device.isCurrent && <span className="ml-1 text-[8px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full uppercase">Current</span>}
                    </div>
                    <div className="text-[9px] text-gray-400 font-bold">{device.location} • <span className="opacity-60">{new Date(device.lastActive).toLocaleDateString()}</span></div>
                  </div>
                </div>
                {!device.isCurrent && (
                  <button 
                    onClick={() => handleRevokeDevice(device.id)}
                    className="p-2 text-red-500 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-50 rounded-lg"
                    title="Revoke Access"
                  >
                    <Trash2 size={16} />
                  </button>
                )}
              </div>
            ))}
          </div>
          {currentUser.authorizedDevices.length > 1 && (
            <button 
                onClick={handleLogoutAll}
                className="w-full py-4 text-[10px] font-black text-red-500 uppercase tracking-widest border border-red-100 rounded-2xl hover:bg-red-50 transition-colors mt-2"
            >
                Terminate other sessions
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default LoginSettings;

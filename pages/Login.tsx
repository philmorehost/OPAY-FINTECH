
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { Eye, EyeOff, ShieldCheck, HelpCircle } from 'lucide-react';

const Login: React.FC = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const { users, setCurrentUser } = useApp();
  const navigate = useNavigate();

  const handleLogin = (e: React.FormEvent) => {
    e.preventDefault();
    const user = users.find(u => u.username.toLowerCase() === username.toLowerCase());
    
    if (user && password === user.password) {
      setCurrentUser(user);
      navigate(user.role === 'admin' ? '/admin' : '/dashboard');
    } else {
      setError('Incorrect username or password. Please try again.');
    }
  };

  return (
    <div className="flex flex-col min-h-screen bg-white max-w-md mx-auto shadow-2xl">
      <div className="p-8 pt-16 flex-1 flex flex-col">
        {/* Header/Logo Section */}
        <div className="flex flex-col items-center mb-12">
          <div className="w-20 h-20 bg-billpay-green rounded-[24px] flex items-center justify-center text-white text-4xl font-black mb-6 shadow-xl shadow-green-100 rotate-12 transition-transform hover:rotate-0">
            O
          </div>
          <h1 className="text-2xl font-black text-gray-900 tracking-tight">Welcome Back</h1>
          <p className="text-gray-400 text-xs font-bold uppercase tracking-widest mt-2">Sign in to your account</p>
        </div>

        <form onSubmit={handleLogin} className="space-y-8">
          {error && (
            <div className="p-4 bg-red-50 text-red-600 text-[10px] font-black uppercase tracking-tight rounded-2xl border border-red-100 animate-shake">
              {error}
            </div>
          )}
          
          <div className="space-y-2">
            <label className="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] ml-1">Username / Phone</label>
            <input
              type="text"
              className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green focus:bg-white rounded-[24px] outline-none font-black text-lg transition-all"
              placeholder="e.g. 08123456789"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              required
            />
          </div>

          <div className="space-y-2 relative">
            <label className="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] ml-1">Password</label>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-billpay-green focus:bg-white rounded-[24px] outline-none font-black text-lg transition-all"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
              <button 
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400"
              >
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
            </div>
          </div>

          <div className="flex justify-between items-center px-1">
            <div className="flex items-center gap-2 cursor-pointer">
               <div className="w-5 h-5 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                  <div className="w-2.5 h-2.5 bg-billpay-green rounded-sm" />
               </div>
               <span className="text-[10px] font-black text-gray-400 uppercase">Stay Logged In</span>
            </div>
            <span className="text-[10px] font-black text-billpay-green uppercase tracking-tight cursor-pointer">Forgot Password?</span>
          </div>

          <button
            type="submit"
            className="w-full bg-billpay-green text-white font-black py-5 rounded-[24px] shadow-2xl shadow-green-100 active:scale-[0.98] transition-all text-sm tracking-widest uppercase mt-4"
          >
            Sign In
          </button>
        </form>

        <div className="mt-12 text-center">
          <p className="text-[11px] font-bold text-gray-400 uppercase">
            Don't have an account? <span className="text-billpay-green font-black cursor-pointer">Create Account</span>
          </p>
        </div>

        <div className="mt-auto pt-10 pb-4 flex flex-col items-center gap-4">
          <div className="flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-full border border-gray-100">
            <ShieldCheck size={14} className="text-billpay-green" />
            <span className="text-[8px] font-black text-gray-400 uppercase tracking-widest">PCI-DSS Level 1 Secure</span>
          </div>
          <div className="flex items-center gap-4 opacity-30">
            <HelpCircle size={16} />
            <div className="h-4 w-[1px] bg-gray-400" />
            <span className="text-[8px] font-black uppercase">Support</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;

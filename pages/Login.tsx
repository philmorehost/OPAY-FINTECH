
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';

const Login: React.FC = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
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
      setError('Invalid username or password');
    }
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-screen px-6 bg-white">
      <div className="w-full max-w-md">
        <div className="flex flex-col items-center mb-10">
          <div className="w-20 h-20 bg-billpay-green rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-lg">
            B
          </div>
          <h1 className="text-2xl font-bold text-gray-800">Welcome to Billpay</h1>
          <p className="text-gray-50 text-sm mt-1">Reliable, Fast and Easy</p>
        </div>

        <form onSubmit={handleLogin} className="space-y-6">
          {error && <div className="p-3 bg-red-50 text-red-500 text-sm rounded-lg border border-red-100">{error}</div>}
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Username</label>
            <input
              type="text"
              className="w-full p-4 bg-gray-50 text-gray-900 border border-transparent focus:border-billpay-green rounded-xl outline-none transition-all"
              placeholder="Enter your username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              type="password"
              className="w-full p-4 bg-gray-50 text-gray-900 border border-transparent focus:border-billpay-green rounded-xl outline-none transition-all"
              placeholder="Enter your password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>

          <button
            type="submit"
            className="w-full bg-billpay-green text-white font-bold py-4 rounded-xl shadow-lg hover:opacity-90 transition-opacity active:scale-[0.98]"
          >
            Sign In
          </button>
        </form>

        <div className="mt-8 text-center">
          <p className="text-sm text-gray-400">
            Forgot Password? <span className="text-billpay-green font-medium cursor-pointer">Reset here</span>
          </p>
        </div>

        <div className="mt-12 text-xs text-gray-300 text-center uppercase tracking-widest font-bold">
          SECURE & LICENSED BY CBN
        </div>
      </div>
    </div>
  );
};

export default Login;

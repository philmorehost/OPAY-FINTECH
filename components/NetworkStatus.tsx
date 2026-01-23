
import React, { useState, useEffect } from 'react';
import { WifiOff, Wifi, AlertTriangle, CheckCircle2 } from 'lucide-react';

const NetworkStatus: React.FC = () => {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [showRestored, setShowRestored] = useState(false);

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      setShowRestored(true);
      // Hide the "Back Online" message after 3 seconds
      setTimeout(() => setShowRestored(false), 3000);
    };

    const handleOffline = () => {
      setIsOnline(false);
      setShowRestored(false);
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  if (isOnline && !showRestored) return null;

  return (
    <div className="fixed top-0 left-0 right-0 z-[200] flex justify-center pointer-events-none p-4 max-w-md mx-auto">
      {!isOnline ? (
        <div className="w-full bg-red-600/90 backdrop-blur-md text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center justify-between animate-slide-down pointer-events-auto border border-white/20">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center animate-pulse">
              <WifiOff size={16} />
            </div>
            <div>
              <div className="text-[11px] font-black uppercase tracking-widest">You are offline</div>
              <div className="text-[9px] font-bold opacity-80">Check your internet connection</div>
            </div>
          </div>
          <AlertTriangle size={18} className="text-yellow-400" />
        </div>
      ) : showRestored ? (
        <div className="w-full bg-opay-green/90 backdrop-blur-md text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center justify-between animate-slide-down pointer-events-auto border border-white/20">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
              <Wifi size={16} />
            </div>
            <div>
              <div className="text-[11px] font-black uppercase tracking-widest">Back Online</div>
              <div className="text-[9px] font-bold opacity-80">Connection restored successfully</div>
            </div>
          </div>
          <CheckCircle2 size={18} className="text-white" />
        </div>
      ) : null}
    </div>
  );
};

export default NetworkStatus;

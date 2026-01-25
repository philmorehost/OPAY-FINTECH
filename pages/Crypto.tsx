import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { 
  ArrowLeft, Bitcoin, RefreshCcw, 
  AlertCircle, CheckCircle2, Copy, QrCode, 
  ChevronDown, ShieldCheck, X, ShoppingCart, DollarSign, 
  Repeat, TrendingUp as TrendingUpIcon, TrendingDown as TrendingDownIcon,
  Wallet, ArrowDownToLine, ArrowUpFromLine, Activity
} from 'lucide-react';

interface AssetConfig {
  id: string;
  name: string;
  label: string;
  coingeckoId: string;
  color: string;
  icon: string;
  networks: string[];
}

const ASSETS: AssetConfig[] = [
  { id: 'BTC', name: 'BTC', label: 'Bitcoin', coingeckoId: 'bitcoin', color: 'bg-orange-500', icon: '₿', networks: ['Bitcoin (Native)', 'BEP20'] },
  { id: 'ETH', name: 'ETH', label: 'Ethereum', coingeckoId: 'ethereum', color: 'bg-blue-600', icon: 'Ξ', networks: ['ERC20', 'Base', 'Arbitrum'] },
  { id: 'USDT', name: 'USDT', label: 'Tether', coingeckoId: 'tether', color: 'bg-green-600', icon: '₮', networks: ['TRC20', 'ERC20', 'BEP20'] }
];

const NETWORK_FEES: Record<string, string> = {
  'Bitcoin (Native)': '0.0004 BTC',
  'BEP20': '1.00 USDT',
  'ERC20': '8.50 USDT',
  'Base': '0.25 USDT',
  'Arbitrum': '0.30 USDT',
  'TRC20': '1.00 USDT',
};

const NAIRA_RATE = 1650; // Fixed 1 USD = 1650 NGN

const Crypto: React.FC = () => {
  const { currentUser, setUsers, transactions, setTransactions, settings, setCurrentUser } = useApp();
  const navigate = useNavigate();
  
  const [portfolio, setPortfolio] = useState<Record<string, number>>(() => {
    const saved = localStorage.getItem('billpay_crypto_portfolio');
    return saved ? JSON.parse(saved) : { BTC: 0.0045, ETH: 0.12, USDT: 550.00 };
  });

  const [prices, setPrices] = useState<Record<string, { usd: number, change: number }>>({
    BTC: { usd: 65000, change: 0 },
    ETH: { usd: 3500, change: 0 },
    USDT: { usd: 1.00, change: 0 }
  });

  const [activeAction, setActiveAction] = useState<'buy' | 'sell' | 'deposit' | 'withdraw'>('buy');
  const [selectedAsset, setSelectedAsset] = useState<AssetConfig>(ASSETS[0]);
  const [selectedNetwork, setSelectedNetwork] = useState(selectedAsset.networks[0]);
  const [amount, setAmount] = useState('');
  const [withdrawalAddress, setWithdrawalAddress] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  useEffect(() => {
    localStorage.setItem('billpay_crypto_portfolio', JSON.stringify(portfolio));
  }, [portfolio]);

  const totalNgnPortfolioValue = useMemo(() => {
    return Object.entries(portfolio).reduce((acc, [id, qty]) => {
      const price = prices[id]?.usd || 0;
      // Fixed: Explicitly cast qty to number to resolve TypeScript arithmetic operation error
      return acc + ((qty as number) * price * NAIRA_RATE);
    }, 0);
  }, [portfolio, prices]);

  const fetchPrices = async () => {
    try {
      const response = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,tether&vs_currencies=usd&include_24hr_change=true');
      const data = await response.json();
      setPrices({
        BTC: { usd: data.bitcoin.usd, change: data.bitcoin.usd_24h_change },
        ETH: { usd: data.ethereum.usd, change: data.ethereum.usd_24h_change },
        USDT: { usd: data.tether.usd, change: data.tether.usd_24h_change }
      });
    } catch (err) {
      console.warn("Could not fetch real-time prices. Using fallback.");
    }
  };

  useEffect(() => {
    fetchPrices();
    const interval = setInterval(fetchPrices, 60000);
    return () => clearInterval(interval);
  }, []);

  const handleAssetChange = (asset: AssetConfig) => {
    setSelectedAsset(asset);
    setSelectedNetwork(asset.networks[0]);
    setAmount('');
    setStatus(null);
  };

  const handleTrade = async () => {
    if (!currentUser) return;
    const inputVal = parseFloat(amount);
    if (isNaN(inputVal) || inputVal <= 0) return;

    const currentAssetPrice = prices[selectedAsset.id].usd;
    const ref = generateId();
    setIsLoading(true);

    setTimeout(async () => {
      if (activeAction === 'buy') {
        if (currentUser.walletBalance < inputVal) {
          setStatus({ type: 'error', text: 'Insufficient wallet balance' });
          setIsLoading(false);
          return;
        }

        const cryptoEquivalent = (inputVal / NAIRA_RATE) / currentAssetPrice;
        
        const newTx: any = {
          id: ref,
          userId: currentUser.id,
          type: 'Crypto Buy',
          amount: inputVal,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Bought ${cryptoEquivalent.toFixed(8)} ${selectedAsset.id}`,
          recipient: selectedAsset.id,
          provider: 'Billpay Crypto'
        };

        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - inputVal } : u));
        setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance - inputVal });
        setPortfolio(prev => ({ ...prev, [selectedAsset.id]: (prev[selectedAsset.id] || 0) + cryptoEquivalent }));
        setTransactions(prev => [newTx, ...prev]);

        try {
          await sendNotificationEmail(settings, currentUser.email, 'Crypto Purchase', currentUser.fullName, {
            'Asset': selectedAsset.id,
            'Units': cryptoEquivalent.toFixed(8),
            'Spent': formatCurrency(inputVal),
            'Price USD': `$${currentAssetPrice.toLocaleString()}`,
            'Ref': ref
          });
        } catch (err) { console.warn(err); }

      } else if (activeAction === 'sell') {
        if ((portfolio[selectedAsset.id] || 0) < inputVal) {
          setStatus({ type: 'error', text: `Insufficient ${selectedAsset.id} balance` });
          setIsLoading(false);
          return;
        }

        const nairaEquivalent = inputVal * currentAssetPrice * NAIRA_RATE;
        
        const newTx: any = {
          id: ref,
          userId: currentUser.id,
          type: 'Crypto Sell',
          amount: nairaEquivalent,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Sold ${inputVal} ${selectedAsset.id}`,
          recipient: 'Wallet',
          provider: 'Billpay Crypto'
        };

        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + nairaEquivalent } : u));
        setCurrentUser({ ...currentUser, walletBalance: currentUser.walletBalance + nairaEquivalent });
        setPortfolio(prev => ({ ...prev, [selectedAsset.id]: (prev[selectedAsset.id] || 0) - inputVal }));
        setTransactions(prev => [newTx, ...prev]);

        try {
          await sendNotificationEmail(settings, currentUser.email, 'Crypto Sale', currentUser.fullName, {
            'Asset': selectedAsset.id,
            'Units Sold': inputVal,
            'Naira Credited': formatCurrency(nairaEquivalent),
            'Price USD': `$${currentAssetPrice.toLocaleString()}`,
            'Ref': ref
          });
        } catch (err) { console.warn(err); }

      } else if (activeAction === 'withdraw') {
        if ((portfolio[selectedAsset.id] || 0) < inputVal) {
          setStatus({ type: 'error', text: 'Insufficient balance' });
          setIsLoading(false);
          return;
        }
        if (!withdrawalAddress) {
          setStatus({ type: 'error', text: 'Address required' });
          setIsLoading(false);
          return;
        }

        const newTx: any = {
          id: ref,
          userId: currentUser.id,
          type: 'Crypto Withdrawal',
          amount: inputVal,
          status: 'pending',
          date: new Date().toISOString(),
          details: `Withdrew ${inputVal} ${selectedAsset.id} to ${withdrawalAddress}`,
          recipient: withdrawalAddress,
          provider: selectedNetwork
        };

        setPortfolio(prev => ({ ...prev, [selectedAsset.id]: (prev[selectedAsset.id] || 0) - inputVal }));
        setTransactions(prev => [newTx, ...prev]);
        setStatus({ type: 'success', text: 'Withdrawal request submitted!' });
        setAmount('');
        setWithdrawalAddress('');
        setIsLoading(false);
        return;
      }

      setIsLoading(false);
      setStatus({ type: 'success', text: `Transaction completed successfully!` });
      setAmount('');
    }, 1500);
  };

  const cryptoActivity = useMemo(() => {
    return transactions.filter(tx => tx.userId === currentUser?.id && tx.type.toLowerCase().includes('crypto')).slice(0, 8);
  }, [transactions, currentUser]);

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      {/* Header */}
      <div className="bg-white p-4 flex flex-col gap-4 border-b sticky top-0 z-50">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
            <h1 className="text-lg font-black text-gray-900 uppercase tracking-tight">Crypto Hub</h1>
          </div>
          <button onClick={fetchPrices} className="p-2 bg-gray-50 rounded-xl text-gray-400">
            <RefreshCcw size={18} className={isLoading ? 'animate-spin' : ''} />
          </button>
        </div>
        
        <div className="flex bg-gray-100 p-1.5 rounded-2xl">
          {(['buy', 'sell', 'deposit', 'withdraw'] as const).map(action => (
            <button 
              key={action}
              onClick={() => { setActiveAction(action); setStatus(null); setAmount(''); }}
              className={`flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all ${activeAction === action ? 'bg-white shadow-md text-billpay-green' : 'text-gray-400'}`}
            >
              {action}
            </button>
          ))}
        </div>
      </div>

      <div className="p-4 flex-1 space-y-6 overflow-y-auto scrollbar-hide">
        {/* Market Overview Horizontal */}
        <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
          {ASSETS.map(asset => {
            const data = prices[asset.id];
            return (
              <div 
                key={asset.id} 
                onClick={() => handleAssetChange(asset)}
                className={`min-w-[140px] p-5 rounded-[32px] border-2 transition-all cursor-pointer ${selectedAsset.id === asset.id ? 'border-billpay-green bg-white shadow-xl shadow-green-50' : 'border-transparent bg-white shadow-sm opacity-60'}`}
              >
                <div className="flex items-center gap-3 mb-3">
                  <div className={`w-8 h-8 rounded-xl ${asset.color} flex items-center justify-center text-white font-black text-sm`}>{asset.icon}</div>
                  <span className="text-xs font-black text-gray-800">{asset.name}</span>
                </div>
                <div className="text-sm font-black text-gray-900">${data.usd.toLocaleString()}</div>
                <div className={`text-[9px] font-black mt-1 flex items-center gap-1 ${data.change >= 0 ? 'text-green-500' : 'text-red-500'}`}>
                  {data.change >= 0 ? <TrendingUpIcon size={10} /> : <TrendingDownIcon size={10} />}
                  {data.change.toFixed(2)}%
                </div>
              </div>
            );
          })}
        </div>

        {/* Global Portfolio Value */}
        <div className="bg-gradient-to-br from-gray-900 to-black p-8 rounded-[40px] text-white space-y-4 relative overflow-hidden shadow-2xl">
          <div className="absolute -right-10 -top-10 w-48 h-48 bg-billpay-green/20 rounded-full blur-3xl" />
          <div className="flex justify-between items-center opacity-60">
            <span className="text-[10px] font-black uppercase tracking-[0.2em]">Estimated Portfolio Value</span>
            <Activity size={16} className="text-billpay-green" />
          </div>
          <div className="text-4xl font-black">{formatCurrency(totalNgnPortfolioValue)}</div>
          <div className="flex items-center gap-2 text-xs font-bold text-white/40">
             <span className="bg-white/10 px-3 py-1 rounded-full border border-white/5">Rate: ₦{NAIRA_RATE}/$</span>
          </div>
        </div>

        {status && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${status.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {status.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-xs font-bold flex-1">{status.text}</span>
            <X size={16} className="cursor-pointer opacity-50" onClick={() => setStatus(null)} />
          </div>
        )}

        {/* Dynamic Action Forms */}
        <div className="bg-white p-8 rounded-[40px] shadow-sm border border-gray-100 space-y-8 animate-fade-in">
          {(activeAction === 'buy' || activeAction === 'sell') && (
            <div className="space-y-6">
              <div className="flex justify-between items-center px-1">
                <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">{activeAction === 'buy' ? 'Spend (NGN)' : `Sell (${selectedAsset.id})`}</label>
                <div className="flex flex-col items-end">
                   <span className="text-[9px] font-black text-billpay-green uppercase">1 {selectedAsset.id} = ${prices[selectedAsset.id].usd.toLocaleString()}</span>
                   {activeAction === 'sell' && <span className="text-[8px] font-bold text-gray-400 uppercase">Available: {portfolio[selectedAsset.id]?.toFixed(8)}</span>}
                </div>
              </div>
              
              <div className="relative">
                <input 
                  type="number" 
                  placeholder="0.00" 
                  className="w-full p-6 bg-gray-50 text-gray-900 rounded-3xl border-2 border-transparent focus:border-billpay-green outline-none font-black text-3xl transition-all"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                />
                <div className="absolute right-6 top-1/2 -translate-y-1/2 text-xs font-black text-gray-300 uppercase">
                  {activeAction === 'buy' ? 'NGN' : selectedAsset.id}
                </div>
              </div>

              {amount && (
                <div className="p-5 bg-gray-50 rounded-3xl border border-gray-100 flex justify-between items-center animate-slide-down">
                  <div className="flex items-center gap-3">
                    <Repeat size={18} className="text-gray-400" />
                    <span className="text-[10px] font-black text-gray-400 uppercase">Conversion</span>
                  </div>
                  <div className="text-right">
                    <div className="text-sm font-black text-gray-800">
                      {activeAction === 'buy' 
                        ? `${((parseFloat(amount) / NAIRA_RATE) / prices[selectedAsset.id].usd).toFixed(8)} ${selectedAsset.id}`
                        : formatCurrency(parseFloat(amount) * prices[selectedAsset.id].usd * NAIRA_RATE)
                      }
                    </div>
                  </div>
                </div>
              )}

              <button 
                onClick={handleTrade}
                disabled={isLoading || !amount || parseFloat(amount) <= 0}
                className={`w-full py-5 rounded-[28px] font-black text-sm text-white shadow-xl transition-all active:scale-95 flex items-center justify-center gap-3 ${activeAction === 'buy' ? 'bg-billpay-green' : 'bg-gray-900'} disabled:opacity-50`}
              >
                {isLoading ? <RefreshCcw className="animate-spin" /> : activeAction === 'buy' ? <><ShoppingCart size={18} /> CONFIRM PURCHASE</> : <><DollarSign size={18} /> CONFIRM SALE</>}
              </button>
            </div>
          )}

          {activeAction === 'deposit' && (
            <div className="space-y-8 text-center animate-fade-in">
              <div className="space-y-4">
                <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Select Network</label>
                <div className="flex flex-wrap justify-center gap-2">
                  {selectedAsset.networks.map(net => (
                    <button 
                      key={net}
                      onClick={() => setSelectedNetwork(net)}
                      className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all ${selectedNetwork === net ? 'bg-billpay-green text-white shadow-md' : 'bg-gray-50 text-gray-400'}`}
                    >
                      {net}
                    </button>
                  ))}
                </div>
              </div>

              <div className="p-6 bg-white border-2 border-dashed border-gray-100 rounded-[40px] flex flex-col items-center gap-6">
                <div className="w-48 h-48 bg-gray-50 rounded-[32px] flex items-center justify-center p-4">
                  <QrCode size={140} className="text-gray-300 opacity-40" />
                </div>
                <div className="space-y-2 w-full">
                  <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Your {selectedAsset.id} ({selectedNetwork}) Address</span>
                  <div className="flex gap-2 w-full">
                    <div className="flex-1 bg-gray-50 p-4 rounded-2xl text-[10px] font-mono break-all text-gray-600 border border-gray-100">
                      bc1q{generateId().toLowerCase()}{generateId().toLowerCase()}
                    </div>
                    <button onClick={() => alert("Copied")} className="p-4 bg-gray-900 text-white rounded-2xl active:scale-95"><Copy size={20} /></button>
                  </div>
                </div>
              </div>

              <div className="bg-amber-50 p-5 rounded-3xl border border-amber-100 flex gap-4 items-start text-left">
                <AlertCircle size={20} className="text-amber-500 shrink-0 mt-0.5" />
                <p className="text-[9px] font-bold text-amber-700 leading-relaxed uppercase">Only send <span className="font-black">{selectedAsset.id}</span> via the <span className="font-black">{selectedNetwork}</span> network. Others will be lost.</p>
              </div>
            </div>
          )}

          {activeAction === 'withdraw' && (
            <div className="space-y-6">
              <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Choose Network</label>
                <div className="grid grid-cols-2 gap-2">
                  {selectedAsset.networks.map(net => (
                    <button 
                      key={net}
                      onClick={() => setSelectedNetwork(net)}
                      className={`py-3 rounded-2xl text-[10px] font-black uppercase transition-all ${selectedNetwork === net ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-400'}`}
                    >
                      {net}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest px-1">Address</label>
                <div className="relative">
                  <input type="text" placeholder="Paste address..." className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-bold text-xs" value={withdrawalAddress} onChange={(e) => setWithdrawalAddress(e.target.value)} />
                  <ShieldCheck className="absolute right-4 top-1/2 -translate-y-1/2 text-billpay-green/40" size={18} />
                </div>
              </div>

              <div>
                <div className="flex justify-between items-center mb-2 px-1">
                  <label className="block text-[10px] font-black text-gray-400 uppercase tracking-widest">Amount ({selectedAsset.id})</label>
                  <button onClick={() => setAmount(portfolio[selectedAsset.id]?.toString() || '0')} className="text-[10px] font-black text-billpay-green uppercase">Use Max</button>
                </div>
                <input type="number" placeholder="0.00" className="w-full p-4 bg-gray-50 text-gray-900 rounded-2xl outline-none font-black text-xl" value={amount} onChange={(e) => setAmount(e.target.value)} />
              </div>

              <div className="bg-gray-900 p-6 rounded-[32px] text-white space-y-3">
                <div className="flex justify-between text-[10px] font-black uppercase tracking-widest opacity-60"><span>Network Fee</span><span>{NETWORK_FEES[selectedNetwork] || 'Calculating...'}</span></div>
                <div className="h-px bg-white/10" />
                <div className="flex justify-between text-xs font-black uppercase"><span>Total Withdrawal</span><span>{amount || '0'} {selectedAsset.id}</span></div>
              </div>

              <button onClick={handleTrade} disabled={isLoading || !amount || !withdrawalAddress} className="w-full bg-billpay-green text-white py-5 rounded-[28px] font-black text-sm shadow-xl active:scale-95 disabled:opacity-50">
                {isLoading ? <RefreshCcw className="animate-spin" /> : 'INITIATE WITHDRAWAL'}
              </button>
            </div>
          )}
        </div>

        {/* Refined Transaction History Section */}
        <div className="space-y-4 pb-12">
           <div className="flex justify-between items-center px-1">
              <h3 className="text-xs font-black text-gray-800 uppercase tracking-widest">Transaction History</h3>
              <button onClick={() => navigate('/transactions')} className="text-[10px] font-black text-billpay-green uppercase">History</button>
           </div>
           <div className="space-y-3">
              {cryptoActivity.length === 0 ? (
                <div className="py-12 text-center bg-white rounded-[40px] text-gray-300 font-black text-[9px] uppercase border-2 border-dashed border-gray-100">No recent activity</div>
              ) : (
                cryptoActivity.map(tx => (
                  <div key={tx.id} className="bg-white p-5 rounded-[32px] border border-gray-100 flex items-center justify-between shadow-sm">
                     <div className="flex items-center gap-4">
                        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${tx.type.includes('Buy') || tx.type.includes('Deposit') ? 'bg-green-50 text-green-500' : 'bg-red-50 text-red-500'}`}>
                           {tx.type.includes('Buy') || tx.type.includes('Deposit') ? <ArrowDownToLine size={18} /> : <ArrowUpFromLine size={18} />}
                        </div>
                        <div>
                           <div className="text-[11px] font-black text-gray-800 uppercase">{tx.type}</div>
                           <div className="text-[9px] text-gray-400 font-bold">{new Date(tx.date).toLocaleDateString()} • {tx.recipient}</div>
                        </div>
                     </div>
                     <div className="text-right">
                        <div className="text-xs font-black text-gray-900">{tx.amount.toLocaleString()} {tx.type.includes('Sell') || tx.type.includes('Buy') ? 'NGN' : ''}</div>
                        <div className={`text-[8px] font-black uppercase ${tx.status === 'successful' ? 'text-billpay-green' : 'text-amber-500'}`}>{tx.status}</div>
                     </div>
                  </div>
                ))
              )}
           </div>
        </div>
      </div>
    </div>
  );
};

export default Crypto;

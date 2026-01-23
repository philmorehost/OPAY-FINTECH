
import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { 
  ArrowLeft, Bitcoin, ArrowUpRight, ArrowDownLeft, RefreshCcw, 
  AlertCircle, CheckCircle2, Copy, QrCode, History, Clock, 
  ChevronDown, ShieldCheck, Zap, Globe, Bell, Plus, Trash2,
  ArrowUp, ArrowDown, X, ShoppingCart, TrendingUp, TrendingDown,
  DollarSign, ArrowRight, Info, ExternalLink, Repeat
} from 'lucide-react';

interface PriceAlert {
  id: string;
  asset: string;
  targetPrice: number;
  condition: 'above' | 'below';
  triggered: boolean;
  createdAt: string;
}

const Crypto: React.FC = () => {
  const { currentUser, setUsers, transactions, setTransactions } = useApp();
  const navigate = useNavigate();
  
  const [prices, setPrices] = useState({
    BTC: 65420.50,
    ETH: 3450.20,
    SOL: 145.85,
    ADA: 0.46,
    USDT: 1.00
  });
  
  // Local session portfolio to simulate crypto balances
  const [portfolio, setPortfolio] = useState<Record<string, number>>({
    BTC: 0.0045,
    ETH: 0.12,
    SOL: 8.50,
    ADA: 420.00,
    USDT: 50.00
  });

  const [selectedAsset, setSelectedAsset] = useState('BTC');
  const [targetSwapAsset, setTargetSwapAsset] = useState('USDT');
  const [selectedNetwork, setSelectedNetwork] = useState('');
  const [amount, setAmount] = useState('');
  const [depositAmount, setDepositAmount] = useState('');
  const [address, setAddress] = useState('');
  const [activeTab, setActiveTab] = useState<'send' | 'receive' | 'trade' | 'alerts' | 'history'>('trade');
  const [tradeType, setTradeType] = useState<'buy' | 'sell' | 'swap'>('buy');
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState<{ type: 'success' | 'error', text: string } | null>(null);
  
  const [withdrawalStep, setWithdrawalStep] = useState<'input' | 'review'>('input');
  const [priceAlerts, setPriceAlerts] = useState<PriceAlert[]>([]);
  const [isAlertModalOpen, setIsAlertModalOpen] = useState(false);
  const [newAlertPrice, setNewAlertPrice] = useState('');
  const [newAlertCondition, setNewAlertCondition] = useState<'above' | 'below'>('above');
  const [userAddresses, setUserAddresses] = useState<Record<string, string>>({});

  const assets = [
    { 
      name: 'BTC', 
      label: 'Bitcoin', 
      color: 'bg-orange-500', 
      icon: '₿', 
      networks: ['Bitcoin (Native)', 'BEP20 (BSC)', 'Lightning']
    },
    { 
      name: 'ETH', 
      label: 'Ethereum', 
      color: 'bg-blue-600', 
      icon: 'Ξ', 
      networks: ['ERC20 (Ethereum)', 'BEP20 (BSC)', 'Arbitrum One', 'Optimism']
    },
    { 
      name: 'SOL', 
      label: 'Solana', 
      color: 'bg-purple-600', 
      icon: 'S', 
      networks: ['Solana (Native)', 'BEP20 (BSC)', 'Devnet']
    },
    { 
      name: 'ADA', 
      label: 'Cardano', 
      color: 'bg-blue-800', 
      icon: 'A', 
      networks: ['Cardano (Native)', 'BEP20 (BSC)']
    },
    { 
      name: 'USDT', 
      label: 'Tether', 
      color: 'bg-green-600', 
      icon: '₮', 
      networks: ['TRC20 (Tron)', 'ERC20 (Ethereum)', 'BEP20 (BSC)', 'Polygon']
    }
  ];

  const currentAsset = useMemo(() => assets.find(a => a.name === selectedAsset) || assets[0], [selectedAsset]);

  useEffect(() => {
    setSelectedNetwork(currentAsset.networks[0]);
    setWithdrawalStep('input');
  }, [selectedAsset, currentAsset.networks]);

  const generateNewAddress = (asset: string, network: string) => {
    setIsLoading(true);
    setTimeout(() => {
      let prefix = '';
      if (asset === 'BTC') prefix = network.includes('Native') ? 'bc1q' : '1';
      else if (asset === 'ETH' || network.includes('ERC20') || network.includes('BEP20')) prefix = '0x';
      else if (asset === 'USDT' && network.includes('TRC20')) prefix = 'T';
      else if (asset === 'SOL') prefix = '';
      else if (asset === 'ADA') prefix = 'addr1';
      
      const randomPart = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
      const newAddr = prefix + randomPart.toUpperCase();
      
      const key = `${asset}-${network}`;
      setUserAddresses(prev => ({ ...prev, [key]: newAddr }));
      setIsLoading(false);
    }, 1200);
  };

  const currentAddress = userAddresses[`${selectedAsset}-${selectedNetwork}`];

  const cryptoTransactions = useMemo(() => {
    return transactions.filter(tx => 
      tx.userId === currentUser?.id && 
      (tx.type.includes('Crypto'))
    ).sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
  }, [transactions, currentUser]);

  useEffect(() => {
    const interval = setInterval(() => {
      setPrices(prev => ({
        BTC: prev.BTC + (Math.random() - 0.5) * 40,
        ETH: prev.ETH + (Math.random() - 0.5) * 10,
        SOL: prev.SOL + (Math.random() - 0.5) * 2,
        ADA: prev.ADA + (Math.random() - 0.5) * 0.05,
        USDT: 1.00
      }));
    }, 5000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    priceAlerts.forEach(alert => {
      if (alert.triggered) return;
      const currentPrice = prices[alert.asset as keyof typeof prices];
      const isMet = alert.condition === 'above' 
        ? currentPrice >= alert.targetPrice 
        : currentPrice <= alert.targetPrice;

      if (isMet) {
        setPriceAlerts(prev => prev.map(a => a.id === alert.id ? { ...a, triggered: true } : a));
        setStatus({ 
          type: 'success', 
          text: `🚨 ALERT: ${alert.asset} has hit your target of $${alert.targetPrice.toLocaleString()}!` 
        });
        if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
      }
    });
  }, [prices, priceAlerts]);

  const swapRate = useMemo(() => {
    const fromPrice = prices[selectedAsset as keyof typeof prices];
    const toPrice = prices[targetSwapAsset as keyof typeof prices];
    return fromPrice / toPrice;
  }, [prices, selectedAsset, targetSwapAsset]);

  const handleTrade = () => {
    if (!currentUser) return;
    const inputAmount = parseFloat(amount);
    if (isNaN(inputAmount) || inputAmount <= 0) {
      setStatus({ type: 'error', text: 'Please enter a valid amount' });
      return;
    }

    const currentAssetPrice = prices[selectedAsset as keyof typeof prices];

    if (tradeType === 'buy') {
      const exchangeRate = 1620; 
      const cryptoEquivalent = (inputAmount / exchangeRate) / currentAssetPrice;
      if (currentUser.walletBalance < inputAmount) {
        setStatus({ type: 'error', text: 'Insufficient balance' });
        return;
      }
      setIsLoading(true);
      setTimeout(() => {
        const newTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Crypto Buy',
          amount: inputAmount,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Bought ${cryptoEquivalent.toFixed(8)} ${selectedAsset}`,
          recipient: selectedAsset,
          provider: 'OPay Exchange'
        };
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - inputAmount } : u));
        setPortfolio(prev => ({ ...prev, [selectedAsset]: (prev[selectedAsset] || 0) + cryptoEquivalent }));
        setTransactions(prev => [newTx, ...prev]);
        setIsLoading(false);
        setStatus({ type: 'success', text: `Successfully bought ${cryptoEquivalent.toFixed(6)} ${selectedAsset}!` });
        setAmount('');
      }, 1500);
    } 
    else if (tradeType === 'sell') {
      const exchangeRate = 1620; 
      const amountToSellInCrypto = (inputAmount / exchangeRate) / currentAssetPrice;

      if ((portfolio[selectedAsset] || 0) < amountToSellInCrypto) {
        setStatus({ type: 'error', text: `Insufficient ${selectedAsset} balance` });
        return;
      }

      setIsLoading(true);
      setTimeout(() => {
        const newTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Crypto Sell',
          amount: inputAmount,
          status: 'successful',
          date: new Date().toISOString(),
          details: `Sold ${amountToSellInCrypto.toFixed(8)} ${selectedAsset}`,
          recipient: selectedAsset,
          provider: 'OPay Exchange'
        };
        setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance + inputAmount } : u));
        setPortfolio(prev => ({ ...prev, [selectedAsset]: prev[selectedAsset] - amountToSellInCrypto }));
        setTransactions(prev => [newTx, ...prev]);
        setIsLoading(false);
        setStatus({ type: 'success', text: `Successfully sold ${selectedAsset} for ${formatCurrency(inputAmount)}!` });
        setAmount('');
      }, 1500);
    }
    else if (tradeType === 'swap') {
      const fromAmount = inputAmount; // Entered in source crypto units
      const toAmount = fromAmount * swapRate;
      const fee = toAmount * 0.005; // 0.5% swap fee
      const finalToAmount = toAmount - fee;

      if ((portfolio[selectedAsset] || 0) < fromAmount) {
        setStatus({ type: 'error', text: `Insufficient ${selectedAsset} balance to swap` });
        return;
      }

      setIsLoading(true);
      setTimeout(() => {
        const newTx: any = {
          id: generateId(),
          userId: currentUser.id,
          type: 'Crypto Swap',
          amount: fromAmount * prices[selectedAsset as keyof typeof prices] * 1620, // Value in NGN for history
          status: 'successful',
          date: new Date().toISOString(),
          details: `Swapped ${fromAmount} ${selectedAsset} for ${finalToAmount.toFixed(8)} ${targetSwapAsset}`,
          recipient: targetSwapAsset,
          provider: 'OPay DEX'
        };

        setPortfolio(prev => ({
          ...prev,
          [selectedAsset]: prev[selectedAsset] - fromAmount,
          [targetSwapAsset]: (prev[targetSwapAsset] || 0) + finalToAmount
        }));

        setTransactions(prev => [newTx, ...prev]);
        setIsLoading(false);
        setStatus({ type: 'success', text: `Swapped ${selectedAsset} to ${targetSwapAsset} successfully!` });
        setAmount('');
        setActiveTab('history');
      }, 2000);
    }
  };

  const handleWithdraw = () => {
    if (!currentUser) return;
    const usdAmount = parseFloat(amount);
    if (isNaN(usdAmount) || usdAmount <= 0 || address.length < 26) {
        setStatus({ type: 'error', text: 'Check amount and address' });
        return;
    }
    
    const exchangeRate = 1620; 
    const nairaEquivalent = usdAmount * exchangeRate;
    const cryptoAmount = usdAmount / prices[selectedAsset as keyof typeof prices];

    if ((portfolio[selectedAsset] || 0) < cryptoAmount) {
      setStatus({ type: 'error', text: 'Insufficient crypto balance' });
      return;
    }

    setIsLoading(true);
    setTimeout(() => {
      const newTx: any = {
        id: generateId(),
        userId: currentUser.id,
        type: 'Crypto Transfer',
        amount: nairaEquivalent,
        status: 'successful',
        date: new Date().toISOString(),
        details: `Sent ${cryptoAmount.toFixed(6)} ${selectedAsset} to external address`,
        recipient: address,
        provider: selectedNetwork
      };

      setPortfolio(prev => ({ ...prev, [selectedAsset]: prev[selectedAsset] - cryptoAmount }));
      setTransactions(prev => [newTx, ...prev]);
      setIsLoading(false);
      setStatus({ type: 'success', text: `Transaction broadcasted successfully!` });
      setAmount('');
      setAddress('');
      setWithdrawalStep('input');
    }, 2500);
  };

  const handleSimulateDeposit = () => {
    if (!currentUser || !currentAddress) return;
    const usdAmount = parseFloat(depositAmount);
    if (isNaN(usdAmount) || usdAmount <= 0) return;
    
    setIsLoading(true);
    const cryptoAmount = usdAmount / prices[selectedAsset as keyof typeof prices];

    setTimeout(() => {
      const newTx: any = {
        id: generateId(),
        userId: currentUser.id,
        type: 'Crypto Deposit',
        amount: usdAmount * 1620,
        status: 'successful',
        date: new Date().toISOString(),
        details: `Received ${cryptoAmount.toFixed(6)} ${selectedAsset} via ${selectedNetwork}`,
        recipient: currentAddress,
        provider: selectedNetwork
      };

      setPortfolio(prev => ({ ...prev, [selectedAsset]: (prev[selectedAsset] || 0) + cryptoAmount }));
      setTransactions(prev => [newTx, ...prev]);
      setIsLoading(false);
      setStatus({ type: 'success', text: `Deposit of ${selectedAsset} confirmed!` });
      setDepositAmount('');
    }, 3000);
  };

  const handleAddAlert = () => {
    const target = parseFloat(newAlertPrice);
    if (isNaN(target) || target <= 0) return;
    const newAlert: PriceAlert = {
      id: generateId(),
      asset: selectedAsset,
      targetPrice: target,
      condition: newAlertCondition,
      triggered: false,
      createdAt: new Date().toISOString()
    };
    setPriceAlerts(prev => [newAlert, ...prev]);
    setIsAlertModalOpen(false);
    setNewAlertPrice('');
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    setStatus({ type: 'success', text: 'Copied to clipboard!' });
  };

  const flipSwap = () => {
    const temp = selectedAsset;
    setSelectedAsset(targetSwapAsset);
    setTargetSwapAsset(temp);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10">
      <div className="bg-white p-4 flex items-center justify-between sticky top-0 z-10 border-b">
        <div className="flex items-center gap-4">
          <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
          <h1 className="text-lg font-black text-gray-900">Crypto Hub</h1>
        </div>
        <div className="flex items-center gap-2 bg-green-50 px-3 py-1 rounded-full border border-green-100">
           <div className="w-1.5 h-1.5 bg-opay-green rounded-full animate-pulse" />
           <span className="text-[9px] font-black text-opay-green uppercase">Live Market</span>
        </div>
      </div>

      <div className="p-4 space-y-6 flex-1">
        {status && (
          <div className={`p-4 rounded-2xl flex items-center gap-3 animate-fade-in ${status.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'}`}>
            {status.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
            <span className="text-sm font-bold flex-1">{status.text}</span>
            <button onClick={() => setStatus(null)}><X size={16} /></button>
          </div>
        )}

        {/* Portfolio Mini Card */}
        <div className="bg-gray-900 p-6 rounded-[32px] text-white shadow-xl relative overflow-hidden">
           <div className="absolute -right-8 -top-8 w-32 h-32 bg-opay-green/20 rounded-full blur-3xl" />
           <div className="text-[10px] font-black uppercase tracking-[0.2em] text-white/50 mb-1">Your Portfolio</div>
           <div className="text-2xl font-black mb-4">
             {formatCurrency(Object.entries(portfolio).reduce((acc: number, [asset, bal]) => acc + ((bal as number) * (prices[asset as keyof typeof prices] || 0) * 1620), 0))}
           </div>
           <div className="flex gap-4 overflow-x-auto scrollbar-hide pb-1">
              {assets.map(a => (
                <div key={a.name} className="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-xl border border-white/10 shrink-0">
                  <span className="text-[10px] font-black">{a.name}</span>
                  <span className="text-[10px] font-bold text-white/70">{(portfolio[a.name] || 0).toFixed(a.name === 'USDT' ? 2 : 4)}</span>
                </div>
              ))}
           </div>
        </div>

        {/* Assets Carousel */}
        <div className="flex gap-4 overflow-x-auto pb-2 scrollbar-hide px-1">
          {assets.map(asset => (
            <div 
              key={asset.name}
              onClick={() => setSelectedAsset(asset.name)}
              className={`min-w-[140px] p-5 rounded-[32px] border-2 transition-all cursor-pointer relative overflow-hidden bg-white ${selectedAsset === asset.name ? 'border-opay-green shadow-xl shadow-green-100 scale-105' : 'border-transparent opacity-60'}`}
            >
              <div className={`w-10 h-10 ${asset.color} rounded-2xl flex items-center justify-center text-white text-lg font-black shadow-md mb-3`}>
                {asset.icon}
              </div>
              <div className="text-[10px] font-black text-gray-400 uppercase tracking-widest">{asset.name}</div>
              <div className="text-sm font-black text-gray-900 mt-0.5">
                ${prices[asset.name as keyof typeof prices].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
              </div>
            </div>
          ))}
        </div>

        {/* Primary Tabs */}
        <div className="bg-white p-6 rounded-[40px] shadow-sm space-y-8 border border-gray-100">
          <div className="flex bg-gray-100 p-1.5 rounded-3xl overflow-x-auto scrollbar-hide">
            {[
              { id: 'trade', icon: <Repeat size={14} />, label: 'Market' },
              { id: 'receive', icon: <ArrowDownLeft size={14} />, label: 'Deposit' },
              { id: 'send', icon: <ArrowUpRight size={14} />, label: 'Send' },
              { id: 'alerts', icon: <Bell size={14} />, label: 'Alerts' },
              { id: 'history', icon: <History size={14} />, label: 'History' }
            ].map(tab => (
              <button 
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`flex-1 min-w-[80px] py-4 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 ${activeTab === tab.id ? 'bg-white shadow-xl text-opay-green' : 'text-gray-400'}`}
              >
                {tab.icon} {tab.label}
              </button>
            ))}
          </div>

          {/* Trade Tab (Buy/Sell/Swap) */}
          {activeTab === 'trade' && (
            <div className="space-y-6 animate-fade-in">
              <div className="flex bg-gray-100 p-1 rounded-2xl">
                {['buy', 'sell', 'swap'].map(type => (
                  <button 
                    key={type}
                    onClick={() => { setTradeType(type as any); setAmount(''); }}
                    className={`flex-1 py-3 rounded-xl text-[10px] font-black uppercase transition-all ${tradeType === type ? 'bg-white shadow-md text-opay-green' : 'text-gray-400'}`}
                  >
                    {type}
                  </button>
                ))}
              </div>

              {tradeType === 'swap' ? (
                <div className="space-y-6 animate-fade-in">
                  <div className="space-y-4">
                    <div className="relative">
                      <div className="text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest">From</div>
                      <div className="bg-gray-50 p-5 rounded-3xl border border-gray-100 flex items-center justify-between">
                        <div className="space-y-1">
                          <input 
                            type="number"
                            placeholder="0.00"
                            className="bg-transparent border-none outline-none font-black text-2xl w-full text-gray-900"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                          />
                          <div className="text-[9px] font-bold text-gray-400">Balance: {(portfolio[selectedAsset] || 0).toFixed(6)} {selectedAsset}</div>
                        </div>
                        <div className="flex items-center gap-2 bg-white px-3 py-2 rounded-2xl shadow-sm border border-gray-100">
                          <div className={`w-6 h-6 rounded-full flex items-center justify-center text-white font-black text-[10px] ${assets.find(a => a.name === selectedAsset)?.color}`}>
                            {assets.find(a => a.name === selectedAsset)?.icon}
                          </div>
                          <span className="text-xs font-black">{selectedAsset}</span>
                        </div>
                      </div>
                    </div>

                    <div className="flex justify-center -my-6 relative z-10">
                      <button 
                        onClick={flipSwap}
                        className="bg-white p-3 rounded-full shadow-lg border border-gray-100 text-opay-green active:scale-90 transition-all"
                      >
                        <RefreshCcw size={20} />
                      </button>
                    </div>

                    <div className="relative">
                      <div className="text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest">To (Estimated)</div>
                      <div className="bg-gray-50 p-5 rounded-3xl border border-gray-100 flex items-center justify-between">
                        <div className="space-y-1">
                          <div className="font-black text-2xl text-gray-900">
                            {amount ? (parseFloat(amount) * swapRate * 0.995).toFixed(8) : '0.00'}
                          </div>
                          <div className="text-[9px] font-bold text-gray-400">Balance: {(portfolio[targetSwapAsset] || 0).toFixed(6)} {targetSwapAsset}</div>
                        </div>
                        <select 
                          value={targetSwapAsset}
                          onChange={(e) => setTargetSwapAsset(e.target.value)}
                          className="bg-white px-3 py-2 rounded-2xl shadow-sm border border-gray-100 text-xs font-black outline-none"
                        >
                          {assets.filter(a => a.name !== selectedAsset).map(a => (
                            <option key={a.name} value={a.name}>{a.name}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>

                  <div className="bg-green-50 p-4 rounded-2xl border border-green-100 flex flex-col gap-2">
                     <div className="flex justify-between text-[10px] font-black">
                        <span className="text-gray-400 uppercase tracking-widest">Exchange Rate</span>
                        <span className="text-opay-green">1 {selectedAsset} = {swapRate.toFixed(4)} {targetSwapAsset}</span>
                     </div>
                     <div className="flex justify-between text-[10px] font-black">
                        <span className="text-gray-400 uppercase tracking-widest">Slippage & Fees</span>
                        <span className="text-gray-900">0.5% Guaranteed</span>
                     </div>
                  </div>

                  <button
                    onClick={handleTrade}
                    disabled={isLoading || !amount}
                    className="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-2"
                  >
                    {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : 'SWAP ASSETS NOW'}
                  </button>
                </div>
              ) : (
                <div className="space-y-6 animate-fade-in">
                  <div className="space-y-4">
                    <div className="flex justify-between items-center px-1">
                      <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Market Price</span>
                      <span className="text-sm font-black text-gray-900">${prices[selectedAsset as keyof typeof prices].toLocaleString()}</span>
                    </div>

                    <div className="relative">
                      <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest">Amount to {tradeType} (₦)</label>
                      <div className="relative">
                        <input 
                          type="number"
                          placeholder="0.00"
                          className="w-full p-5 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-black text-2xl"
                          value={amount}
                          onChange={(e) => setAmount(e.target.value)}
                        />
                        <div className="absolute right-5 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-300">NGN</div>
                      </div>
                    </div>

                    {amount && (
                      <div className="bg-gray-50 p-5 rounded-3xl border border-gray-100 space-y-3">
                        <div className="flex justify-between items-center text-[10px] font-black">
                           <span className="text-gray-400 uppercase">Conversion Rate</span>
                           <span className="text-gray-700">1 USD = ₦1,620</span>
                        </div>
                        <div className="flex justify-between items-center text-[10px] font-black">
                           <span className="text-gray-400 uppercase">You {tradeType === 'buy' ? 'Receive' : 'Sell'}</span>
                           <span className="text-opay-green text-sm">
                             {((parseFloat(amount) / 1620) / prices[selectedAsset as keyof typeof prices]).toFixed(8)} {selectedAsset}
                           </span>
                        </div>
                      </div>
                    )}
                  </div>

                  <button
                    onClick={handleTrade}
                    disabled={isLoading || !amount}
                    className={`w-full font-black py-5 rounded-[24px] shadow-xl transition-all active:scale-[0.98] flex items-center justify-center gap-2 disabled:opacity-50 ${tradeType === 'buy' ? 'bg-opay-green text-white' : 'bg-red-50 text-white'}`}
                  >
                    {isLoading ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" /> : <>CONFIRM {tradeType.toUpperCase()}</>}
                  </button>
                </div>
              )}
            </div>
          )}

          {/* Deposit Tab */}
          {activeTab === 'receive' && (
            <div className="space-y-8 animate-fade-in">
              <div className="space-y-4">
                 <div className="flex justify-between items-center px-1">
                    <h3 className="text-[11px] font-black text-gray-400 uppercase tracking-widest">Deposit {selectedAsset}</h3>
                 </div>
                 <div className="grid grid-cols-2 gap-3">
                   {currentAsset.networks.map(net => (
                     <button
                       key={net}
                       onClick={() => setSelectedNetwork(net)}
                       className={`p-4 rounded-2xl border-2 text-[10px] font-black uppercase text-center transition-all ${selectedNetwork === net ? 'border-opay-green bg-green-50 text-opay-green' : 'border-gray-50 bg-gray-50 text-gray-400'}`}
                     >
                       {net}
                     </button>
                   ))}
                 </div>
              </div>

              {!currentAddress ? (
                <div className="py-12 flex flex-col items-center gap-6 border-2 border-dashed border-gray-100 rounded-[40px] bg-gray-50/50">
                   <QrCode size={32} className="text-gray-200" />
                   <button 
                    onClick={() => generateNewAddress(selectedAsset, selectedNetwork)}
                    disabled={isLoading}
                    className="bg-opay-green text-white px-8 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest"
                   >
                     {isLoading ? 'Generating...' : 'Generate New Address'}
                   </button>
                </div>
              ) : (
                <div className="space-y-8 animate-slide-up">
                  <div className="flex flex-col items-center">
                    <div className="p-6 bg-white rounded-[40px] shadow-xl border border-gray-100 mb-6">
                      <QrCode size={180} className="text-gray-900" />
                    </div>
                    <div className="w-full bg-gray-50 p-5 rounded-2xl border border-gray-100 flex items-center gap-4">
                      <div className="flex-1 text-[11px] font-black text-gray-600 break-all font-mono leading-relaxed">
                        {currentAddress}
                      </div>
                      <button onClick={() => copyToClipboard(currentAddress)} className="p-3 bg-white text-opay-green rounded-xl shadow-sm"><Copy size={18} /></button>
                    </div>
                  </div>

                  <div className="w-full bg-gray-900 p-8 rounded-[40px] space-y-6">
                     <h4 className="text-[10px] font-black text-white/50 uppercase tracking-widest">Simulate Deposit (USD)</h4>
                     <input 
                        type="number"
                        placeholder="0.00"
                        className="w-full p-4 bg-white/10 text-white rounded-2xl font-black text-xl"
                        value={depositAmount}
                        onChange={(e) => setDepositAmount(e.target.value)}
                     />
                     <button onClick={handleSimulateDeposit} className="w-full bg-opay-green text-white font-black py-5 rounded-[24px]">SIMULATE DEPOSIT</button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Send Tab */}
          {activeTab === 'send' && (
            <div className="space-y-6 animate-fade-in">
              <h3 className="text-[11px] font-black text-gray-400 uppercase tracking-widest px-1">Withdraw {selectedAsset}</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest">Recipient Address</label>
                  <input 
                    type="text"
                    placeholder={`Paste ${selectedAsset} Address`}
                    className="w-full p-4 bg-gray-50 text-gray-900 border-2 border-transparent focus:border-opay-green outline-none rounded-2xl font-bold text-xs"
                    value={address}
                    onChange={(e) => setAddress(e.target.value)}
                  />
                </div>
                <div>
                  <label className="block text-[9px] font-black text-gray-400 mb-2 uppercase tracking-widest">Amount (USD)</label>
                  <div className="relative">
                    <input 
                      type="number"
                      placeholder="0.00"
                      className="w-full p-5 bg-gray-50 text-gray-900 rounded-2xl font-black text-2xl"
                      value={amount}
                      onChange={(e) => setAmount(e.target.value)}
                    />
                    <div className="absolute right-5 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-300">USD</div>
                  </div>
                </div>
                <button onClick={handleWithdraw} disabled={isLoading} className="w-full bg-opay-green text-white font-black py-5 rounded-[24px] shadow-xl">
                  {isLoading ? 'Processing...' : 'BROADCAST TRANSACTION'}
                </button>
              </div>
            </div>
          )}

          {/* Alerts Tab */}
          {activeTab === 'alerts' && (
            <div className="space-y-6 animate-fade-in">
               <div className="flex justify-between items-center px-1">
                  <h3 className="text-[11px] font-black text-gray-400 uppercase tracking-widest">Price Alerts</h3>
                  <button onClick={() => setIsAlertModalOpen(true)} className="p-2 bg-opay-green text-white rounded-xl shadow-md"><Plus size={16} /></button>
               </div>
               <div className="space-y-3">
                 {priceAlerts.length === 0 ? (
                   <div className="py-24 text-center text-gray-300 font-black uppercase text-[10px] tracking-widest">No alerts set</div>
                 ) : (
                   priceAlerts.map(alert => (
                     <div key={alert.id} className="bg-gray-50 p-4 rounded-[24px] border border-gray-100 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                           <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-white text-[10px] font-black ${assets.find(a => a.name === alert.asset)?.color}`}>
                              {assets.find(a => a.name === alert.asset)?.icon}
                           </div>
                           <div>
                              <div className="text-[10px] font-black text-gray-800">{alert.asset} {alert.condition} ${alert.targetPrice.toLocaleString()}</div>
                              <div className={`text-[8px] font-bold ${alert.triggered ? 'text-green-500' : 'text-amber-500'}`}>{alert.triggered ? 'TRIGGERED' : 'ACTIVE'}</div>
                           </div>
                        </div>
                        <button onClick={() => setPriceAlerts(prev => prev.filter(a => a.id !== alert.id))} className="text-red-400 p-2"><Trash2 size={14} /></button>
                     </div>
                   ))
                 )}
               </div>
            </div>
          )}

          {/* Activity Tab */}
          {activeTab === 'history' && (
            <div className="space-y-6 animate-fade-in">
              <h3 className="text-[11px] font-black text-gray-400 uppercase tracking-widest px-1">Transaction History</h3>
              <div className="space-y-3 max-h-[460px] overflow-y-auto pr-1 scrollbar-hide">
                {cryptoTransactions.length === 0 ? (
                  <div className="py-24 text-center text-gray-300 font-black uppercase text-[10px] tracking-widest">No activity found</div>
                ) : (
                  cryptoTransactions.map((tx) => (
                    <div key={tx.id} className="bg-gray-50 p-5 rounded-3xl border border-gray-100 flex items-center justify-between group">
                      <div className="flex items-center gap-4">
                        <div className={`w-11 h-11 rounded-2xl flex items-center justify-center shadow-sm ${tx.type.includes('Deposit') || tx.type.includes('Buy') ? 'bg-green-100 text-green-600' : 'bg-orange-50 text-orange-600'}`}>
                          {tx.type.includes('Swap') ? <Repeat size={20} /> : tx.type.includes('Deposit') || tx.type.includes('Buy') ? <ArrowDownLeft size={20} /> : <ArrowUpRight size={20} />}
                        </div>
                        <div className="min-w-0">
                          <div className="text-[11px] font-black text-gray-800 uppercase truncate">{tx.type.replace('Crypto ', '')}</div>
                          <div className="text-[9px] text-gray-400 font-bold mt-1">{new Date(tx.date).toLocaleDateString()}</div>
                        </div>
                      </div>
                      <div className="text-right flex flex-col items-end gap-1">
                        <div className={`text-sm font-black ${tx.type === 'Crypto Deposit' || tx.type === 'Crypto Sell' ? 'text-green-500' : 'text-gray-900'}`}>
                          {formatCurrency(tx.amount)}
                        </div>
                        <div className="text-[8px] font-black text-opay-green uppercase tracking-widest opacity-0 group-hover:opacity-100 transition-opacity">Details</div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Alert Modal */}
      {isAlertModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[60] flex items-center justify-center p-6">
          <div className="bg-white w-full max-w-xs rounded-[40px] p-8 space-y-6 animate-slide-up shadow-2xl">
            <h3 className="text-lg font-black text-gray-900">Set Price Alert</h3>
            <div className="space-y-4">
               <div>
                  <label className="text-[9px] font-black text-gray-400 uppercase mb-2 block">Target Price (USD)</label>
                  <input 
                    type="number" 
                    className="w-full p-4 bg-gray-50 rounded-2xl outline-none font-black text-lg"
                    placeholder="e.g. 70000"
                    value={newAlertPrice}
                    onChange={(e) => setNewAlertPrice(e.target.value)}
                  />
               </div>
               <div className="flex bg-gray-100 p-1 rounded-2xl">
                  <button onClick={() => setNewAlertCondition('above')} className={`flex-1 py-2 text-[10px] font-black rounded-xl transition-all ${newAlertCondition === 'above' ? 'bg-white shadow-sm text-opay-green' : 'text-gray-400'}`}>ABOVE</button>
                  <button onClick={() => setNewAlertCondition('below')} className={`flex-1 py-2 text-[10px] font-black rounded-xl transition-all ${newAlertCondition === 'below' ? 'bg-white shadow-sm text-opay-green' : 'text-gray-400'}`}>BELOW</button>
               </div>
            </div>
            <button onClick={handleAddAlert} className="w-full bg-opay-green text-white py-5 rounded-[24px] font-black uppercase text-xs">Create Alert</button>
            <button onClick={() => setIsAlertModalOpen(false)} className="w-full text-[10px] font-black uppercase text-gray-400">Cancel</button>
          </div>
        </div>
      )}
    </div>
  );
};

export default Crypto;

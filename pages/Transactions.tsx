
import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency } from '../utils';
import { 
  ArrowLeft, Search, Filter, Phone, Wifi, CreditCard, 
  ArrowRightLeft, TrendingUp, History, Calendar, 
  ChevronRight, Printer, Share2, X, Download, Zap, Tv, 
  MessageCircle, Bitcoin, ShieldCheck, TrendingDown,
  CheckCircle2, AlertCircle
} from 'lucide-react';
import { Transaction } from '../types';
// @ts-ignore
import html2canvas from 'html2canvas';

const Transactions: React.FC = () => {
  const { currentUser, transactions } = useApp();
  const navigate = useNavigate();

  const [searchTerm, setSearchTerm] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [typeFilter, setTypeFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [sortField, setSortField] = useState<'date' | 'amount' | 'status'>('date');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');
  const [selectedTx, setSelectedTx] = useState<Transaction | null>(null);
  const [isExporting, setIsExporting] = useState(false);

  const myTxs = useMemo(() => {
    let filtered = transactions.filter(tx => tx.userId === currentUser?.id);

    if (searchTerm) {
      filtered = filtered.filter(tx => 
        tx.recipient.toLowerCase().includes(searchTerm.toLowerCase()) ||
        tx.details.toLowerCase().includes(searchTerm.toLowerCase()) ||
        tx.id.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }

    if (typeFilter !== 'all') {
      filtered = filtered.filter(tx => tx.type.toLowerCase() === typeFilter.toLowerCase());
    }

    if (statusFilter !== 'all') {
      filtered = filtered.filter(tx => tx.status === statusFilter);
    }

    if (startDate) {
      filtered = filtered.filter(tx => new Date(tx.date) >= new Date(startDate));
    }

    if (endDate) {
      const end = new Date(endDate);
      end.setHours(23, 59, 59);
      filtered = filtered.filter(tx => new Date(tx.date) <= end);
    }

    return filtered.sort((a, b) => {
      let comparison = 0;
      if (sortField === 'date') {
        comparison = new Date(a.date).getTime() - new Date(b.date).getTime();
      } else if (sortField === 'amount') {
        comparison = a.amount - b.amount;
      } else if (sortField === 'status') {
        comparison = a.status.localeCompare(b.status);
      }
      return sortOrder === 'desc' ? -comparison : comparison;
    });
  }, [transactions, currentUser, searchTerm, typeFilter, statusFilter, startDate, endDate, sortField, sortOrder]);

  const getIcon = (type: string) => {
    const t = type.toLowerCase();
    if (t.includes('airtime')) return <Phone size={18} className="text-blue-500" />;
    if (t.includes('data')) return <Wifi size={18} className="text-orange-500" />;
    if (t.includes('electricity')) return <Zap size={18} className="text-yellow-500" />;
    if (t.includes('cable')) return <Tv size={18} className="text-red-500" />;
    if (t.includes('sms')) return <MessageCircle size={18} className="text-emerald-500" />;
    if (t.includes('crypto')) return <Bitcoin size={18} className="text-orange-600" />;
    if (t.includes('betting')) return <TrendingUp size={18} className="text-green-500" />;
    if (t.includes('transfer')) return <ArrowRightLeft size={18} className="text-indigo-500" />;
    if (t.includes('card')) return <CreditCard size={18} className="text-pink-500" />;
    return <CreditCard size={18} className="text-gray-400" />;
  };

  const types = Array.from(new Set(transactions.filter(t => t.userId === currentUser?.id).map(t => t.type)));

  const handlePrint = () => {
    window.print();
  };

  const handleDownloadImage = async () => {
    const element = document.getElementById('printable-receipt');
    if (!element) return;
    
    setIsExporting(true);
    try {
      const canvas = await html2canvas(element, {
        backgroundColor: '#ffffff',
        scale: 2, // Higher quality
        logging: false,
        useCORS: true
      });
      const image = canvas.toDataURL("image/png");
      const link = document.createElement('a');
      link.download = `OPay-Receipt-${selectedTx?.id || 'TX'}.png`;
      link.href = image;
      link.click();
    } catch (error) {
      console.error('Export failed', error);
      alert('Failed to generate image. Please try again.');
    } finally {
      setIsExporting(false);
    }
  };

  const handleShare = async () => {
    const element = document.getElementById('printable-receipt');
    if (!element) return;

    setIsExporting(true);
    try {
      const canvas = await html2canvas(element, {
        backgroundColor: '#ffffff',
        scale: 2,
        logging: false,
        useCORS: true
      });
      
      canvas.toBlob(async (blob: Blob | null) => {
        if (!blob) throw new Error('Canvas to Blob failed');
        
        const file = new File([blob], `OPay-Receipt-${selectedTx?.id}.png`, { type: 'image/png' });
        
        if (navigator.share && navigator.canShare({ files: [file] })) {
          await navigator.share({
            files: [file],
            title: 'OPay Transaction Receipt',
            text: `Receipt for ${selectedTx?.type} of ${formatCurrency(selectedTx?.amount || 0)}`
          });
        } else {
          // Fallback: Just trigger download if sharing isn't supported for files
          handleDownloadImage();
        }
      }, 'image/png');
    } catch (error) {
      console.error('Share failed', error);
      // Fallback: Copy ID
      if (selectedTx) {
        navigator.clipboard.writeText(selectedTx.id);
        alert('Sharing failed. Transaction ID copied to clipboard.');
      }
    } finally {
      setIsExporting(false);
    }
  };

  const TransactionDetailsModal = ({ tx }: { tx: Transaction }) => {
    const isElectricity = tx.type.toLowerCase().includes('electricity');
    const tokenMatch = tx.details.match(/Token:\s*([\d-]+)/);
    const token = tokenMatch ? tokenMatch[1] : null;

    return (
      <div className="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-4 sm:p-6 animate-fade-in print:bg-white print:p-0">
        <div className="bg-white w-full max-w-md rounded-[40px] overflow-hidden shadow-2xl relative animate-slide-up print:shadow-none print:rounded-none">
          {/* Header Actions */}
          <div className="p-6 flex justify-between items-center border-b border-gray-50 print:hidden">
            <button onClick={() => setSelectedTx(null)} className="p-2 hover:bg-gray-100 rounded-full transition-colors">
              <X size={20} className="text-gray-400" />
            </button>
            <div className="flex gap-2">
              <button 
                onClick={handlePrint} 
                className="p-2 hover:bg-gray-100 rounded-full transition-colors text-opay-green"
                title="Download PDF / Print"
              >
                <Printer size={20} />
              </button>
              <button 
                onClick={handleShare}
                disabled={isExporting}
                className={`p-2 hover:bg-gray-100 rounded-full transition-colors text-blue-500 ${isExporting ? 'animate-pulse' : ''}`}
                title="Share Receipt"
              >
                <Share2 size={20} />
              </button>
            </div>
          </div>

          {/* Receipt Content */}
          <div className="p-8 space-y-8 bg-white" id="printable-receipt">
            <div className="text-center space-y-4">
              <div className="flex justify-center mb-2">
                 <div className="w-16 h-16 bg-opay-green rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-xl">O</div>
              </div>
              <h2 className="text-xs font-black text-gray-400 uppercase tracking-[0.2em]">Transaction Receipt</h2>
              <div className="text-3xl font-black text-gray-900">{formatCurrency(tx.amount)}</div>
              <div className={`inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider ${
                tx.status === 'successful' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'
              }`}>
                {tx.status === 'successful' ? <CheckCircle2 size={12} /> : <AlertCircle size={12} />}
                {tx.status}
              </div>
            </div>

            <div className="space-y-4 pt-4">
              <div className="flex justify-between items-center py-1">
                <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Date</span>
                <span className="text-[11px] font-black text-gray-800">{new Date(tx.date).toLocaleString()}</span>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Transaction Type</span>
                <span className="text-[11px] font-black text-gray-800 uppercase tracking-tight">{tx.type}</span>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Recipient</span>
                <span className="text-[11px] font-black text-gray-800">{tx.recipient}</span>
              </div>
              {tx.provider && (
                <div className="flex justify-between items-center py-1">
                  <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Provider</span>
                  <span className="text-[11px] font-black text-gray-800 uppercase">{tx.provider}</span>
                </div>
              )}
              <div className="flex justify-between items-center py-1">
                <span className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Ref Number</span>
                <span className="text-[11px] font-black text-gray-800 font-mono uppercase">{tx.id}</span>
              </div>
            </div>

            {/* Token Section for Electricity */}
            {isElectricity && token && (
              <div className="bg-yellow-50 p-6 rounded-3xl border border-yellow-100 space-y-3 text-center animate-pulse-slow">
                <div className="text-[10px] font-black text-yellow-700 uppercase tracking-widest">Prepaid Token</div>
                <div className="text-xl font-black text-gray-900 tracking-[0.15em] font-mono">{token}</div>
                <div className="text-[9px] font-bold text-yellow-600/80 italic">Enter this code into your meter to recharge.</div>
              </div>
            )}

            <div className="pt-10 border-t border-dashed border-gray-100 flex flex-col items-center gap-4">
              <div className="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Thank you for using O-Pay</div>
              <div className="flex gap-4 print:hidden">
                 <div className="p-3 bg-gray-50 rounded-xl">
                   <ShieldCheck size={24} className="text-opay-green/40" />
                 </div>
                 <button 
                  onClick={handleDownloadImage}
                  className="p-3 bg-gray-50 rounded-xl hover:bg-opay-green/10 transition-colors"
                  title="Download Image"
                 >
                   <Download size={24} className="text-opay-green/40" />
                 </button>
              </div>
            </div>
          </div>

          <div className="p-6 bg-gray-50 border-t border-gray-100 flex gap-4 print:hidden">
            <button 
              onClick={() => setSelectedTx(null)}
              className="flex-1 bg-white text-gray-900 py-4 rounded-2xl font-black text-xs border border-gray-200 uppercase tracking-widest active:scale-95 transition-all"
            >
              Close
            </button>
            <button 
              onClick={handlePrint}
              className="flex-1 bg-opay-green text-white py-4 rounded-2xl font-black text-xs shadow-xl uppercase tracking-widest active:scale-95 transition-all"
            >
              Download PDF
            </button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-10">
      <div className="bg-white p-4 flex flex-col sticky top-0 z-40 border-b shadow-sm">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-4">
            <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
            <h1 className="text-lg font-black text-gray-900">History</h1>
          </div>
          <button 
            onClick={() => setShowFilters(!showFilters)}
            className={`p-2 rounded-xl transition-all ${showFilters ? 'bg-opay-green text-white shadow-lg' : 'bg-gray-50 text-gray-400'}`}
          >
            <Filter size={20} />
          </button>
        </div>

        <div className="relative">
          <input
            type="text"
            placeholder="Search transactions, IDs..."
            className="w-full pl-11 pr-4 py-3.5 bg-gray-50 text-gray-900 border-none outline-none rounded-2xl font-bold text-sm placeholder:text-gray-300"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300" size={18} />
        </div>

        {showFilters && (
          <div className="mt-4 pt-4 border-t border-gray-50 space-y-4 animate-slide-down">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Start Date</label>
                <div className="relative">
                   <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className="w-full p-3 bg-gray-50 rounded-xl text-[11px] font-bold outline-none border border-transparent focus:border-opay-green" />
                   <Calendar className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none" size={12} />
                </div>
              </div>
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">End Date</label>
                <div className="relative">
                  <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} className="w-full p-3 bg-gray-50 rounded-xl text-[11px] font-bold outline-none border border-transparent focus:border-opay-green" />
                  <Calendar className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none" size={12} />
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Type</label>
                <select value={typeFilter} onChange={e => setTypeFilter(e.target.value)} className="w-full p-3 bg-gray-50 rounded-xl text-[11px] font-bold outline-none">
                  <option value="all">All Services</option>
                  {types.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <div className="space-y-1.5">
                <label className="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Status</label>
                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} className="w-full p-3 bg-gray-50 rounded-xl text-[11px] font-bold outline-none">
                  <option value="all">All Status</option>
                  <option value="successful">Successful</option>
                  <option value="failed">Failed</option>
                  <option value="pending">Pending</option>
                </select>
              </div>
            </div>

            <div className="flex gap-3 pt-2">
               <button 
                onClick={() => { setStartDate(''); setEndDate(''); setTypeFilter('all'); setStatusFilter('all'); setSearchTerm(''); }}
                className="flex-1 py-3 text-[10px] font-black uppercase text-gray-400 tracking-widest hover:bg-gray-100 rounded-xl"
               >
                Clear All
               </button>
               <div className="flex-1 flex gap-2">
                 <select 
                    value={sortField} 
                    onChange={e => setSortField(e.target.value as any)} 
                    className="flex-1 p-2 bg-gray-100 rounded-xl text-[9px] font-black uppercase"
                  >
                   <option value="date">Date</option>
                   <option value="amount">Amount</option>
                   <option value="status">Status</option>
                 </select>
                 <button 
                  onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                  className="p-2 bg-gray-100 rounded-xl"
                 >
                   <ArrowUpDown size={14} className="text-gray-400" />
                 </button>
               </div>
            </div>
          </div>
        )}
      </div>

      <div className="flex-1 overflow-y-auto pt-4">
        {myTxs.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-40 text-gray-400 animate-fade-in">
            <div className="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-6">
               <History size={48} strokeWidth={1.5} className="opacity-20" />
            </div>
            <p className="font-black text-[10px] uppercase tracking-[0.2em]">No transactions found</p>
          </div>
        ) : (
          <div className="px-4 space-y-3">
            {myTxs.map(tx => (
              <div 
                key={tx.id} 
                onClick={() => setSelectedTx(tx)}
                className="bg-white p-5 rounded-[28px] flex items-center justify-between border border-gray-100/50 shadow-sm active:scale-[0.98] active:bg-gray-50 transition-all cursor-pointer group"
              >
                <div className="flex items-center gap-4">
                  <div className={`w-12 h-12 rounded-[20px] flex items-center justify-center transition-transform group-hover:scale-110 ${
                    tx.status === 'successful' ? 'bg-gray-50' : 'bg-red-50 text-red-600'
                  }`}>
                    {getIcon(tx.type)}
                  </div>
                  <div className="min-w-0">
                    <div className="text-xs font-black text-gray-800 uppercase tracking-tight truncate">{tx.type}</div>
                    <div className="text-[10px] text-gray-400 font-bold mt-1 flex items-center gap-1.5">
                      {tx.recipient} • {new Date(tx.date).toLocaleDateString([], { month: 'short', day: 'numeric' })}
                    </div>
                  </div>
                </div>
                <div className="text-right shrink-0">
                  <div className={`text-sm font-black ${tx.type === 'Crypto Deposit' || tx.type === 'Referral Bonus' ? 'text-green-500' : 'text-gray-900'}`}>
                    {tx.type === 'Crypto Deposit' || tx.type === 'Referral Bonus' ? '+' : '-'}{formatCurrency(tx.amount)}
                  </div>
                  <div className={`text-[8px] font-black uppercase tracking-widest mt-1 inline-flex items-center gap-1 ${
                    tx.status === 'successful' ? 'text-opay-green' : 'text-red-400'
                  }`}>
                    <div className={`w-1 h-1 rounded-full ${tx.status === 'successful' ? 'bg-opay-green' : 'bg-red-400'}`} />
                    {tx.status}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {selectedTx && <TransactionDetailsModal tx={selectedTx} />}
      
      {/* Bottom Nav Spacer */}
      <div className="h-20" />
    </div>
  );
};

const ArrowUpDown = ({ size, className }: { size?: number, className?: string }) => (
  <svg width={size || 24} height={size || 24} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className={className}>
    <path d="m21 16-4 4-4-4"/><path d="M17 20V4"/><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/>
  </svg>
);

export default Transactions;

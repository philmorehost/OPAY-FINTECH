
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId } from '../utils';
import { ArrowLeft, Plus, Lock, Unlock, Eye, EyeOff, CreditCard, ShieldCheck } from 'lucide-react';

const VirtualCards: React.FC = () => {
  const { currentUser, virtualCards, setVirtualCards, setUsers, setTransactions } = useApp();
  const navigate = useNavigate();
  
  const [showDetails, setShowDetails] = useState(false);
  const [isCreating, setIsCreating] = useState(false);

  if (!currentUser) return null;

  const myCards = virtualCards.filter(c => c.userId === currentUser.id);

  const createCard = () => {
    const cardFee = 1500;
    if (currentUser.walletBalance < cardFee) {
      alert("Insufficient balance to create a virtual card. Fee: ₦1,500");
      return;
    }

    setIsCreating(true);
    setTimeout(() => {
      const newCard: any = {
        id: generateId(),
        userId: currentUser.id,
        cardNumber: `5399 ${Math.floor(Math.random() * 8999 + 1000)} ${Math.floor(Math.random() * 8999 + 1000)} ${Math.floor(Math.random() * 8999 + 1000)}`,
        expiry: '12/27',
        cvv: Math.floor(Math.random() * 899 + 100).toString(),
        balance: 0,
        type: 'Mastercard',
        isFrozen: false
      };

      const newTx: any = {
        id: generateId(),
        userId: currentUser.id,
        type: 'Card Issuance',
        amount: cardFee,
        status: 'successful',
        date: new Date().toISOString(),
        details: `Virtual Mastercard Issuance Fee`,
        recipient: 'O-Pay Systems'
      };

      setVirtualCards(prev => [...prev, newCard]);
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - cardFee } : u));
      setTransactions(prev => [newTx, ...prev]);
      setIsCreating(false);
      alert("Virtual Card Created Successfully!");
    }, 2000);
  };

  const toggleFreeze = (cardId: string) => {
    setVirtualCards(prev => prev.map(c => c.id === cardId ? { ...c, isFrozen: !c.isFrozen } : c));
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col">
      <div className="bg-white p-4 flex items-center justify-between sticky top-0 z-10 border-b">
        <div className="flex items-center gap-4">
          <ArrowLeft className="text-gray-900" onClick={() => navigate('/dashboard')} />
          <h1 className="text-lg font-black text-gray-900">Virtual Cards</h1>
        </div>
        <Plus className="text-opay-green" onClick={createCard} />
      </div>

      <div className="p-6 space-y-8 flex-1">
        {myCards.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center space-y-6">
            <div className="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center">
              <CreditCard size={48} className="text-gray-300" />
            </div>
            <div>
              <h3 className="text-lg font-black text-gray-800">No Virtual Cards</h3>
              <p className="text-sm text-gray-400 mt-2 px-10">Create a virtual card to shop globally on Amazon, Netflix, and more.</p>
            </div>
            <button 
              onClick={createCard}
              disabled={isCreating}
              className="bg-opay-green text-white px-10 py-4 rounded-2xl font-black text-xs shadow-xl shadow-green-100"
            >
              {isCreating ? 'CREATING...' : 'GET A CARD NOW'}
            </button>
          </div>
        ) : (
          <div className="space-y-10">
            {myCards.map(card => (
              <div key={card.id} className="space-y-6">
                <div className={`relative h-56 rounded-[32px] p-8 text-white overflow-hidden shadow-2xl transition-all ${card.isFrozen ? 'grayscale opacity-80' : 'bg-gradient-to-br from-gray-900 via-gray-800 to-black'}`}>
                  <div className="flex justify-between items-start">
                    <div className="font-black tracking-widest text-sm opacity-60">VIRTUAL CARD</div>
                    <div className="w-12 h-8 bg-white/10 rounded-md backdrop-blur-md flex items-center justify-center font-bold text-[10px]">
                      {card.type}
                    </div>
                  </div>
                  
                  <div className="mt-10">
                    <div className="text-xl font-black tracking-[0.2em] mb-4">
                      {showDetails ? card.cardNumber : card.cardNumber.replace(/\d{4} \d{4} \d{4}/, '**** **** ****')}
                    </div>
                    <div className="flex gap-10">
                      <div>
                        <div className="text-[8px] font-black uppercase opacity-40 mb-1">Expiry</div>
                        <div className="text-xs font-black tracking-widest">{card.expiry}</div>
                      </div>
                      <div>
                        <div className="text-[8px] font-black uppercase opacity-40 mb-1">CVV</div>
                        <div className="text-xs font-black tracking-widest">{showDetails ? card.cvv : '***'}</div>
                      </div>
                    </div>
                  </div>

                  <div className="absolute right-8 bottom-8 flex items-center gap-2">
                    <ShieldCheck size={20} className="text-opay-green" />
                    <span className="text-[10px] font-black text-opay-green tracking-widest">SECURE</span>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <button 
                    onClick={() => setShowDetails(!showDetails)}
                    className="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center gap-3 font-black text-[10px] uppercase text-gray-700"
                  >
                    {showDetails ? <EyeOff size={16} /> : <Eye size={16} />} 
                    {showDetails ? 'Hide' : 'Show'} Details
                  </button>
                  <button 
                    onClick={() => toggleFreeze(card.id)}
                    className={`p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center gap-3 font-black text-[10px] uppercase ${card.isFrozen ? 'bg-green-50 text-green-600 border-green-100' : 'bg-red-50 text-red-600 border-red-100'}`}
                  >
                    {card.isFrozen ? <Unlock size={16} /> : <Lock size={16} />} 
                    {card.isFrozen ? 'Unfreeze' : 'Freeze'}
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}

        <div className="bg-blue-50 p-6 rounded-[32px] border border-blue-100">
          <h4 className="text-xs font-black text-blue-800 uppercase tracking-widest mb-2">Usage Policy</h4>
          <ul className="text-[10px] text-blue-600/80 space-y-2 font-bold leading-relaxed">
            <li>• Cards are strictly for online international payments.</li>
            <li>• Maximum daily spend: $1,000 equivalent.</li>
            <li>• Funding is tied directly to your Naira wallet.</li>
          </ul>
        </div>
      </div>
    </div>
  );
};

export default VirtualCards;

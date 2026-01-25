
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApp } from '../store';
import { formatCurrency, generateId, sendNotificationEmail } from '../utils';
import { ArrowLeft, Plus, Lock, Unlock, Eye, EyeOff, CreditCard, ShieldCheck } from 'lucide-react';

const VirtualCards: React.FC = () => {
  const { currentUser, virtualCards, setVirtualCards, setUsers, setTransactions, settings } = useApp();
  const navigate = useNavigate();
  
  const [showDetails, setShowDetails] = useState(false);
  const [isCreating, setIsCreating] = useState(false);

  if (!currentUser) return null;

  const myCards = virtualCards.filter(c => c.userId === currentUser.id);

  const createCard = async () => {
    const cardFee = 1500;
    if (currentUser.walletBalance < cardFee) {
      alert("Insufficient balance. Fee: ₦1,500");
      return;
    }

    setIsCreating(true);
    setTimeout(async () => {
      const ref = generateId();
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

      setVirtualCards(prev => [...prev, newCard]);
      setUsers(prev => prev.map(u => u.id === currentUser.id ? { ...u, walletBalance: u.walletBalance - cardFee } : u));
      
      const tx: any = { id: ref, userId: currentUser.id, type: 'Card Issuance', amount: cardFee, status: 'successful', date: new Date().toISOString(), details: `Virtual Card Fee`, recipient: 'Billpay Card' };
      setTransactions(prev => [tx, ...prev]);

      // LIVE EMAIL NOTIFICATION
      try {
        await sendNotificationEmail(settings, currentUser.email, 'Virtual Card Active', currentUser.fullName, {
          'Card Type': 'Mastercard',
          'Issuance Fee': cardFee,
          'Expiry': '12/27',
          'Reference': ref
        });
      } catch (err) { console.warn(err); }

      setIsCreating(false);
      alert("Virtual Card Created!");
    }, 2000);
  };

  return (
    <div className="max-w-md mx-auto min-h-screen bg-gray-50 flex flex-col pb-20">
      <div className="bg-white p-4 flex items-center justify-between border-b">
        <div className="flex items-center gap-4">
          <ArrowLeft className="text-gray-900 cursor-pointer" onClick={() => navigate('/dashboard')} />
          <h1 className="text-lg font-black text-gray-900">Virtual Cards</h1>
        </div>
      </div>
      <div className="p-6 text-center">
        {myCards.length === 0 ? (
          <button onClick={createCard} disabled={isCreating} className="bg-billpay-green text-white px-10 py-5 rounded-[24px] font-black">{isCreating ? 'Issuing...' : 'GET CARD'}</button>
        ) : (
          <div className="bg-gray-900 p-8 rounded-[32px] text-white">Active Virtual Mastercard</div>
        )}
      </div>
    </div>
  );
};

export default VirtualCards;

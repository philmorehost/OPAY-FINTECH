
import React, { createContext, useContext, useState, useEffect } from 'react';
import { User, Transaction, Settings, SupportTicket, VirtualCard, KYCSubmission, DepositRequest, SmsSenderId, Contact, GiftCardRequest } from './types';

interface AppContextType {
  currentUser: User | null;
  setCurrentUser: (user: User | null) => void;
  users: User[];
  setUsers: React.Dispatch<React.SetStateAction<User[]>>;
  transactions: Transaction[];
  setTransactions: React.Dispatch<React.SetStateAction<Transaction[]>>;
  depositRequests: DepositRequest[];
  setDepositRequests: React.Dispatch<React.SetStateAction<DepositRequest[]>>;
  smsSenderIds: SmsSenderId[];
  setSmsSenderIds: React.Dispatch<React.SetStateAction<SmsSenderId[]>>;
  giftCardRequests: GiftCardRequest[];
  setGiftCardRequests: React.Dispatch<React.SetStateAction<GiftCardRequest[]>>;
  phoneBook: Contact[];
  setPhoneBook: React.Dispatch<React.SetStateAction<Contact[]>>;
  settings: Settings;
  setSettings: React.Dispatch<React.SetStateAction<Settings>>;
  tickets: SupportTicket[];
  setTickets: React.Dispatch<React.SetStateAction<SupportTicket[]>>;
  virtualCards: VirtualCard[];
  setVirtualCards: React.Dispatch<React.SetStateAction<VirtualCard[]>>;
  kycSubmissions: KYCSubmission[];
  setKycSubmissions: React.Dispatch<React.SetStateAction<KYCSubmission[]>>;
}

const AppContext = createContext<AppContextType | undefined>(undefined);

export const AppProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  
  const [users, setUsers] = useState<User[]>(() => {
    const saved = localStorage.getItem('billpay_users_v8');
    return saved ? JSON.parse(saved) : [
      {
        id: '1',
        username: 'demo',
        fullName: 'Demo User',
        walletBalance: 15500.50,
        bonusCoins: 120,
        role: 'user',
        phone: '08123456789',
        email: 'demo@example.com',
        password: '123456',
        paymentPin: '0000',
        isSuspended: false,
        streakCount: 3,
        referralCount: 5,
        referralEarnings: 2500,
        kycStatus: 'none',
        tier: 1,
        loginAlertsEnabled: true,
        biometricEnabled: true,
        marketingEmailsEnabled: true,
        smsAlertsEnabled: false,
        authorizedDevices: [
          { id: 'dev-1', name: 'iPhone 15 Pro', location: 'Lagos, Nigeria', lastActive: new Date().toISOString(), isCurrent: true }
        ]
      },
      {
        id: 'admin-1',
        username: 'admin',
        fullName: 'Administrator',
        walletBalance: 0,
        bonusCoins: 0,
        role: 'admin',
        phone: '0000000000',
        email: 'admin@billpay.com',
        password: 'password123',
        isSuspended: false,
        streakCount: 0,
        referralCount: 0,
        referralEarnings: 0,
        kycStatus: 'verified',
        tier: 3,
        loginAlertsEnabled: true,
        biometricEnabled: false,
        authorizedDevices: [
          { id: 'adm-dev', name: 'Admin Console', location: 'Internal', lastActive: new Date().toISOString(), isCurrent: true }
        ]
      }
    ];
  });

  const [transactions, setTransactions] = useState<Transaction[]>(() => {
    const saved = localStorage.getItem('billpay_transactions_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [depositRequests, setDepositRequests] = useState<DepositRequest[]>(() => {
    const saved = localStorage.getItem('billpay_deposits_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [smsSenderIds, setSmsSenderIds] = useState<SmsSenderId[]>(() => {
    const saved = localStorage.getItem('billpay_sms_ids_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [giftCardRequests, setGiftCardRequests] = useState<GiftCardRequest[]>(() => {
    const saved = localStorage.getItem('billpay_giftcards_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [phoneBook, setPhoneBook] = useState<Contact[]>(() => {
    const saved = localStorage.getItem('billpay_phonebook_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [settings, setSettings] = useState<Settings>(() => {
    const saved = localStorage.getItem('billpay_settings_v8');
    return saved ? JSON.parse(saved) : {
      bonusPerDay: 20,
      referralBonus: 100,
      welcomeBonus: 50,
      streakBonus: 150,
      maxCoinThreshold: 200,
      conversionRate: 20,
      bankAccount: '1234567890',
      bankName: 'Digital Bank PLC',
      accountName: 'BILLPAY CLONE SERVICES',
      manualDepositCharge: 50,
      paystackChargePercent: 1.5,
      paystackPublicKey: '',
      paystackSecretKey: '',
      maxDailyTxPerId: 5,
      minDepositAmount: 100,
      minAirtimePurchase: 50,
      isMaintenanceMode: false,
      adminTheme: 'light',
      smtpHost: '',
      smtpPort: '587',
      smtpUser: '',
      smtpPass: '',
      senderName: 'Billpay Support',
      fromEmail: '',
      smsRate: 4.5,
      apiKeys: {},
      offers: [
        {
          id: 'offer-1',
          title: 'Welcome Bonus Active',
          description: 'Get 50 coins on your first recharge today!',
          label: 'PROMO',
          gradientFrom: '#00c689',
          gradientTo: '#00a672',
          textColor: '#ffffff'
        }
      ],
      nellobyteUserId: '',
      nellobyteApiKey: '',
      dataGiftingApiKey: '',
      examApiKey: '',
      vtPassApiKey: '',
      vtPassPublicKey: '',
      vtPassEmail: '',
      vtPassPassword: '',
      kudiSmsToken: '',
      stripeSecretKey: '',
      tremendousApiKey: '',
      juicywayApiKey: '',
      examProviders: [],
      dataNetworks: [
        { id: 'mtn', name: 'MTN', apiCode: '01' },
        { id: 'airtel', name: 'Airtel', apiCode: '04' },
        { id: 'glo', name: 'Glo', apiCode: '02' },
        { id: 'mobile9', name: '9mobile', apiCode: '03' }
      ],
      dataProducts: [], 
      cableProviders: [
        { id: 'dstv', name: 'DSTV', serviceId: 'dstv', enabled: true, discountPercent: 1.5, variations: [] },
        { id: 'gotv', name: 'GOtv', serviceId: 'gotv', enabled: true, discountPercent: 1.5, variations: [] },
        { id: 'startimes', name: 'Startimes', serviceId: 'startimes', enabled: true, discountPercent: 2.0, variations: [] },
        { id: 'showmax', name: 'Showmax', serviceId: 'showmax', enabled: true, discountPercent: 1.5, variations: [] }
      ],
      electricProviders: [
        { id: 'ekedc', name: 'Eko Electric', serviceId: 'eko-electric', enabled: true, discountPercent: 1.0 },
        { id: 'eedc', name: 'Enugu Electric', serviceId: 'enugu-electric', enabled: true, discountPercent: 1.0 },
        { id: 'ikedc', name: 'Ikeja Electric', serviceId: 'ikeja-electric', enabled: true, discountPercent: 1.0 },
        { id: 'jedc', name: 'Jos Electric', serviceId: 'jos-electric', enabled: true, discountPercent: 1.0 },
        { id: 'kedco', name: 'Kano Electric', serviceId: 'kano-electric', enabled: true, discountPercent: 1.0 },
        { id: 'ibedc', name: 'Ibadan Electric', serviceId: 'ibadan-electric', enabled: true, discountPercent: 1.0 },
        { id: 'phed', name: 'PH Electric', serviceId: 'portharcourt-electric', enabled: true, discountPercent: 1.0 },
        { id: 'aedc', name: 'Abuja Electric', serviceId: 'abuja-electric', enabled: true, discountPercent: 1.0 },
        { id: 'yedc', name: 'Yola Electric', serviceId: 'yola-electric', enabled: true, discountPercent: 1.0 },
        { id: 'bedc', name: 'Benin Electric', serviceId: 'benin-electric', enabled: true, discountPercent: 1.0 },
        { id: 'aba', name: 'Aba Electric', serviceId: 'aba-electric', enabled: true, discountPercent: 1.0 },
        { id: 'kaedco', name: 'Kaduna Electric', serviceId: 'kaduna-electric', enabled: true, discountPercent: 1.0 }
      ],
      bettingProviders: [
        { id: 'msport', name: 'MSport', enabled: true, discountPercent: 0 },
        { id: 'naijabet', name: 'NaijaBet', enabled: true, discountPercent: 0 },
        { id: 'nairabet', name: 'NairaBet', enabled: true, discountPercent: 0 },
        { id: 'bet9ja-agent', name: 'Bet9ja (Agent)', enabled: true, discountPercent: 0 },
        { id: 'betland', name: 'Betland', enabled: true, discountPercent: 0 },
        { id: 'betlion', name: 'Betlion', enabled: true, discountPercent: 0 },
        { id: 'supabet', name: 'Supabet', enabled: true, discountPercent: 0 },
        { id: 'bet9ja', name: 'Bet9ja', enabled: true, discountPercent: 0 },
        { id: 'bangbet', name: 'BangBet', enabled: true, discountPercent: 0 },
        { id: 'betking', name: 'BetKing', enabled: true, discountPercent: 0 },
        { id: '1xbet', name: '1xBet', enabled: true, discountPercent: 0 },
        { id: 'betway', name: 'Betway', enabled: true, discountPercent: 0 },
        { id: 'merrybet', name: 'MerryBet', enabled: true, discountPercent: 0 },
        { id: 'mlotto', name: 'MLotto', enabled: true, discountPercent: 0 },
        { id: 'western-lotto', name: 'Western Lotto', enabled: true, discountPercent: 0 },
        { id: 'hallabet', name: 'HallaBet', enabled: true, discountPercent: 0 },
        { id: 'green-lotto', name: 'Green Lotto', enabled: true, discountPercent: 0 }
      ],
      airtimeDiscounts: {
        mtn: 3,
        glo: 8,
        airtel: 3,
        nineMobile: 7
      }
    };
  });

  const [tickets, setTickets] = useState<SupportTicket[]>(() => {
    const saved = localStorage.getItem('billpay_tickets_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [virtualCards, setVirtualCards] = useState<VirtualCard[]>(() => {
    const saved = localStorage.getItem('billpay_vcards_v8');
    return saved ? JSON.parse(saved) : [];
  });

  const [kycSubmissions, setKycSubmissions] = useState<KYCSubmission[]>(() => {
    const saved = localStorage.getItem('billpay_kyc_v8');
    return saved ? JSON.parse(saved) : [];
  });

  useEffect(() => { localStorage.setItem('billpay_users_v8', JSON.stringify(users)); }, [users]);
  useEffect(() => { localStorage.setItem('billpay_transactions_v8', JSON.stringify(transactions)); }, [transactions]);
  useEffect(() => { localStorage.setItem('billpay_deposits_v8', JSON.stringify(depositRequests)); }, [depositRequests]);
  useEffect(() => { localStorage.setItem('billpay_sms_ids_v8', JSON.stringify(smsSenderIds)); }, [smsSenderIds]);
  useEffect(() => { localStorage.setItem('billpay_giftcards_v8', JSON.stringify(giftCardRequests)); }, [giftCardRequests]);
  useEffect(() => { localStorage.setItem('billpay_phonebook_v8', JSON.stringify(phoneBook)); }, [phoneBook]);
  useEffect(() => { localStorage.setItem('billpay_settings_v8', JSON.stringify(settings)); }, [settings]);
  useEffect(() => { localStorage.setItem('billpay_tickets_v8', JSON.stringify(tickets)); }, [tickets]);
  useEffect(() => { localStorage.setItem('billpay_vcards_v8', JSON.stringify(virtualCards)); }, [virtualCards]);
  useEffect(() => { localStorage.setItem('billpay_kyc_v8', JSON.stringify(kycSubmissions)); }, [kycSubmissions]);

  return (
    <AppContext.Provider value={{ 
      currentUser, setCurrentUser, 
      users, setUsers, 
      transactions, setTransactions,
      depositRequests, setDepositRequests,
      smsSenderIds, setSmsSenderIds,
      giftCardRequests, setGiftCardRequests,
      phoneBook, setPhoneBook,
      settings, setSettings,
      tickets, setTickets,
      virtualCards, setVirtualCards,
      kycSubmissions, setKycSubmissions
    }}>
      {children}
    </AppContext.Provider>
  );
};

export const useApp = () => {
  const context = useContext(AppContext);
  if (!context) throw new Error("useApp must be used within AppProvider");
  return context;
};

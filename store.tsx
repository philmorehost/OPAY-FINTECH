
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
    const saved = localStorage.getItem('opay_users_v5');
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
        isSuspended: false,
        streakCount: 3,
        referralCount: 5,
        referralEarnings: 2500,
        kycStatus: 'none',
        tier: 1,
        loginAlertsEnabled: true,
        biometricEnabled: true,
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
        email: 'admin@opay.com',
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
    const saved = localStorage.getItem('opay_transactions_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [depositRequests, setDepositRequests] = useState<DepositRequest[]>(() => {
    const saved = localStorage.getItem('opay_deposits_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [smsSenderIds, setSmsSenderIds] = useState<SmsSenderId[]>(() => {
    const saved = localStorage.getItem('opay_sms_ids_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [giftCardRequests, setGiftCardRequests] = useState<GiftCardRequest[]>(() => {
    const saved = localStorage.getItem('opay_giftcards_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [phoneBook, setPhoneBook] = useState<Contact[]>(() => {
    const saved = localStorage.getItem('opay_phonebook_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [settings, setSettings] = useState<Settings>(() => {
    const saved = localStorage.getItem('opay_settings_v5');
    return saved ? JSON.parse(saved) : {
      bonusPerDay: 20,
      referralBonus: 100,
      welcomeBonus: 50,
      streakBonus: 150,
      maxCoinThreshold: 200,
      conversionRate: 20,
      bankAccount: '1234567890',
      bankName: 'O-Pay Digital Bank',
      accountName: 'OPAY CLONE TECH',
      manualDepositCharge: 50,
      paystackChargePercent: 1.5,
      paystackPublicKey: '',
      paystackSecretKey: '',
      maxDailyTxPerId: 3,
      minDepositAmount: 100,
      minAirtimePurchase: 50,
      isMaintenanceMode: false,
      adminTheme: 'light',
      smtpHost: '',
      smtpPort: '',
      smtpUser: '',
      smtpPass: '',
      senderName: 'OPay Support',
      fromEmail: '',
      smsRate: 4.5,
      apiKeys: {},
      offers: [
        {
          id: 'offer-1',
          title: 'Virtual Dollar Card Now Available',
          description: 'Spend globally with 0.1% fees',
          label: 'SECURE',
          gradientFrom: '#4f46e5',
          gradientTo: '#7c3aed',
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
      examProviders: [],
      dataNetworks: [
        { id: 'mtn', name: 'MTN', apiCode: 'mtn' },
        { id: 'airtel', name: 'Airtel', apiCode: 'airtel' },
        { id: 'glo', name: 'Glo', apiCode: 'glo' },
        { id: 'mobile9', name: '9mobile', apiCode: '9mobile' }
      ],
      dataProducts: [], 
      cableProviders: [
        { id: 'dstv', name: 'DSTV', serviceId: 'dstv', enabled: true, discountPercent: 1.5, variations: [] },
        { id: 'gotv', name: 'GOtv', serviceId: 'gotv', enabled: true, discountPercent: 1.5, variations: [] },
        { id: 'startimes', name: 'Startimes', serviceId: 'startimes', enabled: true, discountPercent: 2.0, variations: [] },
        { id: 'showmax', name: 'Showmax', serviceId: 'showmax', enabled: true, discountPercent: 1.0, variations: [] }
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
    const saved = localStorage.getItem('opay_tickets_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [virtualCards, setVirtualCards] = useState<VirtualCard[]>(() => {
    const saved = localStorage.getItem('opay_vcards_v5');
    return saved ? JSON.parse(saved) : [];
  });

  const [kycSubmissions, setKycSubmissions] = useState<KYCSubmission[]>(() => {
    const saved = localStorage.getItem('opay_kyc_v5');
    return saved ? JSON.parse(saved) : [];
  });

  useEffect(() => { localStorage.setItem('opay_users_v5', JSON.stringify(users)); }, [users]);
  useEffect(() => { localStorage.setItem('opay_transactions_v5', JSON.stringify(transactions)); }, [transactions]);
  useEffect(() => { localStorage.setItem('opay_deposits_v5', JSON.stringify(depositRequests)); }, [depositRequests]);
  useEffect(() => { localStorage.setItem('opay_sms_ids_v5', JSON.stringify(smsSenderIds)); }, [smsSenderIds]);
  useEffect(() => { localStorage.setItem('opay_giftcards_v5', JSON.stringify(giftCardRequests)); }, [giftCardRequests]);
  useEffect(() => { localStorage.setItem('opay_phonebook_v5', JSON.stringify(phoneBook)); }, [phoneBook]);
  useEffect(() => { localStorage.setItem('opay_settings_v5', JSON.stringify(settings)); }, [settings]);
  useEffect(() => { localStorage.setItem('opay_tickets_v5', JSON.stringify(tickets)); }, [tickets]);
  useEffect(() => { localStorage.setItem('opay_vcards_v5', JSON.stringify(virtualCards)); }, [virtualCards]);
  useEffect(() => { localStorage.setItem('opay_kyc_v5', JSON.stringify(kycSubmissions)); }, [kycSubmissions]);

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

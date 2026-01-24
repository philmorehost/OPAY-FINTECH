
export type UserRole = 'user' | 'admin';
export type KYCStatus = 'none' | 'pending' | 'verified' | 'rejected';
export type UserTier = 1 | 2 | 3;

export interface Device {
  id: string;
  name: string;
  location: string;
  lastActive: string;
  isCurrent: boolean;
}

export interface User {
  id: string;
  username: string;
  fullName: string;
  walletBalance: number;
  bonusCoins: number;
  role: UserRole;
  phone: string;
  password?: string;
  isSuspended: boolean;
  streakCount: number;
  lastPurchaseDate?: string;
  referralCount: number;
  referralEarnings: number;
  kycStatus: KYCStatus;
  tier: UserTier;
  email: string;
  loginAlertsEnabled: boolean;
  biometricEnabled: boolean;
  authorizedDevices: Device[];
}

export interface Transaction {
  id: string;
  userId: string;
  type: string;
  amount: number;
  status: 'pending' | 'successful' | 'failed';
  date: string;
  details: string;
  recipient: string;
  provider?: string;
  refunded?: boolean;
}

export interface ExamProvider {
  id: string; 
  name: string;
  unitAmount: number;
  userPrice: number;
  enabled: boolean;
  availability: string;
  routingProvider: 'naija' | 'vtpass';
  serviceId?: string;
  variationCode?: string;
}

export interface DataProduct {
  id: string;
  networkId: string;
  type: string; 
  size: string;
  apiQuantityCode: string;
  userPrice: number;
  enabled: boolean;
}

export interface DataNetwork {
  id: string;
  name: string;
  apiCode: string;
}

export interface CableVariation {
  variation_code: string;
  name: string;
  variation_amount: string;
  fixedPrice: string;
}

export interface CableProvider {
  id: string;
  name: string;
  serviceId: string;
  enabled: boolean;
  discountPercent: number;
  variations: CableVariation[];
}

export interface GiftCardRequest {
  id: string;
  userId: string;
  cardBrand: string;
  amount: number;
  nairaAmount: number;
  type: 'buy' | 'sell';
  code?: string;
  status: 'pending' | 'successful' | 'rejected';
  date: string;
  rate: number;
}

export interface DepositRequest {
  id: string;
  userId: string;
  amount: number;
  method: 'manual' | 'paystack';
  status: 'pending' | 'successful' | 'rejected';
  date: string;
  senderName?: string;
  reference?: string;
  charge: number;
}

export interface SmsSenderId {
  id: string;
  userId: string;
  name: string;
  sampleMessage: string;
  status: 'pending' | 'approved' | 'rejected';
  createdAt: string;
}

export interface Contact {
  id: string;
  userId: string;
  name: string;
  phone: string;
  createdAt: string;
}

export interface Offer {
  id: string;
  title: string;
  description: string;
  label: string;
  gradientFrom: string;
  gradientTo: string;
  textColor: string;
}

export interface Settings {
  bonusPerDay: number;
  referralBonus: number;
  welcomeBonus: number;
  streakBonus: number;
  maxCoinThreshold: number;
  conversionRate: number;
  bankAccount: string;
  bankName: string;
  accountName: string;
  manualDepositCharge: number;
  paystackChargePercent: number;
  paystackPublicKey: string;
  paystackSecretKey: string;
  maxDailyTxPerId: number;
  minDepositAmount: number;
  minAirtimePurchase: number;
  isMaintenanceMode: boolean;
  adminTheme: 'light' | 'dark';
  smtpHost: string;
  smtpPort: string;
  smtpUser: string;
  smtpPass: string;
  senderName: string;
  fromEmail: string;
  smsRate: number;
  apiKeys: Record<string, string>;
  offers: Offer[];
  nellobyteUserId: string;
  nellobyteApiKey: string;
  dataGiftingApiKey: string;
  examApiKey: string;
  vtPassApiKey: string;
  vtPassPublicKey: string;
  vtPassEmail?: string;
  vtPassPassword?: string;
  kudiSmsToken: string;
  stripeSecretKey: string;
  tremendousApiKey: string;
  juicywayApiKey: string;
  examProviders: ExamProvider[];
  dataNetworks: DataNetwork[];
  dataProducts: DataProduct[];
  cableProviders: CableProvider[];
  airtimeDiscounts: {
    mtn: number;
    glo: number;
    airtel: number;
    nineMobile: number;
  };
}

export interface SupportTicket {
  id: string;
  userId: string;
  subject: string;
  message: string;
  status: 'open' | 'closed';
  createdAt: string;
  replies: { author: string; message: string; date: string }[];
}

export interface VirtualCard {
  id: string;
  userId: string;
  cardNumber: string;
  expiry: string;
  cvv: string;
  balance: number;
  type: 'Visa' | 'Mastercard';
  isFrozen: boolean;
}

export interface KYCSubmission {
  id: string;
  userId: string;
  fullName: string;
  dob: string;
  address: string;
  idType: string;
  idNumber: string;
  idImageUrl: string;
  addressImageUrl: string;
  status: KYCStatus;
  date: string;
  rejectionReason?: string;
}

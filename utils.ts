
export const detectNetwork = (phone: string): string => {
  if (!phone || phone.length < 4) return '';
  
  // Clean the number (remove +234 or non-digits)
  let cleanPhone = phone.replace(/\D/g, '');
  if (cleanPhone.startsWith('234')) {
    cleanPhone = '0' + cleanPhone.substring(3);
  }

  const prefix4 = cleanPhone.substring(0, 4);
  const prefix5 = cleanPhone.substring(0, 5);

  const mtn = [
    '0703', '0706', '0803', '0806', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916', 
    '07025', '07026', '0704'
  ];
  const airtel = [
    '0701', '0708', '0802', '0808', '0812', '0901', '0902', '0904', '0907', '0912', '0911'
  ];
  const glo = [
    '0705', '0805', '0807', '0811', '0815', '0905', '0915'
  ];
  const nineMobile = [
    '0809', '0817', '0818', '0908', '0909'
  ];

  // Check 5-digit prefixes first (more specific)
  if (mtn.includes(prefix5)) return 'MTN';
  
  // Check 4-digit prefixes
  if (mtn.includes(prefix4)) return 'MTN';
  if (airtel.includes(prefix4)) return 'Airtel';
  if (glo.includes(prefix4)) return 'Glo';
  if (nineMobile.includes(prefix4)) return '9mobile';

  return '';
};

export const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
  }).format(amount);
};

export const generateId = () => Math.random().toString(36).substr(2, 9).toUpperCase();

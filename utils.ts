
import { Settings } from './types';

export const detectNetwork = (phone: string): string => {
  if (!phone || phone.length < 4) return '';
  
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

  if (mtn.includes(prefix5)) return 'MTN';
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

/**
 * EMAIL SERVICE & TEMPLATING (LIVE SMTP)
 */

export const getEmailTemplate = (title: string, greeting: string, contentHtml: string, actionText?: string) => {
  return `
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8">
        <style>
          body { font-family: 'Inter', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; }
          .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
          .header { background-color: #00c689; padding: 40px 20px; text-align: center; color: white; }
          .logo { background: white; color: #00c689; width: 50px; height: 50px; line-height: 50px; border-radius: 12px; display: inline-block; font-size: 24px; font-weight: 900; margin-bottom: 10px; }
          .body { padding: 40px; color: #333333; line-height: 1.6; }
          .footer { padding: 20px; text-align: center; font-size: 12px; color: #999; background: #fafafa; }
          .btn { background-color: #00c689; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; margin-top: 20px; }
          .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
          .table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
          .table td:first-child { color: #888; font-weight: 500; }
          .table td:last-child { text-align: right; font-weight: 700; color: #111; }
        </style>
      </head>
      <body>
        <div class="container">
          <div class="header">
            <div class="logo">B</div>
            <h1 style="margin:0; font-size: 20px; letter-spacing: 1px; color: #ffffff;">${title}</h1>
          </div>
          <div class="body">
            <p style="font-size: 16px; font-weight: 600; margin-top: 0;">Hi ${greeting},</p>
            <div style="font-size: 14px; color: #555;">${contentHtml}</div>
            ${actionText ? `<a href="#" class="btn">${actionText}</a>` : ''}
          </div>
          <div class="footer">
            &copy; ${new Date().getFullYear()} Billpay Services. Licensed by CBN.<br>
            If you did not authorize this, please contact support immediately.
          </div>
        </div>
      </body>
    </html>
  `;
};

const internalSend = async (settings: Settings, to: string, subject: string, body: string) => {
  if (!settings.smtpHost || !settings.smtpUser || !settings.smtpPass) {
    console.warn("SMTP settings missing. Email logged to console instead.");
    console.log(`To: ${to}\nSubject: ${subject}\nBody: ${body}`);
    return;
  }

  // @ts-ignore
  if (typeof Email === 'undefined') {
    throw new Error("Email library not loaded correctly.");
  }

  try {
    // @ts-ignore
    const message = await Email.send({
      Host: settings.smtpHost,
      Username: settings.smtpUser,
      Password: settings.smtpPass,
      To: to,
      From: settings.fromEmail || settings.smtpUser,
      Subject: subject,
      Body: body,
    });
    
    if (message !== 'OK') {
      throw new Error(message);
    }
    return true;
  } catch (err) {
    console.error("Failed to send live email:", err);
    throw err;
  }
};

export const sendNotificationEmail = async (settings: Settings, userEmail: string, title: string, fullName: string, data: Record<string, string | number>) => {
  let tableRows = '';
  Object.entries(data).forEach(([key, val]) => {
    tableRows += `<tr><td>${key}</td><td>${typeof val === 'number' && key.toLowerCase().includes('amount') ? formatCurrency(val) : val}</td></tr>`;
  });

  const html = getEmailTemplate(
    title,
    fullName.split(' ')[0],
    `
    <p>Your transaction was successful. Here are the details:</p>
    <table class="table">${tableRows}</table>
    <p>The amount has been debited from your wallet.</p>
    `,
    'VIEW RECEIPT'
  );

  return internalSend(settings, userEmail, title, html);
};

export const sendBroadcastEmail = async (settings: Settings, emails: string[], subject: string, bodyHtml: string) => {
  // To avoid being flagged as spam, we send them sequentially or in batches.
  // Sequential for maximum reliability in this context.
  for (const email of emails) {
    const html = getEmailTemplate(
      'Platform Update',
      'Valued User',
      bodyHtml,
      'EXPLORE BILLPAY'
    );
    await internalSend(settings, email, subject, html);
  }
  return true;
};

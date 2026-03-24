require('dotenv').config();

const required = [
  'API_KEY',
  'WEBEX_CLIENT_ID',
  'WEBEX_CLIENT_SECRET',
  'WEBEX_REFRESH_TOKEN',
  'WEBEX_GUEST_ISSUER_ID',
  'WEBEX_GUEST_SHARED_SECRET',
];

const missing = required.filter((key) => !process.env[key]);
if (missing.length > 0) {
  console.error(`Missing required environment variables: ${missing.join(', ')}`);
  process.exit(1);
}

module.exports = {
  port: parseInt(process.env.PORT, 10) || 3000,
  apiKey: process.env.API_KEY,
  webex: {
    clientId: process.env.WEBEX_CLIENT_ID,
    clientSecret: process.env.WEBEX_CLIENT_SECRET,
    refreshToken: process.env.WEBEX_REFRESH_TOKEN,
    guestIssuerId: process.env.WEBEX_GUEST_ISSUER_ID,
    guestSharedSecret: process.env.WEBEX_GUEST_SHARED_SECRET,
  },
};

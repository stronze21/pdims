const axios = require('axios');
const fs = require('fs');
const path = require('path');
const config = require('../config');
const logger = require('../utils/logger');

const TOKEN_FILE = path.join(__dirname, '../../.refresh-token');
const REFRESH_BUFFER_MS = 5 * 60 * 1000; // Refresh 5 minutes before expiry

let accessToken = null;
let tokenExpiresAt = 0;
let currentRefreshToken = config.webex.refreshToken;

// Load persisted refresh token if available
function loadPersistedRefreshToken() {
  try {
    if (fs.existsSync(TOKEN_FILE)) {
      const stored = fs.readFileSync(TOKEN_FILE, 'utf8').trim();
      if (stored) {
        currentRefreshToken = stored;
        logger.info('Loaded persisted refresh token');
      }
    }
  } catch (err) {
    logger.warn('Could not load persisted refresh token, using env value', {
      error: err.message,
    });
  }
}

function persistRefreshToken(token) {
  try {
    fs.writeFileSync(TOKEN_FILE, token, { mode: 0o600 });
    logger.info('Persisted new refresh token');
  } catch (err) {
    logger.error('Failed to persist refresh token', { error: err.message });
  }
}

async function refreshAccessToken() {
  logger.info('Refreshing Webex access token...');

  const response = await axios.post(
    'https://webexapis.com/v1/access_token',
    new URLSearchParams({
      grant_type: 'refresh_token',
      client_id: config.webex.clientId,
      client_secret: config.webex.clientSecret,
      refresh_token: currentRefreshToken,
    }).toString(),
    {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      timeout: 15000,
    }
  );

  accessToken = response.data.access_token;
  tokenExpiresAt = Date.now() + response.data.expires_in * 1000;

  // Webex may rotate the refresh token
  if (response.data.refresh_token && response.data.refresh_token !== currentRefreshToken) {
    currentRefreshToken = response.data.refresh_token;
    persistRefreshToken(currentRefreshToken);
  }

  logger.info('Access token refreshed successfully', {
    expiresIn: response.data.expires_in,
  });

  return accessToken;
}

async function getAccessToken() {
  if (accessToken && Date.now() < tokenExpiresAt - REFRESH_BUFFER_MS) {
    return accessToken;
  }

  return refreshAccessToken();
}

// Initialize on load
loadPersistedRefreshToken();

module.exports = { getAccessToken };

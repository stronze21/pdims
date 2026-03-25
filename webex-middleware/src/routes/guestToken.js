const express = require('express');
const jwt = require('jsonwebtoken');
const axios = require('axios');
const config = require('../config');
const logger = require('../utils/logger');

const router = express.Router();

// POST /api/guest-token - Generate guest access token for patient
router.post('/', async (req, res) => {
  try {
    const { name, session_id } = req.body;

    if (!name) {
      return res.status(400).json({ error: 'name is required' });
    }

    // Build the Guest Issuer JWT
    const payload = {
      sub: name,
      iss: config.webex.guestIssuerId,
      exp: Math.floor(Date.now() / 1000) + 24 * 60 * 60, // 24 hours
    };

    // Decode the base64-encoded shared secret
    const secretBuffer = Buffer.from(config.webex.guestSharedSecret, 'base64');

    const guestJwt = jwt.sign(payload, secretBuffer, { algorithm: 'HS256' });

    // Exchange the JWT for a Webex guest access token
    const response = await axios.post(
      'https://webexapis.com/v1/jwt/login',
      null,
      {
        headers: { Authorization: `Bearer ${guestJwt}` },
        timeout: 15000,
      }
    );

    logger.info('Guest token generated', { name, sessionId: session_id });

    res.json({
      token: response.data.token,
      expiresIn: response.data.expiresIn,
    });
  } catch (err) {
    logger.error('Failed to generate guest token', {
      error: err.response?.data || err.message,
    });
    res.status(err.response?.status || 500).json({
      error: 'Failed to generate guest token',
      details: err.response?.data?.message || err.message,
    });
  }
});

module.exports = router;

const express = require('express');
const { getAccessToken } = require('../services/tokenManager');
const logger = require('../utils/logger');

const router = express.Router();

// POST /api/host-token - Get current host access token
router.post('/', async (req, res) => {
  try {
    const token = await getAccessToken();

    logger.info('Host token requested');

    res.json({ token });
  } catch (err) {
    logger.error('Failed to get host token', {
      error: err.response?.data || err.message,
    });
    res.status(err.response?.status || 500).json({
      error: 'Failed to get host token',
      details: err.response?.data?.message || err.message,
    });
  }
});

module.exports = router;

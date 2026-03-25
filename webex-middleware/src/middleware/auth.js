const config = require('../config');
const logger = require('../utils/logger');

function apiKeyAuth(req, res, next) {
  const apiKey = req.headers['x-api-key'];

  if (!apiKey || apiKey !== config.apiKey) {
    logger.warn('Unauthorized request', {
      ip: req.ip,
      path: req.path,
    });
    return res.status(401).json({ error: 'Unauthorized' });
  }

  next();
}

module.exports = apiKeyAuth;

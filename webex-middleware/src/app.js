const express = require('express');
const apiKeyAuth = require('./middleware/auth');
const meetingsRouter = require('./routes/meetings');
const guestTokenRouter = require('./routes/guestToken');
const hostTokenRouter = require('./routes/hostToken');
const logger = require('./utils/logger');

const app = express();

// Body parsing
app.use(express.json());

// Request logging
app.use((req, res, next) => {
  const start = Date.now();
  res.on('finish', () => {
    logger.info(`${req.method} ${req.path}`, {
      status: res.statusCode,
      duration: `${Date.now() - start}ms`,
      ip: req.ip,
    });
  });
  next();
});

// Health check (no auth required)
app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// All API routes require API key
app.use('/api', apiKeyAuth);

// Routes
app.use('/api/meetings', meetingsRouter);
app.use('/api/guest-token', guestTokenRouter);
app.use('/api/host-token', hostTokenRouter);

// 404 handler
app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// Global error handler
app.use((err, req, res, _next) => {
  logger.error('Unhandled error', { error: err.message, stack: err.stack });
  res.status(500).json({ error: 'Internal server error' });
});

module.exports = app;

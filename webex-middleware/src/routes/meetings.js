const express = require('express');
const { createMeeting, getMeeting, deleteMeeting } = require('../services/webexApi');
const logger = require('../utils/logger');

const router = express.Router();

// POST /api/meetings - Create a meeting
router.post('/', async (req, res) => {
  try {
    const { title, start, duration_minutes, invitees } = req.body;

    if (!title || !start) {
      return res.status(400).json({ error: 'title and start are required' });
    }

    const meeting = await createMeeting({
      title,
      start,
      durationMinutes: duration_minutes || 30,
      invitees: invitees || [],
    });

    res.json(meeting);
  } catch (err) {
    logger.error('Failed to create meeting', {
      error: err.response?.data || err.message,
    });
    res.status(err.response?.status || 500).json({
      error: 'Failed to create meeting',
      details: err.response?.data?.message || err.message,
    });
  }
});

// GET /api/meetings/:meetingId - Get meeting details
router.get('/:meetingId', async (req, res) => {
  try {
    const meeting = await getMeeting(req.params.meetingId);
    res.json(meeting);
  } catch (err) {
    logger.error('Failed to get meeting', {
      meetingId: req.params.meetingId,
      error: err.response?.data || err.message,
    });
    res.status(err.response?.status || 500).json({
      error: 'Failed to get meeting',
      details: err.response?.data?.message || err.message,
    });
  }
});

// DELETE /api/meetings/:meetingId - Delete a meeting
router.delete('/:meetingId', async (req, res) => {
  try {
    await deleteMeeting(req.params.meetingId);
    res.json({ success: true });
  } catch (err) {
    logger.error('Failed to delete meeting', {
      meetingId: req.params.meetingId,
      error: err.response?.data || err.message,
    });
    res.status(err.response?.status || 500).json({
      error: 'Failed to delete meeting',
      details: err.response?.data?.message || err.message,
    });
  }
});

module.exports = router;

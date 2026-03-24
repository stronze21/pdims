const axios = require('axios');
const { getAccessToken } = require('./tokenManager');
const logger = require('../utils/logger');

const webexClient = axios.create({
  baseURL: 'https://webexapis.com/v1',
  timeout: 15000,
});

// Attach Bearer token to every request
webexClient.interceptors.request.use(async (reqConfig) => {
  const token = await getAccessToken();
  reqConfig.headers.Authorization = `Bearer ${token}`;
  return reqConfig;
});

async function createMeeting({ title, start, durationMinutes, invitees }) {
  logger.info('Creating Webex meeting', { title, start });

  const startDate = new Date(start);
  const endDate = new Date(startDate.getTime() + (durationMinutes || 30) * 60000);

  const body = {
    title,
    start: startDate.toISOString(),
    end: endDate.toISOString(),
    enabledAutoRecordMeeting: false,
    allowAnyUserToBeCoHost: false,
  };

  if (invitees && invitees.length > 0) {
    body.invitees = invitees.map((email) => ({ email }));
  }

  const response = await webexClient.post('/meetings', body);

  return {
    id: response.data.id,
    meetingLink: response.data.webLink,
    sipAddress: response.data.sipAddress,
    meetingNumber: response.data.meetingNumber,
    password: response.data.password,
    start: response.data.start,
    end: response.data.end,
  };
}

async function getMeeting(meetingId) {
  logger.info('Getting Webex meeting', { meetingId });
  const response = await webexClient.get(`/meetings/${meetingId}`);

  return {
    id: response.data.id,
    title: response.data.title,
    meetingLink: response.data.webLink,
    sipAddress: response.data.sipAddress,
    meetingNumber: response.data.meetingNumber,
    state: response.data.state,
    start: response.data.start,
    end: response.data.end,
  };
}

async function deleteMeeting(meetingId) {
  logger.info('Deleting Webex meeting', { meetingId });
  await webexClient.delete(`/meetings/${meetingId}`);
  return true;
}

module.exports = { createMeeting, getMeeting, deleteMeeting };

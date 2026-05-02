// services/Api.js
import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add auth token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const getDoctors = async () => {
  const response = await api.get('/doctors');
  return response;
};

export const getChatMessages = async (doctorId) => {
  const response = await api.get(`/chat/messages/${doctorId}`);
  return response;
};

export const sendChatMessage = async (doctorId, data) => {
  const response = await api.post(`/chat/messages/${doctorId}`, data);
  return response;
};

export default api;
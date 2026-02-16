
import { GoogleGenAI } from "@google/genai";

// Fix: Updated initialization to use process.env.API_KEY directly as per guidelines
export const getTravelAdvice = async (query: string) => {
  const ai = new GoogleGenAI({ apiKey: process.env.API_KEY });
  const response = await ai.models.generateContent({
    model: 'gemini-3-flash-preview',
    contents: `Вы профессиональный турагент. Пользователь спрашивает: "${query}". Дайте краткую, дружелюбную и полезную рекомендацию на РУССКОМ языке. Не более 150 слов.`,
  });
  return response.text;
};

// Fix: Updated initialization to follow named parameter for apiKey and direct access to environment variable
export const getPartnerInsights = async (statsDescription: string) => {
  const ai = new GoogleGenAI({ apiKey: process.env.API_KEY });
  const response = await ai.models.generateContent({
    model: 'gemini-3-pro-preview',
    contents: `Вы бизнес-консультант для туристической платформы. На основе этих данных: ${statsDescription}, составьте 3 пункта стратегических советов на РУССКОМ языке для компании-партнера по максимизации прибыли.`,
  });
  return response.text;
};

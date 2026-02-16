
import React, { useState, useEffect } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import { PARTNER_STATS } from '../constants';
import { getPartnerInsights } from '../services/geminiService';
import { PartnerData } from '../types';

interface PartnerDashboardProps {
  partnerData: PartnerData | null;
}

const PartnerDashboard: React.FC<PartnerDashboardProps> = ({ partnerData }) => {
  const [insight, setInsight] = useState<string>('');
  const [loading, setLoading] = useState(false);

  const fetchInsights = async () => {
    setLoading(true);
    try {
      const text = await getPartnerInsights(`Компания ${partnerData?.companyName || 'Партнер'}. Текущие продажи растут на 20% в месяц, Турция — наш лучший регион, за ней следует Египет. Валюта: KZT.`);
      setInsight(text || 'Не удалось получить рекомендации в данный момент.');
    } catch (err) {
      console.error(err);
      setInsight('ИИ-рекомендации временно недоступны.');
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchInsights();
  }, []);

  return (
    <div className="pt-24 pb-12 px-4 max-w-7xl mx-auto">
      <div className="mb-8 reveal">
        <h1 className="text-3xl font-extrabold text-slate-900 mb-2">Панель управления: {partnerData?.companyName || 'Моя компания'}</h1>
        <p className="text-slate-500">Метрики сотрудничества в валюте {partnerData?.currency || 'KZT'} (₸).</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 space-y-8 reveal">
          <div className="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 className="text-lg font-bold mb-6 text-slate-800">Проданные туры и Активные клиенты</h3>
            <div className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={PARTNER_STATS}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                  <XAxis dataKey="month" stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} />
                  <YAxis stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} />
                  <Tooltip 
                    contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                  />
                  <Legend verticalAlign="top" align="right" />
                  <Bar dataKey="tours" fill="#3b82f6" radius={[4, 4, 0, 0]} name="Туры" />
                  <Bar dataKey="clients" fill="#93c5fd" radius={[4, 4, 0, 0]} name="Клиенты" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-blue-600 p-6 rounded-3xl text-white">
              <div className="text-blue-100 text-sm mb-1">Всего продано</div>
              <div className="text-3xl font-bold">12,409</div>
              <div className="text-xs text-blue-200 mt-2">↑ 14% с прошлого месяца</div>
            </div>
            <div className="bg-slate-800 p-6 rounded-3xl text-white">
              <div className="text-slate-400 text-sm mb-1">Активных партнеров</div>
              <div className="text-3xl font-bold">142</div>
              <div className="text-xs text-slate-500 mt-2">Глобальная сеть</div>
            </div>
            <div className="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
              <div className="text-slate-500 text-sm mb-1">Выплачено комиссий</div>
              <div className="text-3xl font-bold text-slate-800">₸2,4M</div>
              <div className="text-xs text-green-500 mt-2">Быстрые выплаты активны</div>
            </div>
          </div>
        </div>

        <div className="space-y-6 reveal">
          <div className="bg-gradient-to-br from-indigo-500 to-purple-600 p-6 rounded-3xl text-white shadow-lg">
            <div className="flex items-center gap-2 mb-4">
              <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                <i className="fa-solid fa-wand-magic-sparkles"></i>
              </div>
              <h3 className="font-bold">Бизнес-инсайты для {partnerData?.companyName}</h3>
            </div>
            {loading ? (
              <div className="animate-pulse space-y-2">
                <div className="h-4 bg-white/20 rounded w-3/4"></div>
                <div className="h-4 bg-white/20 rounded w-full"></div>
                <div className="h-4 bg-white/20 rounded w-5/6"></div>
              </div>
            ) : (
              <div className="text-sm leading-relaxed opacity-90 whitespace-pre-line">
                {insight}
              </div>
            )}
            <button 
              onClick={fetchInsights}
              className="mt-4 text-xs font-bold uppercase tracking-wider py-2 px-4 bg-white/10 hover:bg-white/20 rounded-full transition-all"
            >
              Обновить советы
            </button>
          </div>

          <div className="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 className="font-bold text-slate-800 mb-4">Почему стоит быть партнером?</h3>
            <ul className="space-y-4">
              {[
                { icon: 'fa-rocket', text: 'Собственная система бронирования' },
                { icon: 'fa-globe', text: 'Доступ к 50+ странам' },
                { icon: 'fa-shield-halved', text: 'Полное страхование и комплаенс' },
                { icon: 'fa-coins', text: 'Самые высокие комиссии' }
              ].map((item, i) => (
                <li key={i} className="flex items-center gap-3 text-sm text-slate-600">
                  <div className="w-8 h-8 bg-slate-50 rounded-full flex items-center justify-center text-blue-600">
                    <i className={`fa-solid ${item.icon}`}></i>
                  </div>
                  {item.text}
                </li>
              ))}
            </ul>
            <button className="w-full mt-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all">
              Подать заявку на расширение
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PartnerDashboard;


import React, { useState } from 'react';
import { UserRole, PartnerData } from '../types';

interface AuthModalProps {
  onSelect: (role: UserRole, data?: PartnerData) => void;
  onClose: () => void;
}

const AuthModal: React.FC<AuthModalProps> = ({ onSelect, onClose }) => {
  const [step, setStep] = useState(1);
  const [roleType, setRoleType] = useState<UserRole>(UserRole.TRAVELER);
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [username, setUsername] = useState('');
  const [companyName, setCompanyName] = useState('');
  
  const [age, setAge] = useState('');
  const [travelDuration, setTravelDuration] = useState('');
  const [expectations, setExpectations] = useState('');

  const isPasswordReliable = password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password);

  const handleNext = () => {
    if (step === 1) {
      if (!email || !password || !username) {
        alert('Пожалуйста, заполните все поля (имя, почта, пароль).');
        return;
      }
      if (!isPasswordReliable) {
        alert('Ваш пароль недостаточно надежен. Мы рекомендуем использовать минимум 8 символов, включая заглавную букву и хотя бы одну цифру.');
        return;
      }
      setStep(2);
    } else if (step === 2) {
      if (!age || !travelDuration) {
        alert('Пожалуйста, ответьте на вопросы о вашем возрасте и опыте путешествий.');
        return;
      }
      setStep(3);
    }
  };

  const handleFinish = () => {
    if (roleType === UserRole.PARTNER) {
      if (!companyName) {
        alert('Пожалуйста, укажите название вашей компании.');
        return;
      }
      onSelect(UserRole.PARTNER, { companyName, currency: 'KZT' });
    } else {
      onSelect(UserRole.TRAVELER);
    }
  };

  const renderStep = () => {
    switch (step) {
      case 1:
        return (
          <div className="animate-fade-in space-y-6">
            <div className="text-center">
              <h3 className="text-3xl font-black text-slate-800 mb-2">Добро пожаловать</h3>
              <p className="text-slate-500">Начнем с создания вашего профиля</p>
            </div>
            
            <div className="space-y-4">
              <input 
                type="text" 
                placeholder="Как вас зовут?" 
                value={username} 
                onChange={e => setUsername(e.target.value)}
                className="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-2 focus:ring-blue-500 transition-all outline-none font-medium"
              />
              <input 
                type="email" 
                placeholder="Электронная почта" 
                value={email} 
                onChange={e => setEmail(e.target.value)}
                className="w-full px-5 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:ring-2 focus:ring-blue-500 transition-all outline-none font-medium"
              />
              <div className="relative">
                <input 
                  type="password" 
                  placeholder="Надежный пароль" 
                  value={password} 
                  onChange={e => setPassword(e.target.value)}
                  className={`w-full px-5 py-4 rounded-2xl bg-slate-50 border ${password && !isPasswordReliable ? 'border-red-300 bg-red-50/30' : 'border-slate-100'} focus:ring-2 focus:ring-blue-500 transition-all outline-none font-medium`}
                />
                {password && !isPasswordReliable && (
                  <p className="text-[10px] text-red-500 mt-2 px-1 font-bold">
                    Пароль слишком простой. Добавьте заглавные буквы и цифры.
                  </p>
                )}
              </div>
            </div>

            <div className="flex bg-slate-50 p-1.5 rounded-2xl border border-slate-100">
               <button 
                  onClick={() => setRoleType(UserRole.TRAVELER)} 
                  className={`flex-1 py-3 rounded-xl font-bold transition-all ${roleType === UserRole.TRAVELER ? 'bg-white shadow-md text-blue-600' : 'text-slate-400'}`}
               >Путешественник</button>
               <button 
                  onClick={() => setRoleType(UserRole.PARTNER)} 
                  className={`flex-1 py-3 rounded-xl font-bold transition-all ${roleType === UserRole.PARTNER ? 'bg-white shadow-md text-slate-800' : 'text-slate-400'}`}
               >Партнер</button>
            </div>

            <button 
              onClick={handleNext} 
              className="w-full p-5 bg-blue-600 text-white rounded-2xl font-black hover:bg-blue-700 transition-all shadow-xl shadow-blue-100"
            >
              Далее
            </button>
          </div>
        );
      case 2:
        return (
          <div className="animate-fade-in space-y-8">
            <div className="text-center">
              <h3 className="text-3xl font-black text-slate-800 mb-2">Ваш Опыт</h3>
              <p className="text-slate-500">Помогите нам узнать вас лучше</p>
            </div>
            
            <div className="space-y-6">
              <div className="group">
                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Какой ваш возраст?</label>
                <input 
                  type="number" 
                  value={age} 
                  onChange={e => setAge(e.target.value)} 
                  placeholder="Напр: 24" 
                  className="w-full px-6 py-5 rounded-3xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-blue-100 transition-all outline-none font-bold text-xl"
                />
              </div>
              <div className="group">
                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Как долго вы уже путешествуете?</label>
                <input 
                  type="text" 
                  value={travelDuration} 
                  onChange={e => setTravelDuration(e.target.value)} 
                  placeholder="Напр: 3 года или 'Только начинаю'" 
                  className="w-full px-6 py-5 rounded-3xl bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-blue-100 transition-all outline-none font-bold text-lg"
                />
              </div>
            </div>
            
            <div className="flex gap-4">
              <button 
                onClick={() => setStep(1)} 
                className="px-8 py-5 border border-slate-200 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 transition-all"
              >
                Назад
              </button>
              <button 
                onClick={handleNext} 
                className="flex-1 py-5 bg-blue-600 text-white rounded-2xl font-black shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all"
              >
                Продолжить
              </button>
            </div>
          </div>
        );
      case 3:
        return (
          <div className="animate-fade-in space-y-8">
            <div className="text-center">
              <h3 className="text-3xl font-black text-slate-800 mb-2">Финальный Шаг</h3>
              <p className="text-slate-500">Что вы ожидаете от GlobalQuest?</p>
            </div>
            
            <div className="space-y-6">
              <textarea 
                value={expectations} 
                onChange={e => setExpectations(e.target.value)}
                placeholder="Опишите ваши ожидания от поездок..."
                className="w-full px-8 py-6 rounded-[2rem] bg-slate-50 border border-slate-100 focus:ring-4 focus:ring-blue-100 transition-all outline-none min-h-[160px] resize-none font-medium"
              />
              
              {roleType === UserRole.PARTNER && (
                <div className="animate-scale-in pt-4 border-t border-slate-50">
                  <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Название вашей компании</label>
                  <input 
                    type="text" 
                    value={companyName} 
                    onChange={e => setCompanyName(e.target.value)} 
                    placeholder="Напр: Almaty Travel Agency" 
                    className="w-full px-6 py-5 rounded-3xl bg-blue-50/50 border border-blue-100 focus:ring-4 focus:ring-blue-100 transition-all outline-none font-bold"
                  />
                  <div className="mt-4 p-4 bg-slate-900 rounded-2xl text-xs text-slate-400 flex justify-between items-center">
                    <span className="font-bold">Основная валюта:</span>
                    <span className="text-white font-black">KZT (₸)</span>
                  </div>
                </div>
              )}
            </div>
            
            <div className="flex gap-4">
              <button 
                onClick={() => setStep(2)} 
                className="px-8 py-5 border border-slate-200 rounded-2xl font-bold text-slate-400 hover:bg-slate-50 transition-all"
              >
                Назад
              </button>
              <button 
                onClick={handleFinish} 
                className="flex-1 py-5 bg-slate-900 text-white rounded-2xl font-black shadow-2xl hover:bg-slate-800 transition-all"
              >
                Начать Приключение
              </button>
            </div>
          </div>
        );
      default: return null;
    }
  };

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-slate-900/70 backdrop-blur-lg" onClick={onClose}></div>
      <div className="relative bg-white w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden scale-in p-12">
        <button onClick={onClose} className="absolute top-8 right-8 text-slate-300 hover:text-slate-500 transition-colors">
          <i className="fa-solid fa-xmark text-2xl"></i>
        </button>
        {renderStep()}
      </div>
    </div>
  );
};

export default AuthModal;

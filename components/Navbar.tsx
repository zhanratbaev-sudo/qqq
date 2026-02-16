
import React from 'react';
import { UserRole } from '../types';

interface NavbarProps {
  role: UserRole;
  onLogout: () => void;
  onOpenAuth: () => void;
}

const Navbar: React.FC<NavbarProps> = ({ role, onLogout, onOpenAuth }) => {
  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
      <div className="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <div className="flex items-center gap-2 cursor-pointer" onClick={() => window.location.hash = ''}>
          <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">G</div>
          <span className="text-xl font-bold tracking-tight text-slate-800">GlobalQuest</span>
        </div>

        <div className="hidden md:flex items-center gap-8 font-medium text-slate-600">
          <a href="#" className="hover:text-blue-600 transition-colors">Главная</a>
          <a href="#/all-tours" className="hover:text-blue-600 transition-colors">Все туры</a>
          <a href="#/contacts" className="hover:text-blue-600 transition-colors">Контакты</a>
          {role === UserRole.PARTNER && (
            <a href="#/partner" className="text-blue-600">Панель партнера</a>
          )}
        </div>

        <div className="flex items-center gap-3">
          {role === UserRole.GUEST ? (
            <button 
              onClick={onOpenAuth}
              className="px-4 py-2 bg-blue-600 text-white rounded-full font-semibold hover:bg-blue-700 transition-all shadow-md shadow-blue-200"
            >
              Войти
            </button>
          ) : (
            <div className="flex items-center gap-4">
              <span className="text-sm font-medium text-slate-500 hidden sm:inline">
                {role === UserRole.PARTNER ? '🏢 Аккаунт партнера' : '👤 Путешественник'}
              </span>
              {/* Fix: use onLogout prop instead of undefined handleLogout */}
              <button 
                onClick={onLogout}
                className="px-4 py-2 border border-slate-200 text-slate-600 rounded-full font-semibold hover:bg-slate-50 transition-all"
              >
                Выйти
              </button>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
};

export default Navbar;

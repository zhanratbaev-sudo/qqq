
import React, { useState, useEffect } from 'react';
import Navbar from './components/Navbar';
import AuthModal from './components/AuthModal';
import TravelerHome from './components/TravelerHome';
import PartnerDashboard from './components/PartnerDashboard';
import AllToursPage from './components/AllToursPage';
import ContactPage from './components/ContactPage';
import { UserRole, PartnerData } from './types';

const App: React.FC = () => {
  const [role, setRole] = useState<UserRole>(UserRole.GUEST);
  const [isAuthOpen, setIsAuthOpen] = useState(false);
  const [path, setPath] = useState(window.location.hash);
  const [partnerData, setPartnerData] = useState<PartnerData | null>(null);

  useEffect(() => {
    const handleHashChange = () => setPath(window.location.hash);
    window.addEventListener('hashchange', handleHashChange);
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
        }
      });
    }, { threshold: 0.1 });

    const timeout = setTimeout(() => {
      document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    }, 500);

    return () => {
      window.removeEventListener('hashchange', handleHashChange);
      clearTimeout(timeout);
    };
  }, [path]);

  const handleLogin = (selectedRole: UserRole, data?: PartnerData) => {
    setRole(selectedRole);
    if (data) setPartnerData(data);
    setIsAuthOpen(false);
    if (selectedRole === UserRole.PARTNER) {
      window.location.hash = '/partner';
    } else {
      window.location.hash = '';
    }
  };

  const handleLogout = () => {
    setRole(UserRole.GUEST);
    setPartnerData(null);
    window.location.hash = '';
  };

  const renderContent = () => {
    if (role === UserRole.PARTNER && path === '#/partner') {
      return <PartnerDashboard partnerData={partnerData} />;
    }
    if (path === '#/all-tours') {
      return <AllToursPage />;
    }
    if (path === '#/contacts') {
      return <ContactPage />;
    }
    return <TravelerHome />;
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <Navbar 
        role={role} 
        onLogout={handleLogout} 
        onOpenAuth={() => setIsAuthOpen(true)} 
      />
      
      <main>
        {renderContent()}
      </main>

      {isAuthOpen && (
        <AuthModal 
          onSelect={handleLogin} 
          onClose={() => setIsAuthOpen(false)} 
        />
      )}
    </div>
  );
};

export default App;

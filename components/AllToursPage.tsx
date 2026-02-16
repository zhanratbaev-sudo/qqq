
import React, { useState, useMemo } from 'react';
import { TURKEY_TOURS, EGYPT_TOURS } from '../constants';
import { Tour } from '../types';

const AllToursPage: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCountry, setSelectedCountry] = useState('Все');
  const [bookedTour, setBookedTour] = useState<Tour | null>(null);

  const allTours = useMemo(() => [...TURKEY_TOURS, ...EGYPT_TOURS], []);
  const countries = ['Все', 'Турция', 'Египет'];

  const filteredTours = useMemo(() => {
    return allTours.filter(tour => {
      const matchesSearch = tour.title.toLowerCase().includes(searchTerm.toLowerCase()) || 
                            tour.location.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesCountry = selectedCountry === 'Все' || tour.location === selectedCountry;
      return matchesSearch && matchesCountry;
    });
  }, [searchTerm, selectedCountry, allTours]);

  const handleBook = (tour: Tour) => {
    setBookedTour(tour);
    setTimeout(() => setBookedTour(null), 8000);
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('kk-KZ', { style: 'currency', currency: 'KZT', maximumFractionDigits: 0 }).format(price);
  };

  return (
    <div className="pt-24 pb-20 min-h-screen bg-slate-50 px-4">
      <div className="max-w-7xl mx-auto">
        <div className="mb-12 reveal">
          <h1 className="text-4xl font-extrabold text-slate-900 mb-4">Каталог всех туров</h1>
          <p className="text-slate-500 text-lg">Каждое бронирование приносит вам бонусные баллы и купоны на скидки!</p>
        </div>

        {/* Booking Confirmation Overlay */}
        {bookedTour && (
            <div className="fixed bottom-10 right-10 z-[60] animate-bounce">
                <div className="bg-green-600 text-white p-6 rounded-3xl shadow-2xl flex items-center gap-6 max-w-md">
                    <div className="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-3xl">
                        <i className="fa-solid fa-gift"></i>
                    </div>
                    <div>
                        <h4 className="font-bold text-lg">Тур забронирован!</h4>
                        <p className="text-sm opacity-90">Ваш промокод: <span className="font-mono font-black text-yellow-300">ALMATY2024</span> (Скидка 15% на след. тур)</p>
                        <button onClick={() => setBookedTour(null)} className="mt-2 text-xs font-bold underline">Закрыть</button>
                    </div>
                </div>
            </div>
        )}

        {/* Search and Filters */}
        <div className="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 mb-12 space-y-6 reveal">
          <div className="flex flex-col md:flex-row gap-6">
            <div className="flex-1 relative">
              <i className="fa-solid fa-magnifying-glass absolute left-6 top-1/2 -translate-y-1/2 text-slate-400"></i>
              <input 
                type="text"
                placeholder="Поиск по названию или стране..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-14 pr-6 py-5 rounded-2xl bg-slate-50 border border-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-medium"
              />
            </div>
            <div className="flex flex-wrap gap-2 items-center">
              <span className="text-slate-500 font-bold text-sm uppercase tracking-wider mr-2">Фильтр стран:</span>
              <div className="flex bg-slate-50 p-1.5 rounded-2xl border border-slate-100">
                {countries.map((country) => (
                  <button
                    key={country}
                    onClick={() => setSelectedCountry(country)}
                    className={`px-6 py-3 rounded-xl text-sm font-black transition-all ${
                      selectedCountry === country 
                      ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' 
                      : 'text-slate-600 hover:bg-slate-200'
                    }`}
                  >
                    {country}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Tours Grid */}
        {filteredTours.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            {filteredTours.map((tour) => (
              <div 
                key={tour.id} 
                className="bg-white rounded-[2rem] overflow-hidden shadow-sm hover:shadow-2xl transition-all border border-slate-100 flex flex-col group reveal"
              >
                <div className="relative h-64 overflow-hidden">
                  <img 
                    src={tour.image} 
                    alt={tour.title} 
                    className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                  />
                  <div className="absolute top-5 left-5 bg-white/95 backdrop-blur px-4 py-1.5 rounded-full text-xs font-black text-slate-800 shadow-sm">
                    {tour.location}
                  </div>
                  <div className="absolute bottom-5 left-5 bg-blue-600/90 backdrop-blur px-3 py-1 rounded-lg text-xs font-bold text-white">
                    ⭐ {tour.rating}
                  </div>
                </div>
                <div className="p-8 flex-1 flex flex-col">
                  <h3 className="font-black text-2xl text-slate-800 leading-tight mb-3">{tour.title}</h3>
                  <p className="text-slate-500 text-sm mb-8 line-clamp-3 flex-1 leading-relaxed">{tour.description}</p>
                  <div className="flex flex-col pt-6 border-t border-slate-50">
                    <div className="flex justify-between items-center mb-4">
                      <div className="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em]">Цена</div>
                      <div className="text-2xl font-black text-blue-600">{formatPrice(tour.price)}</div>
                    </div>
                    <button 
                        onClick={() => handleBook(tour)}
                        className="w-full py-4 bg-slate-900 text-white rounded-xl font-bold hover:bg-blue-600 transition-all flex items-center justify-center gap-2"
                    >
                        <span>Купить тур</span>
                        <i className="fa-solid fa-cart-plus text-xs"></i>
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-32 bg-white rounded-[3rem] border-2 border-dashed border-slate-200 reveal">
            <div className="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-8 text-slate-300 text-4xl">
              <i className="fa-solid fa-earth-europe animate-pulse"></i>
            </div>
            <h3 className="text-3xl font-black text-slate-800 mb-3">На горизонте пусто</h3>
            <p className="text-slate-500 text-lg">Попробуйте другие фильтры или сбросьте параметры поиска.</p>
            <button 
              onClick={() => {setSearchTerm(''); setSelectedCountry('Все');}}
              className="mt-8 px-8 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-100"
            >
              Сбросить фильтры
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default AllToursPage;

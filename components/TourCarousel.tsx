
import React from 'react';
import { Tour } from '../types';

interface TourCarouselProps {
  title: string;
  tours: Tour[];
}

const TourCarousel: React.FC<TourCarouselProps> = ({ title, tours }) => {
  const scrollContainerRef = React.useRef<HTMLDivElement>(null);

  const scroll = (direction: 'left' | 'right') => {
    if (scrollContainerRef.current) {
      const { current } = scrollContainerRef;
      const scrollAmount = 400;
      current.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
    }
  };

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('kk-KZ', { style: 'currency', currency: 'KZT', maximumFractionDigits: 0 }).format(price);
  };

  return (
    <div className="mb-12 relative group">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-2xl font-bold text-slate-800 flex items-center gap-3">
          <span className="w-1 h-8 bg-blue-600 rounded-full"></span>
          {title}
        </h2>
        <div className="flex gap-2">
          <button 
            onClick={() => scroll('left')}
            className="p-2 rounded-full border border-slate-200 hover:bg-white hover:shadow-md transition-all text-slate-400 hover:text-blue-600"
          >
            <i className="fa-solid fa-chevron-left"></i>
          </button>
          <button 
            onClick={() => scroll('right')}
            className="p-2 rounded-full border border-slate-200 hover:bg-white hover:shadow-md transition-all text-slate-400 hover:text-blue-600"
          >
            <i className="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      </div>

      <div 
        ref={scrollContainerRef}
        className="flex gap-6 overflow-x-auto hide-scrollbar pb-4 -mx-2 px-2"
      >
        {tours.map((tour) => (
          <div 
            key={tour.id} 
            className="min-w-[280px] md:min-w-[320px] bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-slate-100 group/card"
          >
            <div className="relative h-48 overflow-hidden">
              <img 
                src={tour.image} 
                alt={tour.title} 
                className="w-full h-full object-cover group-hover/card:scale-105 transition-transform duration-500"
              />
              <div className="absolute top-3 right-3 bg-white/90 backdrop-blur px-2 py-1 rounded-lg text-xs font-bold text-slate-800">
                ⭐ {tour.rating}
              </div>
            </div>
            <div className="p-5">
              <div className="flex flex-col mb-2">
                <h3 className="font-bold text-lg text-slate-800 truncate">{tour.title}</h3>
                <span className="text-blue-600 font-bold text-xl">{formatPrice(tour.price)}</span>
              </div>
              <p className="text-slate-500 text-sm mb-4 line-clamp-2">{tour.description}</p>
              <button 
                onClick={() => window.location.hash = '#/all-tours'}
                className="w-full py-2 bg-slate-100 hover:bg-blue-600 hover:text-white text-slate-700 font-semibold rounded-xl transition-all"
              >
                Подробнее
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default TourCarousel;

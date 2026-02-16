
import React, { useState, useEffect } from 'react';
import TourCarousel from './TourCarousel';
import { TURKEY_TOURS, EGYPT_TOURS, TESTIMONIALS_ROW_1, TESTIMONIALS_ROW_2, SHUFFLE_IMAGES } from '../constants';

const TravelerHome: React.FC = () => {
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  const shuffleImages = () => {
    setCurrentImageIndex((prev) => (prev + 1) % SHUFFLE_IMAGES.length);
  };

  useEffect(() => {
    const timer = setInterval(shuffleImages, 4000);
    return () => clearInterval(timer);
  }, []);

  // Fix: Explicitly define as React.FC to include 'key' prop support in list rendering
  const TestimonialCard: React.FC<{ item: any }> = ({ item }) => (
    <div className="w-[350px] bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 flex gap-5 mx-5">
      <img src={item.avatar} className="w-14 h-14 rounded-2xl object-cover shrink-0" alt={item.name} />
      <div>
        <div className="font-black text-slate-800 text-lg">{item.name}</div>
        <div className="text-blue-600 text-xs font-black uppercase tracking-widest mb-3">{item.role}</div>
        <p className="text-slate-500 text-sm leading-relaxed italic">"{item.content}"</p>
      </div>
    </div>
  );

  return (
    <div className="pt-20">
      {/* Hero Section */}
      <section className="relative h-[700px] flex items-center px-4 overflow-hidden">
        <div className="absolute inset-0 z-0">
          <img 
            src="https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&q=80&w=1920" 
            className="w-full h-full object-cover brightness-[0.4]"
            alt="Hero Background"
          />
        </div>
        
        <div className="max-w-7xl mx-auto w-full relative z-10 text-white text-center">
          <h1 className="text-6xl md:text-9xl font-black mb-8 scale-in tracking-tighter leading-none">
            МИР <span className="text-blue-400">ЖДЕТ</span><br />
            ТЕБЯ.
          </h1>
          <p className="text-xl md:text-3xl text-slate-200 max-w-3xl mx-auto mb-12 font-medium opacity-90">
            Забронируй тур прямо сейчас и получи эксклюзивный купон на 50,000 ₸ для следующей поездки!
          </p>
          
          <div className="flex flex-col sm:flex-row gap-6 justify-center">
            <button 
              onClick={() => window.location.hash = '#/all-tours'}
              className="px-14 py-6 bg-blue-600 hover:bg-blue-700 rounded-full text-xl font-black transition-all shadow-2xl shadow-blue-900/50"
            >
              Найти свой тур
            </button>
            <button 
              onClick={() => window.location.hash = '#/contacts'}
              className="px-14 py-6 bg-white/10 backdrop-blur-md border-2 border-white/20 hover:bg-white/20 rounded-full text-xl font-black transition-all"
            >
              Где мы находимся?
            </button>
          </div>
        </div>
      </section>

      {/* Coupon Banner */}
      <section className="max-w-7xl mx-auto px-4 -mt-14 relative z-20 reveal">
        <div className="bg-gradient-to-br from-orange-400 to-red-600 p-10 rounded-[3rem] shadow-2xl flex flex-col md:flex-row items-center justify-between gap-8 text-white">
          <div className="flex items-center gap-8">
            <div className="w-24 h-24 bg-white/20 rounded-[2rem] flex items-center justify-center text-5xl shrink-0">
                <i className="fa-solid fa-gift animate-bounce"></i>
            </div>
            <div>
                <h3 className="text-4xl font-black mb-2">БОНУС ПРИ БРОНИРОВАНИИ</h3>
                <p className="opacity-90 font-bold text-lg">Скидка 15% на все будущие путешествия при покупке тура в этом месяце!</p>
            </div>
          </div>
          <button 
            onClick={() => window.location.hash = '#/all-tours'}
            className="px-12 py-6 bg-white text-orange-600 font-black rounded-3xl hover:scale-110 transition-all whitespace-nowrap shadow-xl"
          >
            ПОЛУЧИТЬ КУПОН
          </button>
        </div>
      </section>

      {/* Popular Tours Section */}
      <section className="max-w-7xl mx-auto px-4 py-24 reveal">
        <TourCarousel title="Популярное: Турция" tours={TURKEY_TOURS} />
        <TourCarousel title="Мистический Египет" tours={EGYPT_TOURS} />
        
        <div className="flex justify-center mt-16">
          <button 
            onClick={() => window.location.hash = '#/all-tours'}
            className="group px-14 py-7 bg-slate-900 text-white rounded-full font-black hover:bg-blue-600 transition-all flex items-center gap-6 text-xl shadow-2xl shadow-slate-300"
          >
            Посмотреть все направления
            <i className="fa-solid fa-arrow-right group-hover:translate-x-3 transition-transform"></i>
          </button>
        </div>
      </section>

      {/* Image Shuffle Gallery Section */}
      <section className="max-w-7xl mx-auto px-4 py-32 bg-slate-100 rounded-[5rem] reveal">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center px-10">
            <div className="order-2 lg:order-1 relative group">
                <div 
                    onClick={shuffleImages}
                    className="relative h-[600px] w-full rounded-[4rem] overflow-hidden cursor-pointer shadow-3xl border-8 border-white"
                >
                    <img 
                        src={SHUFFLE_IMAGES[currentImageIndex]} 
                        className="w-full h-full object-cover transition-all duration-1000 group-hover:scale-110"
                        alt="Destinations"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                    <div className="absolute bottom-16 left-16 right-16 text-white text-center">
                        <div className="inline-block bg-blue-600 px-6 py-2 rounded-full text-xs font-black uppercase tracking-[0.3em] mb-6">Нажми, чтобы сменить вид</div>
                        <h4 className="text-4xl font-black leading-tight">Ваш идеальный отпуск начинается с вдохновения</h4>
                    </div>
                </div>
                {/* Decorative Elements */}
                <div className="absolute -top-10 -right-10 w-40 h-40 bg-blue-600/10 rounded-full blur-3xl"></div>
                <div className="absolute -bottom-10 -left-10 w-40 h-40 bg-orange-600/10 rounded-full blur-3xl"></div>
            </div>
            
            <div className="order-1 lg:order-2">
                <h2 className="text-5xl font-black text-slate-900 mb-10 leading-none tracking-tight">Вдохновение для <span className="text-blue-600">Вашего</span> Пути</h2>
                <p className="text-slate-500 text-xl mb-12 leading-relaxed font-medium">
                    Каждое изображение слева — это частичка тех эмоций, которые вы испытаете. Мы тщательно отбираем направления, чтобы каждый ваш шаг был напомнен смыслом и красотой.
                </p>
                <div className="space-y-10">
                    <div className="flex items-center gap-6 group">
                        <div className="w-16 h-16 bg-white shadow-xl text-blue-600 rounded-[1.5rem] flex items-center justify-center font-black text-2xl group-hover:bg-blue-600 group-hover:text-white transition-all">01</div>
                        <div>
                            <h5 className="font-black text-slate-800 text-lg">Выбирайте Мечту</h5>
                            <p className="text-slate-400 text-sm">Сотни уникальных маршрутов по всему миру.</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-6 group">
                        <div className="w-16 h-16 bg-white shadow-xl text-blue-600 rounded-[1.5rem] flex items-center justify-center font-black text-2xl group-hover:bg-blue-600 group-hover:text-white transition-all">02</div>
                        <div>
                            <h5 className="font-black text-slate-800 text-lg">Быстрое Бронирование</h5>
                            <p className="text-slate-400 text-sm">Онлайн-оплата в тенге без скрытых комиссий.</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-6 group">
                        <div className="w-16 h-16 bg-white shadow-xl text-blue-600 rounded-[1.5rem] flex items-center justify-center font-black text-2xl group-hover:bg-blue-600 group-hover:text-white transition-all">03</div>
                        <div>
                            <h5 className="font-black text-slate-800 text-lg">Наслаждайтесь Жинью</h5>
                            <p className="text-slate-400 text-sm">Полная поддержка 24/7 во время поездки.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </section>

      {/* Testimonials Marquee Section */}
      <section className="bg-white py-32 overflow-hidden border-y border-slate-100 reveal">
        <div className="max-w-7xl mx-auto px-4 mb-20 text-center">
          <h2 className="text-5xl font-black text-slate-900 mb-4 leading-none">Мнение Сообщества</h2>
          <p className="text-slate-400 text-lg font-medium max-w-2xl mx-auto">Реальные истории от тех, кто уже изменил свою жизнь вместе с нами.</p>
        </div>

        <div className="mb-10 overflow-hidden relative">
          <div className="animate-scroll-right">
            {[...TESTIMONIALS_ROW_1, ...TESTIMONIALS_ROW_1, ...TESTIMONIALS_ROW_1].map((t, i) => (
              <TestimonialCard key={`${t.id}-${i}`} item={t} />
            ))}
          </div>
          <div className="absolute inset-y-0 left-0 w-48 bg-gradient-to-r from-white to-transparent z-10"></div>
          <div className="absolute inset-y-0 right-0 w-48 bg-gradient-to-l from-white to-transparent z-10"></div>
        </div>

        <div className="overflow-hidden relative">
          <div className="animate-scroll-left">
            {[...TESTIMONIALS_ROW_2, ...TESTIMONIALS_ROW_2, ...TESTIMONIALS_ROW_2].map((t, i) => (
              <TestimonialCard key={`${t.id}-${i}`} item={t} />
            ))}
          </div>
          <div className="absolute inset-y-0 left-0 w-48 bg-gradient-to-r from-white to-transparent z-10"></div>
          <div className="absolute inset-y-0 right-0 w-48 bg-gradient-to-l from-white to-transparent z-10"></div>
        </div>
      </section>

      {/* Map & Office Sneak Peek */}
      <section className="max-w-7xl mx-auto px-4 py-32 reveal">
        <div className="bg-slate-900 rounded-[4rem] overflow-hidden flex flex-col lg:flex-row items-center">
            <div className="p-20 lg:w-1/2 text-white">
                <h2 className="text-5xl font-black mb-10 leading-none">Ждем Вас в Гости в Алматы</h2>
                <div className="space-y-8 mb-12">
                    <div className="flex items-center gap-6">
                        <div className="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center text-blue-400 text-2xl shrink-0"><i className="fa-solid fa-map-location-dot"></i></div>
                        <div>
                            <h6 className="font-black text-sm uppercase tracking-widest text-slate-500">Наш адрес</h6>
                            <p className="text-xl font-bold">пр. Абая 165/2, Бостандыкский р-н</p>
                        </div>
                    </div>
                </div>
                <button 
                  onClick={() => window.location.hash = '#/contacts'}
                  className="px-10 py-5 bg-blue-600 hover:bg-white hover:text-blue-600 rounded-3xl font-black transition-all shadow-xl"
                >
                  Связаться с Командой
                </button>
            </div>
            <div className="h-[400px] lg:h-[600px] lg:w-1/2 w-full relative">
                <img src="https://images.unsplash.com/photo-1577412647305-991150c7d163?auto=format&fit=crop&q=80&w=1200" className="w-full h-full object-cover grayscale opacity-50" alt="Office" />
                <div className="absolute inset-0 bg-blue-600/20 mix-blend-overlay"></div>
            </div>
        </div>
      </section>
      
      {/* Footer */}
      <footer className="bg-white border-t border-slate-100 py-24 px-4">
        <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-20">
          <div className="col-span-1 md:col-span-2">
            <div className="flex items-center gap-4 mb-10">
              <div className="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center text-white font-black text-3xl">G</div>
              <span className="font-black text-4xl text-slate-800 tracking-tighter">GlobalQuest</span>
            </div>
            <p className="text-slate-400 max-w-sm mb-12 leading-relaxed text-xl font-medium">Создаем воспоминания, которые живут вечно. Мы — ваше доверенное окно в мир приключений из самого сердца Казахстана.</p>
            <div className="flex gap-6">
                <a href="https://instagram.com" target="_blank" className="w-16 h-16 bg-slate-50 rounded-[1.5rem] flex items-center justify-center text-slate-400 hover:bg-blue-600 hover:text-white transition-all text-3xl">
                    <i className="fa-brands fa-instagram"></i>
                </a>
                <a href="https://facebook.com" target="_blank" className="w-16 h-16 bg-slate-50 rounded-[1.5rem] flex items-center justify-center text-slate-400 hover:bg-blue-600 hover:text-white transition-all text-3xl">
                    <i className="fa-brands fa-facebook"></i>
                </a>
            </div>
          </div>
          <div>
            <h4 className="font-black text-slate-900 mb-10 uppercase tracking-[0.2em] text-sm">Навигация</h4>
            <ul className="space-y-6 text-slate-400 font-bold text-lg">
                <li><a href="#" className="hover:text-blue-600 transition-colors">Главная</a></li>
                <li><a href="#/all-tours" className="hover:text-blue-600 transition-colors">Все туры</a></li>
                <li><a href="#/contacts" className="hover:text-blue-600 transition-colors">Контакты</a></li>
                <li><a href="#/partner" className="hover:text-blue-600 transition-colors">Партнерам</a></li>
            </ul>
          </div>
          <div>
            <h4 className="font-black text-slate-900 mb-10 uppercase tracking-[0.2em] text-sm">Помощь</h4>
            <ul className="space-y-6 text-slate-400 font-bold text-lg">
                <li><a href="#/contacts" className="hover:text-blue-600 transition-colors">О нас</a></li>
                <li><a href="#" className="hover:text-blue-600 transition-colors">Приватность</a></li>
                <li><a href="#" className="hover:text-blue-600 transition-colors">Оплата</a></li>
            </ul>
          </div>
        </div>
        <div className="max-w-7xl mx-auto mt-24 pt-12 border-t border-slate-50 text-center text-slate-300 text-sm font-bold uppercase tracking-widest">
            © 2024 GlobalQuest Travel. Алматы, пр. Абая 165/2. Все права защищены.
        </div>
      </footer>
    </div>
  );
};

export default TravelerHome;

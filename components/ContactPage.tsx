
import React from 'react';

const ContactPage: React.FC = () => {
  return (
    <div className="pt-24 pb-20 min-h-screen bg-white px-4">
      <div className="max-w-7xl mx-auto">
        <div className="text-center mb-20 reveal">
          <h1 className="text-6xl md:text-8xl font-black text-slate-900 mb-6 leading-none">СВЯЖИТЕСЬ С <span className="text-blue-600">НАМИ</span></h1>
          <p className="text-xl text-slate-400 font-medium max-w-2xl mx-auto">Мы всегда на связи, чтобы помочь вам спланировать идеальный отпуск.</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-stretch">
          <div className="reveal">
            <div className="bg-slate-50 p-12 rounded-[3.5rem] h-full flex flex-col border border-slate-100 shadow-sm">
                <h3 className="text-3xl font-black text-slate-800 mb-10">Наш Штаб в Алматы</h3>
                
                <div className="space-y-12 flex-1">
                    <div className="flex gap-8">
                        <div className="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-blue-600 text-3xl shrink-0">
                            <i className="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <span className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Наш адрес</span>
                            <p className="text-2xl font-black text-slate-800 leading-tight">г. Алматы, Бостандыкский район<br />проспект Абая 165/2</p>
                        </div>
                    </div>

                    <div className="flex gap-8">
                        <div className="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-blue-600 text-3xl shrink-0">
                            <i className="fa-solid fa-phone"></i>
                        </div>
                        <div>
                            <span className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Телефон / WhatsApp</span>
                            <p className="text-2xl font-black text-slate-800 leading-tight">+7 (727) 345-67-89<br />+7 (701) 987-65-43</p>
                        </div>
                    </div>

                    <div className="flex gap-8">
                        <div className="w-20 h-20 bg-white rounded-3xl shadow-xl flex items-center justify-center text-blue-600 text-3xl shrink-0">
                            <i className="fa-solid fa-envelope-open-text"></i>
                        </div>
                        <div>
                            <span className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Эл. почта</span>
                            <p className="text-2xl font-black text-slate-800 leading-tight">go@globalquest.kz</p>
                        </div>
                    </div>
                </div>

                <div className="mt-12 pt-12 border-t border-slate-200 flex gap-6">
                    <a href="https://instagram.com" className="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all shadow-md text-2xl"><i className="fa-brands fa-instagram"></i></a>
                    <a href="https://telegram.org" className="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all shadow-md text-2xl"><i className="fa-brands fa-telegram"></i></a>
                    <a href="https://wa.me/77019876543" className="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all shadow-md text-2xl"><i className="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
          </div>

          <div className="reveal">
            <div className="bg-slate-900 p-12 rounded-[3.5rem] text-white h-full shadow-2xl">
                <h3 className="text-3xl font-black mb-8">Напишите нам</h3>
                <form className="space-y-6" onSubmit={e => e.preventDefault()}>
                    <div className="space-y-2">
                        <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Ваше полное имя</label>
                        <input type="text" className="w-full px-6 py-5 rounded-2xl bg-white/5 border border-white/10 focus:bg-white/10 focus:border-blue-500 transition-all outline-none font-bold" placeholder="Иван Иванов" />
                    </div>
                    <div className="space-y-2">
                        <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Контактный Email</label>
                        <input type="email" className="w-full px-6 py-5 rounded-2xl bg-white/5 border border-white/10 focus:bg-white/10 focus:border-blue-500 transition-all outline-none font-bold" placeholder="example@mail.kz" />
                    </div>
                    <div className="space-y-2">
                        <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Ваше сообщение</label>
                        <textarea className="w-full px-6 py-5 rounded-3xl bg-white/5 border border-white/10 focus:bg-white/10 focus:border-blue-500 transition-all outline-none font-bold min-h-[160px] resize-none" placeholder="Опишите ваши пожелания..."></textarea>
                    </div>
                    <button className="w-full py-6 bg-blue-600 hover:bg-white hover:text-blue-600 rounded-2xl font-black text-xl transition-all shadow-xl shadow-blue-500/20">Отправить Запрос</button>
                </form>
            </div>
          </div>
        </div>

        {/* Full Map Section */}
        <div className="mt-24 h-[600px] rounded-[4rem] overflow-hidden border-[10px] border-slate-50 shadow-3xl reveal">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2906.7725227787994!2d76.8920953!3d43.2352233!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38836ec2619a9a35%3A0x6758509890f5757b!2z0L_RgNC-0YHQvy4g0JDQsdCw0Y8gMTY1LzIsINCQ0LvQvNCw0YLRiyAwNTAwMDA!5e0!3m2!1sru!2skz!4v1710000000000!5m2!1sru!2skz" 
                width="100%" 
                height="100%" 
                style={{ border: 0 }} 
                allowFullScreen={true} 
                loading="lazy" 
                referrerPolicy="no-referrer-when-downgrade"
            ></iframe>
        </div>
      </div>
    </div>
  );
};

export default ContactPage;

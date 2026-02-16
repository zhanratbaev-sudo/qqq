
import { Tour, PartnerStat, Testimony } from './types';

export const TURKEY_TOURS: Tour[] = [
  { id: 'tr1', title: 'Магия Стамбула', location: 'Турция', price: 580000, rating: 4.8, image: 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&q=80&w=800', description: 'Окунитесь в историческое сердце Турции.' },
  { id: 'tr2', title: 'Шары Каппадокии', location: 'Турция', price: 720000, rating: 4.9, image: 'https://images.unsplash.com/photo-1621259182978-f09e5e2ca1ff?auto=format&fit=crop&q=80&w=800', description: 'Виды на горизонт, не имеющие равных.' },
  { id: 'tr3', title: 'Курорты Антальи', location: 'Турция', price: 430000, rating: 4.7, image: 'https://images.unsplash.com/photo-15420518418c7-a169b5087c55?auto=format&fit=crop&q=80&w=800', description: 'Кристально чистая вода и роскошь.' },
  { id: 'tr4', title: 'Белые скалы Памуккале', location: 'Турция', price: 520000, rating: 4.6, image: 'https://images.unsplash.com/photo-1527838832702-585f23df82a7?auto=format&fit=crop&q=80&w=800', description: 'Термальные воды и древние руины.' },
  { id: 'tr5', title: 'Ночи Бодрума', location: 'Турция', price: 620000, rating: 4.8, image: 'https://images.unsplash.com/photo-1512100356956-c1224c636a0d?auto=format&fit=crop&q=80&w=800', description: 'Элитная ночная жизнь и яхты.' },
];

export const EGYPT_TOURS: Tour[] = [
  { id: 'eg1', title: 'Великие пирамиды', location: 'Египет', price: 400000, rating: 4.9, image: 'https://images.unsplash.com/photo-1503177119275-0aa32b3a9368?auto=format&fit=crop&q=80&w=800', description: 'Последнее сохранившееся чудо света.' },
  { id: 'eg2', title: 'Круиз по Нилу', location: 'Египет', price: 530000, rating: 4.8, image: 'https://images.unsplash.com/photo-1539650116574-8efeb43e2750?auto=format&fit=crop&q=80&w=800', description: 'Проплывите сквозь историю по Нилу.' },
  { id: 'eg3', title: 'Шарм-эль-Шейх', location: 'Египет', price: 460000, rating: 4.7, image: 'https://images.unsplash.com/photo-1518391846015-55a9cb00bb86?auto=format&fit=crop&q=80&w=800', description: 'Дайвинг мирового класса и солнце Красного моря.' },
  { id: 'eg4', title: 'Луксорский храм', location: 'Египет', price: 340000, rating: 4.6, image: 'https://images.unsplash.com/photo-1568503504139-bfdc551bd744?auto=format&fit=crop&q=80&w=800', description: 'Музей под открытым небом древних богов.' },
  { id: 'eg5', title: 'Спокойствие Асуана', location: 'Египет', price: 380000, rating: 4.5, image: 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?auto=format&fit=crop&q=80&w=800', description: 'Отдохните у водопадов высокой плотины.' },
];

export const PARTNER_STATS: PartnerStat[] = [
  { month: 'Янв', tours: 450, clients: 1200 },
  { month: 'Фев', tours: 520, clients: 1450 },
  { month: 'Мар', tours: 610, clients: 1800 },
  { month: 'Апр', tours: 580, clients: 1650 },
  { month: 'Май', tours: 740, clients: 2100 },
  { month: 'Июн', tours: 920, clients: 2800 },
];

export const TESTIMONIALS_ROW_1: Testimony[] = [
  { id: '1', name: 'Сара Дж.', role: 'Исследователь', content: 'Тур в Турцию превзошел все мои ожидания. Все было идеально!', avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=100' },
  { id: '3', name: 'Елена К.', role: 'Блогер', content: 'Стамбул покорил моё сердце. Отличная организация.', avatar: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&q=80&w=100' },
  { id: '4', name: 'Дмитрий С.', role: 'Альпинист', content: 'Поход в Каппадокии был незабываемым.', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=100' },
  { id: '5', name: 'Анна М.', role: 'Йога-инструктор', content: 'Нашла здесь настоящий покой в Памуккале.', avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100' },
  { id: '6', name: 'Артем Л.', role: 'Инженер', content: 'Все четко по графику, без задержек.', avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=100' },
];

export const TESTIMONIALS_ROW_2: Testimony[] = [
  { id: '2', name: 'Марко В.', role: 'Фотограф', content: 'Партнерство открыло новые горизонты для моего бизнеса.', avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=100' },
  { id: '7', name: 'Виктор П.', role: 'Историк', content: 'Гиды в Египте знают свое дело. Очень познавательно.', avatar: 'https://images.unsplash.com/photo-1552058544-f2b08422138a?auto=format&fit=crop&q=80&w=100' },
  { id: '8', name: 'Ольга Р.', role: 'Дизайнер', content: 'Цвета Красного моря — это нечто невероятное!', avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100' },
  { id: '9', name: 'Сергей Т.', role: 'Дайвер', content: 'Шарм-эль-Шейх — лучший дайвинг в моей жизни.', avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=100' },
  { id: '10', name: 'Мария Ф.', role: 'Писатель', content: 'Вдохновение нашла именно в круизе по Нилу.', avatar: 'https://images.unsplash.com/photo-1531746020798-e795c5399c47?auto=format&fit=crop&q=80&w=100' },
];

export const SHUFFLE_IMAGES = [
  'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&q=80&w=800',
  'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&q=80&w=800',
  'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&q=80&w=800',
  'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&q=80&w=800',
  'https://images.unsplash.com/photo-1493246507139-91e8fad9978e?auto=format&fit=crop&q=80&w=800'
];


export enum UserRole {
  GUEST = 'GUEST',
  TRAVELER = 'TRAVELER',
  PARTNER = 'PARTNER'
}

export interface PartnerData {
  companyName: string;
  currency: string;
}

export interface Tour {
  id: string;
  title: string;
  location: string;
  price: number; // In KZT
  rating: number;
  image: string;
  description: string;
}

export interface PartnerStat {
  month: string;
  tours: number;
  clients: number;
}

export interface Testimony {
  id: string;
  name: string;
  role: string;
  content: string;
  avatar: string;
}

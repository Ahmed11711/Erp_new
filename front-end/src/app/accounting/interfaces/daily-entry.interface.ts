export interface DailyEntryItem {
  id?: number;
  account_id: number;
  debit: number;
  credit: number;
  notes?: string;
  account?: {
    id: number;
    name: string;
    code?: number;
  };
}

export interface DailyEntry {
  id?: number;
  date: string;
  entry_number?: string;
  description?: string;
  user_id?: number;
  user?: {
    id: number;
    name: string;
  };
  items?: DailyEntryItem[];
  created_at?: string;
  updated_at?: string;
}

export interface DailyEntryResponse {
  current_page?: number;
  data: DailyEntry[];
  first_page_url?: string;
  from?: number;
  last_page?: number;
  last_page_url?: string;
  links?: any[];
  next_page_url?: string;
  path?: string;
  per_page?: number;
  prev_page_url?: string;
  to?: number;
  total?: number;
}

export interface DailyEntryFormData {
  date: string;
  description?: string;
  items: DailyEntryItem[];
}


export interface CostCenter {
  id?: number;
  name: string;
  name_en?: string;
  code?: number;
  type: 'main' | 'sub';
  parent_id?: number;
  responsible_person_id?: number;
  location?: string;
  phone?: string;
  email?: string;
  start_date?: string;
  end_date?: string;
  duration?: string;
  value?: number;
  parent?: {
    id: number;
    name: string;
    code: number;
  };
  responsiblePerson?: {
    id: number;
    name: string;
  };
  children?: CostCenter[];
  created_at?: string;
  updated_at?: string;
}

export interface CostCenterResponse {
  success?: boolean;
  status?: number;
  message?: string;
  data: CostCenter | CostCenter[];
  current_page?: number;
  per_page?: number;
  total?: number;
  last_page?: number;
}


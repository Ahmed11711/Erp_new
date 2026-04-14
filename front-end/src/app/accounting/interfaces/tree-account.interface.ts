export interface TreeAccount {
  id?: number;
  name: string;
  name_en?: string;
  code?: number;
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense' | 'settlement';
  account_type?: 'رئيسي' | 'فرعي' | 'مستوى أول';
  budget_type?: string;
  budget_amount?: number;
  budget_period?: 'yearly' | 'monthly';
  is_trading_account?: boolean;
  level?: number;
  balance?: number;
  debit_balance?: number;
  credit_balance?: number;
  previous_year_amount?: string;
  total_balance?: number;
  parent_id?: number;
  main_account_id?: number;
  parent?: {
    id: number;
    name: string;
    code: number;
  };
  main_account?: {
    id: number;
    name: string;
    code: number;
  };
  children?: TreeAccount[];
  safes?: {
    id: number;
    name: string;
    balance: number;
    type: string;
    is_inside_branch: boolean;
    branch_name: string;
  }[];
  detail_type?: string;
  created_at?: string;
  updated_at?: string;
}

export interface TreeAccountResponse {
  success: boolean;
  status: number;
  message: string;
  data: TreeAccount[];
}


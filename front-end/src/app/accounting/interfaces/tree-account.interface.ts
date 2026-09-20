export interface TreeAccountUserRef {
  id: number;
  name: string;
}

export interface TreeAccountAuditEntry {
  id: number;
  tree_account_id?: number | null;
  action: 'created' | 'updated' | 'deleted' | 'restored' | 'force_deleted';
  action_label?: string;
  performed_by?: number | null;
  performer?: TreeAccountUserRef | null;
  account_code?: string;
  account_name?: string;
  parent_id?: number | null;
  changes?: Record<string, { old: unknown; new: unknown }>;
  created_at?: string;
}

export interface TreeAccount {
  id?: number;
  name: string;
  name_en?: string;
  code?: number;
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense' | 'settlement';
  /** @deprecated قديم — لا يُستخدم؛ الدور يُستنتج من الشجرة (جذر / مجموعة / تفصيلي) */
  account_type?: string | null;
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
  created_by?: number | null;
  updated_by?: number | null;
  created_by_user?: TreeAccountUserRef | null;
  updated_by_user?: TreeAccountUserRef | null;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string;
}

export interface TreeAccountResponse {
  success: boolean;
  status: number;
  message: string;
  data: TreeAccount[];
}


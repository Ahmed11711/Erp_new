export interface CashClientCompanyOption {
  id: number;
  label: string;
  balance?: number;
}

export interface CashClientIndividualOption {
  phone: string;
  name: string;
  label: string;
}

export interface CashClientOptionsResponse {
  companies: CashClientCompanyOption[];
  individuals: CashClientIndividualOption[];
}

export function companyClientKey(id: number): string {
  return `company:${id}`;
}

export function individualClientKey(phone: string): string {
  return `individual:${phone}`;
}

export function buildClientVoucherFields(
  selectedKey: string | null,
  options: CashClientOptionsResponse,
): Record<string, unknown> {
  if (!selectedKey) {
    return {
      client_kind: null,
      client_id: null,
      individual_customer_phone: null,
      individual_customer_name: null,
    };
  }

  if (selectedKey.startsWith('company:')) {
    const id = Number(selectedKey.slice('company:'.length));
    const company = options.companies.find((c) => c.id === id);

    return {
      client_kind: 'company',
      client_id: id,
      individual_customer_phone: null,
      individual_customer_name: null,
      client_or_supplier_name: company?.label ?? null,
    };
  }

  if (selectedKey.startsWith('individual:')) {
    const phone = selectedKey.slice('individual:'.length);
    const person = options.individuals.find((p) => p.phone === phone);

    return {
      client_kind: 'individual',
      client_id: null,
      individual_customer_phone: phone,
      individual_customer_name: person?.name ?? '',
      client_or_supplier_name: person?.label ?? phone,
    };
  }

  return {
    client_kind: null,
    client_id: null,
    individual_customer_phone: null,
    individual_customer_name: null,
  };
}

export function resolveClientSelectionKey(voucher: {
  client_kind?: string | null;
  client_id?: number | null;
  individual_customer_phone?: string | null;
}): string | null {
  if (voucher.client_kind === 'individual' || (!voucher.client_id && voucher.individual_customer_phone)) {
    return individualClientKey(String(voucher.individual_customer_phone));
  }

  if (voucher.client_id) {
    return companyClientKey(Number(voucher.client_id));
  }

  return null;
}

export function formatVoucherClientPartyName(voucher: any): string {
  if (voucher?.client_kind === 'individual' || (!voucher?.client_id && voucher?.individual_customer_phone)) {
    const name = voucher?.individual_customer_name || voucher?.client_or_supplier_name || '';
    const phone = voucher?.individual_customer_phone || '';

    return name && phone ? `${name} — ${phone}` : (name || phone || '—');
  }

  return voucher?.client?.name
    || voucher?.client?.company_name
    || voucher?.client_or_supplier_name
    || '—';
}

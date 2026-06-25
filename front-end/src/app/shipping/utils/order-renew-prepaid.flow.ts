import Swal from 'sweetalert2';

export type PrepaidReturnResult = {
  returnedStatus?: string;
  returnedBank?: string | number;
};

export type PrepaidPaymentSources = {
  banks: { id: number; name: string }[];
  safes: { id: number; name: string }[];
  serviceAccounts: { id: number; name: string }[];
  collectionCompanies: { id: number; name: string }[];
};

type OrderPrepaidContext = {
  prepaid_amount?: number;
  bank_id?: number;
  prepaid_payment_type?: string | null;
  order_details?: {
    collection_provider_type?: string | null;
  } | null;
};

type RenewPrepaidFormValues = {
  renewAmount: string;
  paymentType: string;
  bankId?: string;
  safeId?: string;
  serviceAccountId?: string;
  collectionCompanyId?: string;
};

/** مديونية على شركة تحصيل (Shopify/Paymob) — لا يُطلب اختيار خزينة عند الإلغاء */
export function isPrepaidHeldByCollectionIntermediary(order: OrderPrepaidContext): boolean {
  const prepaid = parseFloat(String(order?.prepaid_amount)) || 0;
  if (prepaid <= 0.009) {
    return false;
  }
  const paymentType = String(order?.prepaid_payment_type ?? '').trim();
  if (['bank', 'safe', 'service_account'].includes(paymentType)) {
    return false;
  }
  return order?.order_details?.collection_provider_type === 'collection_company';
}

export function requiresManualPrepaidRefundSelection(order: OrderPrepaidContext): boolean {
  const prepaid = parseFloat(String(order?.prepaid_amount)) || 0;
  if (prepaid <= 0.009) {
    return false;
  }
  if (isPrepaidHeldByCollectionIntermediary(order)) {
    return false;
  }
  const paymentType = String(order?.prepaid_payment_type ?? '').trim();
  if (['bank', 'safe', 'service_account'].includes(paymentType)) {
    return false;
  }
  // مصدر الدفع مسجّل مسبقاً — الخادم يعكس الحركة تلقائياً
  if (order?.bank_id) {
    return false;
  }
  return ['', 'pending', 'none'].includes(paymentType);
}

function buildSelectOptions(items: { id: number; name: string }[], placeholder: string): string {
  const head = `<option value="" disabled selected>${placeholder}</option>`;
  const body = items.map((item) => `<option value="${item.id}">${item.name}</option>`).join('');
  return head + body;
}

function wireRenewPrepaidSourcePanels(): void {
  const paymentTypeEl = document.getElementById('swal-input-paymentType') as HTMLSelectElement | null;
  if (!paymentTypeEl) {
    return;
  }

  const panels: Record<string, string> = {
    bank: 'swal-source-bank',
    safe: 'swal-source-safe',
    service_account: 'swal-source-service',
    collection_company: 'swal-source-collection',
  };

  const syncPanels = () => {
    const selected = paymentTypeEl.value;
    Object.entries(panels).forEach(([type, panelId]) => {
      const panel = document.getElementById(panelId);
      if (panel) {
        panel.classList.toggle('d-none', selected !== type);
      }
    });
    const hint = document.getElementById('swal-prepaid-hint');
    if (hint) {
      hint.classList.toggle('d-none', selected !== 'pending');
    }
  };

  paymentTypeEl.addEventListener('change', syncPanels);
  syncPanels();
}

function validateRenewPrepaidForm(values: RenewPrepaidFormValues): string | null {
  const amount = parseFloat(values.renewAmount);
  if (!values.renewAmount || !Number.isFinite(amount) || amount <= 0) {
    return 'أدخل مبلغاً أكبر من صفر';
  }

  switch (values.paymentType) {
    case 'bank':
      if (!values.bankId) {
        return 'اختر البنك';
      }
      break;
    case 'safe':
      if (!values.safeId) {
        return 'اختر الخزينة';
      }
      break;
    case 'service_account':
      if (!values.serviceAccountId) {
        return 'اختر الحساب الخدمي';
      }
      break;
    case 'collection_company':
      if (!values.collectionCompanyId) {
        return 'اختر شركة التحصيل';
      }
      break;
    case 'pending':
      break;
    default:
      return 'اختر مصدر الدفع';
  }

  return null;
}

function mapRenewPrepaidFormToParams(values: RenewPrepaidFormValues): Record<string, string | number> {
  const param: Record<string, string | number> = {
    renewAmount: parseFloat(values.renewAmount),
    renewPaymentType: values.paymentType,
  };

  if (values.paymentType === 'bank' && values.bankId) {
    param['renewBankId'] = values.bankId;
  } else if (values.paymentType === 'safe' && values.safeId) {
    param['renewSafeId'] = values.safeId;
  } else if (values.paymentType === 'service_account' && values.serviceAccountId) {
    param['renewServiceAccountId'] = values.serviceAccountId;
  } else if (values.paymentType === 'collection_company' && values.collectionCompanyId) {
    param['renewCollectionCompanyId'] = values.collectionCompanyId;
  }

  return param;
}

/** إرجاع مبلغ تحت الحساب (خزينة + قيد انتظار) */
export async function promptReturnPrepaidAmount(
  order: { prepaid_amount?: number; bank_id?: number },
  banks: { id: number; name: string }[]
): Promise<PrepaidReturnResult> {
  const bankSelectOptions = banks.reduce((options: Record<string, string>, bank) => {
    options[String(bank.id)] = bank.name;
    return options;
  }, {});

  const data: PrepaidReturnResult = {};
  await Swal.fire({
    title: ' إرجاع مبلغ تحت الحساب ' + order.prepaid_amount,
    input: 'select',
    inputOptions: bankSelectOptions,
    inputPlaceholder: 'اختر الخزينة',
    inputValue: order.bank_id as unknown as string,
    showCancelButton: true,
    confirmButtonText: 'تأكيد',
    cancelButtonText: 'قيد الانتظار',
    customClass: { input: 'text-center' },
  }).then((result) => {
    if (result.isConfirmed && result.value) {
      data.returnedStatus = 'approved';
      data.returnedBank = result.value;
    } else if (result.dismiss === Swal.DismissReason.cancel) {
      data.returnedStatus = 'pending';
    }
  });
  return data;
}

export async function promptRenewPrepaidAmount(
  sources: PrepaidPaymentSources
): Promise<Record<string, string | number>> {
  const data: Record<string, string | number> = {};
  const { value: formValues } = await Swal.fire({
    title: ' ادخال مبلغ تحت الحساب ',
    html: `
      <div class="row w-100 m-auto text-start">
        <div class="col-md-12 mb-2">
          <label class="form-label small mb-1">المبلغ</label>
          <input id="swal-input-renewAmount" class="form-control text-center" placeholder="المبلغ" type="number" min="0" step="0.01">
        </div>
        <div class="col-md-12 mb-2">
          <label class="form-label small mb-1">مصدر الدفع</label>
          <select id="swal-input-paymentType" class="form-control text-center bg-main">
            <option value="pending">بانتظار التسجيل المحاسبي</option>
            <option value="bank">بنك</option>
            <option value="safe">خزينة</option>
            <option value="service_account">حساب خدمي</option>
            <option value="collection_company">مديونية على شركة تحصيل</option>
          </select>
        </div>
        <div id="swal-source-bank" class="col-md-12 mb-2 d-none">
          <label class="form-label small mb-1">البنك</label>
          <select id="swal-input-bank" class="form-control text-center bg-main">
            ${buildSelectOptions(sources.banks, 'اختر البنك')}
          </select>
        </div>
        <div id="swal-source-safe" class="col-md-12 mb-2 d-none">
          <label class="form-label small mb-1">الخزينة</label>
          <select id="swal-input-safe" class="form-control text-center bg-main">
            ${buildSelectOptions(sources.safes, 'اختر الخزينة')}
          </select>
        </div>
        <div id="swal-source-service" class="col-md-12 mb-2 d-none">
          <label class="form-label small mb-1">الحساب الخدمي</label>
          <select id="swal-input-service" class="form-control text-center bg-main">
            ${buildSelectOptions(sources.serviceAccounts, 'اختر الحساب الخدمي')}
          </select>
        </div>
        <div id="swal-source-collection" class="col-md-12 mb-2 d-none">
          <label class="form-label small mb-1">شركة التحصيل</label>
          <select id="swal-input-collection" class="form-control text-center bg-main">
            ${buildSelectOptions(sources.collectionCompanies, 'اختر شركة التحصيل')}
          </select>
          <small class="text-muted d-block mt-1">للطلبات الإلكترونية (Shopify / أونلاين) — المبلغ يُسجَّل مديونية على الشركة حتى التسوية</small>
        </div>
        <div id="swal-prepaid-hint" class="col-12">
          <div class="alert alert-info py-2 mb-0 small">
            يُسجَّل المبلغ تحت الحساب دون قيد بنك/خزينة — يمكن تحديد المصدر لاحقاً من المحاسبة.
          </div>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'تأكيد',
    cancelButtonText: 'الغاء',
    didOpen: wireRenewPrepaidSourcePanels,
    preConfirm: () => {
      const renewAmount = (document.getElementById('swal-input-renewAmount') as HTMLInputElement)?.value ?? '';
      const paymentType = (document.getElementById('swal-input-paymentType') as HTMLSelectElement)?.value ?? '';
      const values: RenewPrepaidFormValues = {
        renewAmount,
        paymentType,
        bankId: (document.getElementById('swal-input-bank') as HTMLSelectElement)?.value,
        safeId: (document.getElementById('swal-input-safe') as HTMLSelectElement)?.value,
        serviceAccountId: (document.getElementById('swal-input-service') as HTMLSelectElement)?.value,
        collectionCompanyId: (document.getElementById('swal-input-collection') as HTMLSelectElement)?.value,
      };

      const validationError = validateRenewPrepaidForm(values);
      if (validationError) {
        Swal.showValidationMessage(validationError);
        return false;
      }

      return values;
    },
  });

  if (formValues) {
    Object.assign(data, mapRenewPrepaidFormToParams(formValues as RenewPrepaidFormValues));
  }
  return data;
}

/** منطق تجديد الطلب مع مبلغ تحت الحساب */
export async function collectRenewPrepaidParams(
  order: { prepaid_amount?: number },
  sources: PrepaidPaymentSources
): Promise<Record<string, string | number> | null> {
  const param: Record<string, string | number> = { prepaidPolicy: 'none' };
  const existing = parseFloat(String(order?.prepaid_amount)) || 0;

  if (existing > 0.009) {
    const choice = await Swal.fire({
      title: `مبلغ تحت الحساب الحالي: ${existing}`,
      text: 'كيف تريد التعامل مع الدفعة قبل تجديد الطلب؟',
      icon: 'question',
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'إبقاء على الطلب المجدد',
      denyButtonText: 'إرجاع للعميل',
      cancelButtonText: 'إلغاء',
    });

    if (choice.isDismissed) {
      return null;
    }
    if (choice.isConfirmed) {
      param['prepaidPolicy'] = 'keep';
      return param;
    }
    if (choice.isDenied) {
      const returnPaidMoney = await promptReturnPrepaidAmount(order, sources.banks);
      if (Object.keys(returnPaidMoney).length === 0) {
        return null;
      }
      param['prepaidPolicy'] = 'return';
      param['moneyReturnedStatus'] = returnPaidMoney.returnedStatus as string;
      if (returnPaidMoney.returnedStatus === 'approved' && returnPaidMoney.returnedBank != null) {
        param['moneyReturnedBank'] = returnPaidMoney.returnedBank;
      }
    }
  }

  if (param['prepaidPolicy'] === 'keep') {
    return param;
  }

  const askNew = await Swal.fire({
    title: 'هل يوجد مبلغ تحت الحساب للطلب المجدد؟',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'نعم',
    cancelButtonText: 'لا',
  });

  // «لا» = بدون دفعة جديدة — نُكمل التجديد. الإلغاء فقط عند ESC أو النقر خارج الحوار.
  if (askNew.isDismissed && askNew.dismiss !== Swal.DismissReason.cancel) {
    return null;
  }

  if (askNew.isConfirmed) {
    const prepaidAmountData = await promptRenewPrepaidAmount(sources);
    if (Object.keys(prepaidAmountData).length === 0) {
      return null;
    }
    const amt = parseFloat(String(prepaidAmountData['renewAmount']));
    const paymentType = String(prepaidAmountData['renewPaymentType'] ?? '');
    if (!amt || amt <= 0 || !paymentType) {
      await Swal.fire({ icon: 'warning', title: 'أدخل مبلغاً ومصدر دفع صحيحين' });
      return null;
    }
    Object.assign(param, prepaidAmountData);
  }

  return param;
}

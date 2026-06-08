import Swal from 'sweetalert2';

export type PrepaidReturnResult = {
  returnedStatus?: string;
  returnedBank?: string | number;
};

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
  banks: { id: number; name: string }[]
): Promise<Record<string, string | number>> {
  let options = '';
  banks.forEach((elm) => {
    options += `<option value="${elm.id}">${elm.name}</option>`;
  });

  const data: Record<string, string | number> = {};
  const { value: formValues } = await Swal.fire({
    title: ' ادخال مبلغ تحت الحساب ',
    html: `
      <div class="row w-100 m-auto">
        <div class="col-md-12">
          <div class="form-group">
            <input id="swal-input-renewAmount" class="form-control text-center" placeholder="المبلغ" type="number" min="0" step="0.01">
          </div>
        </div>
        <div class="col-md-12">
          <div class="form-group">
            <select id="swal-input-bank" class="form-control text-center bg-main">
              <option value="" disabled selected>اختر الخزينة</option>
              ${options}
            </select>
          </div>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'تأكيد',
    cancelButtonText: 'الغاء',
    preConfirm: () => {
      const renewAmount = document.getElementById('swal-input-renewAmount') as HTMLInputElement;
      const selectedBankId = document.getElementById('swal-input-bank') as HTMLSelectElement;
      if (!renewAmount?.value || parseFloat(renewAmount.value) <= 0) {
        Swal.showValidationMessage('أدخل مبلغاً أكبر من صفر');
        return false;
      }
      if (!selectedBankId?.value) {
        Swal.showValidationMessage('اختر الخزينة');
        return false;
      }
      return { renewAmount: renewAmount.value, selectedBankId: selectedBankId.value };
    },
  });

  if (formValues) {
    data['renewAmount'] = formValues.renewAmount;
    data['renewBankId'] = formValues.selectedBankId;
  }
  return data;
}

/** منطق تجديد الطلب مع مبلغ تحت الحساب */
export async function collectRenewPrepaidParams(
  order: { prepaid_amount?: number },
  banks: { id: number; name: string }[]
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
      const returnPaidMoney = await promptReturnPrepaidAmount(order, banks);
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

  if (askNew.isDismissed) {
    return null;
  }

  if (askNew.isConfirmed) {
    const prepaidAmountData = await promptRenewPrepaidAmount(banks);
    if (Object.keys(prepaidAmountData).length === 0) {
      return null;
    }
    const amt = parseFloat(String(prepaidAmountData['renewAmount']));
    const bankId = prepaidAmountData['renewBankId'];
    if (!amt || amt <= 0 || bankId == null || bankId === '') {
      await Swal.fire({ icon: 'warning', title: 'أدخل مبلغاً وخزينة صحيحين' });
      return null;
    }
    param['renewAmount'] = amt;
    param['renewBankId'] = bankId;
  }

  return param;
}

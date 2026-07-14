/** بصمات من 00:00 حتى قبل هذه الساعة تُعتبر انصرافاً لدوام الليلة السابقة */
export const OVERNIGHT_CHECKOUT_CUTOFF_HOUR = 8;

/** أيام العمل الشهرية — 26 يوم × 8 س = 208 ، 26 × 9 س = 234 */
export const WORKING_DAYS_PER_MONTH = 26;

/** مضاعف الإضافي والخصم: (الراتب ÷ 208 أو 234) × 1.5 × الساعات */
export const OVERTIME_DEDUCTION_MULTIPLIER = 1.5;

/** سعر الساعة الأساسي = الراتب ÷ (26 × ساعات اليوم) */
export function baseHourPrice(fixedSalary: number, dayHours: number): number {
  return fixedSalary / (WORKING_DAYS_PER_MONTH * dayHours);
}

/** معدل الساعة للإضافي/الخصم = (الراتب ÷ 208 أو 234) × 1.5 */
export function overtimeDeductionRate(fixedSalary: number, dayHours: number): number {
  return baseHourPrice(fixedSalary, dayHours) * OVERTIME_DEDUCTION_MULTIPLIER;
}

/** أيام العمل لزر «يوم إضافي» فقط — 30 يوم */
export const EXTRA_DAY_WORKING_DAYS = 30;

/** مبلغ يوم إضافي (زر الإضافة فقط) = (الراتب ÷ 30 ÷ ساعات اليوم) × 1.5 × ساعات اليوم */
export function extraDayMeritAmountAction(fixedSalary: number, dayHours: number): number {
  const hourlyBase = fixedSalary / (EXTRA_DAY_WORKING_DAYS * dayHours);
  return dayHours * hourlyBase * OVERTIME_DEDUCTION_MULTIPLIER;
}

/** مبلغ يوم إضافي (26 يوم — للمرجعية الداخلية) */
export function extraDayMeritAmount(fixedSalary: number, dayHours: number): number {
  return dayHours * overtimeDeductionRate(fixedSalary, dayHours);
}

export function monthlyHoursDivisor(dayHours: number): number {
  return WORKING_DAYS_PER_MONTH * dayHours;
}

/** مضاعف الخصم: 1.5 افتراضياً أو absence_deduction عند الغياب بدون إذن */
export function deductionMultiplier(absenceDeduction?: number | string | null): number {
  if (absenceDeduction != null && absenceDeduction !== '') {
    return Number(absenceDeduction);
  }
  return OVERTIME_DEDUCTION_MULTIPLIER;
}

export function convertMinutesToHours(minutes: number): string {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

export function parseTimesArray(times: unknown): string[] {
  if (Array.isArray(times)) {
    return times.filter(Boolean).map(String);
  }
  if (typeof times === 'string') {
    try {
      const parsed = JSON.parse(times.replace(/\\/g, ''));
      return Array.isArray(parsed) ? parsed.filter(Boolean).map(String) : [];
    } catch {
      return [];
    }
  }
  return [];
}

export function punchHour24(isoDate: string): number {
  return new Date(isoDate).getHours();
}

export function hoursBetweenFirstAndLastPunch(isoDates: string[]): string | null {
  if (isoDates.length < 2) {
    return null;
  }
  const stamps = isoDates.map(d => new Date(d).getTime()).filter(t => !Number.isNaN(t));
  if (stamps.length < 2) {
    return null;
  }
  const diffMin = (Math.max(...stamps) - Math.min(...stamps)) / 60000;
  return convertMinutesToHours(Math.max(diffMin, 0));
}

export function hoursFromTimeInOut(
  timeIn: string | null | undefined,
  timeOut: string | null | undefined
): string | null {
  if (!timeIn || !timeOut) {
    return null;
  }
  const inDate = new Date(timeIn);
  let outDate = new Date(timeOut);
  if (Number.isNaN(inDate.getTime()) || Number.isNaN(outDate.getTime())) {
    return null;
  }
  if (outDate.getTime() <= inDate.getTime()) {
    outDate = new Date(outDate);
    outDate.setDate(outDate.getDate() + 1);
  }
  const diffMin = (outDate.getTime() - inDate.getTime()) / 60000;
  if (diffMin <= 0) {
    return null;
  }
  return convertMinutesToHours(diffMin);
}

export function hoursFromCheckInOutStrings(
  dateStr: string,
  checkIn12h: string,
  checkOut12h: string
): string | null {
  const checkInDate = parseLocalDateTime(dateStr, checkIn12h);
  const checkOutDate = resolveCheckOutDate(dateStr, checkIn12h, checkOut12h);
  if (!checkInDate || !checkOutDate) {
    return null;
  }
  const diffMin = diffMsBetween(checkInDate, checkOutDate) / 60000;
  return diffMin > 0 ? convertMinutesToHours(diffMin) : null;
}

/** يعيد حساب ساعات اليوم — check_in/check_out له الأولوية بعد التعديل اليدوي */
export function resolveWorkDayHours(record: {
  date?: string;
  check_in?: string;
  check_out?: string;
  hours?: string;
  times?: unknown;
  time_in?: string;
  time_out?: string;
  hours_permission?: string | null;
  working_hours?: number;
}): string {
  const dayHours = record.working_hours ?? 8;
  if (isFullDayPermission(record.hours_permission, dayHours)) {
    return convertMinutesToHours(dayHours * 60);
  }

  if (
    record.date
    && record.check_in
    && record.check_out
    && record.check_in !== record.check_out
  ) {
    const fromCheck = hoursFromCheckInOutStrings(record.date, record.check_in, record.check_out);
    if (fromCheck) {
      return fromCheck;
    }
  }

  const fromInOut = hoursFromTimeInOut(record.time_in, record.time_out);
  if (fromInOut) {
    return fromInOut;
  }

  const times = parseTimesArray(record.times);
  const fromTimes = hoursBetweenFirstAndLastPunch(times);
  if (fromTimes) {
    return fromTimes;
  }

  return record.hours || '00:00';
}

export function formatTime12h(isoDate: string): string {
  const d = new Date(isoDate);
  if (Number.isNaN(d.getTime())) {
    return '';
  }
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
}

/** تحويل تاريخ اليوم + وقت 12 ساعة إلى Date محلي بدون مشاكل UTC */
export function parseLocalDateTime(dateStr: string, time12h: string): Date | null {
  if (!dateStr || !time12h) {
    return null;
  }

  const parts = time12h.trim().split(/\s+/);
  const timePart = parts[0] ?? '';
  const period = (parts[1] ?? '').toUpperCase();
  const [hourRaw, minuteRaw] = timePart.split(':');
  let hour = Number(hourRaw);
  const minute = Number(minuteRaw ?? 0);

  if (Number.isNaN(hour) || Number.isNaN(minute)) {
    return null;
  }

  if (period === 'PM' && hour < 12) {
    hour += 12;
  } else if (period === 'AM' && hour === 12) {
    hour = 0;
  }

  const [y, m, d] = dateStr.split('-').map(Number);
  if ([y, m, d].some(n => Number.isNaN(n))) {
    return null;
  }

  return new Date(y, m - 1, d, hour, minute, 0);
}

export function toTimeInputValue(date: Date): string {
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

export function toDatetimeLocalValue(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${toTimeInputValue(date)}`;
}

/** يُرجع وقت الانصراف مع دعم العبور بعد منتصف الليل */
export function resolveCheckOutDate(dateStr: string, checkIn12h: string, checkOut12h: string): Date | null {
  const checkInDate = parseLocalDateTime(dateStr, checkIn12h);
  let checkOutDate = parseLocalDateTime(dateStr, checkOut12h);
  if (!checkInDate || !checkOutDate) {
    return null;
  }
  if (checkOutDate.getTime() <= checkInDate.getTime()) {
    checkOutDate = new Date(checkOutDate);
    checkOutDate.setDate(checkOutDate.getDate() + 1);
  }
  return checkOutDate;
}

export function parseDatetimeLocalValue(value: string): Date | null {
  if (!value) {
    return null;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function diffMsBetween(checkIn: Date, checkOut: Date): number {
  let end = new Date(checkOut);
  if (end.getTime() <= checkIn.getTime()) {
    end.setDate(end.getDate() + 1);
  }
  return end.getTime() - checkIn.getTime();
}

export function nextDateStr(dateStr: string): string {
  const [y, m, d] = dateStr.split('-').map(Number);
  const next = new Date(y, m - 1, d + 1);
  return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
}

function punchTimestamp(isoDate: string): number {
  return new Date(isoDate).getTime();
}

export function permissionMinutes(hoursPermission: string): number {
  const [h, m] = hoursPermission.split(':').map(Number);
  return (h || 0) * 60 + (m || 0);
}

export function isFullDayPermission(
  hoursPermission: string | null | undefined,
  dayHours: number
): boolean {
  if (!hoursPermission) {
    return false;
  }
  return permissionMinutes(hoursPermission) >= dayHours * 60;
}

export const DEFAULT_SHIFT_START = '08:00 AM';

/** وقت انصراف الدوام العادي حسب عدد ساعات اليوم (يبدأ 08:00 ص) */
export function defaultShiftCheckOut(dayHours: number): string {
  const endHour24 = 8 + dayHours;
  const period = endHour24 >= 12 ? 'PM' : 'AM';
  let h12 = endHour24 % 12;
  if (h12 === 0) {
    h12 = 12;
  }
  return `${String(h12).padStart(2, '0')}:00 ${period}`;
}

/** يضبط حضور/انصراف/ساعات يوم الإذن الكامل كدوام عادي */
export function applyNormalShiftTimes<T extends {
  check_in?: string;
  check_out?: string;
  hours?: string;
}>(record: T, dayHours: number): T {
  record.check_in = DEFAULT_SHIFT_START;
  record.check_out = defaultShiftCheckOut(dayHours);
  record.hours = convertMinutesToHours(dayHours * 60);
  return record;
}

/** حقول إضافية تُرسل للخادم عند حفظ إذن يوم كامل */
export function fullDayPermissionSavePayload(
  hoursPermission: string,
  dayHours: number
): { check_in: string; check_out: string; hours: string } | Record<string, never> {
  if (!isFullDayPermission(hoursPermission, dayHours)) {
    return {};
  }
  return {
    check_in: DEFAULT_SHIFT_START,
    check_out: defaultShiftCheckOut(dayHours),
    hours: convertMinutesToHours(dayHours * 60),
  };
}

function isAbsenceOrPermissionPlaceholder(record: {
  check_in?: string;
  check_out?: string;
  hours?: string;
  hours_permission?: string | null;
  working_hours?: number;
}): boolean {
  const dayHours = record.working_hours ?? 8;
  if (isFullDayPermission(record.hours_permission, dayHours)) {
    return true;
  }
  if (record.hours_permission && record.check_in === record.check_out) {
    return true;
  }
  return record.hours === '00:00'
    && !!record.check_in
    && !!record.check_out
    && record.check_in === record.check_out;
}

/** دمج بصمات ما بعد منتصف الليل (00:00–07:59) مع يوم الحضور السابق */
export function normalizeOvernightFingerPrintRecords<T extends {
  id?: number | null;
  date: string;
  times?: unknown;
  time_in?: string;
  time_out?: string;
  check_in?: string;
  check_out?: string;
  hours?: string;
  hours_permission?: string | null;
  working_hours?: number;
}>(records: T[]): T[] {
  if (!records.length) {
    return records;
  }

  const sorted = [...records].sort((a, b) => a.date.localeCompare(b.date));
  const byDate = new Map(sorted.map(r => [r.date, r]));
  const consumedDates = new Set<string>();

  for (const record of sorted) {
    if (isAbsenceOrPermissionPlaceholder(record)) {
      continue;
    }

    const nextDate = nextDateStr(record.date);
    const nextRecord = byDate.get(nextDate);
    if (!nextRecord || consumedDates.has(nextDate)) {
      continue;
    }

    const dayTimes = parseTimesArray(record.times);
    const dayIsoSources = dayTimes.length
      ? dayTimes
      : (record.time_in ? [record.time_in] : []);
    if (!dayIsoSources.length) {
      continue;
    }

    const nextTimes = parseTimesArray(nextRecord.times);
    const nextIsoSources = nextTimes.length
      ? nextTimes
      : (nextRecord.time_in ? [nextRecord.time_in] : []);

    const earlyNextIso: string[] = [];
    for (const iso of [...nextIsoSources].sort((a, b) => punchTimestamp(a) - punchTimestamp(b))) {
      if (punchHour24(iso) >= OVERNIGHT_CHECKOUT_CUTOFF_HOUR) {
        break;
      }
      earlyNextIso.push(iso);
    }

    if (!earlyNextIso.length) {
      continue;
    }

    const mergedTimes = [...new Set([...dayIsoSources, ...earlyNextIso])]
      .sort((a, b) => punchTimestamp(a) - punchTimestamp(b));

    record.times = mergedTimes;
    record.time_in = mergedTimes[0];
    record.time_out = mergedTimes[mergedTimes.length - 1];
    record.check_in = formatTime12h(record.time_in);
    record.check_out = formatTime12h(record.time_out);
    record.hours = resolveWorkDayHours(record);

    const remainingNextTimes = nextIsoSources
      .filter(iso => !earlyNextIso.includes(iso))
      .sort((a, b) => punchTimestamp(a) - punchTimestamp(b));

    if (!remainingNextTimes.length) {
      consumedDates.add(nextDate);
      nextRecord.hours = '00:00';
      nextRecord.times = [];
      nextRecord.check_in = nextRecord.check_in || '08:00 AM';
      nextRecord.check_out = nextRecord.check_in;
    } else {
      nextRecord.times = remainingNextTimes;
      nextRecord.time_in = remainingNextTimes[0];
      nextRecord.time_out = remainingNextTimes[remainingNextTimes.length - 1];
      nextRecord.check_in = formatTime12h(nextRecord.time_in);
      nextRecord.check_out = formatTime12h(nextRecord.time_out);
      nextRecord.hours = resolveWorkDayHours(nextRecord);
    }
  }

  const result = sorted.filter(r => !consumedDates.has(r.date));
  result.forEach(r => {
    const dayHours = r.working_hours ?? 8;
    if (isFullDayPermission(r.hours_permission, dayHours)) {
      applyNormalShiftTimes(r, dayHours);
      return;
    }
    if (isAbsenceOrPermissionPlaceholder(r)) {
      r.hours = '00:00';
      return;
    }
    r.hours = resolveWorkDayHours(r);
  });
  return result;
}

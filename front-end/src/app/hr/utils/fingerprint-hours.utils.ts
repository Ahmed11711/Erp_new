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

/** مضاعف ساعة الجمعة = سعر الساعة العادية × 2 */
export const FRIDAY_HOUR_MULTIPLIER = 2;

/** يوم الجمعة الكامل = يوم عادي (الراتب ÷ 30) بدون مضاعف 1.5 */
export function fridayNormalDayAmount(fixedSalary: number): number {
  return (Number(fixedSalary) || 0) / EXTRA_DAY_WORKING_DAYS;
}

/**
 * حافز حضور الجمعة:
 * - أقل من ساعات اليوم: ساعات إضافي فقط (سعر الساعة × 2)
 * - يساوي ساعات اليوم: يوم عادي (الراتب ÷ 30)
 * - أكثر من ساعات اليوم: يوم عادي + ساعات إضافي الزيادة (سعر الساعة × 2)
 */
export function fridayAttendanceMeritAmount(
  fixedSalary: number,
  dayHours: number,
  workedHours: number
): number {
  const dh = Math.max(dayHours || 8, 1);
  const wh = Math.max(workedHours || 0, 0);
  if (wh <= 0) {
    return 0;
  }
  const extraHourRate = baseHourPrice(fixedSalary, dh) * FRIDAY_HOUR_MULTIPLIER;
  if (wh + 1e-6 < dh) {
    return wh * extraHourRate;
  }
  const overtimeHours = Math.max(wh - dh, 0);
  return fridayNormalDayAmount(fixedSalary) + overtimeHours * extraHourRate;
}

export function isFridayFullDay(workedHours: number, dayHours: number): boolean {
  return (workedHours || 0) + 1e-6 >= Math.max(dayHours || 8, 1);
}

/** تسمية عرض حافز الجمعة حسب الساعات */
export function fridayAttendanceLabel(workedHours: number, dayHours: number): string {
  const dh = Math.max(dayHours || 8, 1);
  const wh = Math.max(workedHours || 0, 0);
  if (wh + 1e-6 < dh) {
    return 'جمعة (ساعات إضافي)';
  }
  if (wh > dh + 1e-6) {
    return 'جمعة (يوم عادي + ساعات إضافي)';
  }
  return 'جمعة (يوم عادي)';
}

export function fridayExtraDayReason(date: string): string {
  return `إضافي يوم كامل (${date})`;
}

/** نصف يوم مكافأة على يوم حضور عادي */
export const ATTENDANCE_HALF_DAY_BONUS = 0.5;

export function attendanceDayBonusReason(date: string, days: number): string {
  if (Number(days) === 1) {
    return fridayExtraDayReason(date);
  }
  if (Number(days) === ATTENDANCE_HALF_DAY_BONUS) {
    return `مكافأة نصف يوم (${date})`;
  }
  return `مكافأة ${days} يوم (${date})`;
}

export function attendanceDayBonusAmount(fixedSalary: number, dayHours: number, days: number): number {
  const d = Number(days);
  if (!d || d <= 0) {
    return 0;
  }
  return extraDayMeritAmountAction(fixedSalary, dayHours) * d;
}

export function attendanceDayBonusType(days: number): 'حوافز' | 'مكافئات' {
  return Number(days) === 1 ? 'حوافز' : 'مكافئات';
}

export function attendanceDayBonusLabel(days: number): string {
  if (Number(days) === 1) {
    return 'مضاعفة اليوم';
  }
  if (Number(days) === ATTENDANCE_HALF_DAY_BONUS) {
    return 'مكافأة نصف يوم';
  }
  return `مكافأة ${days} يوم`;
}

export function summarizeAttendanceHalfDayBonuses(
  merits: Array<{ reason?: string | null; amount?: number | string | null }>
): { count: number; amount: number } {
  let count = 0;
  let amount = 0;
  for (const item of merits || []) {
    const parsed = parseAttendanceDayBonusReason(item.reason);
    if (!parsed || parsed.days !== ATTENDANCE_HALF_DAY_BONUS) {
      continue;
    }
    count += 1;
    amount += Number(item.amount) || 0;
  }
  return { count, amount: Number(amount.toFixed(2)) };
}

export function parseAttendanceDayBonusReason(reason: string | null | undefined): { date: string; days: number } | null {
  if (!reason) {
    return null;
  }
  const half = String(reason).match(/^مكافأة نصف يوم \((\d{4}-\d{2}-\d{2})\)$/);
  if (half) {
    return { date: half[1], days: ATTENDANCE_HALF_DAY_BONUS };
  }
  const generic = String(reason).match(/^مكافأة ([\d.]+) يوم \((\d{4}-\d{2}-\d{2})\)$/);
  if (generic) {
    return { date: generic[2], days: Number(generic[1]) };
  }
  const full = String(reason).match(/^إضافي يوم كامل \((\d{4}-\d{2}-\d{2})\)$/);
  if (full) {
    return { date: full[1], days: 1 };
  }
  return null;
}

export function fridayPartialReason(date: string): string {
  return `إضافي جمعة (${date})`;
}

export function fridayAttendanceReason(date: string, fullDay: boolean): string {
  return fullDay ? fridayExtraDayReason(date) : fridayPartialReason(date);
}

export function parseFridayWorkedHours(hours: string | null | undefined): number {
  if (!hours) {
    return 0;
  }
  return permissionMinutes(hours) / 60;
}

export type OvertimeMeritKind = 'day' | 'hours' | 'amount';

export interface OvertimeMeritRowInput {
  date: string;
  kind: OvertimeMeritKind;
  hours?: number | null;
  amount?: number | null;
}

/** مبلغ سطر إضافي/خصم: يوم كامل أو ساعات × المعدل أو مبلغ معيّن */
export function overtimeMeritRowAmount(
  fixedSalary: number,
  dayHours: number,
  row: OvertimeMeritRowInput
): number {
  if (row.kind === 'amount') {
    return Math.max(Number(row.amount || 0), 0);
  }
  if (row.kind === 'day') {
    return extraDayMeritAmountAction(fixedSalary, dayHours);
  }
  const hours = Number(row.hours || 0);
  if (hours <= 0) {
    return 0;
  }
  return overtimeDeductionRate(fixedSalary, dayHours) * hours;
}

export function overtimeMeritRowReason(row: OvertimeMeritRowInput): string {
  if (row.kind === 'day') {
    return `إضافي يوم كامل (${row.date})`;
  }
  const hours = Number(row.hours || 0);
  return `إضافي ${hours} ساعة (${row.date})`;
}

export function deductionRowReason(row: OvertimeMeritRowInput): string {
  if (row.kind === 'amount') {
    return `خصم مبلغ (${row.date})`;
  }
  if (row.kind === 'day') {
    return `خصم يوم كامل (${row.date})`;
  }
  const hours = Number(row.hours || 0);
  return `خصم ${hours} ساعة (${row.date})`;
}

export function parseDeductionReason(reason: string | null | undefined): ParsedOvertimeMerit | null {
  if (!reason) {
    return null;
  }
  const dayMatch = String(reason).match(/^خصم يوم كامل \((\d{4}-\d{2}-\d{2})\)$/);
  if (dayMatch) {
    return { date: dayMatch[1], kind: 'day', hours: null };
  }
  const hoursMatch = String(reason).match(/^خصم ([\d.]+) ساعة \((\d{4}-\d{2}-\d{2})\)$/);
  if (hoursMatch) {
    return { date: hoursMatch[2], kind: 'hours', hours: Number(hoursMatch[1]) };
  }
  return null;
}

export function deductionReasonLabel(parsed: ParsedOvertimeMerit): string {
  if (parsed.kind === 'day') {
    return 'خصم يوم كامل';
  }
  if (parsed.hours == null) {
    return 'خصم ساعات';
  }
  return `خصم ${parsed.hours} ساعة`;
}

export interface DeductionCounts {
  deductionDays: number;
  deductionHours: number;
  deductionDaysAmount: number;
  deductionHoursAmount: number;
}

export function summarizeDeductionCounts(
  subtractions: Array<{ type?: string; reason?: string | null; amount?: number | string | null }>
): DeductionCounts {
  let deductionDays = 0;
  let deductionHours = 0;
  let deductionDaysAmount = 0;
  let deductionHoursAmount = 0;
  for (const item of subtractions || []) {
    if (item.type !== 'خصومات') {
      continue;
    }
    const parsed = parseDeductionReason(item.reason);
    if (!parsed) {
      continue;
    }
    const amount = Number(item.amount) || 0;
    if (parsed.kind === 'day') {
      deductionDays += 1;
      deductionDaysAmount += amount;
    } else if (typeof parsed.hours === 'number' && parsed.hours > 0) {
      deductionHours += parsed.hours;
      deductionHoursAmount += amount;
    }
  }
  return { deductionDays, deductionHours, deductionDaysAmount, deductionHoursAmount };
}

export interface ParsedOvertimeMerit {
  date: string;
  kind: OvertimeMeritKind;
  hours: number | null;
}

/** يستخرج تاريخ/نوع الإضافي من سبب الحافز المحفوظ */
export function parseOvertimeMeritReason(reason: string | null | undefined): ParsedOvertimeMerit | null {
  if (!reason) {
    return null;
  }
  const dayMatch = String(reason).match(/^إضافي يوم كامل \((\d{4}-\d{2}-\d{2})\)$/);
  if (dayMatch) {
    return { date: dayMatch[1], kind: 'day', hours: null };
  }
  const fridayPartialMatch = String(reason).match(/^إضافي جمعة \((\d{4}-\d{2}-\d{2})\)$/);
  if (fridayPartialMatch) {
    return { date: fridayPartialMatch[1], kind: 'hours', hours: null };
  }
  const hoursMatch = String(reason).match(/^إضافي ([\d.]+) ساعة \((\d{4}-\d{2}-\d{2})\)$/);
  if (hoursMatch) {
    return { date: hoursMatch[2], kind: 'hours', hours: Number(hoursMatch[1]) };
  }
  return null;
}

export function overtimeMeritReasonLabel(parsed: ParsedOvertimeMerit): string {
  if (parsed.kind === 'day') {
    return 'يوم كامل';
  }
  if (parsed.hours == null) {
    return 'جمعة (ساعات)';
  }
  return `${parsed.hours} ساعة`;
}

export interface OvertimeMeritCounts {
  extraDays: number;
  extraHours: number;
  extraDaysAmount: number;
  extraHoursAmount: number;
}

/** يعد أيام/ساعات الإضافي المحفوظة كحوافز لمراجعة كشف الشهر */
export function summarizeOvertimeMeritCounts(
  merits: Array<{ type?: string; reason?: string | null; amount?: number | string | null }>
): OvertimeMeritCounts {
  let extraDays = 0;
  let extraHours = 0;
  let extraDaysAmount = 0;
  let extraHoursAmount = 0;
  for (const item of merits || []) {
    if (item.type !== 'حوافز') {
      continue;
    }
    const parsed = parseOvertimeMeritReason(item.reason);
    if (!parsed) {
      continue;
    }
    const amount = Number(item.amount) || 0;
    if (parsed.kind === 'day') {
      extraDays += 1;
      extraDaysAmount += amount;
    } else if (typeof parsed.hours === 'number' && parsed.hours > 0) {
      extraHours += parsed.hours;
      extraHoursAmount += amount;
    }
  }
  return { extraDays, extraHours, extraDaysAmount, extraHoursAmount };
}

/** يحول فرق الساعات المعروض (02:30 / -01:15) إلى دقائق — يتجاهل نص الأيام */
export function hoursDifferenceToMinutes(value: string | null | undefined): number {
  if (!value) {
    return 0;
  }
  const raw = String(value).trim();
  if (!raw || raw.includes('يوم')) {
    return 0;
  }
  const negative = raw.startsWith('-');
  const [h, m] = raw.replace(/^-/, '').split(':').map(Number);
  if (Number.isNaN(h) || Number.isNaN(m)) {
    return 0;
  }
  const minutes = (h || 0) * 60 + (m || 0);
  return negative ? -minutes : minutes;
}

export function overtimeMeritCountsLabel(counts: OvertimeMeritCounts): string {
  const parts: string[] = [];
  if (counts.extraDays > 0) {
    parts.push(`${counts.extraDays} يوم إضافي`);
  }
  if (counts.extraHours > 0) {
    parts.push(`${Number(counts.extraHours.toFixed(2))} ساعة`);
  }
  return parts.join(' + ');
}

export function parseYearMonthFromDate(dateStr: string): { year: number; month: number } | null {
  if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    return null;
  }
  const [year, month] = dateStr.split('-').map(Number);
  if (!year || !month) {
    return null;
  }
  return { year, month };
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
  const parsed = parseDatetimeLocalValue(isoDate);
  return parsed ? parsed.getHours() : NaN;
}

export function hoursBetweenFirstAndLastPunch(isoDates: string[]): string | null {
  if (isoDates.length < 2) {
    return null;
  }
  const stamps = isoDates.map(d => parseDatetimeLocalValue(d)?.getTime() ?? NaN).filter(t => !Number.isNaN(t));
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
  const inDate = parseDatetimeLocalValue(timeIn);
  let outDate = parseDatetimeLocalValue(timeOut);
  if (!inDate || !outDate) {
    return null;
  }
  // بصمة واحدة (نفس الوقت) = 0 ساعات — لا تُحسب كـ 24 ساعة
  if (outDate.getTime() === inDate.getTime()) {
    return null;
  }
  // انصراف بعد منتصف الليل فقط عندما يكون الوقت أصغر فعلاً من الحضور
  if (outDate.getTime() < inDate.getTime()) {
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

/** يعيد حساب ساعات اليوم من بصمات الجهاز (أول → آخر ضربة) قبل أوقات العرض 12 ساعة */
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

  const times = parseTimesArray(record.times);
  const fromTimes = hoursBetweenFirstAndLastPunch(times);
  if (fromTimes) {
    return fromTimes;
  }

  const fromInOut = hoursFromTimeInOut(record.time_in, record.time_out);
  if (fromInOut) {
    return fromInOut;
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

  return record.hours || '00:00';
}

export function hoursLabelToMinutes(value: string | null | undefined): number {
  const [h, m] = parseHoursMinutes(value);
  return h * 60 + m;
}

/** دقائق العمل من بصمة الجهاز فقط — بدون إذن أو غياب محسوب */
export function fingerprintWorkedMinutes(record: {
  vacation?: unknown;
  hours?: string;
  hours_permission?: string | null;
  working_hours?: number;
  date?: string;
  check_in?: string;
  check_out?: string;
  times?: unknown;
  time_in?: string;
  time_out?: string;
}): number {
  if (isTruthyFlag(record.vacation)) {
    return 0;
  }
  const times = parseTimesArray(record.times);
  const fromTimes = hoursBetweenFirstAndLastPunch(times);
  if (fromTimes) {
    return hoursLabelToMinutes(fromTimes);
  }
  const fromInOut = hoursFromTimeInOut(record.time_in, record.time_out);
  if (fromInOut) {
    return hoursLabelToMinutes(fromInOut);
  }
  if (isFullDayPermission(record.hours_permission, record.working_hours ?? 8)) {
    return 0;
  }
  return hoursLabelToMinutes(resolveWorkDayHours({ ...record, hours_permission: null }));
}

/** مجموع ساعات البصمة لكل موظف = مجموع (آخر بصمة − أول بصمة) لكل يوم */
export function sumFingerprintWorkedMinutes(
  records: Array<Parameters<typeof fingerprintWorkedMinutes>[0]> | null | undefined,
  dayHours?: number
): number {
  if (!records?.length) {
    return 0;
  }
  return records.reduce((sum, record) => {
    return sum + fingerprintWorkedMinutes(
      dayHours != null ? { ...record, working_hours: dayHours } : record
    );
  }, 0);
}

export interface FingerprintDayFromPunches {
  check_in: string;
  check_out: string;
  time_in: string;
  time_out: string;
  hours: string;
  times: string[];
}

/** يوم واحد من ضربات الجهاز: الحضور = أول بصمة، الانصراف = آخر بصمة، الساعات = الفرق */
export function fingerprintDayFromPunches(isoDates: string[]): FingerprintDayFromPunches | null {
  const stamps = [...new Set(isoDates.filter(Boolean))]
    .sort((a, b) => punchTimestamp(a) - punchTimestamp(b));
  if (!stamps.length) {
    return null;
  }
  const first = stamps[0];
  const last = stamps[stamps.length - 1];
  return {
    check_in: formatTime12h(first),
    check_out: formatTime12h(last),
    time_in: first,
    time_out: last,
    hours: hoursBetweenFirstAndLastPunch(stamps) || '00:00',
    times: stamps,
  };
}

export function formatTime12h(isoDate: string): string {
  const d = parseDatetimeLocalValue(isoDate);
  if (!d) {
    return '';
  }
  const h24 = d.getHours();
  const m = d.getMinutes();
  const period = h24 >= 12 ? 'PM' : 'AM';
  const h12 = h24 % 12 || 12;
  return `${String(h12).padStart(2, '0')}:${String(m).padStart(2, '0')} ${period}`;
}

/** يزامن check_in/check_out المعروضين من time_in/time_out */
export function syncDisplayTimesFromIso<T extends {
  time_in?: string | null;
  time_out?: string | null;
  check_in?: string;
  check_out?: string;
}>(record: T): T {
  if (record.time_in) {
    const formatted = formatTime12h(record.time_in);
    if (formatted) {
      record.check_in = formatted;
    }
  }
  if (record.time_out) {
    const formatted = formatTime12h(record.time_out);
    if (formatted) {
      record.check_out = formatted;
    }
  }
  return record;
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
  if (checkOutDate.getTime() === checkInDate.getTime()) {
    return checkOutDate;
  }
  if (checkOutDate.getTime() < checkInDate.getTime()) {
    checkOutDate = new Date(checkOutDate);
    checkOutDate.setDate(checkOutDate.getDate() + 1);
  }
  return checkOutDate;
}

/** يقرأ datetime-local / ISO بدون timezone كوقت محلي — بدون إزاحة UTC */
export function parseDatetimeLocalValue(value: string): Date | null {
  if (!value) {
    return null;
  }
  const match = String(value).trim().match(
    /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/
  );
  if (match) {
    const date = new Date(
      Number(match[1]),
      Number(match[2]) - 1,
      Number(match[3]),
      Number(match[4]),
      Number(match[5]),
      Number(match[6] ?? 0),
      0
    );
    return Number.isNaN(date.getTime()) ? null : date;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function diffMsBetween(checkIn: Date, checkOut: Date): number {
  let end = new Date(checkOut);
  if (end.getTime() === checkIn.getTime()) {
    return 0;
  }
  if (end.getTime() < checkIn.getTime()) {
    end.setDate(end.getDate() + 1);
  }
  return end.getTime() - checkIn.getTime();
}

/**
 * دقائق تُطرح لتطبيع أيام التقويم الزائدة فوق 26 يوم عمل.
 * لا نضيف ساعات وهمية عندما يكون شيت البصمة ناقصاً (غياب).
 */
export function monthHoursPaddingMinutes(
  tableDayCount: number,
  holidayCount: number,
  dayHours: number
): number {
  const excessDays = tableDayCount - holidayCount - WORKING_DAYS_PER_MONTH;
  if (excessDays <= 0) {
    return 0;
  }
  return excessDays * dayHours * 60;
}

export function nextDateStr(dateStr: string): string {
  const [y, m, d] = dateStr.split('-').map(Number);
  const next = new Date(y, m - 1, d + 1);
  return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
}

/** يوم تقفيل البصمة — بعده يبدأ شهر التقفيل التالي */
export const FINGERPRINT_CLOSE_DAY = 25;
/** أول يوم في دورة البصمة (26 الشهر السابق) */
export const FINGERPRINT_PERIOD_START_DAY = 26;

export interface FingerprintPeriod {
  year: number;
  month: number;
  dateFrom: string;
  dateTo: string;
}

export function formatYmd(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** شهر التقفيل M = من 26 الشهر السابق إلى 25 الشهر M */
export function fingerprintPeriodForMonth(year: number, month: number): FingerprintPeriod {
  const dateTo = new Date(year, month - 1, FINGERPRINT_CLOSE_DAY);
  const dateFrom = new Date(year, month - 2, FINGERPRINT_PERIOD_START_DAY);
  return {
    year,
    month,
    dateFrom: formatYmd(dateFrom),
    dateTo: formatYmd(dateTo),
  };
}

/** تاريخ اليوم 26 فما فوق يتبع شهر التقفيل التالي */
export function payrollMonthForDate(date: Date | string): { year: number; month: number } {
  const d = typeof date === 'string' ? parseYmdLocal(date) : date;
  if (!d) {
    const today = new Date();
    return { year: today.getFullYear(), month: today.getMonth() + 1 };
  }
  if (d.getDate() <= FINGERPRINT_CLOSE_DAY) {
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
  }
  const next = new Date(d.getFullYear(), d.getMonth() + 1, 1);
  return { year: next.getFullYear(), month: next.getMonth() + 1 };
}

export function defaultFingerprintMonthValue(today = new Date()): string {
  const { year, month } = payrollMonthForDate(today);
  return `${year}-${String(month).padStart(2, '0')}`;
}

export function parseYearMonthValue(value: string | null | undefined): { year: number; month: number } | null {
  if (!value || !/^\d{4}-\d{2}$/.test(value)) {
    return null;
  }
  const [year, month] = value.split('-').map(Number);
  if (!year || !month) {
    return null;
  }
  return { year, month };
}

export function eachDateInRange(from: string, to: string): string[] {
  const start = parseYmdLocal(from);
  const end = parseYmdLocal(to);
  if (!start || !end || start.getTime() > end.getTime()) {
    return [];
  }
  const dates: string[] = [];
  const cur = new Date(start.getFullYear(), start.getMonth(), start.getDate());
  while (cur.getTime() <= end.getTime()) {
    dates.push(formatYmd(cur));
    cur.setDate(cur.getDate() + 1);
  }
  return dates;
}

export function fridayDatesInRange(from: string, to: string): string[] {
  return eachDateInRange(from, to).filter((date) => isFridayDate(date));
}

function parseYmdLocal(value: string): Date | null {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return null;
  }
  const [y, m, d] = value.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return Number.isNaN(date.getTime()) ? null : date;
}

function punchTimestamp(isoDate: string): number {
  const parsed = parseDatetimeLocalValue(isoDate);
  return parsed ? parsed.getTime() : Number.NaN;
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

/** هل التاريخ يوم جمعة؟ */
export function isFridayDate(dateStr: string): boolean {
  if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    return false;
  }
  const [y, m, d] = dateStr.split('-').map(Number);
  return new Date(y, m - 1, d).getDay() === 5;
}

/** حضور فعلي يوم الجمعة (ليس إجازة فارغة) */
export function hasFridayAttendance(record: {
  check_in?: string | null;
  check_out?: string | null;
  hours?: string | null;
}): boolean {
  const checkIn = record.check_in || '';
  const checkOut = record.check_out || '';
  if (!checkIn || !checkOut || checkIn === checkOut) {
    return false;
  }
  return permissionMinutes(record.hours || '00:00') > 0;
}

export const DEFAULT_SHIFT_START = '08:00 AM';

/** أيام الشهر لخصم الغياب باليوم = الراتب ÷ 30 */
export const ABSENCE_DAY_DIVISOR = 30;

/** مضاعفة يوم الغياب = خصم يومين */
export const ABSENCE_DAY_DOUBLE = 2;

/** قيمة يوم غياب = (الراتب ÷ 30) × عدد الأيام */
export function absenceDayAmount(fixedSalary: number, days: number = 1): number {
  const d = Number(days);
  const dayCount = !d || d <= 0 ? 1 : d;
  return (Number(fixedSalary) || 0) / ABSENCE_DAY_DIVISOR * dayCount;
}

/** عدد أيام الخصم ليوم الغياب (1 أو قيمة بدون إذن) */
export function absenceDayCount(absenceDeduction?: number | string | null): number {
  if (absenceDeduction != null && absenceDeduction !== '') {
    const n = Number(absenceDeduction);
    if (n > 0) {
      return n;
    }
  }
  return 1;
}

/** يوم الغياب محسوب يومين (راتب ÷ 30 × 2) */
export function isAbsenceDoubled(absenceDeduction?: number | string | null): boolean {
  return Number(absenceDeduction) === ABSENCE_DAY_DOUBLE;
}

/** يوم غياب ظاهر في الكشف (بدون بصمة / بدون إذن / ليس إجازة) */
export function isAbsentDay(record: {
  hours?: string | null;
  holiday?: boolean;
  vacation?: boolean | number | string;
  check_in?: string | null;
  hours_permission?: string | null;
}): boolean {
  if (record.holiday || record.vacation === true || record.vacation === 1 || record.vacation === '1') {
    return false;
  }
  if (record.hours_permission) {
    return false;
  }
  const hours = record.hours || '00:00';
  const checkIn = record.check_in || DEFAULT_SHIFT_START;
  return hours === '00:00' && checkIn === DEFAULT_SHIFT_START;
}

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

/** إجازة بإذن (يوم غياب كامل مغطى بإذن) */
export function isFullDayPermissionLeave(record: {
  hours_permission?: string | null;
  holiday?: boolean;
  vacation?: boolean | number | string;
}, dayHours: number): boolean {
  if (record.holiday || record.vacation === true || record.vacation === 1 || record.vacation === '1') {
    return false;
  }
  return isFullDayPermission(record.hours_permission, dayHours);
}

/**
 * قالب الغياب بعد التراجع عن إجازة بإذن.
 * يجب إعادة الحضور/الانصراف لـ 08:00 وإلا يُحسب اليوم حضور عمل بدون خصم.
 */
export function absentDayPlaceholderSavePayload(): {
  hours_permission: null;
  absence_deduction: null;
  check_in: string;
  check_out: string;
  hours: string;
} {
  return {
    hours_permission: null,
    absence_deduction: null,
    check_in: DEFAULT_SHIFT_START,
    check_out: DEFAULT_SHIFT_START,
    hours: '00:00',
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

    const dayStamps = dayIsoSources
      .map((iso) => punchTimestamp(iso))
      .filter((t) => !Number.isNaN(t));
    const sameDaySpanMin = dayStamps.length
      ? (Math.max(...dayStamps) - Math.min(...dayStamps)) / 60000
      : 0;
    // يوم فيه حضور وانصراف مكتملين قبل منتصف الليل: لا نسرق بصمة 00:01 لليوم التالي
    if (sameDaySpanMin >= 6 * 60) {
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
    syncDisplayTimesFromIso(r);
    r.hours = resolveWorkDayHours(r);
  });
  return result;
}

/** ملخص كشف الشهر من الحضور — نفس أرقام صفحة الحضور والانصراف */
export interface AttendanceMonthAccount {
  fixedSalary: number;
  changedSalary: number;
  incentives: number;
  suits: number;
  rewards: number;
  overtimeFromHours: number;
  hoursDeduction: number;
  dailyIncentive: number;
  dailyDeduction: number;
  rival: number;
  absenceSub: number;
  advancePayment: number;
  totalMerit: number;
  totalSub: number;
  netTotal: number;
  overtimeHoursLabel: string;
  lateHoursLabel: string;
  extraDaysCount: number;
  extraDaysAmount: number;
  extraHoursFromMerits: number;
  extraHoursMeritsAmount: number;
  extraMeritsLabel: string;
  otherIncentives: number;
  deductionDaysCount: number;
  deductionDaysAmount: number;
  extraDeductionDaysCount: number;
  extraDeductionDaysAmount: number;
  extraDeductionHours: number;
  extraDeductionHoursAmount: number;
  otherRival: number;
  halfDayBonusCount: number;
  halfDayBonusAmount: number;
  otherRewards: number;
  reviewed: boolean;
}

export interface AnnotateAttendanceDayContext {
  dayHours: number;
  fixedSalary: number;
  hourPrice: number;
}

function parseHoursMinutes(value: string | null | undefined): [number, number] {
  const [h, m] = String(value || '00:00').split(':').map(Number);
  return [Number(h) || 0, Number(m) || 0];
}

function isTruthyFlag(value: unknown): boolean {
  return value === true || value === 1 || value === '1' || value === 'true';
}

/**
 * يحسب حافز/خصم اليوم من البصمة ويحدّث الصف.
 * القيمة المعادة = دقائق تُضاف لإجمالي ساعات الشهر (بدون جمعة).
 */
export function annotateAttendanceDay(elm: any, ctx: AnnotateAttendanceDayContext): number {
  const dayHours = Math.max(ctx.dayHours || 8, 1);
  const holiday = !!elm.holiday;
  const vacation = isTruthyFlag(elm.vacation);
  elm.working_hours = dayHours;

  const fullDayPermission = isFullDayPermission(elm.hours_permission, dayHours)
    && !vacation
    && !holiday;

  if (fullDayPermission) {
    applyNormalShiftTimes(elm, dayHours);
    elm.hoursDifference = '00:00';
    elm.salary_type = 0;
    elm.salary_type2 = 'اذن';
    elm.absence_day = false;
    elm.friday_work = false;
    return dayHours * 60;
  }

  if (!vacation && elm.check_in && elm.check_out && elm.check_in !== elm.check_out) {
    elm.hours = resolveWorkDayHours({ ...elm, working_hours: dayHours });
  }

  if (vacation) {
    elm.hoursDifference = '00:00';
    elm.salary_type = 0;
    elm.salary_type2 = '';
    elm.absence_day = false;
    elm.friday_work = false;
    return 0;
  }

  const [hours, minutes] = parseHoursMinutes(elm.hours);
  const workedMins = hours * 60 + minutes;
  const fridayWork = holiday && hasFridayAttendance(elm);
  const absentDay = !fridayWork && isAbsentDay(elm);

  if (absentDay) {
    const days = absenceDayCount(elm.absence_deduction);
    elm.absence_day = true;
    elm.absence_days = days;
    elm.hoursDifference = `${days} يوم`;
    elm.salary_type = absenceDayAmount(ctx.fixedSalary || 0, days);
    elm.salary_type2 = 'خصم';
    elm.friday_work = false;
    return dayHours * 60;
  }

  elm.absence_day = false;
  let minutesForMonth = 0;
  if (!fridayWork) {
    minutesForMonth += workedMins;
  }
  if (!fridayWork && elm.is_overTime_removed) {
    minutesForMonth -= workedMins - (dayHours * 60);
  }
  if (!fridayWork && elm.hours_permission) {
    const [ph, pm] = parseHoursMinutes(elm.hours_permission);
    minutesForMonth += ph * 60 + pm;
  }

  if (fridayWork) {
    elm.hoursDifference = elm.hours;
    elm.friday_work = true;
    const worked = parseFridayWorkedHours(elm.hours);
    elm.salary_type = fridayAttendanceMeritAmount(ctx.fixedSalary || 0, dayHours, worked);
    elm.salary_type2 = 'حافز';
    elm.friday_label = fridayAttendanceLabel(worked, dayHours);
    return minutesForMonth;
  }

  elm.friday_work = false;
  const hoursDifference = workedMins - (dayHours * 60);
  if (hoursDifference >= 0) {
    elm.hoursDifference = convertMinutesToHours(hoursDifference);
    elm.salary_type = hoursDifference / 60 * ctx.hourPrice * OVERTIME_DEDUCTION_MULTIPLIER;
    elm.salary_type2 = 'حافز';
    return minutesForMonth;
  }

  elm.hoursDifference = '-' + convertMinutesToHours(-hoursDifference);
  const rateMultiplier = deductionMultiplier(elm.absence_deduction);
  let salary = hoursDifference / 60 * ctx.hourPrice * rateMultiplier;
  if (elm.hours_permission) {
    const [hp, mp] = parseHoursMinutes(elm.hours_permission);
    salary += ((hp * 60 + mp) / 60 * ctx.hourPrice * rateMultiplier);
  }
  elm.salary_type = salary === 0 ? 0 : salary * -1;
  elm.salary_type2 = 'خصم';
  return minutesForMonth;
}

export function attendanceDayPlaceholder(date: string, employeeId?: number | null): any {
  return {
    id: null,
    date,
    check_in: DEFAULT_SHIFT_START,
    check_out: DEFAULT_SHIFT_START,
    hours: '00:00',
    times: [],
    employee_id: employeeId ?? null,
    hours_permission: null,
    absence_deduction: null,
    vacation: false,
    reviewed: false,
    is_overTime_removed: false,
  };
}

function normalizeAttendanceRow(elm: any, dayHours: number, holidayDays: string[]): void {
  if (typeof elm.times === 'string') {
    try {
      elm.times = JSON.parse(elm.times.replace(/\\/g, ''));
    } catch {
      elm.times = [];
    }
  }
  elm.date = String(elm.date || '').slice(0, 10);
  elm.working_hours = dayHours;
  elm.holiday = (holidayDays || []).includes(elm.date);
  elm.vacation = isTruthyFlag(elm.vacation);
  if (elm.holiday && (elm.hours === '00:00' || !elm.hours)) {
    elm.check_in = elm.check_in || DEFAULT_SHIFT_START;
    elm.check_out = elm.check_in;
    elm.hours = '00:00';
  }
  if (elm.vacation) {
    elm.vacation_reason = elm.vacation_reason || elm.vacation_reason_en || 'أجازة';
    elm.check_in = elm.check_in || DEFAULT_SHIFT_START;
    elm.check_out = elm.check_out || elm.check_in || DEFAULT_SHIFT_START;
    elm.hours = '00:00';
  }
  if (!elm.holiday && !elm.vacation && (!elm.hours || elm.hours === '00:00')) {
    elm.check_in = elm.check_in || DEFAULT_SHIFT_START;
    elm.check_out = elm.check_out || elm.check_in || DEFAULT_SHIFT_START;
    elm.hours = '00:00';
  }
  syncDisplayTimesFromIso(elm);
}

function asRecordList(value: unknown): any[] {
  if (Array.isArray(value)) {
    return value;
  }
  if (value && typeof value === 'object') {
    return Object.values(value as Record<string, unknown>).filter(
      (item) => item && typeof item === 'object'
    ) as any[];
  }
  return [];
}

/** يبني صفوف الشهر بنفس منطق كشف الحضور (بما فيها الأيام الناقصة = غياب) */
export function buildAttendanceTableData(input: {
  fingerPrint: any[] | null | undefined;
  dayHours: number;
  fixedSalary: number;
  dateFrom: string;
  dateTo: string;
  holidayDays?: string[];
  employeeId?: number | null;
}): { tableData: any[]; noFingerPrints: boolean } {
  const dayHours = Math.max(input.dayHours || 8, 1);
  const holidayDays = input.holidayDays?.length
    ? input.holidayDays
    : fridayDatesInRange(input.dateFrom, input.dateTo);
  let records = asRecordList(input.fingerPrint).map((row) => ({ ...row }));
  if (records.length) {
    records.forEach((r) => {
      r.working_hours = dayHours;
    });
    records = normalizeOvernightFingerPrintRecords(records);
  }
  const byDate = new Map<string, any>();
  for (const row of records) {
    const date = String(row.date || '').slice(0, 10);
    if (date) {
      byDate.set(date, row);
    }
  }
  const hourPrice = baseHourPrice(input.fixedSalary || 0, dayHours);
  const tableData: any[] = [];
  for (const dateStr of eachDateInRange(input.dateFrom, input.dateTo)) {
    const elm = byDate.get(dateStr) || attendanceDayPlaceholder(dateStr, input.employeeId);
    normalizeAttendanceRow(elm, dayHours, holidayDays);
    if (records.length) {
      annotateAttendanceDay(elm, {
        dayHours,
        fixedSalary: input.fixedSalary || 0,
        hourPrice,
      });
    }
    tableData.push(elm);
  }
  return { tableData, noFingerPrints: records.length === 0 };
}

export function buildAttendanceMonthAccount(input: {
  tableData: any[];
  merits?: any[];
  subtractions?: any[];
  advancePayments?: any[];
  fixedSalary: number;
}): AttendanceMonthAccount {
  const merits = input.merits || [];
  const subtractions = input.subtractions || [];
  const advancePayments = input.advancePayments || [];
  const fixed = Number(input.fixedSalary) || 0;

  let incentives = 0;
  let suits = 0;
  let rewards = 0;
  let changedSalary = 0;
  for (const item of merits) {
    const amount = Number(item.amount) || 0;
    if (item.type === 'حوافز') {
      incentives += amount;
    } else if (item.type === 'بدلات') {
      suits += amount;
    } else if (item.type === 'مكافئات') {
      rewards += amount;
    } else if (item.type === 'الراتب المتغير') {
      changedSalary += amount;
    }
  }

  let rival = 0;
  let absenceSub = 0;
  for (const item of subtractions) {
    const amount = Number(item.amount) || 0;
    if (item.type === 'خصومات') {
      rival += amount;
    } else if (item.type === 'غياب') {
      absenceSub += Number(((fixed / 30) * amount).toFixed(2));
    }
  }

  let advancePayment = 0;
  for (const item of advancePayments) {
    if (item.type === 'سلف') {
      advancePayment += Number(item.amount) || 0;
    }
  }

  const extraCounts = summarizeOvertimeMeritCounts(merits);
  const extraDaysAmount = Number((extraCounts.extraDaysAmount || 0).toFixed(2));
  const extraHoursMeritsAmount = Number((extraCounts.extraHoursAmount || 0).toFixed(2));
  const otherIncentives = Number((incentives - extraDaysAmount - extraHoursMeritsAmount).toFixed(2));
  const deductionCounts = summarizeDeductionCounts(subtractions);
  const extraDeductionDaysAmount = Number((deductionCounts.deductionDaysAmount || 0).toFixed(2));
  const extraDeductionHoursAmount = Number((deductionCounts.deductionHoursAmount || 0).toFixed(2));
  const otherRival = Number((rival - extraDeductionDaysAmount - extraDeductionHoursAmount).toFixed(2));
  const halfDayBonuses = summarizeAttendanceHalfDayBonuses(merits);
  const halfDayBonusAmount = halfDayBonuses.amount;
  const otherRewards = Number((rewards - halfDayBonusAmount).toFixed(2));

  let overtimeMinutes = 0;
  let overtimeFromHours = 0;
  let lateMinutes = 0;
  let hoursDeduction = 0;
  let deductionDaysCount = 0;
  let fingerprintAbsenceDays = 0;
  for (const row of input.tableData || []) {
    if (row.absence_day) {
      deductionDaysCount += Number(row.absence_days) || 1;
      fingerprintAbsenceDays += Math.abs(Number(row.salary_type || 0));
      continue;
    }
    if (row.salary_type2 === 'خصم' && String(row.hoursDifference || '').includes('يوم')) {
      continue;
    }
    if (
      row.salary_type2 === 'حافز'
      && !row.is_overTime_removed
      && !row.holiday
      && !row.friday_work
      && !row.vacation
    ) {
      overtimeMinutes += Math.max(hoursDifferenceToMinutes(row.hoursDifference), 0);
      overtimeFromHours += Number(row.salary_type || 0);
    } else if (
      row.salary_type2 === 'خصم'
      && !row.holiday
      && !row.friday_work
      && !row.vacation
    ) {
      const lateMins = hoursDifferenceToMinutes(row.hoursDifference);
      if (lateMins === 0) {
        continue;
      }
      lateMinutes += Math.abs(lateMins);
      hoursDeduction += Math.abs(Number(row.salary_type || 0));
    }
  }
  for (const item of subtractions) {
    if (item.type === 'غياب') {
      deductionDaysCount += Number(item.amount) || 0;
    }
  }

  overtimeFromHours = Number(overtimeFromHours.toFixed(2));
  hoursDeduction = Number(hoursDeduction.toFixed(2));
  const overtimeHoursLabel = overtimeMinutes > 0 ? convertMinutesToHours(overtimeMinutes) : '';
  const lateHoursLabel = lateMinutes > 0 ? convertMinutesToHours(lateMinutes) : '';
  if (fingerprintAbsenceDays > 0) {
    absenceSub = Number((absenceSub + fingerprintAbsenceDays).toFixed(2));
  }
  const deductionDaysAmount = Number(absenceSub.toFixed(2));
  let totalMerit = changedSalary + incentives + suits + rewards + fixed + overtimeFromHours;
  let totalSub = rival + absenceSub + advancePayment + hoursDeduction;
  totalSub = Number(totalSub.toFixed(2));
  totalMerit = Number(totalMerit.toFixed(2));

  const savedRows = (input.tableData || []).filter((row) => row?.id);
  const reviewed = savedRows.length > 0 && savedRows.every((row) => row.reviewed === true || row.reviewed === 1);

  return {
    fixedSalary: fixed,
    changedSalary,
    incentives,
    otherIncentives,
    suits,
    rewards,
    overtimeFromHours,
    hoursDeduction,
    dailyIncentive: overtimeFromHours,
    dailyDeduction: hoursDeduction,
    rival,
    absenceSub,
    advancePayment,
    totalMerit,
    totalSub,
    netTotal: Number((totalMerit - totalSub).toFixed(2)),
    overtimeHoursLabel,
    lateHoursLabel,
    extraDaysCount: extraCounts.extraDays,
    extraDaysAmount,
    extraHoursFromMerits: extraCounts.extraHours,
    extraHoursMeritsAmount,
    extraMeritsLabel: overtimeMeritCountsLabel(extraCounts),
    deductionDaysCount,
    deductionDaysAmount,
    extraDeductionDaysCount: deductionCounts.deductionDays,
    extraDeductionDaysAmount,
    extraDeductionHours: deductionCounts.deductionHours,
    extraDeductionHoursAmount,
    otherRival,
    halfDayBonusCount: halfDayBonuses.count,
    halfDayBonusAmount,
    otherRewards,
    reviewed,
  };
}

/** صف كشف المرتبات من نفس حساب كشف الحضور */
export function buildPayrollRowFromAttendance(emp: any, period: {
  dateFrom: string;
  dateTo: string;
  holidayDays?: string[];
}): AttendanceMonthAccount & {
  extraHours: number;
  absence_sub: number;
  absence: number;
  absenceDetails: {
    absenceHours?: string;
    absenceHoursPrice?: number;
    absenceDaysCount?: number;
    absenceDaysPrice?: number;
  };
  isReviewed: number;
  noFingerPrints: boolean;
  calc_salary: number;
} {
  const dayHours = Number(emp?.working_hours) || 8;
  const fixedSalary = Number(emp?.fixed_salary) || 0;
  const { tableData, noFingerPrints } = buildAttendanceTableData({
    fingerPrint: emp?.finger_print || emp?.fingerPrint || [],
    dayHours,
    fixedSalary,
    dateFrom: period.dateFrom,
    dateTo: period.dateTo,
    holidayDays: period.holidayDays,
    employeeId: emp?.id,
  });
  const account = buildAttendanceMonthAccount({
    tableData,
    merits: asRecordList(emp?.merits),
    subtractions: asRecordList(emp?.subtraction),
    advancePayments: asRecordList(emp?.advance_payment || emp?.advancePayment),
    fixedSalary,
  });
  const absenceDetails: {
    absenceHours?: string;
    absenceHoursPrice?: number;
    absenceDaysCount?: number;
    absenceDaysPrice?: number;
  } = {};
  if (account.lateHoursLabel) {
    absenceDetails.absenceHours = account.lateHoursLabel;
    absenceDetails.absenceHoursPrice = account.hoursDeduction;
  }
  if (account.deductionDaysCount) {
    absenceDetails.absenceDaysCount = account.deductionDaysCount;
    absenceDetails.absenceDaysPrice = account.absenceSub;
  }
  return {
    ...account,
    extraHours: account.overtimeFromHours,
    absence_sub: Number((account.absenceSub + account.hoursDeduction).toFixed(2)),
    absence: account.deductionDaysCount,
    absenceDetails,
    isReviewed: account.reviewed ? 1 : 0,
    noFingerPrints,
    calc_salary: Number((fixedSalary + account.changedSalary).toFixed(2)),
  };
}

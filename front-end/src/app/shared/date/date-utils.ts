export interface DateParseResult {
  date: Date | null;
  valid: boolean;
  complete: boolean;
}

export function isoStringToDate(value: string | null | undefined): Date | null {
  if (!value) {
    return null;
  }
  const match = String(value).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) {
    return null;
  }
  return buildLocalDate(Number(match[1]), Number(match[2]), Number(match[3]));
}

export function dateToIsoString(value: Date | null | undefined): string | null {
  if (!value || Number.isNaN(value.getTime())) {
    return null;
  }
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/** Always formats as DD/MM/YYYY for display. */
export function formatDdMmYyyy(value: Date | null | undefined): string {
  if (!value || Number.isNaN(value.getTime())) {
    return '';
  }
  const day = String(value.getDate()).padStart(2, '0');
  const month = String(value.getMonth() + 1).padStart(2, '0');
  return `${day}/${month}/${value.getFullYear()}`;
}

/**
 * Parses user input strictly as DD/MM/YYYY.
 * Never uses Date.parse — that API treats "05/07/2026" as May 7 (US) not July 5.
 */
export function parseDdMmYyyy(value: unknown): Date | null {
  return parseDdMmYyyyDetailed(value).date;
}

export function parseDdMmYyyyDetailed(value: unknown): DateParseResult {
  if (value == null) {
    return { date: null, valid: true, complete: true };
  }
  if (value instanceof Date) {
    const valid = !Number.isNaN(value.getTime());
    return { date: valid ? value : null, valid, complete: true };
  }
  if (typeof value !== 'string') {
    return { date: null, valid: false, complete: true };
  }

  const trimmed = value.trim();
  if (!trimmed) {
    return { date: null, valid: true, complete: true };
  }

  const iso = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso) {
    const date = buildLocalDate(Number(iso[1]), Number(iso[2]), Number(iso[3]));
    const valid = isValidDateParts(
      Number(iso[3]),
      Number(iso[2]),
      Number(iso[1]),
    );
    return { date: valid ? date : null, valid, complete: true };
  }

  if (!trimmed.includes('/')) {
    return { date: null, valid: true, complete: false };
  }

  const parts = trimmed.split('/').map((part) => part.trim());
  if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
    return { date: null, valid: true, complete: false };
  }

  const day = Number(parts[0]);
  const month = Number(parts[1]);
  const year = Number(parts[2]);

  if (String(parts[2]).length < 4) {
    return { date: null, valid: true, complete: false };
  }

  const valid = isValidDateParts(day, month, year);
  return {
    date: valid ? buildLocalDate(year, month, day) : null,
    valid,
    complete: true,
  };
}

export function isValidDateParts(day: number, month: number, year: number): boolean {
  if (Number.isNaN(day) || Number.isNaN(month) || Number.isNaN(year)) {
    return false;
  }
  if (year < 1000 || year > 9999 || month < 1 || month > 12 || day < 1 || day > 31) {
    return false;
  }
  const date = buildLocalDate(year, month, day);
  return date.getFullYear() === year
    && date.getMonth() === month - 1
    && date.getDate() === day;
}

function buildLocalDate(year: number, month: number, day: number): Date {
  const date = new Date();
  date.setFullYear(year, month - 1, day);
  date.setHours(0, 0, 0, 0);
  return date;
}

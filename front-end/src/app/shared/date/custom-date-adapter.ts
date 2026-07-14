import { Injectable } from '@angular/core';
import { NativeDateAdapter } from '@angular/material/core';
import {
  dateToIsoString,
  formatDdMmYyyy,
  isoStringToDate,
  parseDdMmYyyy,
} from './date-utils';

const ISO_8601_REGEX = /^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?$/;

@Injectable()
export class CustomDateAdapter extends NativeDateAdapter {
  override format(date: Date, displayFormat: Object): string {
    if (!this.isValid(date)) {
      return '';
    }
    if (this.isInputFormat(displayFormat)) {
      return formatDdMmYyyy(date);
    }
    return super.format(date, displayFormat);
  }

  override parse(value: unknown, _parseFormat?: unknown): Date | null {
    if (typeof value === 'number') {
      return new Date(value);
    }
    return parseDdMmYyyy(value);
  }

  override deserialize(value: unknown): Date | null {
    if (typeof value === 'string') {
      if (!value) {
        return null;
      }
      const parsed = parseDdMmYyyy(value);
      if (parsed) {
        return parsed;
      }
      const isoDate = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (isoDate) {
        return isoStringToDate(isoDate[0]);
      }
      if (ISO_8601_REGEX.test(value)) {
        return isoStringToDate(value.slice(0, 10));
      }
      return null;
    }
    return super.deserialize(value);
  }

  override toIso8601(date: Date): string {
    return dateToIsoString(date) ?? '';
  }

  override compareDate(first: Date, second: Date): number {
    const a = dateToIsoString(first);
    const b = dateToIsoString(second);
    if (!a || !b) {
      return super.compareDate(first, second);
    }
    return a < b ? -1 : a > b ? 1 : 0;
  }

  private isInputFormat(format: Object): boolean {
    return format === 'input'
      || (typeof format === 'object'
        && format !== null
        && 'day' in format
        && 'month' in format
        && 'year' in format);
  }
}

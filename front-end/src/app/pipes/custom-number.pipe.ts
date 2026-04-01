import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'customNumber'
})
export class CustomNumberPipe implements PipeTransform {
  transform(value: any): string {
    if (value === null || value === undefined || value === '') {
      return '';
    }
    const num = typeof value === 'number' ? value : Number(value);
    if (Number.isNaN(num)) {
      return String(value);
    }

    const formattedNumber = num.toLocaleString('en-US', {
      minimumFractionDigits: Number.isInteger(num) ? 0 : 1,
      maximumFractionDigits: 4
    });

    return formattedNumber.replace(/(\.[0-9]*[1-9])0+$/, '$1'); // Remove trailing zeros after the decimal point
  }
}

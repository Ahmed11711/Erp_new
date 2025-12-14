import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'customNumber'
})
export class CustomNumberPipe implements PipeTransform {
  transform(value: any): string {
    // Check if the value is a number
    if (typeof value !== 'number') {
      return value.toString(); // Return the original value if it's not a number
    }

    const formattedNumber = value.toLocaleString('en-US', {
      minimumFractionDigits: Number.isInteger(value) ? 0 : 1,
      maximumFractionDigits: 3
    });

    return formattedNumber.replace(/(\.[0-9]*[1-9])0+$/, '$1'); // Remove trailing zeros after the decimal point
  }
}

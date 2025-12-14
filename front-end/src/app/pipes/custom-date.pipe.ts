import { Pipe, PipeTransform } from '@angular/core';
import { DatePipe } from '@angular/common';
import * as dateFns from 'date-fns';
import { arDZ } from 'date-fns/locale';

@Pipe({
  name: 'customDate',
})
export class CustomDatePipe implements PipeTransform {
  transform(value: any, format: string = 'yyyy-MM-dd'): any {
    // Check if the value is a valid date
    if (!value) {
      // Handle the case where no date is provided
      return '';
    }

    const inputDate = new Date(value);
    if (isNaN(inputDate.getTime())) {
      // Handle invalid date gracefully (return original value or an appropriate fallback)
      return value;
    }

    // Transform the date using the built-in DatePipe
    const transformedDate = new DatePipe('en-US').transform(inputDate, format);

    // Get the day name in Arabic
    const dayName = dateFns.format(inputDate, 'EEEE', { locale: arDZ });

    // Combine the transformed date and day name
    return `${dayName} \n ${transformedDate}  `;
  }
}

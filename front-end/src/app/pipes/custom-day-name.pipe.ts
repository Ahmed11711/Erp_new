import { DatePipe } from '@angular/common';
import { Pipe, PipeTransform } from '@angular/core';
import * as dateFns from 'date-fns';
import { arDZ } from 'date-fns/locale';

@Pipe({
  name: 'customDayName'
})
export class CustomDayNamePipe implements PipeTransform {

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
    const dayName = dateFns.format(inputDate, 'EEEE', { locale: arDZ });

    // Combine the transformed date and day name
    return `${dayName}`;
  }

}

import { Pipe, PipeTransform } from '@angular/core';
import { format } from 'date-fns';

@Pipe({
  name: 'arabicDayDate',
})
export class ArabicDayDatePipe implements PipeTransform {
  transform(date: Date): string {
    const arabicDays = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const dayIndex = date.getDay();
    const arabicDay = arabicDays[dayIndex];

    const formattedDate = format(date, 'd/M/yyyy');

    return `${arabicDay} ${formattedDate}`;
  }
}

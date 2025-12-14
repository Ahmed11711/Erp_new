import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'fixedTime'
})
export class FixedTimePipe implements PipeTransform {

  transform(value: string): string {
    // Check if the input value is a valid string
    if (typeof value !== 'string') {
      return value;
    }

    // Split the input string by the colon to separate hours and minutes
    let parts = value.split(':');

    // Check if we have the correct format (hours and minutes)
    if (parts.length !== 2) {
      return value;
    }

    // Get the hours part
    let hours = parts[0];

    // Get the minutes part and remove the fractional part if present
    let minutesParts = parts[1].split('.');
    let minutes = minutesParts[0];

    // Return the formatted string
    return `${hours}:${minutes}`;
  }
}

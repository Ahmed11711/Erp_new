import { MatDateFormats } from '@angular/material/core';

/**
 * Use the 'input' token so CustomDateAdapter.format/parse handle DD/MM/YYYY
 * instead of Intl/Date.parse (which causes MM/DD swaps on manual typing).
 */
export const APP_DATE_FORMATS: MatDateFormats = {
  parse: {
    dateInput: 'input',
  },
  display: {
    dateInput: 'input',
    monthYearLabel: { year: 'numeric', month: 'short' },
    dateA11yLabel: { year: 'numeric', month: 'long', day: 'numeric' },
    monthYearA11yLabel: { year: 'numeric', month: 'long' },
  },
};

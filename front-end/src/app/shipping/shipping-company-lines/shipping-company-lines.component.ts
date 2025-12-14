import { Component } from '@angular/core';
import { CalendarOptions } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

@Component({
  selector: 'app-shipping-company-lines',
  templateUrl: './shipping-company-lines.component.html',
  styleUrls: ['./shipping-company-lines.component.css']
})
export class ShippingCompanyLinesComponent {

  calendarOptions: CalendarOptions = {
    initialView: 'dayGridMonth',
    plugins: [dayGridPlugin, interactionPlugin],
    dateClick: (arg) => this.handleDateClick(arg),
    events: this.generateEvents(),
  };

  generateEvents() {
    const events:any = [
      { title: '', date: '2024-03-20' , color :'green' , display: 'background' },
      { title: '', date: '2024-03-18' , color :'red' , display: 'background' },
    ];
    return events;
  }

  handleDateClick(arg) {
    alert('date click! ' + arg.dateStr)
  }

}

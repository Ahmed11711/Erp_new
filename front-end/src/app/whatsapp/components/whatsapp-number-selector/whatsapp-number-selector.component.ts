import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { MatSelectChange } from '@angular/material/select';
import { WhatsAppService } from '../../services/whatsapp.service';

@Component({
  selector: 'app-whatsapp-number-selector',
  templateUrl: './whatsapp-number-selector.component.html',
  styleUrls: ['./whatsapp-number-selector.component.css']
})
export class WhatsappNumberSelectorComponent implements OnInit {
  @Input() selectedPhoneNumberId: string = '';
  @Output() phoneNumberChange = new EventEmitter<string>();
  
  availablePhoneNumbers: any[] = [];
  loading = false;

  constructor(private whatsappService: WhatsAppService) {}

  ngOnInit(): void {
    this.loadUserPhoneNumbers();
  }

  loadUserPhoneNumbers(): void {
    this.loading = true;
    this.whatsappService.getUserPhoneNumbers().subscribe({
      next: (response) => {
        this.availablePhoneNumbers = response.data || [];
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading user phone numbers:', error);
        this.loading = false;
      }
    });
  }

  onPhoneNumberChange(event: MatSelectChange): void {
    this.selectedPhoneNumberId = event.value;
    this.phoneNumberChange.emit(event.value);
  }

  refreshPhoneNumbers(): void {
    this.loadUserPhoneNumbers();
  }
}

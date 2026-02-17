import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';

import { WhatsAppRoutingModule } from './whatsapp-routing.module';
import { ChatPageComponent } from './components/chat-page/chat-page.component';
import { DialogWhatsAppMessageComponent } from './components/dialog-whatsapp-message/dialog-whatsapp-message.component';
import { WhatsAppService } from './services/whatsapp.service';

@NgModule({
  declarations: [
    ChatPageComponent,
    DialogWhatsAppMessageComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatTooltipModule,
    MatInputModule,
    MatFormFieldModule,
    MatSelectModule,
    WhatsAppRoutingModule
  ],
  providers: [
    WhatsAppService
  ],
  exports: [
    DialogWhatsAppMessageComponent
  ]
})
export class WhatsAppModule { }

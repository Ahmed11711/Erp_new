import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ChatPageComponent } from './components/chat-page/chat-page.component';

const routes: Routes = [
  {
    path: 'chat',
    component: ChatPageComponent
  },
  {
    path: 'chat/:customerId',
    component: ChatPageComponent
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class WhatsAppRoutingModule { }

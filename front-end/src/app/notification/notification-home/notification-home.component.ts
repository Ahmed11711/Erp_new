import { Component, OnInit } from '@angular/core';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-notification-home',
  templateUrl: './notification-home.component.html',
  styleUrls: ['./notification-home.component.css']
})
export class NotificationHomeComponent implements OnInit{

  user!:string;

  constructor(private authService:AuthService){}

  ngOnInit(): void {
      this.user = this.authService.getUser();
  }


}

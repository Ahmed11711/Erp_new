import { Component } from '@angular/core';
import { NotificationService } from '../notification/service/notification.service';
import { AuthService } from '../auth/auth.service';
import { CategoryService } from '../categories/services/category.service';

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent {
  notifications: any[] = [];
  user!:string;

  constructor(private authService:AuthService , private categoryService:CategoryService){}

  ngOnInit(): void {
    this.user = this.authService.getUser();
  }


}

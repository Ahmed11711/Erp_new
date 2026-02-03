import { Component } from '@angular/core';
import { NotificationService } from '../notification/service/notification.service';
import { AuthService } from '../auth/auth.service';
import { CategoryService } from '../categories/services/category.service';
import { ServiceAccountsService } from '../financial/services/service-accounts.service';

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent {
  notifications: any[] = [];
  user!: string;

  serviceAccounts: any[] = [];

  constructor(private authService: AuthService, private categoryService: CategoryService, private serviceAccountsService: ServiceAccountsService) { }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.getServiceAccounts();
  }

  getServiceAccounts() {
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = res;
    });
  }


}

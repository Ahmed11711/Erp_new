import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { HTTP_INTERCEPTORS, HttpClientModule } from '@angular/common/http';
import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { RouterModule } from '@angular/router';
import { DashboardComponent } from './dashboard/dashboard.component';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { FormsModule } from '@angular/forms';
import {MatDialogModule} from '@angular/material/dialog';
import {MatExpansionModule} from '@angular/material/expansion';
import { SidebarComponent } from './sidebar/sidebar.component';
import { SnackBarComponent } from './shared/snack-bar/snack-bar.component';
import {MatSnackBarModule} from '@angular/material/snack-bar';
import {MatListModule} from '@angular/material/list';

import { MatButtonModule } from '@angular/material/button';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatMenuModule } from '@angular/material/menu';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatTabsModule } from '@angular/material/tabs';
import { TokenInterceptor } from './token.interceptor';
import { HomeComponent } from './home/home.component';
import { SharedModule } from './shared/shared.module';

import { NotificationService } from './notification/service/notification.service';
import { LoadingService } from './loading.service';
import { LoadingSpinnerComponent } from './loading-spinner/loading-spinner.component';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { LeadStatusService } from './services/lead-status.service';
import { DateAdapter, MAT_DATE_FORMATS, MAT_DATE_LOCALE } from '@angular/material/core';
import { CustomDateAdapter } from './shared/date/custom-date-adapter';
import { APP_DATE_FORMATS } from './shared/date/date-formats';
 



@NgModule({
  declarations: [
    AppComponent,
    DashboardComponent,
    SidebarComponent,
    SnackBarComponent,
    // HomeComponent,
    LoadingSpinnerComponent,
   ],
  imports: [
    MatTooltipModule,
    MatIconModule,
    MatToolbarModule,
    MatMenuModule,
    MatSidenavModule,
    MatButtonModule,
    MatListModule,
    MatSnackBarModule,
    MatExpansionModule,
    FormsModule,
    RouterModule,
    BrowserModule,
    AppRoutingModule,
    MatDialogModule,
    HttpClientModule,
    BrowserAnimationsModule,
    SharedModule,
    NgxPaginationModule,
    MatPaginatorModule,
    MatTabsModule,
  ],
  providers: [
    LoadingService,
    {
      provide: HTTP_INTERCEPTORS,
      useClass: TokenInterceptor,
      multi: true
    },
    NotificationService,
    LeadStatusService,
    { provide: MAT_DATE_LOCALE, useValue: 'en-GB' },
    { provide: DateAdapter, useClass: CustomDateAdapter },
    { provide: MAT_DATE_FORMATS, useValue: APP_DATE_FORMATS },
  ],
  bootstrap: [AppComponent]
})
export class AppModule {}

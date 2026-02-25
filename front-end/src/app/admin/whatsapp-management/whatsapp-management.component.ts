import { Component, OnInit } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { WhatsAppService } from '../../whatsapp/services/whatsapp.service';
import { UsersService } from '../../services/users.service';

@Component({
  selector: 'app-whatsapp-management',
  templateUrl: './whatsapp-management.component.html',
  styleUrls: ['./whatsapp-management.component.css']
})
export class WhatsappManagementComponent implements OnInit {
  availablePhoneNumbers: any[] = [];
  assignments: any[] = [];
  users: any[] = [];
  selectedUsers: { [key: string]: number[] } = {};
  loading = false;

  constructor(
    private whatsappService: WhatsAppService,
    private usersService: UsersService,
    private snackBar: MatSnackBar
  ) {}

  ngOnInit(): void {
    this.loadAvailablePhoneNumbers();
    this.loadAssignments();
    this.loadUsers();
  }

  loadAvailablePhoneNumbers(): void {
    this.loading = true;
    this.whatsappService.getAvailablePhoneNumbers().subscribe({
      next: (response) => {
        this.availablePhoneNumbers = response.data || [];
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading phone numbers:', error);
        this.snackBar.open('فشل في تحميل أرقام الواتساب', 'إغلاق', {
          duration: 3000,
          panelClass: ['error-snackbar']
        });
        this.loading = false;
      }
    });
  }

  loadAssignments(): void {
    this.loading = true;
    this.whatsappService.getAllAssignments().subscribe({
      next: (response) => {
        this.assignments = response.data || [];
        this.initializeSelectedUsers();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading assignments:', error);
        this.snackBar.open('فشل في تحميل التعيينات', 'إغلاق', {
          duration: 3000,
          panelClass: ['error-snackbar']
        });
        this.loading = false;
      }
    });
  }

  initializeSelectedUsers(): void {
    this.availablePhoneNumbers.forEach(phoneNumber => {
      const assignedUsers = this.getAssignedUsersForPhoneNumber(phoneNumber.id);
      this.selectedUsers[phoneNumber.id] = assignedUsers.map(u => u.id);
    });
  }

  loadUsers(): void {
    this.loading = true;
    this.usersService.getUsers().subscribe({
      next: (response) => {
        this.users = response.data || response || [];
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading users:', error);
        this.snackBar.open('فشل في تحميل المستخدمين', 'إغلاق', {
          duration: 3000,
          panelClass: ['error-snackbar']
        });
        this.loading = false;
        // Fallback to mock data if API fails
        this.users = [
          { id: 1, name: 'Admin', email: 'admin@example.com' },
          { id: 2, name: 'User 1', email: 'user1@example.com' },
          { id: 3, name: 'User 2', email: 'user2@example.com' },
          { id: 4, name: 'User 3', email: 'user3@example.com' },
          { id: 5, name: 'User 4', email: 'user4@example.com' },
        ];
      }
    });
  }

  onSelectionChange(phoneNumberId: string, event: any): void {
    this.selectedUsers[phoneNumberId] = event.value;
  }

  assignUsersToPhoneNumber(phoneNumberId: string): void {
    const selectedUserIds = this.selectedUsers[phoneNumberId];
    
    if (!selectedUserIds || selectedUserIds.length === 0) {
      this.snackBar.open('يرجى اختيار مستخدم واحد على الأقل', 'إغلاق', {
        duration: 3000,
        panelClass: ['warning-snackbar']
      });
      return;
    }

    this.loading = true;
    this.whatsappService.assignUsersToPhoneNumber({
      phone_number_id: phoneNumberId,
      user_ids: selectedUserIds
    }).subscribe({
      next: (response) => {
        this.snackBar.open('تم تعيين المستخدمين بنجاح', 'إغلاق', {
          duration: 3000,
          panelClass: ['success-snackbar']
        });
        this.loadAssignments(); // Reload assignments to show updated data
        this.loading = false;
      },
      error: (error) => {
        console.error('Error assigning users:', error);
        this.snackBar.open('فشل في تعيين المستخدمين', 'إغلاق', {
          duration: 3000,
          panelClass: ['error-snackbar']
        });
        this.loading = false;
      }
    });
  }

  removeUserAssignment(phoneNumberId: string, userId: number): void {
    this.loading = true;
    this.whatsappService.removeUserAssignment({
      phone_number_id: phoneNumberId,
      user_id: userId
    }).subscribe({
      next: (response) => {
        this.snackBar.open('تم إزالة تعيين المستخدم بنجاح', 'إغلاق', {
          duration: 3000,
          panelClass: ['success-snackbar']
        });
        this.loadAssignments(); // Reload assignments to show updated data
        this.loading = false;
      },
      error: (error) => {
        console.error('Error removing assignment:', error);
        this.snackBar.open('فشل في إزالة تعيين المستخدم', 'إغلاق', {
          duration: 3000,
          panelClass: ['error-snackbar']
        });
        this.loading = false;
      }
    });
  }

  getAssignedUsersForPhoneNumber(phoneNumberId: string): any[] {
    const assignment = this.assignments.find(a => a.phone_number_id === phoneNumberId);
    return assignment ? assignment.assigned_users : [];
  }

  getUnassignedUsersForPhoneNumber(phoneNumberId: string): any[] {
    const assignedUsers = this.getAssignedUsersForPhoneNumber(phoneNumberId);
    const assignedUserIds = assignedUsers.map(u => u.id);
    return this.users.filter(user => !assignedUserIds.includes(user.id));
  }

  isPhoneNumberConfigured(phoneNumberId: string): boolean {
    const phoneNumber = this.availablePhoneNumbers.find(p => p.id === phoneNumberId);
    return phoneNumber ? phoneNumber.is_configured : false;
  }

  refreshData(): void {
    this.loadAvailablePhoneNumbers();
    this.loadAssignments();
    this.loadUsers();
  }

  // Helper method to get user display info
  getUserDisplayInfo(user: any): string {
    if (user.email && user.email !== 'undefined') {
      return `${user.name} (${user.email})`;
    }
    return user.name;
  }

  // Clear selection for a specific phone number
  clearSelection(phoneNumberId: string): void {
    this.selectedUsers[phoneNumberId] = [];
  }
}

import { Component, OnInit } from '@angular/core';
import { LeadStatusService } from '../services/lead-status.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-lead-status-management',
  templateUrl: './lead-status-management.component.html',
  styleUrls: ['./lead-status-management.component.css']
})
export class LeadStatusManagementComponent implements OnInit {
  leadStatuses: any[] = [];
  isLoading = false;
  editingStatus: any = null;
  isModalOpen = false;

  // Form data
  formData = {
    name: '',
    description: '',
    order: 1,
    color: '#6c757d',
    requires_follow_up: false
  };

  constructor(private leadStatusService: LeadStatusService) {}

  ngOnInit(): void {
    this.loadLeadStatuses();
  }

  loadLeadStatuses(): void {
    this.isLoading = true;
    this.leadStatusService.getAllStatuses().subscribe({
      next: (data) => {
        this.leadStatuses = data.sort((a, b) => a.order - b.order);
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading lead statuses:', error);
        this.isLoading = false;
        Swal.fire({
          title: 'Error',
          text: 'Failed to load lead statuses',
          icon: 'error'
        });
      }
    });
  }

  openModal(status: any = null): void {
    this.isModalOpen = true;
    this.editingStatus = status;
    
    if (status) {
      this.formData = {
        name: status.name,
        description: status.description || '',
        order: status.order,
        color: status.color || '#6c757d',
        requires_follow_up: status.requires_follow_up || false
      };
    } else {
      this.formData = {
        name: '',
        description: '',
        order: this.leadStatuses.length + 1,
        color: '#6c757d',
        requires_follow_up: false
      };
    }
  }

  closeModal(): void {
    this.isModalOpen = false;
    this.editingStatus = null;
    this.formData = {
      name: '',
      description: '',
      order: 1,
      color: '#6c757d',
      requires_follow_up: false
    };
  }

  saveStatus(): void {
    if (!this.formData.name.trim()) {
      Swal.fire({
        title: 'Validation Error',
        text: 'Status name is required',
        icon: 'warning'
      });
      return;
    }

    const operation = this.editingStatus 
      ? this.leadStatusService.updateStatus(this.editingStatus.id, this.formData)
      : this.leadStatusService.createStatus(this.formData);

    operation.subscribe({
      next: (response) => {
        Swal.fire({
          title: 'Success',
          text: this.editingStatus ? 'Status updated successfully' : 'Status created successfully',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
        this.closeModal();
        this.loadLeadStatuses();
      },
      error: (error) => {
        console.error('Error saving status:', error);
        Swal.fire({
          title: 'Error',
          text: error.error?.message || 'Failed to save status',
          icon: 'error'
        });
      }
    });
  }

  deleteStatus(status: any): void {
    Swal.fire({
      title: 'Are you sure?',
      text: `Are you sure you want to delete "${status.name}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        this.leadStatusService.deleteStatus(status.id).subscribe({
          next: () => {
            Swal.fire({
              title: 'Deleted!',
              text: 'Status has been deleted.',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.loadLeadStatuses();
          },
          error: (error) => {
            console.error('Error deleting status:', error);
            Swal.fire({
              title: 'Error',
              text: error.error?.message || 'Failed to delete status',
              icon: 'error'
            });
          }
        });
      }
    });
  }

  moveStatusUp(status: any): void {
    const currentIndex = this.leadStatuses.findIndex(s => s.id === status.id);
    if (currentIndex > 0) {
      const previousStatus = this.leadStatuses[currentIndex - 1];
      
      // Swap orders
      const tempOrder = status.order;
      status.order = previousStatus.order;
      previousStatus.order = tempOrder;
      
      // Update both statuses
      this.updateStatusOrder(status);
      this.updateStatusOrder(previousStatus);
    }
  }

  moveStatusDown(status: any): void {
    const currentIndex = this.leadStatuses.findIndex(s => s.id === status.id);
    if (currentIndex < this.leadStatuses.length - 1) {
      const nextStatus = this.leadStatuses[currentIndex + 1];
      
      // Swap orders
      const tempOrder = status.order;
      status.order = nextStatus.order;
      nextStatus.order = tempOrder;
      
      // Update both statuses
      this.updateStatusOrder(status);
      this.updateStatusOrder(nextStatus);
    }
  }

  private updateStatusOrder(status: any): void {
    this.leadStatusService.updateStatus(status.id, { order: status.order }).subscribe({
      error: (error) => {
        console.error('Error updating status order:', error);
      }
    });
  }

  getStatusColor(status: any): string {
    return status.color || '#6c757d';
  }

  isFollowUpStatus(status: any): boolean {
    return status.requires_follow_up;
  }
}

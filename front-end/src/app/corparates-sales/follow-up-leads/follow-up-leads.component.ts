import { Component, OnInit } from '@angular/core';
import { LeadStatusService } from '../../services/lead-status.service';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-follow-up-leads',
  templateUrl: './follow-up-leads.component.html',
  styleUrls: ['./follow-up-leads.component.css']
})
export class FollowUpLeadsComponent implements OnInit {
  followUpLeads: any[] = [];
  isLoading = false;
  today = new Date().toISOString().split('T')[0];

  constructor(
    private leadStatusService: LeadStatusService,
    private corparatesSalesService: CorparatesSalesService
  ) {}

  ngOnInit(): void {
    this.loadFollowUpLeads();
  }

  loadFollowUpLeads(): void {
    this.isLoading = true;
    this.leadStatusService.getFollowUpLeads().subscribe({
      next: (data) => {
        this.followUpLeads = data;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading follow-up leads:', error);
        this.isLoading = false;
        Swal.fire({
          title: 'Error',
          text: 'Failed to load follow-up leads',
          icon: 'error'
        });
      }
    });
  }

  getFollowUpStatusText(lead: any): string {
    if (!lead.next_action_date) return '';
    
    const actionDate = new Date(lead.next_action_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    actionDate.setHours(0, 0, 0, 0);
    
    if (actionDate < today) {
      return 'Overdue';
    } else if (actionDate.getTime() === today.getTime()) {
      return 'Today';
    } else {
      return 'Upcoming';
    }
  }

  getFollowUpStatusClass(lead: any): string {
    const status = this.getFollowUpStatusText(lead);
    switch (status) {
      case 'Overdue':
        return 'badge bg-danger';
      case 'Today':
        return 'badge bg-warning';
      case 'Upcoming':
        return 'badge bg-info';
      default:
        return 'badge bg-secondary';
    }
  }

  updateLeadStatus(lead: any, newStatusId: number): void {
    const statusData = {
      lead_status_id: newStatusId,
      next_action_type: null,
      next_action_date: null,
      next_action_notes: null
    };

    this.leadStatusService.updateLeadStatus(lead.id, statusData).subscribe({
      next: () => {
        Swal.fire({
          title: 'Success',
          text: 'Lead status updated successfully',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
        this.loadFollowUpLeads();
      },
      error: (error) => {
        console.error('Error updating lead status:', error);
        Swal.fire({
          title: 'Error',
          text: 'Failed to update lead status',
          icon: 'error'
        });
      }
    });
  }

  completeFollowUp(lead: any): void {
    Swal.fire({
      title: 'Complete Follow-Up',
      text: 'Mark this follow-up as completed?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, complete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        // Move to "Contacted" status
        const contactedStatus = this.getStatusByName('Contacted');
        if (contactedStatus) {
          this.updateLeadStatus(lead, contactedStatus.id);
        }
      }
    });
  }

  private getStatusByName(statusName: string): any {
    // This would need to be implemented or fetched from the service
    return null;
  }
}

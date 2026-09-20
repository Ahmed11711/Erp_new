import { Component, OnInit } from '@angular/core';
import { ProcessingService } from '../services/processing.service';

@Component({
  selector: 'app-processing-dashboard',
  templateUrl: './processing-dashboard.component.html',
  styleUrls: ['./processing-dashboard.component.css'],
})
export class ProcessingDashboardComponent implements OnInit {
  kpis: any = {};
  materials: any[] = [];
  loading = true;

  constructor(private api: ProcessingService) {}

  ngOnInit(): void {
    this.api.kpis().subscribe({
      next: (k) => {
        this.kpis = k || {};
        this.loading = false;
      },
      error: () => (this.loading = false),
    });
    this.api.materialsAtVendor().subscribe({
      next: (rows) => (this.materials = rows || []),
    });
  }
}

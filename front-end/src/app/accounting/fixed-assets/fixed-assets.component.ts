import { Component, OnInit } from '@angular/core';
import { AssetService } from '../../financial/services/asset.service';

@Component({
  selector: 'app-fixed-assets',
  templateUrl: './fixed-assets.component.html',
  styleUrls: ['./fixed-assets.component.css']
})
export class FixedAssetsComponent implements OnInit {
  assets: any[] = [];
  filteredAssets: any[] = [];
  loading: boolean = false;
  searchQuery: string = '';
  totalAssetsValue: number = 0;

  constructor(private assetService: AssetService) { }

  ngOnInit(): void {
    this.loadAssets();
  }

  loadAssets() {
    this.loading = true;
    this.assetService.data().subscribe({
      next: (response) => {
        // The controller returns the array directly or in a data property.
        // AssetController index returns `Asset::all()` directly (array).
        // Let's handle both just in case.
        this.assets = Array.isArray(response) ? response : (response.data || []);
        this.filterAssets();
        this.loading = false;
      },
      error: (err) => {
        console.error('Error loading assets', err);
        this.loading = false;
      }
    });
  }

  filterAssets() {
    this.filteredAssets = this.assets.filter(asset => {
      const matchQuery = !this.searchQuery ||
        (asset.name && asset.name.toLowerCase().includes(this.searchQuery.toLowerCase())) ||
        (asset.code && asset.code.toString().includes(this.searchQuery));

      return matchQuery;
    });

    this.calculateTotal();
  }

  calculateTotal() {
    // Asset model uses 'purchase_price' or 'current_value'
    this.totalAssetsValue = this.filteredAssets.reduce((sum, asset) => sum + (parseFloat(asset.purchase_price) || 0), 0);
  }

  refresh() {
    this.loadAssets();
  }
}


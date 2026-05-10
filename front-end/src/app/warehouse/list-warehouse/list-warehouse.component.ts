import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { StockService } from '../services/stock.service';
import { CategoryService } from 'src/app/categories/services/category.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogComponent } from '../dialog/dialog.component';
import { WAREHOUSE_STOCK_ROWS } from 'src/app/shared/constants/warehouse-stock-rows';

@Component({
  selector: 'app-list-warehouse',
  templateUrl: './list-warehouse.component.html',
  styleUrls: ['./list-warehouse.component.css']
})
export class ListWarehouseComponent implements OnInit {

  /**
   * الرصيد القيمي: مجموع total_price أو sell_total_price حسب المخزن (من warehouse_balance).
   * إجمالي الكمية: مجموع categories.quantity لكل مخزن (quantity_totals) — يعكس الجرد حتى عند تكلفة 0.
   */
  data: any[] = [];
  url = '';
  loading = false;
  loadError = '';

  constructor(
    private matDialog: MatDialog,
    private router: Router,
    private stockService: StockService,
    private categoryService: CategoryService
  ) {}

  ngOnInit(): void {
    const currentUrl = this.router.url;
    const lastIndex = currentUrl.lastIndexOf('/');
    this.url = currentUrl.slice(lastIndex + 1);
    this.getData();
  }

  getData(): void {
    this.loading = true;
    this.loadError = '';
    forkJoin({
      balances: this.categoryService.warehousebalance(),
      stocks: this.stockService.list().pipe(
        catchError(() => of({ data: { data: [] } }))
      )
    }).subscribe({
      next: ({ balances, stocks }) => {
        const stockList = this.stockService.parseListResponse(stocks);
        const byName = new Map<string, any>(stockList.map((s: any) => [s.name, s]));
        const qtyTotals =
          balances != null && typeof balances === 'object' && balances['quantity_totals']
            ? (balances['quantity_totals'] as Record<string, number>)
            : {};
        this.data = WAREHOUSE_STOCK_ROWS.map(({ nameAr, keyEn }) => {
          const s = byName.get(nameAr);
          const rawVal = balances != null ? (balances as any)[keyEn] : undefined;
          const categorySum =
            rawVal !== undefined && rawVal !== null ? Number(rawVal) : 0;
          const quantityTotal =
            qtyTotals[keyEn] !== undefined && qtyTotals[keyEn] !== null
              ? Number(qtyTotals[keyEn])
              : 0;
          const hasStockRow = s != null;
          const stockBal =
            hasStockRow && s.balance !== undefined && s.balance !== null
              ? Number(s.balance)
              : null;
          return {
            name: nameAr,
            balance: categorySum,
            quantityTotal,
            categorySum,
            stockBalance: stockBal,
            id: s?.id,
            asset_id: s?.asset_id,
            asset_name: s?.asset_name ?? null
          };
        });
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.loadError = err?.error?.message || 'تعذر تحميل أرصدة المخازن';
        this.data = [];
      }
    });
  }

openDialog(data: Record<string, unknown> = {}) {
    const dialogRef = this.matDialog.open(DialogComponent, {
      data: {
        ...data,
        lockWarehouseName: !!(data as { name?: string; id?: number }).name && !(data as { id?: number }).id,
      },
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.getData();
      }
    });
  }

  deleteWarehouse(id:number){
    this.stockService.delete(id).subscribe(res=>{
      if (res) {
        this.getData();
      }
    })
  }

  /** mat-menu-item + routerLink often drops queryParams; navigate explicitly. */
  openWarehouseDetails(elm: { name: string; balance?: number }) {
    this.router.navigate(['/dashboard/warehouse/cat'], {
      queryParams: { warehouse: elm.name, balance: elm.balance }
    });
  }

  openWarehouseTransfers(elm: { name: string }) {
    this.router.navigate(['/dashboard/warehouse/cat'], {
      queryParams: { warehouse: elm.name }
    });
  }


}

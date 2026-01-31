import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CostCenterService } from '../services/cost-center.service';
import { EmployeeService } from '../../hr/services/employee.service';
import { CostCenter } from '../interfaces/cost-center.interface';

@Component({
  selector: 'app-cost-centers',
  templateUrl: './cost-centers.component.html',
  styleUrls: ['./cost-centers.component.css']
})
export class CostCentersComponent implements OnInit {
  costCenters: CostCenter[] = [];
  treeData: CostCenter[] = [];
  employees: any[] = [];
  loading = false;
  showAddDialog = false;
  showEditDialog = false;
  viewMode: 'tree' | 'list' = 'tree';
  selectedCostCenter: CostCenter | null = null;
  expandedNodes: Set<number> = new Set();
  searchTerm = '';

  costCenterTypes = [
    { value: 'main', label: 'رئيسي' },
    { value: 'sub', label: 'فرعي' }
  ];

  newCostCenter: CostCenter = {
    name: '',
    type: 'main',
    value: 0
  };

  constructor(
    private costCenterService: CostCenterService,
    private employeeService: EmployeeService,
    private route: ActivatedRoute
  ) { }

  ngOnInit(): void {
    this.loadEmployees();
    this.loadEmployees();

    // Check route data
    this.route.data.subscribe(data => {
      if (data['viewMode']) {
        this.viewMode = data['viewMode'];
      }
      if (data['action'] === 'create') {
        // We open dialog, but we also need a background view. Defaulting to 'list' for create page usually works well.
        // Or we can default to 'tree'. Let's keep existing viewMode or default to tree if not set.
        this.openAddDialog();
      }
    });

    this.loadCostCenters();
  }

  loadEmployees(): void {
    this.employeeService.data().subscribe({
      next: (response: any) => {
        if (response && response.data) {
          this.employees = Array.isArray(response.data) ? response.data : [];
        } else if (Array.isArray(response)) {
          this.employees = response;
        }
      },
      error: (error) => {
        console.error('Error loading employees:', error);
        this.employees = [];
      }
    });
  }

  loadCostCenters(): void {
    this.loading = true;
    if (this.viewMode === 'tree') {
      this.costCenterService.getTree().subscribe({
        next: (response) => {
          this.treeData = Array.isArray(response) ? response : [];
          this.costCenters = this.flattenTree(this.treeData);
          this.loading = false;
        },
        error: (error) => {
          console.error('Error loading cost centers:', error);
          this.treeData = [];
          this.costCenters = [];
          this.loading = false;
        }
      });
    } else {
      this.costCenterService.getAll({ per_page: 100 }).subscribe({
        next: (response) => {
          if (response.data) {
            this.costCenters = Array.isArray(response.data) ? response.data : [response.data];
          } else if (Array.isArray(response)) {
            this.costCenters = response;
          } else {
            this.costCenters = [];
          }
          this.buildTree();
          this.loading = false;
        },
        error: (error) => {
          console.error('Error loading cost centers:', error);
          this.costCenters = [];
          this.treeData = [];
          this.loading = false;
        }
      });
    }
  }

  flattenTree(tree: CostCenter[]): CostCenter[] {
    let result: CostCenter[] = [];
    tree.forEach(node => {
      result.push(node);
      if (node.children && node.children.length > 0) {
        result = result.concat(this.flattenTree(node.children));
      }
    });
    return result;
  }

  buildTree(): void {
    const costCenterMap = new Map<number, CostCenter>();
    const rootCostCenters: CostCenter[] = [];

    this.costCenters.forEach(cc => {
      costCenterMap.set(cc.id!, { ...cc, children: [] });
    });

    this.costCenters.forEach(cc => {
      const costCenterNode = costCenterMap.get(cc.id!);
      if (costCenterNode) {
        if (cc.parent_id) {
          const parent = costCenterMap.get(cc.parent_id);
          if (parent && parent.children) {
            parent.children!.push(costCenterNode);
          }
        } else {
          rootCostCenters.push(costCenterNode);
        }
      }
    });

    this.treeData = rootCostCenters;
  }

  toggleNode(nodeId: number): void {
    if (this.expandedNodes.has(nodeId)) {
      this.expandedNodes.delete(nodeId);
    } else {
      this.expandedNodes.add(nodeId);
    }
  }

  isExpanded(nodeId: number): boolean {
    return this.expandedNodes.has(nodeId);
  }

  switchView(mode: 'tree' | 'list'): void {
    this.viewMode = mode;
    this.loadCostCenters();
  }

  openAddDialog(parentId?: number): void {
    this.newCostCenter = {
      name: '',
      type: parentId ? 'sub' : 'main',
      value: 0,
      parent_id: parentId
    };
    this.showAddDialog = true;
  }

  openEditDialog(costCenter: CostCenter): void {
    this.selectedCostCenter = { ...costCenter };
    this.showEditDialog = true;
  }

  closeDialogs(): void {
    this.showAddDialog = false;
    this.showEditDialog = false;
    this.selectedCostCenter = null;
  }

  saveCostCenter(): void {
    if (!this.newCostCenter.name || !this.newCostCenter.type) {
      alert('الرجاء إدخال اسم مركز التكلفة ونوعه');
      return;
    }

    if (this.newCostCenter.type === 'sub' && !this.newCostCenter.parent_id) {
      alert('الرجاء اختيار المركز الرئيسي');
      return;
    }

    this.loading = true;
    this.costCenterService.create(this.newCostCenter).subscribe({
      next: (response) => {
        this.loadCostCenters();
        this.closeDialogs();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error creating cost center:', error);
        const errorMsg = error.error?.message || error.error?.errors || 'حدث خطأ أثناء إضافة مركز التكلفة';
        alert(errorMsg);
        this.loading = false;
      }
    });
  }

  updateCostCenter(): void {
    if (!this.selectedCostCenter) return;

    if (!this.selectedCostCenter.name) {
      alert('الرجاء إدخال اسم مركز التكلفة');
      return;
    }

    this.loading = true;
    const updateData = {
      name: this.selectedCostCenter.name,
      name_en: this.selectedCostCenter.name_en,
      responsible_person_id: this.selectedCostCenter.responsible_person_id,
      location: this.selectedCostCenter.location,
      phone: this.selectedCostCenter.phone,
      email: this.selectedCostCenter.email,
      start_date: this.selectedCostCenter.start_date,
      end_date: this.selectedCostCenter.end_date,
      duration: this.selectedCostCenter.duration,
      value: this.selectedCostCenter.value
    };

    this.costCenterService.update(this.selectedCostCenter.id!, updateData).subscribe({
      next: (response) => {
        this.loadCostCenters();
        this.closeDialogs();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error updating cost center:', error);
        const errorMsg = error.error?.message || error.error?.errors || 'حدث خطأ أثناء تحديث مركز التكلفة';
        alert(errorMsg);
        this.loading = false;
      }
    });
  }

  deleteCostCenter(costCenter: CostCenter): void {
    if (!confirm(`هل أنت متأكد من حذف مركز التكلفة "${costCenter.name}"؟`)) {
      return;
    }

    this.loading = true;
    this.costCenterService.delete(costCenter.id!).subscribe({
      next: (response) => {
        this.loadCostCenters();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error deleting cost center:', error);
        const errorMsg = error.error?.message || 'حدث خطأ أثناء حذف مركز التكلفة';
        alert(errorMsg);
        this.loading = false;
      }
    });
  }

  getEmployeeName(id?: number): string {
    if (!id) return '-';
    const employee = this.employees.find(emp => emp.id === id);
    return employee ? employee.name : '-';
  }

  getTypeLabel(type: string): string {
    const typeObj = this.costCenterTypes.find(t => t.value === type);
    return typeObj ? typeObj.label : type;
  }

  filterCostCenters(): CostCenter[] {
    if (!this.searchTerm) {
      return this.costCenters;
    }
    const term = this.searchTerm.toLowerCase();
    return this.costCenters.filter(cc =>
      cc.name?.toLowerCase().includes(term) ||
      cc.name_en?.toLowerCase().includes(term) ||
      cc.code?.toString().includes(term) ||
      cc.location?.toLowerCase().includes(term)
    );
  }

  getMainCostCenters(): CostCenter[] {
    return this.costCenters.filter(c => c.type === 'main');
  }
}

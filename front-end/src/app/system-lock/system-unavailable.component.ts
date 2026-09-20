import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { SharedModule } from '../shared/shared.module';
import { SystemLockService } from './system-lock.service';

type DummyOrder = {
  id: number;
  date: string;
  delivery: string;
  type: string;
  status: string;
  statusClass: string;
  governorate: string;
  city: string;
  customer: string;
  customerType: string;
  phone: string;
  cash: string;
  online: string;
  company: string;
  notify: number;
};

@Component({
  selector: 'app-system-unavailable',
  standalone: true,
  imports: [CommonModule, MatIconModule, SharedModule],
  templateUrl: './system-unavailable.component.html',
  styleUrls: ['./system-unavailable.component.css']
})
export class SystemUnavailableComponent {
  readonly dummyOrders: DummyOrder[] = this.buildOrders();

  constructor(public lock: SystemLockService) {}

  private buildOrders(): DummyOrder[] {
    const rows: Array<Omit<DummyOrder, 'id' | 'date' | 'delivery'>> = [
      { type: 'جديد', status: 'طلب مؤكد', statusClass: 'confirmed', governorate: 'القاهرة', city: 'مدينة نصر', customer: 'شركة الأمل للتوريدات', customerType: 'شركة', phone: '01000000000', cash: '1,250', online: '0', company: 'Mylerz', notify: 1 },
      { type: 'جديد', status: 'تم شحن', statusClass: 'shipped', governorate: 'الجيزة', city: 'الدقي', customer: 'أحمد محمود علي', customerType: 'افراد', phone: '01011223344', cash: '890', online: '200', company: 'Bosta', notify: 0 },
      { type: 'طلب صيانة', status: 'طلب جديد', statusClass: 'new', governorate: 'الإسكندرية', city: 'سيدي جابر', customer: 'سارة حسن', customerType: 'افراد', phone: '01155667788', cash: '0', online: '0', company: '—', notify: 2 },
      { type: 'جديد', status: 'تم التسليم', statusClass: 'delivered', governorate: 'القليوبية', city: 'بنها', customer: 'مؤسسة النور', customerType: 'شركة', phone: '01233445566', cash: '2,140', online: '0', company: 'Raya', notify: 0 },
      { type: 'طلب استبدال', status: 'مؤجل', statusClass: 'postponed', governorate: 'الشرقية', city: 'الزقازيق', customer: 'محمد عبد الله', customerType: 'افراد', phone: '01099887766', cash: '640', online: '0', company: 'Lifters', notify: 1 },
      { type: 'جديد', status: 'تم التحصيل', statusClass: 'collected', governorate: 'الدقهلية', city: 'المنصورة', customer: 'شركة البيان', customerType: 'شركة', phone: '01566778899', cash: '3,020', online: '500', company: 'Mylerz', notify: 0 },
      { type: 'جديد', status: 'شحن جزئي', statusClass: 'partshipped', governorate: 'المنوفية', city: 'شبين الكوم', customer: 'نادية كمال', customerType: 'افراد', phone: '01022334455', cash: '1,780', online: '0', company: 'Bosta', notify: 0 },
      { type: 'طلب مرتجع', status: 'رفض استلام', statusClass: 'refuse', governorate: 'البحيرة', city: 'دمنهور', customer: 'خالد إبراهيم', customerType: 'افراد', phone: '01144556677', cash: '510', online: '0', company: 'Raya', notify: 3 },
      { type: 'جديد', status: 'تم الاستلام', statusClass: 'received', governorate: 'أسيوط', city: 'أسيوط', customer: 'بيت الأثاث الحديث', customerType: 'شركة', phone: '01077665544', cash: '4,350', online: '1,000', company: 'Lifters', notify: 0 },
      { type: 'جديد', status: 'طلب مؤكد', statusClass: 'confirmed', governorate: 'سوهاج', city: 'سوهاج', customer: 'فاطمة السيد', customerType: 'افراد', phone: '01288990011', cash: '970', online: '0', company: 'Mylerz', notify: 1 },
    ];

    return rows.map((row, index) => ({
      ...row,
      id: 18421 + index,
      date: this.dateLabel(index),
      delivery: this.dateLabel(index - 2),
    }));
  }

  private dateLabel(daysAgo: number): string {
    const d = new Date();
    d.setDate(d.getDate() - daysAgo);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}-${mm}-${d.getFullYear()}`;
  }
}

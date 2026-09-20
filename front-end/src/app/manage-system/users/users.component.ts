import { Component } from '@angular/core';
import { UserService } from '../services/user.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-users',
  templateUrl: './users.component.html',
  styleUrls: ['./users.component.css']
})
export class UsersComponent {

  data:any[]=[];
  tableData:any[]=[];

  constructor(private userService:UserService){}

  ngOnInit(){
    this.getData()
  }

  getData(){
    return this.userService.data().subscribe((result:any)=>{
      this.data=result;
      this.tableData=result;
    });
  }

  search(e:any){
    this.tableData=this.data.filter(elm=>elm.name.toLowerCase().includes(e.target.value.toLowerCase()));
  }

  editData(){

  }

  changePassword(user: { id: number; name?: string; email?: string }): void {
    const label = user?.name || user?.email || ('#' + user.id);

    Swal.fire({
      title: 'تغيير كلمة المرور',
      html:
        `<p class="mb-2 text-muted">المستخدم: <strong>${this.escapeHtml(label)}</strong></p>` +
        `<input id="swal-new-password" class="swal2-input" type="password" placeholder="كلمة المرور الجديدة" autocomplete="new-password">` +
        `<input id="swal-confirm-password" class="swal2-input" type="password" placeholder="تأكيد كلمة المرور" autocomplete="new-password">`,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const password = (document.getElementById('swal-new-password') as HTMLInputElement)?.value || '';
        const passwordConfirmation = (document.getElementById('swal-confirm-password') as HTMLInputElement)?.value || '';

        if (!password || password.length < 6) {
          Swal.showValidationMessage('كلمة المرور يجب ألا تقل عن 6 أحرف');
          return false;
        }
        if (password !== passwordConfirmation) {
          Swal.showValidationMessage('تأكيد كلمة المرور غير متطابق');
          return false;
        }

        return { password, passwordConfirmation };
      }
    }).then((result: any) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }

      const { password, passwordConfirmation } = result.value;
      this.userService.changePassword(user.id, password, passwordConfirmation).subscribe({
        next: (res) => {
          Swal.fire({
            icon: 'success',
            title: res?.message || 'تم تغيير كلمة المرور بنجاح',
            timer: 2500,
            showConfirmButton: false
          });
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: 'فشل تغيير كلمة المرور',
            text: err?.error?.message || 'حدث خطأ غير متوقع'
          });
        }
      });
    });
  }

  private escapeHtml(value: string): string {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  deleteData(user: { id: number; name?: string; email?: string }): void {
    const label = user?.name || user?.email || ('#' + user.id);

    Swal.fire({
      title: 'تأكيد الحذف؟',
      html: `سيتم حذف المستخدم <strong>${this.escapeHtml(label)}</strong> نهائياً من جدول المستخدمين ولن يتمكن من الدخول.<br><small class="text-muted">البيانات والحركات المرتبطة به ستبقى كما هي.</small>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'لا',
      confirmButtonColor: '#dc2626',
    }).then((result: any) => {
      if (!result.isConfirmed) {
        return;
      }

      this.userService.deleteUser(user.id).subscribe({
        next: (res) => {
          const ok = res?.deleted === true
            || res?.legacy === 'deleted sucuessfully'
            || res === 'deleted sucuessfully';

          if (!ok && res?.message && res?.deleted === false) {
            Swal.fire({
              icon: 'error',
              title: 'تعذر الحذف',
              text: res.message
            });
            return;
          }

          this.getData();
          Swal.fire({
            icon: 'success',
            title: res?.message || 'تم حذف المستخدم بنجاح',
            timer: 2500,
            showConfirmButton: false,
          });
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: 'تعذر الحذف',
            text: err?.error?.message || 'فشل حذف المستخدم من جدول المستخدمين'
          });
        }
      });
    });
  }

}

import { HttpHeaders } from '@angular/common/http';

/** لا يُظهر غطاء «تحميل...» العام (طلبات خلفية / استطلاع / محادثة). */
export const WHATSAPP_SKIP_GLOBAL_LOADING = new HttpHeaders({
  'X-Skip-Global-Loading': '1',
});

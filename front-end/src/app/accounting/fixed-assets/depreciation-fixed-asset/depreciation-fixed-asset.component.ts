import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';

@Component({
    selector: 'app-depreciation-fixed-asset',
    templateUrl: './depreciation-fixed-asset.component.html',
    styleUrls: ['./depreciation-fixed-asset.component.css']
})
export class DepreciationFixedAssetComponent implements OnInit {
    logs: string[] = [];
    loading = false;

    constructor(private http: HttpClient) { }

    ngOnInit(): void {
    }

    runDepreciation() {
        if (!confirm('هل أنت متأكد من تشغيل الإهلاك؟ سيتم إنشاء قيود يومية.')) return;

        this.loading = true;
        this.logs = [];

        this.http.post<any>(`${environment.Url}/assets/run-depreciation`, {}).subscribe({
            next: (res) => {
                this.loading = false;
                this.logs = res.details || ['تم تشغيل الإهلاك بنجاح'];
                alert(res.message);
            },
            error: (err) => {
                this.loading = false;
                console.error(err);
                alert('حدث خطأ أثناء تشغيل الإهلاك');
            }
        });
    }
}

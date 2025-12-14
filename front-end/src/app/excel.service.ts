import { Injectable } from '@angular/core';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';

@Injectable({
  providedIn: 'root',
})
export class ExcelService {
  constructor() {}

  generateExcel(fileName: string, tableElement: HTMLElement, language: number) {
    try {
      const worksheet = XLSX.utils.table_to_sheet(tableElement);

      const columnWidthInExcelUnits = 2 * 7.5;
      const tableHeaders = tableElement.querySelectorAll('th');
      const columnCount = tableHeaders.length || 10;
      worksheet['!cols'] = new Array(columnCount).fill({ width: columnWidthInExcelUnits });

      const workbook:any = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');

      if (!workbook.Workbook) {
        workbook.Workbook = { Views: [{}] };
      }
      workbook.Workbook.Views[0].RTL = language === 1;

      const excelBuffer = XLSX.write(workbook, {
        bookType: 'xlsx',
        type: 'array',
      });

      const blob = new Blob([excelBuffer], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      });
      saveAs(blob, `${fileName}.xlsx`);
    } catch (error) {
      console.error('Error generating Excel file:', error);
    }
  }
}

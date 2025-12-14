import { Component, OnInit, AfterViewInit, ElementRef, Input, SimpleChanges } from '@angular/core';
import * as Highcharts from 'highcharts';

@Component({
  selector: 'app-report-categories-chart',
  templateUrl: './report-categories-chart.component.html',
  styleUrls: ['./report-categories-chart.component.css']
})
export class ReportCategoriesChartComponent implements OnInit, AfterViewInit {

  @Input() soldCategories:any []=[];
  data:any[]=[];

  constructor(private elementRef: ElementRef) { }

  ngOnInit(): void {
    this.data = this.soldCategories.map(item => [item.category_name, item.total_quantity_new]);
  }

  ngAfterViewInit(): void {
    this.renderChart();
  }
  ngOnChanges(changes: SimpleChanges) {
    if (changes.soldCategories) {
      this.updateChartData();
    }
  }
  updateChartData(): void{
    this.data = this.soldCategories
    .sort((a, b) => b.total_quantity_new - a.total_quantity_new)
    .slice(0, 8)
    .map(item => [item.category_name, item.total_quantity_new]);

    if (this.data.length > 0) {
      this.renderChart();
    }
  }

  renderChart(): void {
    Highcharts.chart(this.elementRef.nativeElement.querySelector('#container'), {
      chart: {
        type: 'column'
    },
    title:{
      text:''
    },
    subtitle: {
        text: '<h3 style="font-size:1.7rem;">تقرير مبيعات الأصناف</h3>'
    },
    xAxis: {
        type: 'category',
        labels: {
            // autoRotation: [-45, -90],
            // rotation:-45,
            style: {
                fontSize: '11px',
                fontFamily: 'Verdana, sans-serif'
            }
        }
    },
    yAxis: {
        min: 0,
        title: {
          text: '<h3 style="font-size:1.5rem;">الكمية</h3>'
        }
    },
    legend: {
        enabled: false
    },
    tooltip: {
        pointFormat: '<b>{point.y:.1f}</b>'
    },
    credits: {
      enabled: false
    },
    series: [{
        type:'column',
        name: 'Population',
        colors: [
          '#82225e', '#7e1d65', '#79186c', '#751373', '#701e7a', '#6c1981',
          '#671487', '#631f8e', '#5e1a95', '#5a159c', '#550fa3', '#510aa9',
          '#4c05b0', '#4800b7', '#4300be', '#3f00c5', '#3a00cb', '#3600d2',
          '#3100d9', '#2d00e0'
      ],
        colorByPoint: true,
        groupPadding: 0,
        data: this.data
        ,
        dataLabels: {
            enabled: true,
            // rotation: -90,
            color: '#FFFFFF',
            inside: true,
            verticalAlign: 'top',
            format: '{point.y:.1f}',
            y: 10,
            style: {
                fontSize: '13px',
                fontFamily: 'Verdana, sans-serif'
            }
        }
      }]
    })
  }
}

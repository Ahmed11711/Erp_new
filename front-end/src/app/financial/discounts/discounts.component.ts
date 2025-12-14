import { Component, OnInit } from '@angular/core';
import { CimmitmentService } from '../services/cimmitment.service';

@Component({
  selector: 'app-discounts',
  templateUrl: './discounts.component.html',
  styleUrls: ['./discounts.component.css']
})
export class DiscountsComponent implements OnInit{
  data:any[]=[];
  total:number = 0;
  constructor(private cimmitmentService:CimmitmentService){}

  ngOnInit(): void {
    this.cimmitmentService.data().subscribe((result:any)=>{
      this.data=result;
      result.forEach(elm=>{
        this.total+=elm.deserved_amount;
      })
    });
  }

}

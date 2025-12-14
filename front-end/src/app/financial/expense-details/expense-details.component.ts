import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from '../services/banks.service';
import { ExpenseKindService } from '../services/expense-kind.service';
import { ExpenseService } from '../services/expense.service';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-expense-details',
  templateUrl: './expense-details.component.html',
  styleUrls: ['./expense-details.component.css']
})
export class ExpenseDetailsComponent {

  id!:any
  data!:any;
  imgUrl!: string;

  constructor(private expenseKindService:ExpenseKindService, private bankService:BanksService , private expenseService:ExpenseService ,private route:Router , private router:ActivatedRoute){
    this.id = this.router.snapshot.params['id'];
    this.imgUrl = environment.imgUrl;
    }

  ngOnInit(): void {
    this.expenseService.getByID(this.id).subscribe(res=>{
      console.log(res);

      this.data = res;
    })

  }

}

import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-user',
  templateUrl: './add-user.component.html',
  styleUrls: ['./add-user.component.css']
})
export class AddUserComponent {

  errorform:boolean= false;
  errorMessage!:string;
  data:any[]=[];

  constructor(private userService:UserService , private route:Router){
    this.form.patchValue({
      'department' : 'الادارة'
    })
  }


  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'department' :new FormControl(null , [Validators.required ]),
    'email' :new FormControl(null , [Validators.required ]),
    'password' :new FormControl(null , [Validators.required ]),
    'role' :new FormControl(null),
  })

  submitform(){
    if (this.form.valid) {
      this.userService.add(this.form.value).subscribe((result:any)=>{
        if (result.access_token) {
          this.route.navigate(['/dashboard/system/users']);
        }
      },
      (error)=>{
        if(error.status === 422 && error.error.message === "The email has already been taken."){
          this.errorform = true;
          this.errorMessage = "هذا الاميل تم إضافته من قبل";
        }
      }
      )
    }
  }
}

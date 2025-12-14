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

  deleteData(id:number){
    Swal.fire({
      title: ' تاكيد الحذف ؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result:any) => {
      if (result.isConfirmed) {
        this.userService.deleteUser(id).subscribe(res=>{
          console.log(res);
          if (res == "deleted sucuessfully") {
            this.getData();
            Swal.fire({
              icon: 'success',
              timer: 3000,
              showConfirmButton:false,
            })
          }

        })

      }})

  }

}

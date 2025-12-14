import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
import { UserService } from 'src/app/manage-system/services/user.service';
import { OrderService } from 'src/app/shipping/services/order.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-trackings',
  templateUrl: './trackings.component.html',
  styleUrls: ['./trackings.component.css']
})
export class TrackingsComponent {

  dataList:any[]=[];
  userdata:any[]=[];

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(private OrderService:OrderService, private http:HttpClient, private userService:UserService){}

  ngOnInit(): void {
    this.form.valueChanges.subscribe(() => {
      this.getData();
    });

    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.form.patchValue({
      created_at: `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`,
    })
    this.getData();
    this.getUsers();
    this.geTrackingsAction();
  }


  getUsers(){
    this.userService.data().subscribe((res:any)=>this.userdata = res);
  }

  form:FormGroup = new FormGroup({
    created_at: new FormControl('0'),
    user_id: new FormControl('0'),
  });


  getData(){
    let params = {
      itemsPerPage:this.pageSize,page:this.page+1,...this.form.value
    }
    this.OrderService.getTrackings(params).subscribe((res:any)=>{
      this.dataList = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    });

  }

  undo(id){
    this.OrderService.undo(id).subscribe({
      next : (res) => {
        if (res) {
          Swal.fire({
            icon:'success',
            showConfirmButton: false,
            timer : 1500
          })
          this.getData();
        }
        console.log(res);

      }
    })
  }

  geTrackingsAction(){
    this.OrderService.getActions().subscribe((res:any)=>{
      console.log(res);

    });

  }


  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getData();
  }

}

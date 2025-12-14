import { Component } from '@angular/core';
import { EmployeeService } from 'src/app/hr/services/employee.service';
import { UserService } from '../services/user.service';
import { PermissionService } from '../services/permission.service';

@Component({
  selector: 'app-powers',
  templateUrl: './powers.component.html',
  styleUrls: ['./powers.component.css']
})
export class PowersComponent {

  products:any[]=[];
  catword:any="name"

  data:any[]=[];
  id: number = 0;

  showPerm:boolean=true;

  constructor(private userService:UserService, private permissionService:PermissionService){}

  ngOnInit(){
    this.getData();
  }

  getData(){
    this.userService.data().subscribe(result=>this.products=result);
  }


  productChange(e:any){
    this.id = e.id;
    this.userPermission(this.id)
  }

  resetData(e:any){
    this.userPerm = [];
    this.showPerm = true;
  }


  givePermission(permission){
    this.permissionService.givePermission(this.id , permission).subscribe(result=>{
      console.log(result);
    })
  }

  revokePermission(permission){
    this.permissionService.revokePermission(this.id , permission).subscribe(result=>{
      console.log(result);
    })
  }

  userPerm:any[]=[];
  userPermission(id){
    this.permissionService.userPermission(this.id).subscribe(result=>{
      this.userPerm = result.map(elm=>elm.toLowerCase());
      this.showPerm = false;
      console.log(this.userPerm);
    })
  }


  permission(e:any){

    if (e.target.value==="read warehouse") {
      const permission = {permission:"read warehouse"}
      if (e.target.checked) {
        this.givePermission(permission)
      } else {
        this.revokePermission(permission)
      }
    }

    if (e.target.value==="read Hr") {
      const permission = {permission:"read Hr"}
      if (e.target.checked) {
        this.givePermission(permission)
      } else {
        this.revokePermission(permission)
      }
    }
  }

}

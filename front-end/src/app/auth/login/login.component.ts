import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent {
errorMessage :any =null;
Display:boolean=false;
show = false;
public constructor(private login:AuthService,private router:Router) { }

  ngOnInit(){
    const token = this.login.getToken();
    if (token) {
      this.router.navigate(['/dashboard']);
    }
  }

  loginUser(form : any){
    if(form.invalid){
      return;
    }
    const loginForm = {
      email: form.value.email,
      password: form.value.password
    };

    this.login.login(loginForm).subscribe(
      (res:any)=>{
        console.log(res);

        this.login.saveTolocalStorage(res);
        this.router.navigate(['/dashboard']);
      },
      err=>{
        console.log(err);

      if(err.status==401){
        this.errorMessage= err.error.message.error[0];
        this.Display=true;
        setTimeout(() => {
          this.Display = false;
      }, 3000)
      }
    }

    )
  }
  showPassword(e) {
    this.show = e.target.checked;
}
}

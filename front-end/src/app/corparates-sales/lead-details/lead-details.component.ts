import { Component } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { ActivatedRoute } from '@angular/router';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';
import { SafeHtml } from '@angular/platform-browser';
import { HttpClient } from '@angular/common/http';
import { MatDialog } from '@angular/material/dialog';
import { LeadAddContactComponent } from '../lead-add-contact/lead-add-contact.component';

@Component({
  selector: 'app-lead-details',
  templateUrl: './lead-details.component.html',
  styleUrls: ['./lead-details.component.css']
})
export class LeadDetailsComponent {
  data!:any;
  id;
  imgUrl = environment.imgUrl;
  sanitizer: any;
  countries:any[]=[];

  constructor(private CorparatesSalesService:CorparatesSalesService, private activateRoute:ActivatedRoute, private http:HttpClient,private dialog: MatDialog){}

  ngOnInit(): void {
    this.http.get('assets/country/CountryCodes.json').subscribe((data:any)=>{
      this.countries=data;
    })
    this.getLead();
  }

  getLead(){
    this.id = this.activateRoute.snapshot.params['id'];

    this.CorparatesSalesService.getLeadsById(this.id).subscribe((res:any)=>{
      this.data = res
      console.log(res);

    });

  }

  isImage(value: string): boolean {
    return /\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(value);
  }

  showProgress(value: string): SafeHtml {
    if (this.isImage(value)) {
      return this.sanitizer.bypassSecurityTrustHtml(
        `<img src="${this.imgUrl}${value}"
              style="max-width:100%;height:auto;cursor:pointer"
              onclick="window.dispatchEvent(new CustomEvent('img-click',{detail:this.src}))">`);
    }
    return value;
  }

  showImg(e){
    Swal.fire({
      html: `<img src="${e.target.src}" alt="Preview" style="max-width: 100%; height: auto;" />`,
      showConfirmButton:false
    });
  }

  addToLead(type, title){
    Swal.fire({
      title: title,
      input: 'text',
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Should ' + title;
        }
        if (value !== '') {
          let data = {
            lead_id: this.id,
            value,
            type
          }
          this.CorparatesSalesService.addToLead(data).subscribe(res=>{
            if (res) {
              Swal.fire({
                title: type+' Added',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              })
              this.getLead();
            }
          })
        }
        return undefined
      }
    })
  }

  addEmailToContact(contactId) {
    Swal.fire({
      title: 'Add Email',
      input: 'text',
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        // Email validation regex
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          return 'Please enter a valid email address.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          contactId,
          type: 'email'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Email Added',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  editEmail(email) {
    Swal.fire({
      title: 'Edit Email',
      input: 'text',
      inputValue:email.email,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        // Email validation regex
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          return 'Please enter a valid email address.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          emailId:email.id,
          type: 'edit Email'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Email Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  addPhoneToContact(contactId: number) {
    const countries = this.countries;

    const countryOptions = countries.map(c => `<option value="${c.dial_code}">${c.dial_code}</option>`).join('');

    Swal.fire({
      title: 'Add Phone Number',
      html: `
        <div style="display: flex; align-items: center;">
          <select id="swal-dial-code" class="swal2-select"
            style="width: 100px !important; min-width: 0; height: 40px;
                  border-top-right-radius: 0; border-bottom-right-radius: 0;
                  margin: 0 !important;">
            ${countryOptions}
          </select>
          <input type="text" id="swal-phone-number" class="swal2-input"
            placeholder="Enter phone number" autocomplete="off"
            style="flex: 1; min-width: 0; height: 40px;
                  border-top-left-radius: 0; border-bottom-left-radius: 0;
                  margin: 0 !important;" />
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      preConfirm: () => {
        const dialCode = (document.getElementById('swal-dial-code') as HTMLSelectElement).value;
        const phone = (document.getElementById('swal-phone-number') as HTMLInputElement).value.trim();

        const phoneRegex = /^[0-9]{6,15}$/;

        if (!phone) {
          Swal.showValidationMessage('Phone number is required.');
          return false;
        }

        if (!phoneRegex.test(phone)) {
          Swal.showValidationMessage('Enter a valid phone number (digits only).');
          return false;
        }

        return { dialCode, phone };
      }
    }).then(result => {
      if (result.isConfirmed && result.value) {
        const { dialCode, phone } = result.value;

        const data = {
          lead_id: this.id,
          dialCode,
          phone,
          contactId,
          type: 'phone'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Phone Number Added',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });
      }
    });
  }

  editPhone(phoneId, dial_code, phone) {
    const countries = this.countries;

    const countryOptions = countries.map(c => `
      <option value="${c.dial_code}" ${c.dial_code === dial_code ? 'selected' : ''}>
        ${c.dial_code}
      </option>`).join('');

    Swal.fire({
      title: 'Edit Phone Number',
      html: `
        <div style="display: flex; align-items: center;">
          <select id="swal-dial-code" class="swal2-select"
            style="width: 100px !important; min-width: 0; height: 40px;
                  border-top-right-radius: 0; border-bottom-right-radius: 0;
                  margin: 0 !important;">
            ${countryOptions}
          </select>
          <input type="text" id="swal-phone-number" class="swal2-input"
            placeholder="Enter phone number" autocomplete="off"
            value="${phone}"
            style="flex: 1; min-width: 0; height: 40px;
                  border-top-left-radius: 0; border-bottom-left-radius: 0;
                  margin: 0 !important;" />
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      preConfirm: () => {
        const dialCode = (document.getElementById('swal-dial-code') as HTMLSelectElement).value;
        const phone = (document.getElementById('swal-phone-number') as HTMLInputElement).value.trim();

        const phoneRegex = /^[0-9]{6,15}$/;

        if (!phone) {
          Swal.showValidationMessage('Phone number is required.');
          return false;
        }

        if (!phoneRegex.test(phone)) {
          Swal.showValidationMessage('Enter a valid phone number (digits only).');
          return false;
        }

        return { dialCode, phone };
      }
    }).then(result => {
      if (result.isConfirmed && result.value) {
        const { dialCode, phone } = result.value;

        const data = {
          lead_id: this.id,
          dialCode,
          phone,
          phoneId,
          type: 'edit Phone'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Phone Number Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });
      }
    });
  }



  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
    }
  }

  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];

    if (!this.selectedFile) return;

    Swal.fire({
      title: 'Do you want to upload this file?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, upload it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('file', this.selectedFile);
        formData.append('lead_id', this.id);
        formData.append('type', 'progress');

        this.CorparatesSalesService.addToLead(formData).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Progress Added',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });
      }
    });
  }

  addContact(): void {
    const dialogRef = this.dialog.open(LeadAddContactComponent, {
      data: {lead_id:this.id},
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log(result);
      if (result) {
        this.getLead();
      }

    });
  }


  editContactLinkedIn(contactId,value) {
    Swal.fire({
      title: 'Edit Contact LinkedIn',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        const regex = /^(https?:\/\/)?(www\.)?linkedin\.com\/.*$/;
        if (!regex.test(value)) {
          return 'Please enter a valid LinkedIn.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          contactId,
          type: 'edit Contact LinkedIn'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'LinkedIn Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  editContactName(contactId,value) {
    Swal.fire({
      title: 'Edit Contact Name',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }


        let data = {
          lead_id: this.id,
          value,
          contactId,
          type: 'edit Contact Name'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Name Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        return undefined;
      }
    });
  }

  editCompanyLinkedIn(value) {
    Swal.fire({
      title: 'Edit Company LinkedIn',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        const regex = /^(https?:\/\/)?(www\.)?linkedin\.com\/.*$/;
        if (!regex.test(value)) {
          return 'Please enter a valid LinkedIn.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          type: 'edit Company LinkedIn'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'LinkedIn Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  editCompanyWebsite(value) {
    Swal.fire({
      title: 'Edit Company Website',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        const regex = /^(https?:\/\/)?(www\.)?[A-Za-z0-9.-]+\.[A-Za-z]{2,}(\/\S*)?$/;
        if (!regex.test(value)) {
          return 'Please enter a valid Website.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          type: 'edit Company Website'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Website Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  editCompanyFacebook(value) {
    Swal.fire({
      title: 'Edit Company Facebook',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        const regex = /^https?:\/\/(www\.)?facebook\.com\/[A-Za-z0-9.\-_]+\/?$/;
        if (!regex.test(value)) {
          return 'Please enter a valid Facebook.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          type: 'edit Company Facebook'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Facebook Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  editCompanyInstagram(value) {
    Swal.fire({
      title: 'Edit Company Instagram',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }

        const regex = /^https?:\/\/(www\.)?instagram\.com\/[A-Za-z0-9.\-_]+\/?$/;
        if (!regex.test(value)) {
          return 'Please enter a valid Instagram.';
        }

        // If valid, send the request
        let data = {
          lead_id: this.id,
          value,
          type: 'edit Company Instagram'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Instagram Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        // Return undefined to pass validation
        return undefined;
      }
    });
  }

  editCompanyName(value) {
    Swal.fire({
      title: 'Edit Company Name',
      input: 'text',
      inputValue:value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Email is required.';
        }


        let data = {
          lead_id: this.id,
          value,
          type: 'edit Company Name'
        };

        this.CorparatesSalesService.addToLead(data).subscribe(res => {
          if (res) {
            Swal.fire({
              title: 'Name Updated',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.getLead();
          }
        });

        return undefined;
      }
    });
  }



}

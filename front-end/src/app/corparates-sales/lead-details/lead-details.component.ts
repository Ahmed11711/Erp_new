import { Component } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { ActivatedRoute } from '@angular/router';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';
import { SafeHtml } from '@angular/platform-browser';
import { HttpClient } from '@angular/common/http';
import { MatDialog } from '@angular/material/dialog';
import { LeadAddContactComponent } from '../lead-add-contact/lead-add-contact.component';
import { LeadStatusService } from '../../services/lead-status.service';

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
  leadStatuses: any[] = [];

  constructor(private CorparatesSalesService:CorparatesSalesService, private activateRoute:ActivatedRoute, private http:HttpClient, private dialog: MatDialog, private leadStatusService: LeadStatusService){}

  ngOnInit(): void {
    this.http.get('assets/country/CountryCodes.json').subscribe((data:any)=>{
      this.countries=data;
    })
    this.getLeadStatuses();
    this.getLead();
  }

  getLead(){
    this.id = this.activateRoute.snapshot.params['id'];

    this.CorparatesSalesService.getLeadsById(this.id).subscribe((res:any)=>{
      this.data = res
      console.log('Lead details received:', res);
      console.log('Contact title:', res?.contact_title);
      console.log('Contact department:', res?.contact_department);
      console.log('Lead status:', res?.lead_status);
      console.log('Status:', res?.status);
      console.log('All status data:', {
        lead_status_id: res?.lead_status_id,
        lead_status: res?.lead_status,
        status: res?.status
      });
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

  editContactTitle(value: string) {
    Swal.fire({
      title: 'Edit Contact Title',
      input: 'text',
      inputValue: value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Contact Title'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Contact Title Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editContactDepartment(value: string) {
    Swal.fire({
      title: 'Edit Contact Department',
      input: 'text',
      inputValue: value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Contact Department'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Contact Department Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  // Additional Details Display Functions
  getCompanySizeDisplay(value: string): string {
    const sizeMap = {
      '1-10': '1-10 موظف',
      '11-50': '11-50 موظف',
      '51-200': '51-200 موظف',
      '201-500': '201-500 موظف',
      '500+': 'أكثر من 500 موظف'
    };
    return sizeMap[value] || value;
  }

  getRevenueDisplay(value: string): string {
    const revenueMap = {
      'less-1m': 'أقل من 1 مليون',
      '1m-5m': '1-5 مليون',
      '5m-10m': '5-10 مليون',
      '10m-50m': '10-50 مليون',
      '50m+': 'أكثر من 50 مليون'
    };
    return revenueMap[value] || value;
  }

  getIndustrySectorDisplay(value: string): string {
    const sectorMap = {
      'technology': 'التقنية',
      'healthcare': 'الرعاية الصحية',
      'finance': 'التمويل',
      'retail': 'التجزئة',
      'manufacturing': 'التصنيع',
      'education': 'التعليم',
      'government': 'حكومي',
      'other': 'أخرى'
    };
    return sectorMap[value] || value;
  }

  getPriorityDisplay(value: string): string {
    const priorityMap = {
      'high': 'عالية',
      'medium': 'متوسطة',
      'low': 'منخفضة'
    };
    return priorityMap[value] || value;
  }

  getPriorityClass(value: string): string {
    const classMap = {
      'high': 'bg-danger',
      'medium': 'bg-warning',
      'low': 'bg-success'
    };
    return classMap[value] || 'bg-secondary';
  }

  getTimelineDisplay(value: string): string {
    const timelineMap = {
      'immediate': 'فوري',
      '1-3-months': '1-3 أشهر',
      '3-6-months': '3-6 أشهر',
      '6-12-months': '6-12 شهر',
      'more-than-year': 'أكثر من عام'
    };
    return timelineMap[value] || value;
  }

  getDecisionMakerDisplay(value: string): string {
    const decisionMap = {
      'yes': 'نعم',
      'no': 'لا',
      'influencer': 'مؤثر على القرار'
    };
    return decisionMap[value] || value;
  }

  // Additional Details Edit Functions
  editCompanySize(value: string) {
    Swal.fire({
      title: 'Edit Company Size',
      input: 'select',
      inputOptions: {
        '1-10': '1-10 موظف',
        '11-50': '11-50 موظف',
        '51-200': '51-200 موظف',
        '201-500': '201-500 موظف',
        '500+': 'أكثر من 500 موظف'
      },
      inputValue: value,
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Company Size'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Company Size Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editAnnualRevenue(value: string) {
    Swal.fire({
      title: 'Edit Annual Revenue',
      input: 'select',
      inputOptions: {
        'less-1m': 'أقل من 1 مليون',
        '1m-5m': '1-5 مليون',
        '5m-10m': '5-10 مليون',
        '10m-50m': '10-50 مليون',
        '50m+': 'أكثر من 50 مليون'
      },
      inputValue: value,
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Annual Revenue'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Annual Revenue Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editIndustrySector(value: string) {
    Swal.fire({
      title: 'Edit Industry Sector',
      input: 'select',
      inputOptions: {
        'technology': 'التقنية',
        'healthcare': 'الرعاية الصحية',
        'finance': 'التمويل',
        'retail': 'التجزئة',
        'manufacturing': 'التصنيع',
        'education': 'التعليم',
        'government': 'حكومي',
        'other': 'أخرى'
      },
      inputValue: value,
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Industry Sector'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Industry Sector Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editGeographicRegion(value: string) {
    Swal.fire({
      title: 'Edit Geographic Region',
      input: 'text',
      inputValue: value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Geographic Region'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Geographic Region Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editMainCompetitors(value: string) {
    Swal.fire({
      title: 'Edit Main Competitors',
      input: 'text',
      inputValue: value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Main Competitors'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Main Competitors Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editLeadPriority(value: string) {
    Swal.fire({
      title: 'Edit Lead Priority',
      input: 'select',
      inputOptions: {
        'high': 'عالية',
        'medium': 'متوسطة',
        'low': 'منخفضة'
      },
      inputValue: value,
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Lead Priority'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Lead Priority Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editRequiredProducts(value: string) {
    Swal.fire({
      title: 'Edit Required Products',
      input: 'textarea',
      inputValue: value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Required Products'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Required Products Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editExpectedBudget(value: string) {
    Swal.fire({
      title: 'Edit Expected Budget',
      input: 'text',
      inputValue: value,
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Expected Budget'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Expected Budget Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editProjectTimeline(value: string) {
    Swal.fire({
      title: 'Edit Project Timeline',
      input: 'select',
      inputOptions: {
        'immediate': 'فوري',
        '1-3-months': '1-3 أشهر',
        '3-6-months': '3-6 أشهر',
        '6-12-months': '6-12 شهر',
        'more-than-year': 'أكثر من عام'
      },
      inputValue: value,
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Project Timeline'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Project Timeline Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  editDecisionMaker(value: string) {
    Swal.fire({
      title: 'Edit Decision Maker',
      input: 'select',
      inputOptions: {
        'yes': 'نعم',
        'no': 'لا',
        'influencer': 'مؤثر على القرار'
      },
      inputValue: value,
      showCancelButton: true,
      inputValidator: (newValue) => {
        if (newValue !== value) {
          let data = {
            lead_id: this.id,
            value: newValue,
            type: 'edit Decision Maker'
          };

          this.CorparatesSalesService.addToLead(data).subscribe(res => {
            if (res) {
              Swal.fire({
                title: 'Decision Maker Updated',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getLead();
            }
          });
        }
        return undefined;
      }
    });
  }

  // Lead Status Methods
  getLeadStatuses() {
    this.leadStatusService.getAllStatuses().subscribe((data: any) => {
      this.leadStatuses = data.sort((a, b) => a.order - b.order);
    });
  }

  getStatusClass(statusName?: string): string {
    if (!statusName) return 'status-default';
    
    const statusLower = statusName.toLowerCase().replace(/\s+/g, '-');
    
    switch (statusLower) {
      case 'new':
      case 'new-lead':
      case 'جديد':
        return 'status-new';
      case 'pending':
      case 'في الانتظار':
        return 'status-pending';
      case 'follow-up':
      case 'follow-up-required':
      case 'متابعة':
        return 'status-follow-up';
      case 'qualified':
      case 'مؤهل':
        return 'status-qualified';
      case 'conversion':
      case 'تحويل':
        return 'status-conversion';
      case 'closed-won':
      case 'مغلق-مكتسب':
        return 'status-closed-won';
      case 'closed-lost':
      case 'مغلق-خاسر':
        return 'status-closed-lost';
      case 'not-interested':
      case 'غير مهتم':
        return 'status-not-interested';
      default:
        return 'status-default';
    }
  }

  getStatusDisplay(data: any): string {
    // Try multiple possible data structures
    if (data?.lead_status?.name) return data.lead_status.name;
    if (data?.status?.name) return data.status.name;
    if (data?.lead_status?.status_name) return data.lead_status.status_name;
    if (data?.status?.status_name) return data.status.status_name;
    
    // If we have a status ID, try to find it in our loaded statuses
    if (data?.lead_status_id && this.leadStatuses.length > 0) {
      const status = this.leadStatuses.find(s => s.id == data.lead_status_id);
      if (status?.name) return status.name;
    }
    
    // Fallback to show ID if available
    if (data?.lead_status_id) return `Status #${data.lead_status_id}`;
    
    return 'غير محدد';
  }

  editLeadStatus(currentStatusId?: number, currentStatusName?: string) {
    const statusOptions: any = {};
    this.leadStatuses.forEach(status => {
      statusOptions[status.id] = status.name;
    });

    Swal.fire({
      title: 'تغيير حالة العميل',
      input: 'select',
      inputOptions: statusOptions,
      inputValue: currentStatusId || '',
      showCancelButton: true,
      confirmButtonText: 'تحديث الحالة',
      cancelButtonText: 'إلغاء',
      inputValidator: (newStatusId) => {
        if (!newStatusId) {
          return 'يرجى اختيار حالة';
        }
        if (newStatusId != currentStatusId?.toString()) {
          const newStatus = this.leadStatuses.find(s => s.id == newStatusId);
          
          // Check if status requires a date (follow-up, meeting, etc.)
          const statusName = newStatus?.name?.toLowerCase() || '';
          const requiresDate = statusName.includes('follow') || statusName.includes('متابعة') || 
                             statusName.includes('meeting') || statusName.includes('اجتماع') ||
                             statusName.includes('call') || statusName.includes('مكالمة');
          
          if (requiresDate) {
            // Show date picker for statuses that require dates
            Swal.fire({
              title: 'تحديد التاريخ',
              html: '<input type="date" id="status-date" class="swal2-input" placeholder="يرجى تحديد تاريخ للمتابعة">',
              showCancelButton: true,
              confirmButtonText: 'حفظ',
              cancelButtonText: 'إلغاء',
              preConfirm: () => {
                const dateInput = document.getElementById('status-date') as HTMLInputElement;
                const dateValue = dateInput.value;
                if (!dateValue) {
                  Swal.showValidationMessage('يرجى تحديد التاريخ');
                  return false;
                }
                
                const data = {
                  lead_id: this.id,
                  value: newStatusId,
                  type: 'edit Lead Status',
                  next_action_date: dateValue
                };

                return this.CorparatesSalesService.addToLead(data).subscribe(res => {
                  if (res) {
                    Swal.fire({
                      title: 'تم تحديث الحالة',
                      text: `تم تغيير الحالة إلى: ${newStatus?.name}`,
                      icon: 'success',
                      timer: 2000,
                      showConfirmButton: false
                    });
                    this.getLead();
                  }
                }, error => {
                  Swal.fire({
                    title: 'خطأ',
                    text: 'فشل في تحديث الحالة',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                  });
                });
              }
            });
          } else {
            // Update status without date
            const data = {
              lead_id: this.id,
              value: newStatusId,
              type: 'edit Lead Status'
            };

            this.CorparatesSalesService.addToLead(data).subscribe(res => {
              if (res) {
                Swal.fire({
                  title: 'تم تحديث الحالة',
                  text: `تم تغيير الحالة إلى: ${newStatus?.name}`,
                  icon: 'success',
                  timer: 2000,
                  showConfirmButton: false
                });
                this.getLead();
              }
            }, error => {
              Swal.fire({
                title: 'خطأ',
                text: 'فشل في تحديث الحالة',
                icon: 'error',
                confirmButtonText: 'موافق'
              });
            });
          }
        }
        return undefined;
      }
    });
  }



}

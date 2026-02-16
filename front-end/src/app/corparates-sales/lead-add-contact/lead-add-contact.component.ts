import { ChangeDetectorRef, Component, Inject } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { Router } from '@angular/router';
import { FormGroup, FormControl, Validators, FormArray } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import Swal from 'sweetalert2';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';

@Component({
  selector: 'app-lead-add-edit',
  templateUrl: './lead-add-contact.component.html',
  styleUrls: ['./lead-add-contact.component.css']
})
export class LeadAddContactComponent {

  countries: any[] = [];
  form!: FormGroup;

  dateFrom: string = new Date().toISOString().slice(0, 10);
  minDate!: string;
  maxDate!: string;
  time!: string;

  imgtext: string = "Image"
  fileopend: boolean = false;

  constructor(public dialogRef: MatDialogRef<LeadAddContactComponent>, @Inject(MAT_DIALOG_DATA) public data: any,
    private CorparatesSalesService: CorparatesSalesService, private route: Router, private http: HttpClient, private cd: ChangeDetectorRef) {
  }

  onNoClick(): void {
    this.dialogRef.close();
  }

  ngOnInit(): void {
    this.http.get('assets/country/CountryCodes.json').subscribe((data: any) => {
      this.countries = data;
      this.loadForm();
      const defaultCountry = this.countries.find(c => c.name === 'Egypt') || this.countries[0];
      const defaultDialCode = defaultCountry ? defaultCountry.dial_code : '+20';
      this.form.patchValue({
        dial_code: defaultDialCode,
      })

      this.contacts.controls.forEach(contactGroup => {
        const phones = (contactGroup.get('phones') as FormArray);
        phones.controls.forEach(phoneGroup => {
          (phoneGroup as FormGroup).patchValue({
            dial_code: defaultDialCode,
          });
        });
      });
    });

  }

  loadForm() {
    this.form = new FormGroup({
      contacts: new FormArray([
        this.createContactFormGroup()
      ]),
    });

  }

  get contacts(): FormArray {
    return this.form.get('contacts') as FormArray;
  }

  createContactFormGroup(): FormGroup {
    return new FormGroup({
      contact_name: new FormControl(null, [Validators.required]),
      contact_linkedin: new FormControl(null, [Validators.pattern(/^(https?:\/\/)?(www\.)?linkedin\.com\/.*$/)]),
      phones: new FormArray([this.createPhoneFormGroup()]),
      emails: new FormArray([this.createEmailFormControl()])
    });
  }

  addContact(): void {
    this.contacts.push(this.createContactFormGroup());
  }

  removeContact(index: number): void {
    if (this.contacts.length > 1) {
      this.contacts.removeAt(index);
    }
  }

  getEmails(contactIndex: number): FormArray {
    return this.contacts.at(contactIndex).get('emails') as FormArray;
  }

  createEmailFormControl(): FormControl {
    return new FormControl(null, [
      Validators.email
    ]);
  }

  addEmail(contactIndex: number): void {
    this.getEmails(contactIndex).push(this.createEmailFormControl());
  }

  removeEmail(contactIndex: number, emailIndex: number): void {
    const emails = this.getEmails(contactIndex);
    if (emails.length > 1) emails.removeAt(emailIndex);
  }

  getPhones(contactIndex: number): FormArray {
    return this.contacts.at(contactIndex).get('phones') as FormArray;
  }


  createPhoneFormGroup(): FormGroup {
    let dial_code = '+20';
    if (this.countries && this.countries.length > 0) {
      const egypt = this.countries.find(c => c.name === 'Egypt');
      dial_code = egypt ? egypt.dial_code : this.countries[0].dial_code;
    }
    return new FormGroup({
      dial_code: new FormControl(dial_code, [Validators.required]),
      contact_number: new FormControl(null, [Validators.required, Validators.pattern(/^\d{7,15}$/)]),
    });
  }


  addPhone(contactIndex: number): void {
    this.getPhones(contactIndex).push(this.createPhoneFormGroup());
  }


  removePhone(contactIndex: number, phoneIndex: number): void {
    const phones = this.getPhones(contactIndex);
    if (phones.length > 1) phones.removeAt(phoneIndex);
  }


  submitform() {
    this.form.markAllAsTouched();
    console.log(this.data);

    if (this.form.valid) {
      const data = {
        lead_id: this.data.lead_id,
        type: 'contact',
        data: this.form.value
      };

      this.CorparatesSalesService.addToLead(data).subscribe({
        next: (res: any) => {
          if (res) {
            Swal.fire({
              title: 'Contacts Added',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.dialogRef.close('success');
          }
        },
        error: (err: any) => {
          console.error(err);
          Swal.fire({
            title: 'Error',
            text: err.error.message || 'An error occurred while adding contacts',
            icon: 'error',
            confirmButtonText: 'Ok'
          });
        }
      });
    } else {
      console.log('Form is invalid');
      Object.keys(this.form.controls).forEach(key => {
        const controlErrors = this.form.get(key)?.errors;
        if (controlErrors != null) {
          console.log('Key control: ' + key + ', keyError: ' + JSON.stringify(controlErrors));
        }
      });
      Swal.fire({
        title: 'Validation Error',
        text: 'Please check the form for missing or incorrect fields.',
        icon: 'warning',
        confirmButtonText: 'Ok'
      });
    }
  }
}

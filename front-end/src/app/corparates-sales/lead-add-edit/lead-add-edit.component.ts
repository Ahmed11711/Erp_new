import { ChangeDetectorRef, Component } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { Router } from '@angular/router';
import { FormGroup, FormControl, Validators, FormArray } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-lead-add-edit',
  templateUrl: './lead-add-edit.component.html',
  styleUrls: ['./lead-add-edit.component.css']
})
export class LeadAddEditComponent {

  countries:any[]=[];
  leadSource:any[]=[];
  leadTools:any[]=[];
  leadIndustry:any[]=[];
  form!:FormGroup;

  dateFrom: string = new Date().toISOString().slice(0, 10);
  minDate!: string;
  maxDate!: string;
  time!:string;

  imgtext:string="Image"
  fileopend:boolean=false;

  constructor(private CorparatesSalesService:CorparatesSalesService,private route:Router, private http:HttpClient, private cd : ChangeDetectorRef){
  }


  ngOnInit(): void {
    this.getLeadSource();
    this.getLeadTool();
    this.getLeadIndustry();
    this.http.get('assets/country/CountryCodes.json').subscribe((data:any)=>{
      this.countries=data;
      this.form.patchValue({
        dial_code: this.countries[0]?.dial_code,
      })

      this.contacts.controls.forEach(contactGroup => {
        const phones = (contactGroup.get('phones') as FormArray);
        phones.controls.forEach(phoneGroup => {
          (phoneGroup as FormGroup).patchValue({
            dial_code: this.countries[0]?.dial_code,
          });
        });
      });
    });
    this.loadForm();

  }

  getLeadSource(){
    this.CorparatesSalesService.getLeadSource().subscribe((data:any)=> this.leadSource = data);
  }

  getLeadTool(){
    this.CorparatesSalesService.getLeadTool().subscribe((data:any)=> this.leadTools = data);
  }

  getLeadIndustry(){
    this.CorparatesSalesService.getLeadIndustry().subscribe((data:any)=> this.leadIndustry = data);
  }

  loadForm(){
    this.form = new FormGroup({
      industry :new FormControl(null, [Validators.required ]),
      country: new FormControl(null, [Validators.required]),

      company_name: new FormControl(null, [
        Validators.required,
        Validators.minLength(2),
        Validators.maxLength(100)
      ]),

      company_facebook: new FormControl(null, [
        Validators.required,
        Validators.pattern(/^https?:\/\/(www\.)?facebook\.com\/[A-Za-z0-9.\-_]+\/?$/)
      ]),

      company_instagram: new FormControl(null, [
        Validators.required,
        Validators.pattern(/^https?:\/\/(www\.)?instagram\.com\/[A-Za-z0-9.\-_]+\/?$/)
      ]),

      company_website: new FormControl(null, [
        Validators.required,
        Validators.pattern(/^(https?:\/\/)?(www\.)?[A-Za-z0-9.-]+\.[A-Za-z]{2,}(\/\S*)?$/)
      ]),

      company_linkedin: new FormControl(null, [
        Validators.required,
        Validators.pattern(/^(https?:\/\/)?(www\.)?linkedin\.com\/.*$/)
      ]),

      notes: new FormControl(null, [
        Validators.required,
        Validators.minLength(5),
        Validators.maxLength(1000)
      ]),
      lead_source :new FormControl(0, [Validators.required, Validators.min(1) ]),
      lead_tool :new FormControl(0, [Validators.required, Validators.min(1) ]),

      contacts : new FormArray([
        this.createContactFormGroup()
      ]),

      date :new FormControl(null, [Validators.required ] ),
    });

  }

  get contacts(): FormArray {
    return this.form.get('contacts') as FormArray;
  }

  createContactFormGroup(): FormGroup {
    return new FormGroup({
      contact_name: new FormControl(null, [Validators.required]),
      contact_linkedin: new FormControl(null, [Validators.required, Validators.pattern(/^(https?:\/\/)?(www\.)?linkedin\.com\/.*$/)]),
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
      Validators.required,
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
    let dial_code = '+93';
    if (this.form && this.form.value.country) {
      let firstPhone = this.form.value.contacts[0].phones[0];
      dial_code = firstPhone.dial_code;
    }
    return new FormGroup({
      dial_code: new FormControl(dial_code, [Validators.required]),
      contact_number: new FormControl(null, [Validators.required,Validators.pattern(/^\d{7,15}$/)]),
    });
  }


  addPhone(contactIndex: number): void {
    this.getPhones(contactIndex).push(this.createPhoneFormGroup());
  }


  removePhone(contactIndex: number, phoneIndex: number): void {
    const phones = this.getPhones(contactIndex);
    if (phones.length > 1) phones.removeAt(phoneIndex);
  }


  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }

  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }

  catword = 'name';
  countryChange(event) {
    this.contacts.controls.forEach(contactGroup => {
      const phones = (contactGroup.get('phones') as FormArray);
      phones.controls.forEach(phoneGroup => {
        (phoneGroup as FormGroup).patchValue({
          dial_code: event.dial_code,
          code: event.code
        });
      });
    });

    this.form.patchValue({
      country: event.name
    })
  }

  resetInp(){
    this.form.get('productprice')?.reset();
  }

  industry = 'name';
  industryChange(event) {
    this.form.patchValue({
      industry: Number(event.id)
    })
  }

  industryKeyUp(event) {
    this.form.patchValue({
      industry: event.target.value
    })
  }

  resetIndustry(){
    this.form.get('industry')?.reset();
  }


  addSource(){
    Swal.fire({
      title: 'Add Source?',
      input: 'text',
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'You need to write Source!'
        }
        if (value !== '') {
          this.CorparatesSalesService.addLeadSource({name:value}).subscribe({
            next: (res:any) => {
              if (res.message === "success") {
                this.getLeadSource();
              }
            }
          });
        }
        return undefined
      }
    });
  }

  addTool(){
    Swal.fire({
      title: 'Add Tool?',
      input: 'text',
      inputAttributes: {
        autocomplete: 'off'
      },
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'You need to write Source!'
        }
        if (value !== '') {
          this.CorparatesSalesService.addLeadTool({name:value}).subscribe({
            next: (res:any) => {
              if (res.message === "success") {
                this.getLeadTool();
              }
            }
          });
        }
        return undefined
      }
    });
  }


  submitform(){
    this.form.markAllAsTouched();
    if (this.form.valid) {
      this.CorparatesSalesService.addLead(this.form.value).subscribe({
        next: (res:any) =>{
          if (res.message === "success") {
            this.route.navigate( ['/dashboard/corparates-sales/leads']);
          }
        }
      })
    }
  }
}

import { ChangeDetectorRef, Component } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { Router } from '@angular/router';
import { FormGroup, FormControl, Validators, FormArray } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import Swal from 'sweetalert2';
import { LeadStatusService } from '../../services/lead-status.service';

@Component({
  selector: 'app-lead-add-edit',
  templateUrl: './lead-add-edit.component.html',
  styleUrls: ['./lead-add-edit.component.css']
})
export class LeadAddEditComponent {

  countries: any[] = [];
  leadSource: any[] = [];
  leadTools: any[] = [];
  leadIndustry: any[] = [];
  leadStatuses: any[] = [];
  form!: FormGroup;
  initialCountry: string = '';

  dateFrom: string = new Date().toISOString().slice(0, 10);
  minDate!: string;
  maxDate!: string;
  time!: string;

  imgtext: string = "Image"
  fileopend: boolean = false;

  constructor(private CorparatesSalesService: CorparatesSalesService, private route: Router, private http: HttpClient, private cd: ChangeDetectorRef, private leadStatusService: LeadStatusService) {
  }


  ngOnInit(): void {
    this.getLeadSource();
    this.getLeadTool();
    this.getLeadIndustry();
    this.getLeadStatuses();
    this.http.get('assets/country/CountryCodes.json').subscribe((data: any) => {
      this.countries = data;
      this.loadForm();
      
      // Set Egypt as default country
      const egypt = this.countries.find(c => c.name === 'Egypt');
      this.initialCountry = egypt ? egypt.name : this.countries[0]?.name;
      
      this.form.patchValue({
        country: this.initialCountry,
        dial_code: egypt ? egypt.dial_code : this.countries[0]?.dial_code,
      })

      this.contacts.controls.forEach(contactGroup => {
        const phones = (contactGroup.get('phones') as FormArray);
        phones.controls.forEach(phoneGroup => {
          (phoneGroup as FormGroup).patchValue({
            dial_code: egypt ? egypt.dial_code : this.countries[0]?.dial_code,
          });
        });
      });
    });

  }

  getLeadSource() {
    this.CorparatesSalesService.getLeadSource().subscribe((data: any) => this.leadSource = data);
  }

  getLeadTool() {
    this.CorparatesSalesService.getLeadTool().subscribe((data: any) => this.leadTools = data);
  }

  getLeadIndustry() {
    this.CorparatesSalesService.getLeadIndustry().subscribe((data: any) => this.leadIndustry = data);
  }

  getSelectedStatus() {
    const statusId = this.form.get('lead_status_id')?.value;
    return this.leadStatuses.find(s => s.id === statusId);
  }

  getLeadStatuses() {
    this.leadStatusService.getAllStatuses().subscribe((data: any) => {
      this.leadStatuses = data.sort((a, b) => a.order - b.order);
      // Set default status to "New Lead" if available
      const newLeadStatus = this.leadStatuses.find(s => s.name === 'New Lead');
      if (newLeadStatus && this.form) {
        this.form.patchValue({
          lead_status_id: newLeadStatus.id
        });
      }
    });
  }

  loadForm() {
    this.form = new FormGroup({
      industry: new FormControl(null),
      country: new FormControl(null),
      lead_status_id: new FormControl(null, [Validators.required]),
      contact_title: new FormControl(null),
      contact_department: new FormControl(null),

      company_name: new FormControl(null, [
        Validators.required,
        Validators.minLength(2),
        Validators.maxLength(100)
      ]),

      company_facebook: new FormControl(null, [
        // Validators.required,
        Validators.pattern(/^https?:\/\/(www\.)?facebook\.com\/[A-Za-z0-9.\-_]+\/?$/)
      ]),

      company_instagram: new FormControl(null, [
        // Validators.required,
        Validators.pattern(/^https?:\/\/(www\.)?instagram\.com\/[A-Za-z0-9.\-_]+\/?$/)
      ]),

      company_website: new FormControl(null, [
        // Validators.required,
        Validators.pattern(/^(https?:\/\/)?(www\.)?[A-Za-z0-9.-]+\.[A-Za-z]{2,}(\/\S*)?$/)
      ]),

      company_linkedin: new FormControl(null, [
        Validators.pattern(/^(https?:\/\/)?(www\.)?linkedin\.com\/.*$/)
      ]),

      notes: new FormControl(null, [
        Validators.minLength(5),
        Validators.maxLength(1000)
      ]),
      lead_source: new FormControl(0, [Validators.required, Validators.min(1)]),
      lead_tool: new FormControl(0, [Validators.required, Validators.min(1)]),
      
      // Additional Details Fields
      company_size: new FormControl(null),
      annual_revenue: new FormControl(null),
      industry_sector: new FormControl(null),
      geographic_region: new FormControl(null),
      main_competitors: new FormControl(null),
      lead_priority: new FormControl(null),
      required_products: new FormControl(null, [Validators.maxLength(500)]),
      expected_budget: new FormControl(null, [Validators.maxLength(100)]),
      project_timeline: new FormControl(null),
      decision_maker: new FormControl(null),
      
      // Next action fields for Follow-Up status
      next_action_type: new FormControl(null),
      next_action_date: new FormControl(null),
      next_action_notes: new FormControl(null),

      contacts: new FormArray([
        this.createContactFormGroup()
      ]),

      date: new FormControl(this.dateFrom, [Validators.required]),
    });

  }

  get contacts(): FormArray {
    return this.form.get('contacts') as FormArray;
  }

  createContactFormGroup(): FormGroup {
    return new FormGroup({
      contact_name: new FormControl(null, [Validators.required]),
      contact_title: new FormControl(null),
      contact_department: new FormControl(null),
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
      // Validators.required,
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
      dial_code = egypt ? egypt.dial_code : '+20';
    }

    return new FormGroup({
      dial_code: new FormControl(dial_code, [Validators.required]),
      contact_number: new FormControl(null, [Validators.pattern(/^\d{7,15}$/)]),
    });
  }


  addPhone(contactIndex: number): void {
    const newPhoneGroup = this.createPhoneFormGroup();
    this.getPhones(contactIndex).push(newPhoneGroup);
  }


  removePhone(contactIndex: number, phoneIndex: number): void {
    const phones = this.getPhones(contactIndex);
    if (phones.length > 1) phones.removeAt(phoneIndex);
  }


  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend = true;
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

  resetInp() {
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

  resetIndustry() {
    this.form.get('industry')?.reset();
  }


  addSource() {
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
          this.CorparatesSalesService.addLeadSource({ name: value }).subscribe({
            next: (res: any) => {
              if (res.message === "success") {
                this.getLeadSource();
              }
            },
            error: (err: any) => {
              Swal.fire({
                title: 'Error',
                text: err.error.message || 'Failed to add source',
                icon: 'error'
              });
            }
          });
        }
        return undefined
      }
    });
  }

  addTool() {
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
          this.CorparatesSalesService.addLeadTool({ name: value }).subscribe({
            next: (res: any) => {
              if (res.message === "success") {
                this.getLeadTool();
              }
            },
            error: (err: any) => {
              Swal.fire({
                title: 'Error',
                text: err.error.message || 'Failed to add tool',
                icon: 'error'
              });
            }
          });
        }
        return undefined
      }
    });
  }


  submitform() {
    this.form.markAllAsTouched();
    if (this.form.valid) {
      // Log form data to check if contact_title and contact_department are included
      console.log('Form data being sent:', this.form.value);
      
      this.CorparatesSalesService.addLead(this.form.value).subscribe({
        next: (res: any) => {
          if (res.message === "success") {
            Swal.fire({
              title: 'Lead Added',
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
            this.route.navigate(['/dashboard/corparates-sales/leads']);
          }
        },
        error: (err: any) => {
          console.error('Error adding lead:', err);
          Swal.fire({
            title: 'Error',
            text: err.error.message || 'An error occurred while adding lead',
            icon: 'error',
            confirmButtonText: 'Ok'
          });
        }
      })
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

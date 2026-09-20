import {
  Component,
  ElementRef,
  EventEmitter,
  Input,
  OnDestroy,
  Optional,
  Output,
  Self,
  ViewChild,
} from '@angular/core';
import { ControlValueAccessor, FormControl, NgControl } from '@angular/forms';
import { MatDatepicker } from '@angular/material/datepicker';
import { Subscription } from 'rxjs';
import {
  dateToIsoString,
  isoStringToDate,
  parseDdMmYyyy,
  parseDdMmYyyyDetailed,
} from '../date/date-utils';

@Component({
  selector: 'app-date-input',
  templateUrl: './app-date-input.component.html',
  styleUrls: ['./app-date-input.component.css'],
})
export class AppDateInputComponent implements ControlValueAccessor, OnDestroy {
  @Input() id = '';
  @Input() min = '';
  @Input() max = '';
  @Output() dateValueChange = new EventEmitter<string | null>();

  @ViewChild('picker') picker?: MatDatepicker<Date>;
  @ViewChild('dateInput') dateInput?: ElementRef<HTMLInputElement>;

  readonly pickerControl = new FormControl<Date | null>(null);

  invalidInput = false;

  private lastEmitted: string | null = null;
  private syncingPicker = false;
  private pickerSub?: Subscription;

  private onChange: (value: string | null) => void = () => {};
  private onTouched: () => void = () => {};

  constructor(@Optional() @Self() public ngControl: NgControl) {
    if (this.ngControl) {
      this.ngControl.valueAccessor = this;
    }

    this.pickerSub = this.pickerControl.valueChanges.subscribe((date) => {
      if (this.syncingPicker) {
        return;
      }
      this.applyIncomingDate(date);
    });
  }

  ngOnDestroy(): void {
    this.pickerSub?.unsubscribe();
  }

  get minDate(): Date | null {
    return isoStringToDate(this.min);
  }

  get maxDate(): Date | null {
    return isoStringToDate(this.max);
  }

  readonly pickerDateFilter = (date: Date | null): boolean => this.isWithinRange(date);

  onDateChange(date: Date | null): void {
    if (this.syncingPicker) {
      return;
    }
    this.applyIncomingDate(date);
  }

  get pickerStartAt(): Date {
    return (
      this.pickerControl.value
      ?? this.maxDate
      ?? this.minDate
      ?? new Date()
    );
  }

  writeValue(value: string | Date | null): void {
    const next = value instanceof Date ? value : isoStringToDate(value);
    this.lastEmitted = dateToIsoString(next);
    this.invalidInput = false;
    this.setPickerValue(next);
  }

  registerOnChange(fn: (value: string | null) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    if (isDisabled) {
      this.pickerControl.disable({ emitEvent: false });
    } else {
      this.pickerControl.enable({ emitEvent: false });
    }
  }

  onBlur(): void {
    this.onTouched();
    this.commitFromInputText();
  }

  /** Enter confirms typed date without requiring a second blur. */
  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
      event.preventDefault();
      this.commitFromInputText();
      (event.target as HTMLInputElement | null)?.blur();
    }
  }

  /** Apply filter as soon as a complete dd/mm/yyyy value is typed. */
  onInput(): void {
    const raw = (this.dateInput?.nativeElement?.value ?? '').trim();
    if (!raw) {
      return;
    }
    const detailed = parseDdMmYyyyDetailed(raw);
    if (
      detailed.complete
      && detailed.valid
      && detailed.date
      && this.isWithinRange(detailed.date)
    ) {
      this.invalidInput = false;
      this.setPickerValue(detailed.date);
      this.commitDate(detailed.date);
    }
  }

  private commitFromInputText(): void {
    const raw = (this.dateInput?.nativeElement?.value ?? '').trim();
    if (!raw) {
      this.invalidInput = false;
      this.setPickerValue(null);
      this.commitDate(null);
      return;
    }

    const parsed = parseDdMmYyyy(raw) ?? isoStringToDate(raw);
    if (!parsed || !this.isWithinRange(parsed)) {
      this.invalidInput = true;
      return;
    }

    this.invalidInput = false;
    this.setPickerValue(parsed);
    this.commitDate(parsed);
  }

  private applyIncomingDate(date: Date | string | null): void {
    if (!date) {
      this.invalidInput = false;
      this.commitDate(null);
      return;
    }

    const normalized =
      date instanceof Date ? date : (isoStringToDate(String(date)) ?? parseDdMmYyyy(String(date)));

    if (!normalized) {
      this.invalidInput = true;
      return;
    }

    if (!this.isWithinRange(normalized)) {
      this.invalidInput = true;
      this.setPickerValue(isoStringToDate(this.lastEmitted));
      return;
    }

    this.invalidInput = false;
    this.commitDate(normalized);
  }

  private setPickerValue(value: Date | null): void {
    this.syncingPicker = true;
    try {
      this.pickerControl.setValue(value, { emitEvent: false });
    } finally {
      this.syncingPicker = false;
    }
  }

  private commitDate(value: Date | null): void {
    const iso = dateToIsoString(value);
    if (iso === this.lastEmitted) {
      return;
    }
    this.lastEmitted = iso;
    this.onChange(iso);
    this.dateValueChange.emit(iso);
  }

  private isWithinRange(date: Date | null): boolean {
    if (!date) {
      return true;
    }
    const normalized = new Date(date);
    normalized.setHours(0, 0, 0, 0);

    if (this.minDate) {
      const min = new Date(this.minDate);
      min.setHours(0, 0, 0, 0);
      if (normalized < min) {
        return false;
      }
    }
    if (this.maxDate) {
      const max = new Date(this.maxDate);
      max.setHours(0, 0, 0, 0);
      if (normalized > max) {
        return false;
      }
    }
    return true;
  }
}

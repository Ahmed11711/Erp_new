import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { AngularEditorConfig } from '@kolkov/angular-editor';

@Component({
  selector: 'app-angular-editor',
  templateUrl: './angular-editor.component.html',
  styleUrls: ['./angular-editor.component.css']
})
export class AngularEditorComponent {
  htmlContent: string = '';

  editorConfig: AngularEditorConfig = {
    editable: true,
    spellcheck: true,
    height: 'auto',
    minHeight: '150px',
    maxHeight: '300px',
    width: '100%',
    minWidth: '0',
    translate: 'yes',
    defaultFontName: 'Almarai',
    defaultFontSize: '4',
    toolbarHiddenButtons: [
      ['subscript', 'superscript', 'insertImage', 'insertVideo', 'strikeThrough'],
      ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull', 'heading']
    ],
    sanitize: true,
  };

  constructor(public dialogRef: MatDialogRef<AngularEditorComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {}

  ngOnInit(){
    this.htmlContent = this.data.htmlContent;
  }

  save(){
    this.dialogRef.close(this.htmlContent);
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

}

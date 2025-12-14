import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AssetSubCategoryComponent } from './asset-sub-category.component';

describe('AssetSubCategoryComponent', () => {
  let component: AssetSubCategoryComponent;
  let fixture: ComponentFixture<AssetSubCategoryComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AssetSubCategoryComponent]
    });
    fixture = TestBed.createComponent(AssetSubCategoryComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});

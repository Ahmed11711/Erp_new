import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AssetSubSubCategoryComponent } from './asset-sub-category-end.component';

describe('AssetSubCategoryComponent', () => {
  let component: AssetSubSubCategoryComponent;
  let fixture: ComponentFixture<AssetSubSubCategoryComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AssetSubSubCategoryComponent]
    });
    fixture = TestBed.createComponent(AssetSubSubCategoryComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});

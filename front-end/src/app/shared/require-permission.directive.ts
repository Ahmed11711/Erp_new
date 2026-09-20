import { Directive, Input, OnChanges, ElementRef, Renderer2 } from '@angular/core';
import { RbacService } from 'src/app/core/rbac/rbac.service';

/**
 * Hides host element unless the user holds the permission slug or legacy label.
 * Usage: `<button appRequirePermission="orders.create">...</button>`
 */
@Directive({
  selector: '[appRequirePermission]'
})
export class RequirePermissionDirective implements OnChanges {

  @Input() appRequirePermission!: string | string[];
  /** any (default) | all */
  @Input() appRequirePermissionMode: 'any' | 'all' = 'any';

  constructor(
    private readonly el: ElementRef<HTMLElement>,
    private readonly renderer: Renderer2,
    private readonly rbac: RbacService,
  ) {}

  ngOnChanges(): void {
    const keys = Array.isArray(this.appRequirePermission)
      ? this.appRequirePermission
      : [this.appRequirePermission];

    const ok = this.appRequirePermissionMode === 'all'
      ? keys.every((k) => this.rbac.can(k))
      : keys.some((k) => this.rbac.can(k));

    if (ok) {
      this.renderer.removeStyle(this.el.nativeElement, 'display');
    } else {
      this.renderer.setStyle(this.el.nativeElement, 'display', 'none');
    }
  }
}

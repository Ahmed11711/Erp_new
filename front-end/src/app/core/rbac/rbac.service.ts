import { Injectable } from '@angular/core';
import { AuthService } from 'src/app/auth/auth.service';

/**
 * Reads resolved permission keys from the session cookie set at login (JWT payload mirrors backend resolver).
 */
@Injectable({
  providedIn: 'root'
})
export class RbacService {

  constructor(private auth: AuthService) {}

  /** Effective permission keys: lowercase slugs + legacy permission labels */
  private keySet(): Set<string> {
    const keys = this.auth.getPermission();
    if (!Array.isArray(keys)) {
      return new Set();
    }
    return new Set(keys.map((k: string) => String(k).toLowerCase()));
  }

  /** فحص من الكوكي فقط — للشروط السلبية (مثل !rbac.canStrict('system.rbac')) */
  canStrict(slugOrLegacy: string): boolean {
    return this.keySet().has(String(slugOrLegacy).toLowerCase().trim());
  }

  can(slugOrLegacy: string): boolean {
    return this.keySet().has(String(slugOrLegacy).toLowerCase().trim());
  }

  canAny(slugs: readonly string[]): boolean {
    return slugs.some((s) => this.canStrict(s));
  }

  canAll(slugs: readonly string[]): boolean {
    return slugs.every((s) => this.canStrict(s));
  }

  rbacSnapshot(): unknown {
    return this.auth.getStoredAuthPayload()?.['rbac'] ?? null;
  }
}

import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/services/treasury_api.dart';

void main() {
  group('Treasury filters', () {
    test('filterBanks matches name, usage, and linked account', () {
      final items = [
        BankItem(id: 1, name: 'الأهلي', balance: 10, usage: 'مشتريات', assetName: 'أصول'),
        BankItem(id: 2, name: 'مصر', balance: 5, usage: 'رواتب'),
      ];
      expect(TreasuryApi.filterBanks(items, 'أهلي').map((b) => b.id), [1]);
      expect(TreasuryApi.filterBanks(items, 'رواتب').map((b) => b.id), [2]);
      expect(TreasuryApi.filterBanks(items, 'أصول').map((b) => b.id), [1]);
    });

    test('filterSafes matches name, branch, and account', () {
      final items = [
        SafeItem(id: 1, name: 'الخزنة الرئيسية', balance: 1, type: 'main', accountName: 'نقدية'),
        SafeItem(id: 2, name: 'خزنة فرع', balance: 2, type: 'branch', branchName: 'الإسكندرية'),
      ];
      expect(TreasuryApi.filterSafes(items, 'رئيسية').map((s) => s.id), [1]);
      expect(TreasuryApi.filterSafes(items, 'إسكند').map((s) => s.id), [2]);
      expect(TreasuryApi.filterSafes(items, 'نقدية').map((s) => s.id), [1]);
    });
  });
}

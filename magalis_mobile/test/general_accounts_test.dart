import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/services/accounting_tree_api.dart';

TreeAccountNode _acc({
  required int id,
  required String name,
  required String code,
  String type = 'asset',
  int? parentId,
  List<TreeAccountNode> children = const [],
}) {
  return TreeAccountNode(
    id: id,
    name: name,
    code: code,
    type: type,
    balance: 0,
    level: parentId == null ? 1 : 2,
    parentId: parentId,
    children: children,
  );
}

void main() {
  group('دليل الحسابات helpers', () {
    test('flattenAccounts dedupes nested children and sorts by code', () {
      final child = _acc(id: 2, name: 'نقدية', code: '1100', parentId: 1);
      final root = _acc(
        id: 1,
        name: 'الأصول',
        code: '1000',
        children: [child],
      );
      // API sometimes returns both the parent row and nested children.
      final flat = AccountingTreeApi.flattenAccounts([root, child]);
      expect(flat.map((a) => a.id), [1, 2]);
      expect(flat.map((a) => a.code), ['1000', '1100']);
    });

    test('hierarchyRole matches Angular جذر / مجموعة / تفصيلي', () {
      final leaf = _acc(id: 3, name: 'بنك', code: '1110', parentId: 2);
      final group = _acc(id: 2, name: 'نقدية', code: '1100', parentId: 1);
      final root = _acc(id: 1, name: 'الأصول', code: '1000');
      final all = [root, group, leaf];

      expect(AccountingTreeApi.hierarchyRole(root, all), 'جذر');
      expect(AccountingTreeApi.hierarchyRole(group, all), 'مجموعة');
      expect(AccountingTreeApi.hierarchyRole(leaf, all), 'تفصيلي');
    });

    test('filterAccounts matches name, code, and type', () {
      final accounts = [
        _acc(id: 1, name: 'الأصول', code: '1000', type: 'asset'),
        _acc(id: 2, name: 'الخصوم', code: '2000', type: 'liability'),
        _acc(id: 3, name: 'نقدية بالصندوق', code: '1100', type: 'asset'),
      ];

      expect(
        AccountingTreeApi.filterAccounts(accounts: accounts, query: '1000')
            .map((a) => a.id),
        [1],
      );
      expect(
        AccountingTreeApi.filterAccounts(accounts: accounts, query: 'خصوم')
            .map((a) => a.id),
        [2],
      );
      expect(
        AccountingTreeApi.filterAccounts(
          accounts: accounts,
          type: 'asset',
        ).map((a) => a.id),
        [1, 3],
      );
    });
  });
}

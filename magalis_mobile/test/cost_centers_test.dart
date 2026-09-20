import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/services/cost_centers_api.dart';

CostCenterNode _cc({
  required int id,
  required String name,
  required String code,
  String type = 'main',
  int? parentId,
  String? location,
  List<CostCenterNode> children = const [],
}) {
  return CostCenterNode(
    id: id,
    name: name,
    code: code,
    type: type,
    value: 0,
    parentId: parentId,
    location: location,
    children: children,
  );
}

void main() {
  group('مراكز التكلفة helpers', () {
    test('flatten dedupes nested children and sorts by code', () {
      final child = _cc(
        id: 2,
        name: 'إنتاج',
        code: '11',
        type: 'sub',
        parentId: 1,
      );
      final root = _cc(id: 1, name: 'المصنع', code: '1', children: [child]);
      final flat = CostCentersApi.flatten([root, child]);
      expect(flat.map((a) => a.id), [1, 2]);
      expect(flat.map((a) => a.code), ['1', '11']);
    });

    test('filterFlat matches name, code, and location', () {
      final items = [
        _cc(id: 1, name: 'المصنع', code: '1', location: 'القاهرة'),
        _cc(id: 2, name: 'المبيعات', code: '2', location: 'الإسكندرية'),
      ];
      expect(
        CostCentersApi.filterFlat(items: items, query: '1').map((a) => a.id),
        [1],
      );
      expect(
        CostCentersApi.filterFlat(items: items, query: 'مبيعات').map((a) => a.id),
        [2],
      );
      expect(
        CostCentersApi.filterFlat(items: items, query: 'قاهرة').map((a) => a.id),
        [1],
      );
    });

    test('filterTree keeps matching ancestors', () {
      final child = _cc(
        id: 2,
        name: 'خط الإنتاج',
        code: '11',
        type: 'sub',
        parentId: 1,
      );
      final root = _cc(id: 1, name: 'المصنع', code: '1', children: [child]);
      final filtered = CostCentersApi.filterTree([root], 'خط');
      expect(filtered.single.id, 1);
      expect(filtered.single.children.single.id, 2);
    });
  });
}

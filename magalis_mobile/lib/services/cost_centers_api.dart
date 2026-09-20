import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

const kCostCenterTypeLabels = <String, String>{
  'main': 'رئيسي',
  'sub': 'فرعي',
};

class CostCenterEmployee {
  const CostCenterEmployee({required this.id, required this.name});

  final int id;
  final String name;
}

class CostCenterNode {
  CostCenterNode({
    required this.id,
    required this.name,
    required this.code,
    required this.type,
    required this.value,
    this.nameEn,
    this.parentId,
    this.responsiblePersonId,
    this.responsibleName,
    this.location,
    this.phone,
    this.email,
    this.startDate,
    this.endDate,
    this.duration,
    this.children = const [],
  });

  final int id;
  final String name;
  final String code;
  final String type;
  final double value;
  final String? nameEn;
  final int? parentId;
  final int? responsiblePersonId;
  final String? responsibleName;
  final String? location;
  final String? phone;
  final String? email;
  final String? startDate;
  final String? endDate;
  final String? duration;
  final List<CostCenterNode> children;

  String get typeLabel => kCostCenterTypeLabels[type] ?? type;

  bool get hasChildren => children.isNotEmpty;

  bool get isMain => type == 'main';

  CostCenterNode copyWith({List<CostCenterNode>? children}) {
    return CostCenterNode(
      id: id,
      name: name,
      code: code,
      type: type,
      value: value,
      nameEn: nameEn,
      parentId: parentId,
      responsiblePersonId: responsiblePersonId,
      responsibleName: responsibleName,
      location: location,
      phone: phone,
      email: email,
      startDate: startDate,
      endDate: endDate,
      duration: duration,
      children: children ?? this.children,
    );
  }

  factory CostCenterNode.fromJson(Map<String, dynamic> j) {
    final kids = <CostCenterNode>[];
    final rawKids = j['children'];
    if (rawKids is List) {
      for (final c in rawKids) {
        if (c is Map) {
          kids.add(CostCenterNode.fromJson(Map<String, dynamic>.from(c)));
        }
      }
    }

    String? personName;
    final person = j['responsible_person'] ?? j['responsiblePerson'];
    if (person is Map) {
      final n = pick(Map<String, dynamic>.from(person), ['name']);
      if (n.isNotEmpty) personName = n;
    }

    return CostCenterNode(
      id: int.tryParse('${j['id']}') ?? 0,
      name: pick(j, ['name'], fallback: 'مركز تكلفة'),
      code: pick(j, ['code']),
      type: pick(j, ['type'], fallback: 'main'),
      value: _toDouble(j['value']),
      nameEn: pick(j, ['name_en']).isEmpty ? null : pick(j, ['name_en']),
      parentId:
          j['parent_id'] == null ? null : int.tryParse('${j['parent_id']}'),
      responsiblePersonId: j['responsible_person_id'] == null
          ? null
          : int.tryParse('${j['responsible_person_id']}'),
      responsibleName: personName,
      location: pick(j, ['location']).isEmpty ? null : pick(j, ['location']),
      phone: pick(j, ['phone']).isEmpty ? null : pick(j, ['phone']),
      email: pick(j, ['email']).isEmpty ? null : pick(j, ['email']),
      startDate: _dateOnly(j['start_date']),
      endDate: _dateOnly(j['end_date']),
      duration: pick(j, ['duration']).isEmpty ? null : pick(j, ['duration']),
      children: kids,
    );
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse('$v') ?? 0;
  }

  static String? _dateOnly(dynamic v) {
    if (v == null) return null;
    final s = '$v'.trim();
    if (s.isEmpty || s == 'null') return null;
    return s.length >= 10 ? s.substring(0, 10) : s;
  }
}

/// Cost centers APIs — same Laravel routes as Angular `CostCenterService`.
class CostCentersApi {
  CostCentersApi._();
  static final CostCentersApi instance = CostCentersApi._();

  final _api = ApiClient.instance;

  Future<List<CostCenterNode>> fetchTree() async {
    try {
      final res = await _api.dio.get('/accounting/cost-centers/tree');
      return _parseList(res.data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404 || e.response?.statusCode == 403) {
        return fetchFlat();
      }
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<CostCenterNode>> fetchFlat() async {
    try {
      final res = await _api.dio.get(
        '/accounting/cost-centers',
        queryParameters: const {'per_page': 200},
      );
      return flatten(_parseList(res.data));
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<CostCenterEmployee>> fetchEmployees() async {
    try {
      final res = await _api.dio.get('/employees');
      final list = _extractList(res.data);
      final out = <CostCenterEmployee>[];
      for (final row in list) {
        if (row is! Map) continue;
        final m = Map<String, dynamic>.from(row);
        final id = int.tryParse('${m['id']}') ?? 0;
        if (id == 0) continue;
        out.add(
          CostCenterEmployee(
            id: id,
            name: pick(m, ['name'], fallback: 'موظف'),
          ),
        );
      }
      return out;
    } on DioException {
      return const [];
    }
  }

  Future<CostCenterNode> create({
    required String name,
    required String type,
    String? nameEn,
    int? parentId,
    int? responsiblePersonId,
    String? location,
    String? phone,
    String? email,
    String? startDate,
    String? endDate,
    String? duration,
    double value = 0,
  }) async {
    try {
      final body = <String, dynamic>{
        'name': name,
        'type': type,
        'value': value,
      };
      if (nameEn != null && nameEn.trim().isNotEmpty) {
        body['name_en'] = nameEn.trim();
      }
      if (parentId != null) body['parent_id'] = parentId;
      if (responsiblePersonId != null) {
        body['responsible_person_id'] = responsiblePersonId;
      }
      if (location != null && location.trim().isNotEmpty) {
        body['location'] = location.trim();
      }
      if (phone != null && phone.trim().isNotEmpty) body['phone'] = phone.trim();
      if (email != null && email.trim().isNotEmpty) body['email'] = email.trim();
      if (startDate != null && startDate.isNotEmpty) {
        body['start_date'] = startDate;
      }
      if (endDate != null && endDate.isNotEmpty) body['end_date'] = endDate;
      if (duration != null && duration.trim().isNotEmpty) {
        body['duration'] = duration.trim();
      }

      final res = await _api.dio.post('/accounting/cost-centers', data: body);
      final data = _unwrapData(res.data);
      if (data is Map) {
        return CostCenterNode.fromJson(Map<String, dynamic>.from(data));
      }
      throw ApiException('تعذر إنشاء مركز التكلفة');
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<CostCenterNode> update({
    required int id,
    required String name,
    String? nameEn,
    int? responsiblePersonId,
    String? location,
    String? phone,
    String? email,
    String? startDate,
    String? endDate,
    String? duration,
    double value = 0,
  }) async {
    try {
      final res = await _api.dio.put(
        '/accounting/cost-centers/$id',
        data: {
          'name': name,
          'name_en': nameEn?.trim(),
          'responsible_person_id': responsiblePersonId,
          'location': location?.trim(),
          'phone': phone?.trim(),
          'email': email?.trim(),
          'start_date': startDate,
          'end_date': endDate,
          'duration': duration?.trim(),
          'value': value,
        },
      );
      final data = _unwrapData(res.data);
      if (data is Map) {
        return CostCenterNode.fromJson(Map<String, dynamic>.from(data));
      }
      throw ApiException('تعذر تحديث مركز التكلفة');
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> delete(int id) async {
    try {
      await _api.dio.delete('/accounting/cost-centers/$id');
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  static List<CostCenterNode> flatten(List<CostCenterNode> nodes) {
    final seen = <int>{};
    final out = <CostCenterNode>[];
    void walk(List<CostCenterNode> list) {
      for (final n in list) {
        if (seen.add(n.id)) out.add(n);
        if (n.hasChildren) walk(n.children);
      }
    }

    walk(nodes);
    out.sort((a, b) => a.code.compareTo(b.code));
    return out;
  }

  static List<CostCenterNode> filterFlat({
    required List<CostCenterNode> items,
    String query = '',
  }) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return items;
    return items.where((c) {
      return c.name.toLowerCase().contains(q) ||
          c.code.toLowerCase().contains(q) ||
          (c.nameEn?.toLowerCase().contains(q) ?? false) ||
          (c.location?.toLowerCase().contains(q) ?? false);
    }).toList();
  }

  static List<CostCenterNode> filterTree(
    List<CostCenterNode> roots,
    String query,
  ) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return roots;

    List<CostCenterNode>? filterNode(CostCenterNode node) {
      final selfMatch = node.name.toLowerCase().contains(q) ||
          node.code.toLowerCase().contains(q) ||
          (node.nameEn?.toLowerCase().contains(q) ?? false) ||
          (node.location?.toLowerCase().contains(q) ?? false);
      final kids = <CostCenterNode>[];
      for (final c in node.children) {
        final f = filterNode(c);
        if (f != null) kids.addAll(f);
      }
      if (selfMatch || kids.isNotEmpty) {
        return [node.copyWith(children: kids)];
      }
      return null;
    }

    final out = <CostCenterNode>[];
    for (final r in roots) {
      final f = filterNode(r);
      if (f != null) out.addAll(f);
    }
    return out;
  }

  List<CostCenterNode> _parseList(dynamic data) {
    return _extractList(data)
        .whereType<Map>()
        .map((e) => CostCenterNode.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  dynamic _unwrapData(dynamic data) {
    if (data is Map && data['data'] != null) return data['data'];
    return data;
  }

  List _extractList(dynamic data) {
    if (data is List) return data;
    if (data is! Map) return const [];
    final candidates = [data['data'], data['items']];
    for (final c in candidates) {
      if (c is List) return c;
      if (c is Map && c['data'] is List) return c['data'] as List;
    }
    return const [];
  }

  String _err(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final msg = data['message'];
      if (msg is String && msg.isNotEmpty) return msg;
      final errors = data['errors'];
      if (errors is Map) {
        final parts = <String>[];
        errors.forEach((_, v) {
          if (v is List) {
            parts.addAll(v.map((x) => x.toString()));
          } else if (v != null) {
            parts.add(v.toString());
          }
        });
        if (parts.isNotEmpty) return parts.join('\n');
      }
    }
    return ApiClient.messageFrom(e);
  }
}

import 'dart:convert';

import 'package:flutter/services.dart';

class EgyptGovernorate {
  const EgyptGovernorate({required this.id, required this.nameAr});

  final String id;
  final String nameAr;
}

class EgyptCity {
  const EgyptCity({required this.id, required this.governorateId, required this.nameAr});

  final String id;
  final String governorateId;
  final String nameAr;
}

/// Same JSON as Angular `assets/egypt/{governorates,cities}.json`.
class EgyptLocations {
  EgyptLocations._();

  static List<EgyptGovernorate>? _governorates;
  static List<EgyptCity>? _cities;

  static Future<List<EgyptGovernorate>> governorates() async {
    if (_governorates != null) return _governorates!;
    try {
      final raw = await rootBundle.loadString('assets/egypt/governorates.json');
      final list = jsonDecode(raw);
      final out = <EgyptGovernorate>[];
      if (list is List) {
        for (final row in list) {
          if (row is! Map) continue;
          final id = '${row['id'] ?? ''}';
          final name = '${row['governorate_name_ar'] ?? ''}';
          if (id.isEmpty || name.isEmpty) continue;
          out.add(EgyptGovernorate(id: id, nameAr: name));
        }
      }
      _governorates = out;
    } catch (_) {
      _governorates = const [
        EgyptGovernorate(id: '1', nameAr: 'القاهرة'),
        EgyptGovernorate(id: '2', nameAr: 'الجيزة'),
        EgyptGovernorate(id: '3', nameAr: 'الأسكندرية'),
      ];
    }
    return _governorates!;
  }

  static Future<List<EgyptCity>> citiesForGovernorate(String governorateNameAr) async {
    await _ensureCities();
    EgyptGovernorate? gov;
    for (final g in await governorates()) {
      if (g.nameAr == governorateNameAr) {
        gov = g;
        break;
      }
    }
    if (gov == null) return const [];
    return _cities!.where((c) => c.governorateId == gov!.id).toList();
  }

  static Future<void> _ensureCities() async {
    if (_cities != null) return;
    try {
      final raw = await rootBundle.loadString('assets/egypt/cities.json');
      final list = jsonDecode(raw);
      final out = <EgyptCity>[];
      if (list is List) {
        for (final row in list) {
          if (row is! Map) continue;
          final id = '${row['id'] ?? ''}';
          final gid = '${row['governorate_id'] ?? ''}';
          final name = '${row['city_name_ar'] ?? ''}';
          if (id.isEmpty || name.isEmpty) continue;
          out.add(EgyptCity(id: id, governorateId: gid, nameAr: name));
        }
      }
      _cities = out;
    } catch (_) {
      _cities = const [];
    }
  }
}

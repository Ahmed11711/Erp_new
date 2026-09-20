import '../services/catalog_api.dart';

class ModuleDef {
  const ModuleDef({
    required this.title,
    required this.path,
    required this.mapRow,
    this.searchParam = 'search',
    this.searchHint = 'بحث...',
    this.extraParams,
  });

  final String title;
  final String path;
  final RowMapper mapRow;
  final String searchParam;
  final String searchHint;
  final Map<String, dynamic>? extraParams;
}

/// Maps web `routeHint` → live API list module.
class ModuleCatalog {
  ModuleCatalog._();

  static ModuleDef? byRouteHint(String? routeHint, String fallbackTitle) {
    if (routeHint == null || routeHint.isEmpty) return null;
    final key = routeHint.trim().toLowerCase();

    if (key.contains('/whatsapp/chat')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/whatsapp/customers',
        searchHint: 'بحث بالاسم أو الهاتف...',
        extraParams: const {'per_page': 30},
        mapRow: (j) {
          final phone = pick(j, ['phone', 'wa_id', 'customer_phone']);
          final preview = _whatsappPreview(j) ?? phone;
          return CatalogRow(
            id: pick(j, ['id', 'customer_id']),
            title: pick(j, ['name', 'customer_name', 'display_name'], fallback: 'عميل'),
            subtitle: preview,
            trailing: _whatsappLastTime(j),
            phone: phone.isEmpty ? null : phone,
            isArchived: j['is_archived'] == true ||
                j['is_archived'] == 1 ||
                j['is_archived'] == '1' ||
                pick(j, ['whatsapp_archived_at']).isNotEmpty,
            awaitingReply: j['awaiting_reply'] == true ||
                j['awaiting_reply'] == 1 ||
                j['awaiting_reply'] == '1' ||
                '${j['last_message_direction'] ?? ''}' == 'inbound',
          );
        },
      );
    }

    if (key.contains('/warehouse/list') && !key.contains('listwarhouse')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/stocks',
        searchHint: 'بحث في المخازن...',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['name', 'stock_name', 'warehouse_name'], fallback: 'مخزن'),
          subtitle: pick(j, ['location', 'address', 'note']),
          trailing: pick(j, ['type', 'status']),
        ),
      );
    }

    if (key.contains('/categories/all_categories')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/categories/search',
        searchParam: 'category_name',
        searchHint: 'بحث باسم الصنف...',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['category_name', 'name'], fallback: 'صنف'),
          subtitle: [
            pick(j, ['item_code', 'code']),
            pick(j, ['classification_name', 'unit_name']),
          ].where((s) => s.isNotEmpty).join(' · '),
          trailing: moneyish(j['sale_price'] ?? j['price'] ?? j['cost']),
        ),
      );
    }

    if (key.contains('/suppliers/types')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/suppliers/getAllSupplierTypes',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['supplier_type', 'name', 'type_name'], fallback: 'فئة'),
          subtitle: pick(j, ['description', 'note']),
        ),
      );
    }

    if (key.contains('/manufacturing/recipes') ||
        key.contains('/manufacturing/bom')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/recipes',
        searchHint: 'بحث في الوصفات...',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['recipe_name', 'name'], fallback: 'وصفة'),
          subtitle: pick(j, ['description', 'note']),
          trailing: pick(j, ['status']),
        ),
      );
    }

    if (key.contains('/manufacturing/orders') ||
        key.contains('/manufacturing/confirmation')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/manufacturing/production-orders',
        searchHint: 'بحث في أوامر التصنيع...',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: 'أمر #${pick(j, ['id', 'order_number'])}',
          subtitle: [
            pick(j, ['status', 'state']),
            pick(j, ['created_at', 'date']),
          ].where((s) => s.isNotEmpty).join(' · '),
          trailing: pick(j, ['quantity', 'qty']),
        ),
      );
    }

    if (key.contains('/manufacturing/items-without-recipes')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/manufacture/items-without-recipes',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['category_name', 'name'], fallback: 'صنف'),
          subtitle: pick(j, ['item_code', 'code']),
        ),
      );
    }

    if (key.contains('/accounting/banks') ||
        key.contains('/financial/pendingbanks')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/banks',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['name', 'bank_name'], fallback: 'بنك'),
          subtitle: pick(j, ['account_number', 'iban']),
          trailing: moneyish(j['balance'] ?? j['current_balance']),
        ),
      );
    }

    if (key.contains('/accounting/safes')) {
      return ModuleDef(
        title: fallbackTitle,
        path: '/safes/',
        mapRow: (j) => CatalogRow(
          id: pick(j, ['id']),
          title: pick(j, ['name', 'safe_name'], fallback: 'خزينة'),
          subtitle: pick(j, ['note', 'description']),
          trailing: moneyish(j['balance'] ?? j['current_balance']),
        ),
      );
    }

    return null;
  }

  static String? _whatsappPreview(Map<String, dynamic> j) {
    final type = pick(j, ['last_message_type']);
    if (type == 'image' || type == 'sticker') return '🖼️ صورة';
    if (type == 'video') return '🎬 فيديو';
    if (type == 'audio') return '🎤 رسالة صوتية';
    if (type == 'document') return '📎 ملف مرفق';
    final text = pick(j, ['last_message_content', 'last_message']);
    if (text.isEmpty) return null;
    return text.length > 60 ? '${text.substring(0, 57)}…' : text;
  }

  static String? _whatsappLastTime(Map<String, dynamic> j) {
    final raw = pick(j, [
      'messages_max_created_at',
      'last_message_at',
      'updated_at',
    ]);
    if (raw.isEmpty) return null;
    final d = DateTime.tryParse(raw.replaceFirst(' ', 'T'));
    if (d == null) return null;
    final local = d.toLocal();
    final now = DateTime.now();
    final sameDay = local.year == now.year &&
        local.month == now.month &&
        local.day == now.day;
    if (sameDay) {
      final h = local.hour.toString().padLeft(2, '0');
      final m = local.minute.toString().padLeft(2, '0');
      return '$h:$m';
    }
    final days = DateTime(now.year, now.month, now.day)
        .difference(DateTime(local.year, local.month, local.day))
        .inDays;
    if (days == 1) return 'أمس';
    if (days < 7) {
      const weekdays = ['الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد'];
      // DateTime.weekday: Mon=1 … Sun=7
      return weekdays[local.weekday - 1];
    }
    return '${local.day}/${local.month}';
  }
}

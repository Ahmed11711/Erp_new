class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.createdAt,
    this.isRead = false,
    this.type = NotificationType.info,
  });

  final String id;
  final String title;
  final String body;
  final DateTime createdAt;
  final bool isRead;
  final NotificationType type;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final typeRaw = '${json['type'] ?? ''}'.trim();
    final note = '${json['note'] ?? ''}'.trim();
    final orderId = json['order_id'];
    final sender = json['sender'];
    final senderName =
        sender is Map ? '${sender['name'] ?? ''}' : '${json['sender_name'] ?? ''}';

    final title = typeRaw.isNotEmpty ? typeRaw : 'إشعار';
    final bodyParts = <String>[];
    if (note.isNotEmpty) bodyParts.add(note);
    if (orderId != null) bodyParts.add('طلب #$orderId');
    if (senderName.isNotEmpty) bodyParts.add('من: $senderName');
    final body =
        bodyParts.isNotEmpty ? bodyParts.join(' — ') : 'بدون تفاصيل إضافية';

    final created = DateTime.tryParse('${json['created_at'] ?? ''}') ??
        DateTime.now();

    return AppNotification(
      id: '${json['id']}',
      title: title,
      body: body,
      createdAt: created,
      isRead: json['is_read'] == 1 || json['is_read'] == true,
      type: _mapType(typeRaw),
    );
  }

  static NotificationType _mapType(String raw) {
    if (raw.contains('موافقة') || raw.contains('مراجعة')) {
      return NotificationType.approval;
    }
    if (raw.contains('تحذير') || raw.contains('متأخر')) {
      return NotificationType.warning;
    }
    if (raw.contains('تحصيل') || raw.contains('تم') || raw.contains('قبول')) {
      return NotificationType.success;
    }
    return NotificationType.info;
  }
}

enum NotificationType { info, warning, success, approval }

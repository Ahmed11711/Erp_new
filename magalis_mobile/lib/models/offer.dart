enum OfferStatus { draft, sent, accepted, rejected, converted }

class Offer {
  const Offer({
    required this.id,
    required this.code,
    required this.customerName,
    required this.company,
    required this.status,
    required this.total,
    required this.createdAt,
    required this.itemsCount,
    this.type = 1,
    this.note,
  });

  final String id;
  final String code;
  final String customerName;
  final String company;
  final OfferStatus status;
  final double total;
  final DateTime createdAt;
  final int itemsCount;
  final int type;
  final String? note;

  String get statusLabel {
    switch (status) {
      case OfferStatus.draft:
        return 'مسودة';
      case OfferStatus.sent:
        return 'مرسل';
      case OfferStatus.accepted:
        return 'مقبول';
      case OfferStatus.rejected:
        return 'مرفوض';
      case OfferStatus.converted:
        return 'محوّل لطلب';
    }
  }

  factory Offer.fromJson(Map<String, dynamic> json) {
    final company = json['customer_company'];
    final companyName = company is Map
        ? '${company['name'] ?? ''}'
        : '${json['company_name'] ?? ''}';
    final categories = json['category'];
    final itemsCount = categories is List ? categories.length : 0;
    final offerKind = '${json['offer'] ?? 'offer1'}';
    final converted = json['converted_order_id'] != null ||
        json['converted_at'] != null;

    DateTime created = DateTime.now();
    final rawDate = json['dateFrom'] ?? json['created_at'] ?? json['date'];
    if (rawDate != null) {
      created = DateTime.tryParse(rawDate.toString()) ?? created;
    }

    final quote = '${json['quote'] ?? ''}'.trim();
    final contact = '${json['contact_person'] ?? ''}'.trim();
    final id = '${json['id']}';

    return Offer(
      id: id,
      code: quote.isNotEmpty ? quote : 'Q-$id',
      customerName: contact.isNotEmpty
          ? contact
          : (quote.isNotEmpty ? quote : companyName),
      company: companyName.isNotEmpty ? companyName : '—',
      status: converted ? OfferStatus.converted : OfferStatus.sent,
      total: (json['total'] is num)
          ? (json['total'] as num).toDouble()
          : double.tryParse('${json['total'] ?? 0}') ?? 0,
      createdAt: created,
      itemsCount: itemsCount,
      type: offerKind.contains('2') ? 2 : 1,
      note: json['note']?.toString(),
    );
  }
}

import '../models/app_notification.dart';
import '../models/offer.dart';
import '../models/order.dart';

/// Static demo data — no API / backend calls.
class MockData {
  MockData._();

  static const String userName = 'أحمد محمد';
  static const String userEmail = 'admin@magalis.com';
  static const String userRole = 'مدير النظام';

  static const double todayCollected = 48500;
  static const int pendingApprovals = 7;
  static const int lateConfirmedOrders = 3;
  static const int todayOrders = 24;

  static const int collectPipeline = 12;
  static const int shipPipeline = 18;
  static const int manufacturePipeline = 5;
  static const int purchasePipeline = 4;

  static final DateTime _today = DateTime.now();

  static final List<Order> orders = [
    Order(
      id: '1',
      code: 'ORD-10241',
      customerName: 'سارة العلي',
      phone: '01012345678',
      city: 'مدينة نصر',
      address: 'شارع عباس العقاد',
      status: OrderStatus.confirmed,
      total: 2450,
      createdAt: _today.subtract(const Duration(hours: 2)),
      shippingCompany: 'أرامكس',
      customerType: 'افراد',
      orderType: 'جديد',
      governorate: 'القاهرة',
      shipmentNumber: 'AWB-90011',
      orderSource: 'واتساب',
      shippingMethod: 'توصيل منزلي',
      shippingLine: 'خط القاهرة',
      collectType: 'تحصيل متغير',
      vip: true,
      paid: false,
      reviewed: 1,
      needByDate: _today.add(const Duration(days: 1)),
      statusDate: _today,
      items: const [
        OrderItem(name: 'كنبة زاوية رمادي', qty: 1, price: 1800, itemCode: 'SOFA-01'),
        OrderItem(name: 'طاولة وسط خشب', qty: 1, price: 650, itemCode: 'TBL-02'),
      ],
      notes: 'التسليم بعد الساعة 5 مساءً',
    ),
    Order(
      id: '2',
      code: 'ORD-10240',
      customerName: 'خالد حسن',
      phone: '01198765432',
      city: 'الدقي',
      address: 'شارع التحرير',
      status: OrderStatus.shipped,
      total: 980,
      createdAt: _today.subtract(const Duration(hours: 5)),
      shippingCompany: 'مواصلات مصر',
      customerType: 'افراد',
      orderType: 'جديد',
      governorate: 'الجيزة',
      shipmentNumber: 'AWB-90012',
      orderSource: 'الموقع',
      shippingMethod: 'توصيل منزلي',
      shippingLine: 'خط الدلتا',
      collectType: 'تحصيل الكتروني',
      paid: true,
      reviewed: 0,
      statusDate: _today,
      items: const [
        OrderItem(name: 'كرسي مكتب', qty: 2, price: 490, itemCode: 'CHR-10'),
      ],
    ),
    Order(
      id: '3',
      code: 'ORD-10239',
      customerName: 'منى إبراهيم',
      phone: '01234567890',
      city: 'سموحة',
      address: 'شارع فوزي معاذ',
      status: OrderStatus.pending,
      total: 5200,
      createdAt: _today.subtract(const Duration(days: 1)),
      shippingCompany: '—',
      customerType: 'شركة',
      orderType: 'طلب استبدال',
      governorate: 'الإسكندرية',
      shipmentNumber: '',
      orderSource: 'هاتف',
      shippingMethod: 'استلام من الفرع',
      shippingLine: 'خط الإسكندرية',
      shortage: true,
      prepaidAmount: true,
      reviewed: 2,
      needByDate: _today.add(const Duration(days: 3)),
      items: const [
        OrderItem(name: 'سرير كينج', qty: 1, price: 3500, itemCode: 'BED-01'),
        OrderItem(name: 'مرتبة طبية', qty: 1, price: 1700, itemCode: 'MAT-03'),
      ],
    ),
    Order(
      id: '4',
      code: 'ORD-10238',
      customerName: 'يوسف عبد الله',
      phone: '01555551234',
      city: 'المنصورة',
      address: 'حي الجامعة',
      status: OrderStatus.delivered,
      total: 760,
      createdAt: _today.subtract(const Duration(days: 2)),
      shippingCompany: 'أرامكس',
      customerType: 'افراد',
      orderType: 'جديد',
      governorate: 'الدقهلية',
      shipmentNumber: 'AWB-90020',
      orderSource: 'Shopify',
      shippingMethod: 'توصيل منزلي',
      shippingLine: 'خط الدلتا',
      collectType: 'تحصيل متغير',
      fromShopify: true,
      shopifyReviewed: true,
      reviewed: 1,
      deliveryDate: _today.subtract(const Duration(days: 1)),
      statusDate: _today.subtract(const Duration(days: 1)),
      items: const [
        OrderItem(name: 'مصباح أرضي', qty: 2, price: 380, itemCode: 'LMP-05'),
      ],
    ),
    Order(
      id: '5',
      code: 'ORD-10237',
      customerName: 'نورا فؤاد',
      phone: '01099887766',
      city: 'المعادي',
      address: 'كورنيش النيل',
      status: OrderStatus.collected,
      total: 3100,
      createdAt: _today.subtract(const Duration(days: 3)),
      shippingCompany: 'بوسطة',
      customerType: 'افراد',
      orderType: 'جديد',
      governorate: 'القاهرة',
      shipmentNumber: 'AWB-90021',
      orderSource: 'الموقع',
      shippingMethod: 'توصيل منزلي',
      shippingLine: 'خط القاهرة',
      collectType: 'تحصيل الكتروني',
      vip: true,
      paid: true,
      privateOrder: true,
      reviewed: 1,
      deliveryDate: _today.subtract(const Duration(days: 2)),
      items: const [
        OrderItem(
          name: 'طاولة طعام 6 كراسي',
          qty: 1,
          price: 3100,
          itemCode: 'DIN-06',
        ),
      ],
    ),
    Order(
      id: '6',
      code: 'ORD-10236',
      customerName: 'عمر سعيد',
      phone: '01022223333',
      city: 'طنطا',
      address: 'شارع البحر',
      status: OrderStatus.cancelled,
      total: 450,
      createdAt: _today.subtract(const Duration(days: 4)),
      shippingCompany: '—',
      customerType: 'افراد',
      orderType: 'طلب مرتجع',
      governorate: 'الغربية',
      orderSource: 'واتساب',
      shippingMethod: 'توصيل منزلي',
      shippingLine: 'خط الدلتا',
      fromShopify: true,
      shopifyReviewed: false,
      reviewed: 0,
      items: const [
        OrderItem(name: 'وسادة ديكور', qty: 3, price: 150, itemCode: 'CSH-01'),
      ],
    ),
  ];

  static final List<Offer> offers = [
    Offer(
      id: '1',
      code: 'Q-8841',
      customerName: 'شركة النيل للأثاث',
      company: 'النيل للأثاث',
      status: OfferStatus.sent,
      total: 18500,
      createdAt: DateTime.now().subtract(const Duration(hours: 6)),
      itemsCount: 8,
      type: 1,
    ),
    Offer(
      id: '2',
      code: 'Q-8840',
      customerName: 'مؤسسة الياسمين',
      company: 'الياسمين',
      status: OfferStatus.accepted,
      total: 9200,
      createdAt: DateTime.now().subtract(const Duration(days: 1)),
      itemsCount: 4,
      type: 2,
    ),
    Offer(
      id: '3',
      code: 'Q-8839',
      customerName: 'هشام كريم',
      company: 'فردي',
      status: OfferStatus.draft,
      total: 3400,
      createdAt: DateTime.now().subtract(const Duration(days: 1)),
      itemsCount: 2,
      type: 1,
    ),
    Offer(
      id: '4',
      code: 'Q-8838',
      customerName: 'مجموعة الشرق',
      company: 'الشرق',
      status: OfferStatus.converted,
      total: 27600,
      createdAt: DateTime.now().subtract(const Duration(days: 3)),
      itemsCount: 12,
      type: 2,
    ),
    Offer(
      id: '5',
      code: 'Q-8837',
      customerName: 'ريم عادل',
      company: 'فردي',
      status: OfferStatus.rejected,
      total: 1100,
      createdAt: DateTime.now().subtract(const Duration(days: 5)),
      itemsCount: 1,
      type: 1,
    ),
  ];

  static final List<AppNotification> notifications = [
    AppNotification(
      id: '1',
      title: 'موافقة مطلوبة',
      body: 'طلب شراء رقم PO-441 بانتظار موافقتك',
      createdAt: DateTime.now().subtract(const Duration(minutes: 12)),
      type: NotificationType.approval,
    ),
    AppNotification(
      id: '2',
      title: 'طلب متأخر',
      body: 'الطلب ORD-10239 مؤكد ومتأخر عن موعد الشحن',
      createdAt: DateTime.now().subtract(const Duration(hours: 1)),
      type: NotificationType.warning,
    ),
    AppNotification(
      id: '3',
      title: 'تم التحصيل',
      body: 'تم تحصيل مبلغ 3,100 ج.م للطلب ORD-10237',
      createdAt: DateTime.now().subtract(const Duration(hours: 3)),
      isRead: true,
      type: NotificationType.success,
    ),
    AppNotification(
      id: '4',
      title: 'عرض سعر مقبول',
      body: 'العميل قبل عرض السعر Q-8840',
      createdAt: DateTime.now().subtract(const Duration(hours: 8)),
      isRead: true,
      type: NotificationType.success,
    ),
    AppNotification(
      id: '5',
      title: 'تحديث المخزون',
      body: 'كمية صنف «كنبة زاوية» أقل من الحد الأدنى',
      createdAt: DateTime.now().subtract(const Duration(days: 1)),
      isRead: true,
      type: NotificationType.info,
    ),
  ];

  static Order? orderById(String id) {
    try {
      return orders.firstWhere((o) => o.id == id);
    } catch (_) {
      return null;
    }
  }

  static Offer? offerById(String id) {
    try {
      return offers.firstWhere((o) => o.id == id);
    } catch (_) {
      return null;
    }
  }
}

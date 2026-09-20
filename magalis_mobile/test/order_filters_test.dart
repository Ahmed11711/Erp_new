import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/data/mock_data.dart';
import 'package:magalis_mobile/models/order.dart';
import 'package:magalis_mobile/models/order_filters.dart';

void main() {
  group('OrderFilters.apply', () {
    final orders = MockData.orders;

    test('empty filters returns all orders', () {
      expect(OrderFilters.empty.apply(orders), hasLength(orders.length));
      expect(OrderFilters.empty.hasActiveFilters, isFalse);
      expect(OrderFilters.empty.activeCount, 0);
    });

    test('filters by customer type شركة', () {
      final result = const OrderFilters(customerType: 'شركة').apply(orders);
      expect(result, isNotEmpty);
      expect(result.every((o) => o.customerType == 'شركة'), isTrue);
      expect(result.map((o) => o.code), contains('ORD-10239'));
    });

    test('filters by order type طلب استبدال', () {
      final result =
          const OrderFilters(orderType: 'طلب استبدال').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10239');
    });

    test('filters by shipping company', () {
      final result =
          const OrderFilters(shippingCompany: 'أرامكس').apply(orders);
      expect(result.every((o) => o.shippingCompany == 'أرامكس'), isTrue);
      expect(result.map((o) => o.code), containsAll(['ORD-10241', 'ORD-10238']));
    });

    test('filters VIP only', () {
      final result = const OrderFilters(vip: true).apply(orders);
      expect(result.every((o) => o.vip), isTrue);
      expect(result.length, greaterThanOrEqualTo(2));
    });

    test('filters shortage', () {
      final result = const OrderFilters(shortage: true).apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10239');
    });

    test('filters paid', () {
      final result = const OrderFilters(paid: true).apply(orders);
      expect(result.every((o) => o.paid), isTrue);
      expect(result.map((o) => o.code), containsAll(['ORD-10240', 'ORD-10237']));
    });

    test('filters prepaidAmount', () {
      final result = const OrderFilters(prepaidAmount: true).apply(orders);
      expect(result, hasLength(1));
      expect(result.first.prepaidAmount, isTrue);
    });

    test('filters private admin orders', () {
      final result = const OrderFilters(privateOrder: '1').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10237');
    });

    test('filters by governorate and city', () {
      final byGov =
          const OrderFilters(governorate: 'القاهرة').apply(orders);
      expect(byGov.every((o) => o.governorate == 'القاهرة'), isTrue);

      final byCity = const OrderFilters(
        governorate: 'القاهرة',
        city: 'المعادي',
      ).apply(orders);
      expect(byCity, hasLength(1));
      expect(byCity.first.code, 'ORD-10237');
    });

    test('filters by customer name (partial, case-insensitive)', () {
      final result =
          const OrderFilters(customerName: 'سارة').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.customerName, contains('سارة'));
    });

    test('filters by phone', () {
      final result =
          const OrderFilters(customerPhone: '0101234').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10241');
    });

    test('filters by order number', () {
      final result =
          const OrderFilters(orderNumber: '10240').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10240');
    });

    test('filters by shipment number', () {
      final result =
          const OrderFilters(shipmentNumber: 'AWB-90011').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10241');
    });

    test('filters by product name or item code', () {
      final byName =
          const OrderFilters(productQuery: 'كنبة').apply(orders);
      expect(byName, hasLength(1));
      expect(byName.first.code, 'ORD-10241');

      final byCode =
          const OrderFilters(productQuery: 'chr-10').apply(orders);
      expect(byCode, hasLength(1));
      expect(byCode.first.code, 'ORD-10240');
    });

    test('filters by order status', () {
      final result = const OrderFilters(orderStatus: 'مؤكد').apply(orders);
      expect(result.every((o) => o.status == OrderStatus.confirmed), isTrue);
      expect(result, hasLength(1));
    });

    test('filters by order source', () {
      final result =
          const OrderFilters(orderSource: 'Shopify').apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10238');
    });

    test('filters by shipping method and line', () {
      final method =
          const OrderFilters(shippingMethod: 'استلام من الفرع').apply(orders);
      expect(method, hasLength(1));

      final line =
          const OrderFilters(shippingLine: 'خط القاهرة').apply(orders);
      expect(line.every((o) => o.shippingLine == 'خط القاهرة'), isTrue);
    });

    test('filters by review status', () {
      final notReviewed = const OrderFilters(reviewed: '0').apply(orders);
      expect(notReviewed.every((o) => o.reviewed == 0), isTrue);

      final withNote = const OrderFilters(reviewed: '2').apply(orders);
      expect(withNote, hasLength(1));
      expect(withNote.first.code, 'ORD-10239');
    });

    test('filters shopify variants', () {
      final only = const OrderFilters(shopify: '1').apply(orders);
      expect(only.every((o) => o.fromShopify), isTrue);
      expect(only.length, 2);

      final pending =
          const OrderFilters(shopify: 'pending_review').apply(orders);
      expect(pending, hasLength(1));
      expect(pending.first.code, 'ORD-10236');

      final reviewed =
          const OrderFilters(shopify: 'reviewed').apply(orders);
      expect(reviewed, hasLength(1));
      expect(reviewed.first.code, 'ORD-10238');
    });

    test('filters collect type', () {
      final result =
          const OrderFilters(collectType: 'تحصيل الكتروني').apply(orders);
      expect(result.every((o) => o.collectType == 'تحصيل الكتروني'), isTrue);
    });

    test('filters by order date (same day)', () {
      final today = DateTime.now();
      final result = OrderFilters(orderDate: today).apply(orders);
      expect(
        result.every(
          (o) =>
              o.createdAt.year == today.year &&
              o.createdAt.month == today.month &&
              o.createdAt.day == today.day,
        ),
        isTrue,
      );
      expect(result.length, greaterThanOrEqualTo(2));
    });

    test('combines multiple filters (AND)', () {
      final result = const OrderFilters(
        governorate: 'القاهرة',
        vip: true,
        orderStatus: 'مؤكد',
      ).apply(orders);
      expect(result, hasLength(1));
      expect(result.first.code, 'ORD-10241');
    });

    test('returns empty when no match', () {
      final result =
          const OrderFilters(customerName: 'اسم غير موجود').apply(orders);
      expect(result, isEmpty);
    });

    test('activeCount reflects set fields', () {
      const f = OrderFilters(
        vip: true,
        customerType: 'افراد',
        orderNumber: 'ORD',
      );
      expect(f.hasActiveFilters, isTrue);
      expect(f.activeCount, 3);
    });

    test('copyWith clears dates with clear flags', () {
      final base = OrderFilters(needByDate: DateTime(2026, 1, 1));
      final cleared = base.copyWith(clearNeedByDate: true);
      expect(cleared.needByDate, isNull);
      expect(cleared.hasActiveFilters, isFalse);
    });
  });

  group('OrderFilterOptions', () {
    test('governorates map has cities', () {
      expect(OrderFilterOptions.governorates['القاهرة'], isNotEmpty);
      expect(OrderFilterOptions.customerTypes, containsAll(['افراد', 'شركة']));
      expect(OrderFilterOptions.orderTypes, contains('جديد'));
    });
  });
}

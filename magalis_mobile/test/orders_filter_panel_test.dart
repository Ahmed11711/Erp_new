import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/models/order_filters.dart';
import 'package:magalis_mobile/screens/orders/orders_screen.dart';
import 'package:magalis_mobile/theme/app_theme.dart';
import 'package:magalis_mobile/widgets/orders_filter_panel.dart';

Widget _wrap(Widget child) {
  return MaterialApp(
    theme: AppTheme.light(),
    locale: const Locale('ar'),
    localizationsDelegates: const [
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    supportedLocales: const [Locale('ar'), Locale('en')],
    builder: (context, child) => Directionality(
      textDirection: TextDirection.rtl,
      child: child!,
    ),
    home: child,
  );
}

Future<void> _expandFilters(WidgetTester tester) async {
  await tester.tap(find.text('فلترة الطلبات'));
  await tester.pumpAndSettle();
}

void main() {
  group('OrdersFilterPanel', () {
    testWidgets('renders filter title when collapsed', (tester) async {
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: OrdersFilterPanel(
              value: OrderFilters.empty,
              initiallyExpanded: false,
              onChanged: (_) {},
            ),
          ),
        ),
      );

      expect(find.text('فلترة الطلبات'), findsOneWidget);
      expect(find.text('خيارات سريعة'), findsNothing);
    });

    testWidgets('expands and shows web-like filter sections', (tester) async {
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: SingleChildScrollView(
              child: OrdersFilterPanel(
                value: OrderFilters.empty,
                initiallyExpanded: false,
                onChanged: (_) {},
              ),
            ),
          ),
        ),
      );

      await _expandFilters(tester);
      expect(find.text('التصنيف والحالة'), findsOneWidget);
      expect(find.text('خيارات سريعة'), findsOneWidget);
      expect(find.text('التواريخ'), findsOneWidget);
      expect(find.text('الموقع والعميل'), findsOneWidget);
      expect(find.text('VIP'), findsOneWidget);
      expect(find.text('نواقص'), findsOneWidget);
      expect(find.text('مدفوع'), findsOneWidget);
      expect(find.text('مبلغ تحت الحساب'), findsOneWidget);
    });

    testWidgets('toggles collapse', (tester) async {
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: SingleChildScrollView(
              child: OrdersFilterPanel(
                value: OrderFilters.empty,
                initiallyExpanded: false,
                onChanged: (_) {},
              ),
            ),
          ),
        ),
      );

      await _expandFilters(tester);
      expect(find.text('خيارات سريعة'), findsOneWidget);

      await tester.tap(find.text('فلترة الطلبات'));
      await tester.pumpAndSettle();
      expect(find.text('خيارات سريعة'), findsNothing);
    });

    testWidgets('VIP chip emits updated filters', (tester) async {
      OrderFilters? latest;
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: SingleChildScrollView(
              child: OrdersFilterPanel(
                value: OrderFilters.empty,
                initiallyExpanded: false,
                onChanged: (f) => latest = f,
              ),
            ),
          ),
        ),
      );

      await _expandFilters(tester);
      await tester.ensureVisible(find.widgetWithText(FilterChip, 'VIP'));
      await tester.tap(find.widgetWithText(FilterChip, 'VIP'));
      await tester.pump();
      expect(latest?.vip, isTrue);
      expect(latest?.activeCount, 1);
    });

    testWidgets('clear button resets filters', (tester) async {
      OrderFilters current = const OrderFilters(vip: true, paid: true);
      await tester.pumpWidget(
        _wrap(
          StatefulBuilder(
            builder: (context, setState) {
              return Scaffold(
                body: SingleChildScrollView(
                  child: OrdersFilterPanel(
                    value: current,
                    initiallyExpanded: false,
                    onChanged: (f) => setState(() => current = f),
                  ),
                ),
              );
            },
          ),
        ),
      );

      expect(find.text('مسح'), findsOneWidget);
      expect(find.text('2'), findsOneWidget);
      await tester.tap(find.text('مسح'));
      await tester.pump();
      expect(current.hasActiveFilters, isFalse);
    });

    testWidgets('customer name field updates filters', (tester) async {
      OrderFilters? latest;
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: SingleChildScrollView(
              child: OrdersFilterPanel(
                value: OrderFilters.empty,
                initiallyExpanded: false,
                onChanged: (f) => latest = f,
              ),
            ),
          ),
        ),
      );

      await _expandFilters(tester);
      await tester.enterText(find.byType(TextField).first, 'سارة');
      await tester.pump();
      expect(latest?.customerName, 'سارة');
    });
  });

  group('OrdersScreen', () {
    testWidgets('shows orders count and filter panel', (tester) async {
      await tester.pumpWidget(_wrap(const OrdersScreen()));
      await tester.pumpAndSettle();

      expect(find.text('الطلبات'), findsOneWidget);
      expect(find.text('فلترة الطلبات'), findsOneWidget);
      expect(find.textContaining('عدد الطلبات:'), findsOneWidget);
      expect(find.text('ORD-10241'), findsOneWidget);
    });

    testWidgets('filters list when VIP selected', (tester) async {
      await tester.pumpWidget(_wrap(const OrdersScreen()));
      await tester.pumpAndSettle();

      await _expandFilters(tester);
      await tester.ensureVisible(find.widgetWithText(FilterChip, 'VIP'));
      await tester.tap(find.widgetWithText(FilterChip, 'VIP'));
      await tester.pumpAndSettle();

      // Return to top and collapse filters so list items build in viewport.
      await tester.drag(find.byType(CustomScrollView), const Offset(0, 800));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('فلترة الطلبات'));
      await tester.tap(find.text('فلترة الطلبات'));
      await tester.pumpAndSettle();

      expect(find.textContaining('فلاتر نشطة:'), findsOneWidget);
      expect(find.text('ORD-10241'), findsOneWidget);
      expect(find.text('ORD-10240'), findsNothing);
    });

    testWidgets('shows empty state for unmatched filter', (tester) async {
      await tester.pumpWidget(_wrap(const OrdersScreen()));
      await tester.pumpAndSettle();

      await _expandFilters(tester);
      final nameField = find.byType(TextField).first;
      await tester.ensureVisible(nameField);
      await tester.enterText(nameField, 'zzzz-no-match');
      await tester.pumpAndSettle();

      await tester.drag(find.byType(CustomScrollView), const Offset(0, 800));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('فلترة الطلبات'));
      await tester.tap(find.text('فلترة الطلبات'));
      await tester.pumpAndSettle();

      expect(find.text('لا توجد طلبات مطابقة للفلتر'), findsOneWidget);
    });
  });
}

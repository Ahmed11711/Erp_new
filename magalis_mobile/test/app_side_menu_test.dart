import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/data/app_menu.dart';
import 'package:magalis_mobile/screens/main_shell.dart';
import 'package:magalis_mobile/theme/app_theme.dart';
import 'package:magalis_mobile/widgets/app_side_menu.dart';

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

void main() {
  group('AppMenu (web sidebar content)', () {
    test('top items match web', () {
      expect(AppMenu.topItems.map((e) => e.title), [
        'الصفحة الرئيسية',
        'محادثات واتساب',
      ]);
    });

    test('includes all primary sections from web sidenav', () {
      final titles = AppMenu.groups.map((g) => g.title).toList();
      expect(
        titles,
        containsAll([
          'المخازن',
          'الأصناف',
          'التشغيل الخارجي',
          'الموردين',
          'المشتريات',
          'التصنيع',
          'الحسابات',
          'إدارة الشحن',
          'HR',
          'الإيصالات والأذونات',
          'Admin',
          'Corporate Sales',
        ]),
      );
    });

    test('shipping section links to orders and offers', () {
      final shipping =
          AppMenu.groups.firstWhere((g) => g.title == 'إدارة الشحن');
      expect(
        shipping.children.any((c) => c.target == MenuTarget.orders),
        isTrue,
      );
      expect(
        shipping.children.any((c) => c.target == MenuTarget.offers),
        isTrue,
      );
    });

    test('accounting has nested groups', () {
      final accounting =
          AppMenu.groups.firstWhere((g) => g.title == 'الحسابات');
      expect(accounting.nestedGroups, isNotEmpty);
      expect(
        accounting.nestedGroups.map((g) => g.title),
        containsAll([
          'شجرة الحسابات',
          'الخزن والبنوك',
          'تقارير الحسابات',
        ]),
      );
    });
  });

  group('AppSideMenu widget', () {
    testWidgets('shows Magalis brand, search, and logout', (tester) async {
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: AppSideMenu(
              onOpen: (target, title, route) {},
              onLogout: () {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Magalis'), findsOneWidget);
      expect(find.text('بحث في القائمة'), findsOneWidget);
      expect(find.text('الصفحة الرئيسية'), findsOneWidget);
      expect(find.text('المخازن'), findsOneWidget);
      expect(find.byKey(const Key('side-menu-logout')), findsOneWidget);
      expect(find.text('تسجيل الخروج'), findsOneWidget);
    });

    testWidgets('search filters menu items', (tester) async {
      await tester.pumpWidget(
        _wrap(
          Scaffold(
            body: AppSideMenu(onOpen: (target, title, route) {}),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'الطلبات');
      await tester.pumpAndSettle();

      expect(find.text('الطلبات'), findsWidgets);
      expect(find.text('Corporate Sales'), findsNothing);
    });
  });

  group('MainShell drawer', () {
    testWidgets('opens system sidebar from home menu button', (tester) async {
      await tester.pumpWidget(_wrap(const MainShell()));
      await tester.pumpAndSettle();

      await tester.tap(find.byIcon(Icons.menu_rounded).first);
      await tester.pumpAndSettle();

      expect(find.text('بحث في القائمة'), findsOneWidget);
      expect(find.text('التشغيل الخارجي'), findsOneWidget);

      await tester.scrollUntilVisible(
        find.text('Corporate Sales'),
        300,
        scrollable: find.byType(Scrollable).last,
      );
      expect(find.text('Corporate Sales'), findsOneWidget);
    });
  });
}

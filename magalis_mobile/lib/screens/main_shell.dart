import 'package:flutter/material.dart';

import '../core/auth_storage.dart';
import '../data/app_menu.dart';
import '../navigation/module_router.dart';
import '../services/auth_service.dart';
import '../theme/app_colors.dart';
import '../widgets/app_side_menu.dart';
import 'home/home_screen.dart';
import 'login_screen.dart';
import 'more/more_screen.dart';
import 'notifications/notifications_screen.dart';
import 'offers/offers_screen.dart';
import 'orders/orders_screen.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key, this.initialIndex = 0});

  final int initialIndex;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  late int _index;
  final _scaffoldKey = GlobalKey<ScaffoldState>();

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
  }

  void openTab(int index) => setState(() => _index = index);

  void openDrawer() => _scaffoldKey.currentState?.openDrawer();

  void _handleMenuOpen(MenuTarget target, String title, String? routeHint) {
    Navigator.of(context).maybePop();
    switch (target) {
      case MenuTarget.home:
        openTab(0);
        return;
      case MenuTarget.orders:
        openTab(1);
        return;
      case MenuTarget.offers:
        openTab(2);
        return;
      case MenuTarget.notifications:
        openTab(3);
        return;
      case MenuTarget.placeholder:
        ModuleRouter.open(
          context,
          title: title,
          routeHint: routeHint,
        );
    }
  }

  Future<void> _logout() async {
    await AuthService.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomeScreen(onOpenTab: openTab, onOpenMenu: openDrawer),
      OrdersScreen(onOpenMenu: openDrawer),
      const OffersScreen(),
      const NotificationsScreen(),
      MoreScreen(onOpenTab: openTab),
    ];

    return Scaffold(
      key: _scaffoldKey,
      drawer: Drawer(
        width: MediaQuery.sizeOf(context).width * 0.86,
        backgroundColor: AppColors.surface,
        child: SafeArea(
          child: AppSideMenu(
            userName: AuthStorage.instance.userName,
            department: AuthStorage.instance.department,
            onHome: () {
              Navigator.of(context).pop();
              openTab(0);
            },
            onOpen: _handleMenuOpen,
            onLogout: _logout,
          ),
        ),
      ),
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: openTab,
        backgroundColor: AppColors.surface,
        indicatorColor: AppColors.primaryBg,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded, color: AppColors.primary),
            label: 'الرئيسية',
          ),
          NavigationDestination(
            icon: Icon(Icons.local_shipping_outlined),
            selectedIcon:
                Icon(Icons.local_shipping_rounded, color: AppColors.primary),
            label: 'الطلبات',
          ),
          NavigationDestination(
            icon: Icon(Icons.request_quote_outlined),
            selectedIcon:
                Icon(Icons.request_quote_rounded, color: AppColors.primary),
            label: 'العروض',
          ),
          NavigationDestination(
            icon: Icon(Icons.notifications_outlined),
            selectedIcon:
                Icon(Icons.notifications_rounded, color: AppColors.primary),
            label: 'الإشعارات',
          ),
          NavigationDestination(
            icon: Icon(Icons.menu_rounded),
            selectedIcon: Icon(Icons.menu_rounded, color: AppColors.primary),
            label: 'القائمة',
          ),
        ],
      ),
    );
  }
}

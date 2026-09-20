import 'package:flutter/material.dart';

import '../../core/auth_storage.dart';
import '../../data/app_menu.dart';
import '../../navigation/module_router.dart';
import '../../services/auth_service.dart';
import '../../theme/app_colors.dart';
import '../../widgets/app_side_menu.dart';
import '../login_screen.dart';

/// Full Magalis sidenav — same sections as web dashboard.
class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key, required this.onOpenTab});

  final ValueChanged<int> onOpenTab;

  void _handleOpen(
    BuildContext context,
    MenuTarget target,
    String title,
    String? routeHint,
  ) {
    switch (target) {
      case MenuTarget.home:
        onOpenTab(0);
        return;
      case MenuTarget.orders:
        onOpenTab(1);
        return;
      case MenuTarget.offers:
        onOpenTab(2);
        return;
      case MenuTarget.notifications:
        onOpenTab(3);
        return;
      case MenuTarget.placeholder:
        ModuleRouter.open(
          context,
          title: title,
          routeHint: routeHint,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: AppSideMenu(
          userName: AuthStorage.instance.userName,
          department: AuthStorage.instance.department,
          onHome: () => onOpenTab(0),
          onOpen: (target, title, route) =>
              _handleOpen(context, target, title, route),
          onLogout: () async {
            await AuthService.instance.logout();
            if (!context.mounted) return;
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const LoginScreen()),
              (_) => false,
            );
          },
        ),
      ),
    );
  }
}

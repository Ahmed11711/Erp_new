import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/auth_storage.dart';
import '../../models/order.dart';
import '../../services/notifications_api.dart';
import '../../services/orders_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/kpi_card.dart';
import '../../widgets/quick_tile.dart';
import '../../widgets/section_header.dart';
import '../orders/order_details_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.onOpenTab,
    this.onOpenMenu,
  });

  final ValueChanged<int> onOpenTab;
  final VoidCallback? onOpenMenu;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  int _todayOrders = 0;
  int _lateConfirmed = 0;
  int _pendingApprovals = 0;
  int _shipPipeline = 0;
  int _collectPipeline = 0;
  List<Order> _recent = [];

  String get _greeting {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'صباح الخير';
    return 'مساء الخير';
  }

  String get _todayYmd {
    final d = DateTime.now();
    final m = d.month.toString().padLeft(2, '0');
    final day = d.day.toString().padLeft(2, '0');
    return '${d.year}-$m-$day';
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final today = _todayYmd;

    final todayOrders = await OrdersApi.instance.count(params: {'order_date': today});
    final ship = await OrdersApi.instance.count(params: {'order_status': 'تم شحن'});
    final collect =
        await OrdersApi.instance.count(params: {'order_status': 'تم التحصيل'});
    final recentPage = await OrdersApi.instance.search(page: 1, itemsPerPage: 5);

    var lateConfirmed = 0;
    var unread = 0;
    try {
      final inbox = await NotificationsApi.instance.inbox();
      lateConfirmed = inbox.lateConfirmedCount;
      unread = inbox.items.where((n) => !n.isRead).length;
    } catch (_) {}

    if (!mounted) return;
    setState(() {
      _todayOrders = todayOrders;
      _shipPipeline = ship;
      _collectPipeline = collect;
      _recent = recentPage.items;
      _lateConfirmed = lateConfirmed;
      _pendingApprovals = unread;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final today = DateFormat('EEEE، d MMMM yyyy', 'ar').format(DateTime.now());
    final userName = AuthStorage.instance.userName;

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  child: Column(
                    children: [
                      if (widget.onOpenMenu != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(
                            children: [
                              Material(
                                color: AppColors.surface,
                                borderRadius: BorderRadius.circular(12),
                                child: InkWell(
                                  onTap: widget.onOpenMenu,
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    width: 42,
                                    height: 42,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(12),
                                      border:
                                          Border.all(color: AppColors.border),
                                    ),
                                    child: const Icon(
                                      Icons.menu_rounded,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              const Expanded(
                                child: Text(
                                  'Magalis',
                                  style: TextStyle(
                                    fontSize: 20,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primary,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      _HeroBanner(
                        greeting: '$_greeting، $userName',
                        today: today,
                        pendingApprovals: _pendingApprovals,
                      ),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
                sliver: SliverGrid(
                  gridDelegate:
                      const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 1.28,
                  ),
                  delegate: SliverChildListDelegate([
                    KpiCard(
                      label: 'طلبات اليوم',
                      value: _loading ? '…' : '$_todayOrders',
                      icon: Icons.local_shipping_rounded,
                      iconColor: AppColors.primary,
                      onTap: () => widget.onOpenTab(1),
                    ),
                    KpiCard(
                      label: 'إشعارات غير مقروءة',
                      value: _loading ? '…' : '$_pendingApprovals',
                      icon: Icons.fact_check_rounded,
                      iconColor: AppColors.info,
                      onTap: () => widget.onOpenTab(3),
                    ),
                    KpiCard(
                      label: 'طلبات مؤكدة متأخرة',
                      value: _loading ? '…' : '$_lateConfirmed',
                      icon: Icons.warning_amber_rounded,
                      iconColor: AppColors.warning,
                      onTap: () => widget.onOpenTab(1),
                    ),
                    KpiCard(
                      label: 'قيد الشحن',
                      value: _loading ? '…' : '$_shipPipeline',
                      icon: Icons.local_shipping_outlined,
                      iconColor: AppColors.navy,
                      onTap: () => widget.onOpenTab(1),
                    ),
                  ]),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 22, 16, 8),
                  child: SectionHeader(
                    title: 'العمليات',
                    actionLabel: 'الكل',
                    onAction: () => widget.onOpenTab(4),
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                sliver: SliverGrid(
                  gridDelegate:
                      const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 0.95,
                  ),
                  delegate: SliverChildListDelegate([
                    QuickTile(
                      icon: Icons.local_shipping_outlined,
                      label: 'الطلبات',
                      onTap: () => widget.onOpenTab(1),
                    ),
                    QuickTile(
                      icon: Icons.request_quote_outlined,
                      label: 'عروض الأسعار',
                      onTap: () => widget.onOpenTab(2),
                    ),
                    QuickTile(
                      icon: Icons.notifications_outlined,
                      label: 'الإشعارات',
                      onTap: () => widget.onOpenTab(3),
                    ),
                    QuickTile(
                      icon: Icons.checklist_rounded,
                      label: 'الموافقات',
                      onTap: () => _soon(context, 'الموافقات'),
                    ),
                    QuickTile(
                      icon: Icons.my_location_outlined,
                      label: 'التتبع',
                      onTap: () => _soon(context, 'التتبع'),
                    ),
                    QuickTile(
                      icon: Icons.inventory_2_outlined,
                      label: 'المخزون',
                      onTap: () => _soon(context, 'المخزون'),
                    ),
                  ]),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 22, 16, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'مسار العملية التشغيلية'),
                      const SizedBox(height: 10),
                      _PipelineRow(
                        steps: [
                          _PipeStep(
                            'تحصيل',
                            _collectPipeline,
                            Icons.receipt_long,
                          ),
                          _PipeStep(
                            'شحن',
                            _shipPipeline,
                            Icons.local_shipping,
                          ),
                          const _PipeStep(
                            'تصنيع',
                            0,
                            Icons.precision_manufacturing,
                          ),
                          const _PipeStep(
                            'مشتريات',
                            0,
                            Icons.shopping_cart,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 8),
                  child: SectionHeader(
                    title: 'أحدث الطلبات',
                    actionLabel: 'عرض الكل',
                    onAction: () => widget.onOpenTab(1),
                  ),
                ),
              ),
              if (_loading && _recent.isEmpty)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                )
              else if (_recent.isEmpty)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(
                      child: Text(
                        'لا توجد طلبات حديثة',
                        style: TextStyle(
                          color: AppColors.textMuted,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                )
              else
                SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, i) {
                      final order = _recent[i];
                      return Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                        child: _RecentOrderTile(
                          code: '#${order.code}',
                          customer: order.customerName,
                          total: Formatters.money(order.total),
                          status: order.statusLabel,
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) =>
                                  OrderDetailsScreen(orderId: order.id),
                            ),
                          ),
                        ),
                      );
                    },
                    childCount: _recent.length,
                  ),
                ),
              const SliverToBoxAdapter(child: SizedBox(height: 24)),
            ],
          ),
        ),
      ),
    );
  }

  void _soon(BuildContext context, String name) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('شاشة $name — قريباً'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}

class _HeroBanner extends StatelessWidget {
  const _HeroBanner({
    required this.greeting,
    required this.today,
    required this.pendingApprovals,
  });

  final String greeting;
  final String today;
  final int pendingApprovals;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(
          begin: Alignment.topRight,
          end: Alignment.bottomLeft,
          colors: [
            AppColors.primary,
            AppColors.primaryDark,
            AppColors.navyDark,
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.28),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  greeting,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.dashboard_customize_outlined,
                  color: Colors.white,
                  size: 22,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            today,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.85),
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'لديك $pendingApprovals إشعارات غير مقروءة',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.9),
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}

class _PipeStep {
  const _PipeStep(this.label, this.count, this.icon);
  final String label;
  final int count;
  final IconData icon;
}

class _PipelineRow extends StatelessWidget {
  const _PipelineRow({required this.steps});
  final List<_PipeStep> steps;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          for (var i = 0; i < steps.length; i++) ...[
            if (i > 0)
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 2),
                child: Icon(
                  Icons.chevron_left,
                  color: AppColors.border,
                  size: 18,
                ),
              ),
            Expanded(
              child: Column(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: AppColors.primaryBg,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      steps[i].icon,
                      color: AppColors.primary,
                      size: 20,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    steps[i].label,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  Text(
                    '${steps[i].count}',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppColors.navy,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _RecentOrderTile extends StatelessWidget {
  const _RecentOrderTile({
    required this.code,
    required this.customer,
    required this.total,
    required this.status,
    required this.onTap,
  });

  final String code;
  final String customer;
  final String total;
  final String status;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.primaryBg,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.receipt_long, color: AppColors.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      code,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.navy,
                      ),
                    ),
                    Text(
                      customer,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    total,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    status,
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

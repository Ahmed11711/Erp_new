import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/accounting_tree_api.dart';
import '../../theme/app_colors.dart';

/// دليل الحسابات — mirrors Angular `general-accounts`.
class GeneralAccountsScreen extends StatefulWidget {
  const GeneralAccountsScreen({super.key});

  @override
  State<GeneralAccountsScreen> createState() => _GeneralAccountsScreenState();
}

class _GeneralAccountsScreenState extends State<GeneralAccountsScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');

  final _searchCtrl = TextEditingController();
  List<TreeAccountNode> _all = [];
  List<TreeAccountNode> _filtered = [];
  String _selectedType = '';
  bool _loading = true;
  String? _error;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final accounts = await AccountingTreeApi.instance.fetchAll();
      if (!mounted) return;
      setState(() {
        _all = accounts;
        _applyFilter();
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل دليل الحسابات';
        _loading = false;
      });
    }
  }

  void _applyFilter() {
    _filtered = AccountingTreeApi.filterAccounts(
      accounts: _all,
      query: _searchCtrl.text,
      type: _selectedType,
    );
  }

  void _onSearch(String _) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 250), () {
      if (!mounted) return;
      setState(_applyFilter);
    });
  }

  Color _typeColor(String type) {
    switch (type) {
      case 'asset':
        return const Color(0xFF1976D2);
      case 'liability':
        return const Color(0xFFC62828);
      case 'equity':
        return const Color(0xFF7B1FA2);
      case 'revenue':
        return const Color(0xFF2E7D32);
      case 'expense':
        return const Color(0xFFEF6C00);
      case 'settlement':
        return const Color(0xFF4527A0);
      default:
        return AppColors.textSecondary;
    }
  }

  void _showDetails(TreeAccountNode account) {
    final role = AccountingTreeApi.hierarchyRole(account, _all);
    final balance = account.displayBalance;
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                account.code.isEmpty
                    ? account.name
                    : '${account.code}  ${account.name}',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                  color: AppColors.navy,
                ),
              ),
              if (account.nameEn != null) ...[
                const SizedBox(height: 4),
                Text(
                  account.nameEn!,
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _chip(account.typeLabel, _typeColor(account.type)),
                  _chip(role, AppColors.navy),
                  if (account.isTradingAccount)
                    _chip('حساب تداول', AppColors.warning),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  const Text(
                    'الرصيد',
                    style: TextStyle(
                      color: AppColors.textMuted,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    _money.format(balance),
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: balance < 0 ? AppColors.danger : AppColors.success,
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

  Widget _chip(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('دليل الحسابات'),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'تحديث',
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearch,
              decoration: InputDecoration(
                hintText: 'بحث باسم الحساب أو الكود...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(_applyFilter);
                        },
                        icon: const Icon(Icons.clear),
                      ),
              ),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(
              children: [
                _typeChip('', 'جميع الأنواع'),
                for (final e in kAccountTypeLabels.entries)
                  _typeChip(e.key, e.value),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                _loading ? '' : '${_filtered.length} حساب',
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ),
          ),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  Widget _typeChip(String value, String label) {
    final selected = _selectedType == value;
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 8),
      child: FilterChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) {
          setState(() {
            _selectedType = value;
            _applyFilter();
          });
        },
        selectedColor: AppColors.primaryBg,
        checkmarkColor: AppColors.primary,
        labelStyle: TextStyle(
          fontWeight: FontWeight.w700,
          color: selected ? AppColors.primary : AppColors.textSecondary,
          fontSize: 12,
        ),
        side: BorderSide(
          color: selected ? AppColors.primary : AppColors.border,
        ),
        backgroundColor: AppColors.surface,
        visualDensity: VisualDensity.compact,
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.danger,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: _load,
                child: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
      );
    }
    if (_filtered.isEmpty) {
      return const Center(
        child: Text(
          'لا توجد حسابات تطابق بحثك',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
        itemCount: _filtered.length,
        itemBuilder: (context, i) {
          final account = _filtered[i];
          final balance = account.displayBalance;
          final role = AccountingTreeApi.hierarchyRole(account, _all);
          final indent = ((account.level - 1).clamp(0, 6)) * 10.0;
          return InkWell(
            onTap: () => _showDetails(account),
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 3),
              padding: EdgeInsetsDirectional.only(
                start: 12 + indent,
                end: 12,
                top: 10,
                bottom: 10,
              ),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.bg,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      account.code.isEmpty ? '—' : account.code,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                        color: AppColors.navy,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          account.name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          children: [
                            _chip(account.typeLabel, _typeColor(account.type)),
                            Text(
                              role,
                              style: const TextStyle(
                                color: AppColors.textMuted,
                                fontWeight: FontWeight.w700,
                                fontSize: 11,
                              ),
                            ),
                            if (account.isTradingAccount)
                              const Text(
                                'حساب تداول',
                                style: TextStyle(
                                  color: AppColors.warning,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 11,
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    _money.format(balance),
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                      color: balance < 0 ? AppColors.danger : AppColors.success,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

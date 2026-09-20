import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../navigation/module_catalog.dart';
import '../../services/catalog_api.dart';
import '../../services/whatsapp_api.dart';
import '../../theme/app_colors.dart';
import '../whatsapp/whatsapp_chat_screen.dart';

class ApiListScreen extends StatefulWidget {
  const ApiListScreen({super.key, required this.module});

  final ModuleDef module;

  @override
  State<ApiListScreen> createState() => _ApiListScreenState();
}

class _ApiListScreenState extends State<ApiListScreen> {
  final _searchCtrl = TextEditingController();
  List<CatalogRow> _items = [];
  int _total = 0;
  bool _loading = true;
  String? _error;
  Timer? _debounce;
  String _archiveFilter = 'inbox';
  int? _archiveBusyId;
  bool _archiveAllBusy = false;

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

  List<CatalogRow> get _visibleItems {
    if (!_isWhatsApp) return _items;
    final q = _searchCtrl.text.trim();
    if (q.isEmpty) return _items;
    final lower = q.toLowerCase();
    final qDigits = q.replaceAll(RegExp(r'\D'), '');
    return _items.where((row) {
      final name = row.title.toLowerCase();
      final phone = (row.phone ?? '').toLowerCase();
      final phoneDigits = phone.replaceAll(RegExp(r'\D'), '');
      final nameMatch = name.contains(lower);
      final phoneMatch = qDigits.isNotEmpty
          ? phoneDigits.contains(qDigits)
          : phone.contains(lower);
      return nameMatch || phoneMatch;
    }).toList();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await CatalogApi.instance.fetch(
        path: widget.module.path,
        mapRow: widget.module.mapRow,
        // WhatsApp list search is client-side (same as Angular chat-page).
        search: _isWhatsApp ? null : _searchCtrl.text,
        searchParam: widget.module.searchParam,
        extraParams: {
          ...?widget.module.extraParams,
          if (_isWhatsApp) 'archive': _archiveFilter,
        },
      );
      if (!mounted) return;
      setState(() {
        _items = page.items;
        _total = page.total;
        _loading = false;
        _archiveBusyId = null;
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
        _error = 'تعذر تحميل البيانات';
        _loading = false;
      });
    }
  }

  void _onSearch(String _) {
    if (_isWhatsApp) {
      setState(() {});
      return;
    }
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), _load);
  }

  bool get _isWhatsApp => widget.module.path.contains('whatsapp');

  void _openRow(CatalogRow row) {
    if (!_isWhatsApp || row.id.isEmpty) return;
    Navigator.of(context)
        .push(
      MaterialPageRoute(
        builder: (_) => WhatsAppChatScreen(
          customerId: row.id,
          customerName: row.title,
          customerPhone: row.phone,
          initiallyArchived: row.isArchived,
        ),
      ),
    )
        .then((_) {
      if (mounted) _load();
    });
  }

  Future<void> _archiveAllInbox() async {
    if (_archiveAllBusy || _archiveFilter != 'inbox') return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('أرشفة الجميع؟'),
        content: Text(
          'سيتم أرشفة محادثات الصندوق التي تم الرد عليها فقط. المحادثات المنتظرة رداً ستبقى ظاهرة.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('إلغاء'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('أرشفة الجميع'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _archiveAllBusy = true);
    try {
      final count = await WhatsAppApi.instance.archiveAllCustomers();
      if (!mounted) return;
      setState(() => _archiveAllBusy = false);
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('تم أرشفة $count محادثة'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _archiveAllBusy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _archiveAllBusy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر أرشفة المحادثات'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _toggleArchive(CatalogRow row) async {
    final id = int.tryParse(row.id);
    if (id == null || _archiveBusyId == id) return;
    if (!row.isArchived && row.awaitingReply) return;
    setState(() => _archiveBusyId = id);
    try {
      await WhatsAppApi.instance.setCustomerArchive(
        customerId: id,
        archived: !row.isArchived,
      );
      if (!mounted) return;
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _archiveBusyId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _archiveBusyId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر تحديث الأرشيف'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: Text(widget.module.title),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
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
                hintText: widget.module.searchHint,
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _searchCtrl.clear();
                          _load();
                        },
                        icon: const Icon(Icons.clear),
                      ),
              ),
            ),
          ),
          if (_isWhatsApp)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: _archiveChip(
                      label: 'الصندوق',
                      selected: _archiveFilter == 'inbox',
                      onTap: () {
                        if (_archiveFilter == 'inbox') return;
                        setState(() => _archiveFilter = 'inbox');
                        _load();
                      },
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _archiveChip(
                      label: 'الأرشيف',
                      selected: _archiveFilter == 'archived',
                      onTap: () {
                        if (_archiveFilter == 'archived') return;
                        setState(() => _archiveFilter = 'archived');
                        _load();
                      },
                    ),
                  ),
                ],
              ),
            ),
          if (_isWhatsApp && _archiveFilter == 'inbox')
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: SizedBox(
                width: double.infinity,
                height: 44,
                child: OutlinedButton.icon(
                  onPressed: _loading || _archiveAllBusy || _total == 0
                      ? null
                      : _archiveAllInbox,
                  icon: const Icon(Icons.archive_outlined, size: 18),
                  label: Text(_archiveAllBusy ? 'جاري الأرشفة…' : 'أرشفة الجميع'),
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                'العدد: $_total',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
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
                      )
                    : _visibleItems.isEmpty
                        ? Center(
                            child: Text(
                              _isWhatsApp && _archiveFilter == 'archived'
                                  ? 'لا توجد محادثات مؤرشفة'
                                  : 'لا توجد بيانات',
                              style: const TextStyle(
                                color: AppColors.textMuted,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.separated(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                              itemCount: _visibleItems.length,
                              separatorBuilder: (context, index) =>
                                  const SizedBox(height: 10),
                              itemBuilder: (context, i) {
                                final row = _visibleItems[i];
                                final titleTrim = row.title.trim();
                                final letter = titleTrim.isNotEmpty
                                    ? String.fromCharCode(titleTrim.runes.first)
                                    : '?';
                                return Material(
                                  color: AppColors.surface,
                                  borderRadius: BorderRadius.circular(14),
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(14),
                                    onTap: _isWhatsApp
                                        ? () => _openRow(row)
                                        : null,
                                    child: Container(
                                      padding: const EdgeInsets.all(14),
                                      decoration: BoxDecoration(
                                        borderRadius: BorderRadius.circular(14),
                                        border: Border.all(
                                          color: AppColors.border,
                                        ),
                                      ),
                                      child: Row(
                                        children: [
                                          Container(
                                            width: 40,
                                            height: 40,
                                            decoration: BoxDecoration(
                                              color: AppColors.primaryBg,
                                              borderRadius:
                                                  BorderRadius.circular(
                                                _isWhatsApp ? 20 : 12,
                                              ),
                                            ),
                                            alignment: Alignment.center,
                                            child: _isWhatsApp
                                                ? Text(
                                                    letter.toUpperCase(),
                                                    style: const TextStyle(
                                                      color: AppColors.primary,
                                                      fontWeight:
                                                          FontWeight.w800,
                                                    ),
                                                  )
                                                : const Icon(
                                                    Icons.folder_open_outlined,
                                                    color: AppColors.primary,
                                                    size: 20,
                                                  ),
                                          ),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  row.title,
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.w800,
                                                    color: AppColors.navy,
                                                  ),
                                                ),
                                                if (row.subtitle != null &&
                                                    row.subtitle!
                                                        .isNotEmpty) ...[
                                                  const SizedBox(height: 3),
                                                  Text(
                                                    row.subtitle!,
                                                    maxLines: 2,
                                                    overflow:
                                                        TextOverflow.ellipsis,
                                                    style: const TextStyle(
                                                      color: AppColors
                                                          .textSecondary,
                                                      fontWeight:
                                                          FontWeight.w600,
                                                      fontSize: 12,
                                                    ),
                                                  ),
                                                ],
                                              ],
                                            ),
                                          ),
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.end,
                                            children: [
                                              if (row.trailing != null &&
                                                  row.trailing!.isNotEmpty)
                                                Text(
                                                  row.trailing!,
                                                  style: TextStyle(
                                                    fontWeight: FontWeight.w800,
                                                    color: _isWhatsApp
                                                        ? AppColors.textMuted
                                                        : AppColors.primary,
                                                    fontSize: 12,
                                                  ),
                                                ),
                                              if (_isWhatsApp)
                                                IconButton(
                                                  visualDensity:
                                                      VisualDensity.compact,
                                                  tooltip: row.isArchived
                                                      ? 'إرجاع للصندوق'
                                                      : row.awaitingReply
                                                          ? 'لا يمكن الأرشفة قبل الرد على رسالة العميل'
                                                          : 'أرشفة',
                                                  onPressed: _archiveBusyId ==
                                                              int.tryParse(row.id) ||
                                                          (!row.isArchived &&
                                                              row.awaitingReply)
                                                      ? null
                                                      : () =>
                                                          _toggleArchive(row),
                                                  icon: Icon(
                                                    row.isArchived
                                                        ? Icons.unarchive_outlined
                                                        : Icons.archive_outlined,
                                                    color: AppColors.primary,
                                                  ),
                                                ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _archiveChip({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Material(
      color: selected ? AppColors.primary : AppColors.surface,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected ? AppColors.primary : AppColors.border,
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : AppColors.navy,
              fontWeight: FontWeight.w800,
              fontSize: 13,
            ),
          ),
        ),
      ),
    );
  }
}

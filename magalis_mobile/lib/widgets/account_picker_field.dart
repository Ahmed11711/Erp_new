import 'package:flutter/material.dart';

import '../services/treasury_api.dart';
import '../theme/app_colors.dart';

/// Searchable tree-account picker (replaces Angular mat-autocomplete).
class AccountPickerField extends StatelessWidget {
  const AccountPickerField({
    super.key,
    required this.label,
    required this.options,
    required this.selectedId,
    required this.onSelected,
    this.requiredField = false,
  });

  final String label;
  final List<AccountOption> options;
  final int? selectedId;
  final ValueChanged<int?> onSelected;
  final bool requiredField;

  AccountOption? get _selected {
    if (selectedId == null) return null;
    for (final o in options) {
      if (o.id == selectedId) return o;
    }
    return null;
  }

  Future<void> _open(BuildContext context) async {
    final picked = await showModalBottomSheet<AccountOption?>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => _AccountPickerSheet(
        options: options,
        selectedId: selectedId,
      ),
    );
    if (picked == null) return;
    if (picked.id == 0) {
      onSelected(null);
    } else {
      onSelected(picked.id);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selected = _selected;
    return InkWell(
      onTap: () => _open(context),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: requiredField ? '$label *' : label,
          suffixIcon: const Icon(Icons.search),
        ),
        child: Text(
          selected?.label ?? 'ابحث بالاسم أو رمز الحساب',
          style: TextStyle(
            fontWeight: selected == null ? FontWeight.w500 : FontWeight.w700,
            color: selected == null ? AppColors.textMuted : AppColors.text,
          ),
        ),
      ),
    );
  }
}

class _AccountPickerSheet extends StatefulWidget {
  const _AccountPickerSheet({
    required this.options,
    this.selectedId,
  });

  final List<AccountOption> options;
  final int? selectedId;

  @override
  State<_AccountPickerSheet> createState() => _AccountPickerSheetState();
}

class _AccountPickerSheetState extends State<_AccountPickerSheet> {
  final _ctrl = TextEditingController();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  List<AccountOption> get _filtered {
    final q = _ctrl.text.trim().toLowerCase();
    if (q.isEmpty) return widget.options.take(400).toList();
    return widget.options.where((a) {
      return a.label.toLowerCase().contains(q) || '${a.id}'.contains(q);
    }).take(400).toList();
  }

  @override
  Widget build(BuildContext context) {
    final items = _filtered;
    return SafeArea(
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.75,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: TextField(
                controller: _ctrl,
                autofocus: true,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  hintText: 'ابحث بالاسم أو رمز الحساب',
                  prefixIcon: Icon(Icons.search),
                ),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.clear),
              title: const Text('بدون اختيار'),
              onTap: () => Navigator.pop(
                context,
                const AccountOption(id: 0, label: ''),
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, i) {
                  final a = items[i];
                  final sel = a.id == widget.selectedId;
                  return ListTile(
                    title: Text(
                      a.label,
                      style: TextStyle(
                        fontWeight: sel ? FontWeight.w800 : FontWeight.w600,
                      ),
                    ),
                    selected: sel,
                    onTap: () => Navigator.pop(context, a),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

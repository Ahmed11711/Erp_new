import 'package:flutter/material.dart';

import '../data/app_menu.dart';
import '../theme/app_colors.dart';

typedef MenuOpenCallback = void Function(MenuTarget target, String title, String? routeHint);

/// Web-matching Magalis sidenav (RTL).
class AppSideMenu extends StatefulWidget {
  const AppSideMenu({
    super.key,
    required this.onOpen,
    this.onHome,
    this.showLogout = true,
    this.onLogout,
    this.userName = 'مستخدم',
    this.department = '',
  });

  final MenuOpenCallback onOpen;
  final VoidCallback? onHome;
  final bool showLogout;
  final VoidCallback? onLogout;
  final String userName;
  final String department;

  @override
  State<AppSideMenu> createState() => _AppSideMenuState();
}

class _AppSideMenuState extends State<AppSideMenu> {
  final _searchCtrl = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  bool _match(String text) {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return true;
    return text.toLowerCase().contains(q);
  }

  bool _groupVisible(MenuGroup g) {
    if (_match(g.title)) return true;
    if (g.children.any((c) => _match(c.title))) return true;
    return g.nestedGroups.any(_groupVisible);
  }

  List<MenuLeaf> _leaves(MenuGroup g) {
    if (_query.trim().isEmpty || _match(g.title)) return g.children;
    return g.children.where((c) => _match(c.title)).toList();
  }

  List<MenuGroup> _nested(MenuGroup g) {
    if (_query.trim().isEmpty || _match(g.title)) return g.nestedGroups;
    return g.nestedGroups.where(_groupVisible).toList();
  }

  @override
  Widget build(BuildContext context) {
    final top = AppMenu.topItems.where((i) => _match(i.title)).toList();
    final groups = AppMenu.groups.where(_groupVisible).toList();
    final searching = _query.trim().isNotEmpty;
    final initials = widget.userName.length >= 2
        ? widget.userName.substring(0, 2)
        : widget.userName;

    return Material(
      color: AppColors.surface,
      child: Column(
        children: [
          // Brand — like .side-brand
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Magalis',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary,
                      letterSpacing: 0.4,
                    ),
                  ),
                ),
                Material(
                  color: Colors.transparent,
                  child: InkWell(
                    onTap: widget.onHome ??
                        () => widget.onOpen(MenuTarget.home, 'الصفحة الرئيسية', null),
                    borderRadius: BorderRadius.circular(11),
                    child: Ink(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(11),
                        gradient: const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [AppColors.primaryDark, AppColors.primary],
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: 0.3),
                            blurRadius: 12,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: const Icon(Icons.home, color: Colors.white, size: 20),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: AppColors.border),

          // Search — like .side-search
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (v) => setState(() => _query = v),
              decoration: InputDecoration(
                hintText: 'بحث في القائمة',
                filled: true,
                fillColor: const Color(0xFFF5EEF4),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                prefixIcon: const Icon(Icons.search, color: AppColors.textMuted),
                suffixIcon: _query.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close, size: 18),
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(() => _query = '');
                        },
                      ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(11),
                  borderSide: const BorderSide(color: AppColors.border),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(11),
                  borderSide: const BorderSide(color: AppColors.border),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(11),
                  borderSide: const BorderSide(color: AppColors.primaryLight),
                ),
              ),
            ),
          ),

          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(10, 4, 10, 12),
              children: [
                for (final item in top)
                  _TopLink(
                    item: item,
                    onTap: () =>
                        widget.onOpen(item.target, item.title, item.routeHint),
                  ),
                const SizedBox(height: 4),
                for (final g in groups)
                  _SectionPanel(
                    group: g,
                    leaves: _leaves(g),
                    nested: _nested(g),
                    expand: searching,
                    query: _query,
                    onOpen: widget.onOpen,
                  ),
              ],
            ),
          ),

          if (widget.showLogout && widget.onLogout != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 0, 10, 4),
              child: ListTile(
                key: const Key('side-menu-logout'),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                leading: const Icon(Icons.logout, color: AppColors.danger),
                title: const Text(
                  'تسجيل الخروج',
                  style: TextStyle(
                    color: AppColors.danger,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                onTap: widget.onLogout,
              ),
            ),

          // Footer — like .side-user
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: AppColors.border)),
              color: AppColors.surface,
            ),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(
                      colors: [AppColors.primaryLight, AppColors.primary],
                    ),
                  ),
                  child: Text(
                    initials,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.userName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                          color: AppColors.text,
                        ),
                      ),
                      if (widget.department.isNotEmpty)
                        Text(
                          widget.department,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 11,
                            color: AppColors.textMuted,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TopLink extends StatelessWidget {
  const _TopLink({required this.item, required this.onTap});

  final MenuTopItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final active = item.active;
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Material(
        color: active ? AppColors.primary : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            child: Row(
              children: [
                Icon(
                  item.icon,
                  size: 22,
                  color: active ? Colors.white : AppColors.primary,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    item.title,
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 15,
                      color: active ? Colors.white : AppColors.text,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionPanel extends StatelessWidget {
  const _SectionPanel({
    required this.group,
    required this.leaves,
    required this.nested,
    required this.expand,
    required this.query,
    required this.onOpen,
    this.depth = 0,
  });

  final MenuGroup group;
  final List<MenuLeaf> leaves;
  final List<MenuGroup> nested;
  final bool expand;
  final String query;
  final MenuOpenCallback onOpen;
  final int depth;

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        key: PageStorageKey('menu-${group.title}-$depth-$expand'),
        initiallyExpanded: expand,
        tilePadding: EdgeInsetsDirectional.only(
          start: depth == 0 ? 8 : 16,
          end: 4,
        ),
        childrenPadding: EdgeInsets.only(bottom: 4, right: depth == 0 ? 0 : 4),
        leading: Icon(group.icon, color: AppColors.primary, size: 22),
        title: Text(
          group.title,
          style: TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: depth == 0 ? 15 : 14,
            color: AppColors.text,
          ),
        ),
        iconColor: AppColors.textMuted,
        collapsedIconColor: AppColors.textMuted,
        children: [
          for (final leaf in leaves)
            ListTile(
              dense: true,
              visualDensity: VisualDensity.compact,
              contentPadding: EdgeInsetsDirectional.only(
                start: depth == 0 ? 48 : 56,
                end: 12,
              ),
              title: Row(
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    margin: const EdgeInsetsDirectional.only(end: 8),
                    decoration: const BoxDecoration(
                      color: AppColors.primaryLight,
                      shape: BoxShape.circle,
                    ),
                  ),
                  Expanded(
                    child: Text(
                      leaf.title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w500,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ],
              ),
              onTap: () => onOpen(leaf.target, leaf.title, leaf.routeHint),
            ),
          for (final n in nested)
            _SectionPanel(
              group: n,
              leaves: query.trim().isEmpty || n.title.contains(query)
                  ? n.children
                  : n.children.where((c) => c.title.contains(query)).toList(),
              nested: n.nestedGroups,
              expand: expand,
              query: query,
              onOpen: onOpen,
              depth: depth + 1,
            ),
        ],
      ),
    );
  }
}

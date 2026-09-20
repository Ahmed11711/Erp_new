import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../data/egypt_locations.dart';
import '../../services/companies_api.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';
import '../../widgets/account_picker_field.dart';

/// إضافة / تعديل شركة عميل — Angular `DialogAddCompanyComponent`.
class CompanyFormDialog extends StatefulWidget {
  const CompanyFormDialog({super.key, this.company});

  final CustomerCompany? company;

  static Future<bool> open(BuildContext context, {CustomerCompany? company}) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => CompanyFormDialog(company: company),
    );
    return ok == true;
  }

  @override
  State<CompanyFormDialog> createState() => _CompanyFormDialogState();
}

class _CompanyFormDialogState extends State<CompanyFormDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone1;
  late final TextEditingController _phone2;
  late final TextEditingController _phone3;
  late final TextEditingController _phone4;
  late final TextEditingController _tel;
  late final TextEditingController _address;

  List<EgyptGovernorate> _govs = [];
  List<EgyptCity> _cities = [];
  List<AccountOption> _accounts = [];
  String? _governorate;
  String? _city;
  int? _treeAccountId;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.company != null;

  static final _phoneRe = RegExp(r'^01\d{9}$');

  @override
  void initState() {
    super.initState();
    final c = widget.company;
    _name = TextEditingController(text: c?.name ?? '');
    _phone1 = TextEditingController(text: c?.phone1 ?? '');
    _phone2 = TextEditingController(text: c?.phone2 ?? '');
    _phone3 = TextEditingController(text: c?.phone3 ?? '');
    _phone4 = TextEditingController(text: c?.phone4 ?? '');
    _tel = TextEditingController(text: c?.tel ?? '');
    _address = TextEditingController(text: c?.address ?? '');
    _governorate = c?.governorate;
    _city = c?.city;
    _treeAccountId = c?.treeAccountId;
    _bootstrap();
  }

  @override
  void dispose() {
    _name.dispose();
    _phone1.dispose();
    _phone2.dispose();
    _phone3.dispose();
    _phone4.dispose();
    _tel.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final govs = await EgyptLocations.governorates();
    List<AccountOption> acc = [];
    try {
      acc = await TreasuryApi.instance.fetchAccountOptions();
    } catch (_) {/* optional */}
    if (!mounted) return;
    setState(() {
      _govs = govs;
      _accounts = acc;
    });
    await _loadCities(_governorate);
  }

  Future<void> _loadCities(String? gov) async {
    if (gov == null || gov.isEmpty) {
      setState(() => _cities = []);
      return;
    }
    final cities = await EgyptLocations.citiesForGovernorate(gov);
    if (!mounted) return;
    setState(() {
      _cities = cities;
      if (_city != null && !_cities.any((c) => c.nameAr == _city)) {
        _city = null;
      }
    });
  }

  String? _phoneValidator(String? v, {required bool requiredField}) {
    final s = (v ?? '').trim();
    if (s.isEmpty) return requiredField ? 'مطلوب' : null;
    if (!_phoneRe.hasMatch(s)) return 'رقم غير صحيح';
    return null;
  }

  Future<void> _submit() async {
    if (_formKey.currentState?.validate() != true) return;
    if (_governorate == null || _governorate!.isEmpty) {
      setState(() => _error = 'اختر المحافظة');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final payload = <String, dynamic>{
      'name': _name.text.trim(),
      'phone1': _phone1.text.trim(),
      'phone2': _phone2.text.trim(),
      'phone3': _phone3.text.trim(),
      'phone4': _phone4.text.trim(),
      'tel': _tel.text.trim(),
      'governorate': _governorate,
      'address': _address.text.trim(),
      'tree_account_id': _treeAccountId,
    };
    if (_city != null && _city!.isNotEmpty) {
      payload['city'] = _city;
    }
    try {
      await CompaniesApi.instance.save(id: widget.company?.id, payload: payload);
      if (!mounted) return;
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e.message;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'تعذر حفظ الشركة';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: AlertDialog(
        title: Text(_isEdit ? 'تعديل شركة عميل' : 'اضافة شركة جديدة'),
        content: SizedBox(
          width: 420,
          child: Form(
            key: _formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_isEdit && widget.company != null && !widget.company!.linked)
                    const Padding(
                      padding: EdgeInsets.only(bottom: 8),
                      child: Text(
                        'هذه الشركة غير مربوطة بحساب — اختر حساباً أو اترك الحقل فارغاً ليُنشأ تلقائياً تحت «عملاء شركات».',
                        style: TextStyle(color: AppColors.warning, fontSize: 12),
                      ),
                    ),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(
                        _error!,
                        style: const TextStyle(color: AppColors.danger),
                      ),
                    ),
                  TextFormField(
                    controller: _name,
                    decoration: const InputDecoration(
                      labelText: 'اسم الشركة',
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                  ),
                  const SizedBox(height: 10),
                  AccountPickerField(
                    label: 'حساب الشجرة (بحث — اختياري)',
                    options: _accounts,
                    selectedId: _treeAccountId,
                    onSelected: (id) => setState(() => _treeAccountId = id),
                  ),
                  const Padding(
                    padding: EdgeInsets.only(top: 4, bottom: 10),
                    child: Text(
                      'اختياري: اربط بحساب موجود، أو اتركه فارغاً ليُنشأ حساب تلقائياً تحت «عملاء شركات».',
                      style: TextStyle(fontSize: 11, color: AppColors.textMuted),
                    ),
                  ),
                  TextFormField(
                    controller: _phone1,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'رقم الموبيل 1',
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) => _phoneValidator(v, requiredField: true),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _phone2,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'رقم الموبيل 2',
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) => _phoneValidator(v, requiredField: false),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _phone3,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'رقم الموبيل 3',
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) => _phoneValidator(v, requiredField: false),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _phone4,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'رقم الموبيل 4',
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) => _phoneValidator(v, requiredField: false),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _tel,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'تلفون ارضي',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: _govs.any((g) => g.nameAr == _governorate)
                        ? _governorate
                        : null,
                    decoration: const InputDecoration(
                      labelText: 'المحافظة',
                      border: OutlineInputBorder(),
                    ),
                    items: [
                      for (final g in _govs)
                        DropdownMenuItem(value: g.nameAr, child: Text(g.nameAr)),
                    ],
                    onChanged: (v) {
                      setState(() {
                        _governorate = v;
                        _city = null;
                      });
                      _loadCities(v);
                    },
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'مطلوب' : null,
                  ),
                  if (_governorate == 'القاهرة') ...[
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: _cities.any((c) => c.nameAr == _city) ? _city : null,
                      decoration: const InputDecoration(
                        labelText: 'المدينة',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        for (final c in _cities)
                          DropdownMenuItem(
                            value: c.nameAr,
                            child: Text(c.nameAr),
                          ),
                      ],
                      onChanged: (v) => setState(() => _city = v),
                    ),
                  ],
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _address,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'العنوان',
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                  ),
                ],
              ),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: _saving ? null : () => Navigator.pop(context, false),
            child: const Text('الغاء'),
          ),
          FilledButton(
            onPressed: _saving ? null : _submit,
            child: Text(_isEdit ? 'حفظ' : 'اضف'),
          ),
        ],
      ),
    );
  }
}

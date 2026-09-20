import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/suppliers_api.dart';
import '../../theme/app_colors.dart';

/// إضافة مورد — mirrors Angular `AddSupplierComponent`.
class AddSupplierScreen extends StatefulWidget {
  const AddSupplierScreen({super.key});

  @override
  State<AddSupplierScreen> createState() => _AddSupplierScreenState();
}

class _AddSupplierScreenState extends State<AddSupplierScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _supplierRateCtrl = TextEditingController();
  final _priceRateCtrl = TextEditingController();

  List<SupplierTypeOption> _types = [];
  String _typeId = '';
  bool _loadingTypes = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadTypes();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _addressCtrl.dispose();
    _supplierRateCtrl.dispose();
    _priceRateCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadTypes() async {
    try {
      final types = await SuppliersApi.instance.types();
      if (!mounted) return;
      setState(() {
        _types = types;
        _loadingTypes = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingTypes = false);
    }
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await SuppliersApi.instance.addSupplier(
        name: _nameCtrl.text,
        phone: _phoneCtrl.text,
        address: _addressCtrl.text,
        typeId: _typeId.isEmpty ? null : _typeId,
        supplierRate: double.tryParse(_supplierRateCtrl.text.trim()),
        priceRate: double.tryParse(_priceRateCtrl.text.trim()),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم إضافة المورد بنجاح'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      _formKey.currentState?.reset();
      _nameCtrl.clear();
      _phoneCtrl.clear();
      _addressCtrl.clear();
      _supplierRateCtrl.clear();
      _priceRateCtrl.clear();
      setState(() => _typeId = '');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'تعذر إضافة المورد');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('إضافة مورد'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Form(
          key: _formKey,
          child: ListView(
            primary: true,
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              if (_error != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.danger.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: AppColors.danger.withValues(alpha: 0.35),
                    ),
                  ),
                  child: Text(
                    _error!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.danger,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const SizedBox(height: 14),
              ],
              _field(
                'اسم المورد',
                TextFormField(
                  controller: _nameCtrl,
                  textInputAction: TextInputAction.next,
                  decoration: _decoration('اسم المورد'),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                ),
              ),
              _field(
                'الهاتف',
                TextFormField(
                  controller: _phoneCtrl,
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.next,
                  decoration: _decoration('رقم الهاتف'),
                ),
              ),
              _field(
                'العنوان',
                TextFormField(
                  controller: _addressCtrl,
                  textInputAction: TextInputAction.next,
                  decoration: _decoration('العنوان'),
                ),
              ),
              _field(
                'فئة المورد',
                _loadingTypes
                    ? const LinearProgressIndicator()
                    : DropdownButtonFormField<String>(
                        value: _typeId,
                        decoration: _decoration('اختر الفئة'),
                        items: [
                          const DropdownMenuItem(
                            value: '',
                            child: Text('— بدون —'),
                          ),
                          for (final t in _types)
                            DropdownMenuItem(value: t.id, child: Text(t.name)),
                        ],
                        onChanged: (v) => setState(() => _typeId = v ?? ''),
                      ),
              ),
              _field(
                'تقييم المورد (0–10)',
                TextFormField(
                  controller: _supplierRateCtrl,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: _decoration('اختياري'),
                ),
              ),
              _field(
                'تقييم السعر (0–10)',
                TextFormField(
                  controller: _priceRateCtrl,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: _decoration('اختياري'),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: _saving ? null : _submit,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                  ),
                  child: _saving
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text(
                          'إضافة',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _field(String label, Widget child) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.navy,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 6),
          child,
        ],
      ),
    );
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      filled: true,
      fillColor: AppColors.surface,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}

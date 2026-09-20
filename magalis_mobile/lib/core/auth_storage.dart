import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Persists the same JWT payload shape Angular stores in `magalis_auth`.
class AuthStorage {
  AuthStorage._();
  static final AuthStorage instance = AuthStorage._();

  static const _key = 'magalis_auth';

  Map<String, dynamic>? _cache;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) {
      _cache = null;
      return;
    }
    try {
      _cache = jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      _cache = null;
    }
  }

  Map<String, dynamic>? get payload => _cache;

  String? get accessToken {
    final t = _cache?['access_token'];
    return t is String && t.isNotEmpty ? t : null;
  }

  String get userName {
    final n = _cache?['name'];
    return n is String && n.isNotEmpty ? n : 'مستخدم';
  }

  String get department {
    final d = _cache?['user'];
    return d is String ? d : '';
  }

  bool get isLoggedIn => accessToken != null;

  Future<void> save(Map<String, dynamic> data) async {
    _cache = Map<String, dynamic>.from(data);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(_cache));
  }

  Future<void> clear() async {
    _cache = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}

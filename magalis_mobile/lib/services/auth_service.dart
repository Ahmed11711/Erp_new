import 'package:dio/dio.dart';

import '../core/api_client.dart';
import '../core/auth_storage.dart';

class AuthService {
  AuthService._();
  static final AuthService instance = AuthService._();

  final _api = ApiClient.instance;
  final _storage = AuthStorage.instance;

  Future<void> login({
    required String email,
    required String password,
  }) async {
    try {
      final res = await _api.dio.post(
        '/auth/login',
        data: {'email': email.trim(), 'password': password},
      );
      final data = res.data;
      if (data is! Map<String, dynamic>) {
        throw ApiException('استجابة غير متوقعة من الخادم');
      }
      if (data['access_token'] is! String) {
        throw ApiException('لم يتم استلام رمز الدخول');
      }
      await _storage.save(data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> logout() async {
    try {
      await _api.dio.post('/auth/logout');
    } catch (_) {
      // Always clear local session.
    }
    await _storage.clear();
  }

  Future<Map<String, dynamic>?> me() async {
    try {
      final res = await _api.dio.post('/auth/me');
      final data = res.data;
      if (data is Map<String, dynamic>) return data;
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        await _storage.clear();
      }
      rethrow;
    }
    return null;
  }
}

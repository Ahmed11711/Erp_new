import 'package:dio/dio.dart';

import '../config/api_config.dart';
import 'auth_storage.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

/// Shared Dio client — JWT Bearer, same as Angular TokenInterceptor.
class ApiClient {
  ApiClient._() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConfig.baseUrl,
        connectTimeout: ApiConfig.connectTimeout,
        receiveTimeout: ApiConfig.receiveTimeout,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = AuthStorage.instance.accessToken;
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          handler.next(error);
        },
      ),
    );
  }

  static final ApiClient instance = ApiClient._();

  late final Dio _dio;

  Dio get dio => _dio;

  static String messageFrom(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final msg = data['message'];
      if (msg is String && msg.isNotEmpty) return msg;
      if (msg is Map) {
        final parts = <String>[];
        msg.forEach((_, v) {
          if (v is List) {
            parts.addAll(v.map((x) => x.toString()));
          } else if (v != null) {
            parts.add(v.toString());
          }
        });
        if (parts.isNotEmpty) return parts.join('\n');
      }
      if (msg is List && msg.isNotEmpty) {
        return msg.map((x) => x.toString()).join('\n');
      }
      final err = data['error'];
      if (err is String) return err;
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'انتهت مهلة الاتصال بالخادم';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'تعذر الاتصال بالخادم — تحقق من الشبكة وعنوان الـ API';
    }
    final code = e.response?.statusCode;
    if (code == 401) return 'بيانات الدخول غير صحيحة أو انتهت الجلسة';
    if (code == 403) return 'غير مصرح بهذا الإجراء';
    if (code == 422) return 'بيانات غير صالحة';
    return 'حدث خطأ غير متوقع${code != null ? ' ($code)' : ''}';
  }
}

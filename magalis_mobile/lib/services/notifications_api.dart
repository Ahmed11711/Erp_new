import 'package:dio/dio.dart';

import '../core/api_client.dart';
import '../models/app_notification.dart';

class NotificationsApi {
  NotificationsApi._();
  static final NotificationsApi instance = NotificationsApi._();

  final _api = ApiClient.instance;

  /// Inbox used by Angular home / bell (`GET /notification`).
  Future<({List<AppNotification> items, int lateConfirmedCount})> inbox() async {
    try {
      final res = await _api.dio.get('/notification');
      final data = res.data;
      final items = <AppNotification>[];
      var late = 0;
      if (data is Map) {
        late = int.tryParse('${data['confirmedOrdersCount'] ?? 0}') ?? 0;
        final list = data['notifications'];
        if (list is List) {
          for (final row in list) {
            if (row is Map) {
              items.add(AppNotification.fromJson(Map<String, dynamic>.from(row)));
            }
          }
        }
      }
      return (items: items, lateConfirmedCount: late);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<AppNotification>> received({
    int page = 1,
    int itemsPerPage = 50,
  }) async {
    try {
      final res = await _api.dio.get(
        '/recievednotification',
        queryParameters: {
          'page': page,
          'itemsPerPage': itemsPerPage,
        },
      );
      final data = res.data;
      final items = <AppNotification>[];
      final list = data is Map ? data['data'] : null;
      if (list is List) {
        for (final row in list) {
          if (row is Map) {
            items.add(AppNotification.fromJson(Map<String, dynamic>.from(row)));
          }
        }
      }
      return items;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }
}

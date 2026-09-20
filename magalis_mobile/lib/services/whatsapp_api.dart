import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../core/api_client.dart';
import '../models/chat_message.dart';

/// Same endpoints as Angular `MessageService` / `WhatsAppService`.
class WhatsAppApi {
  WhatsAppApi._();
  static final WhatsAppApi instance = WhatsAppApi._();

  final _api = ApiClient.instance;

  Future<MessagesPage> getMessages({
    required int conversationId,
    String? cursor,
    int limit = 20,
    String? search,
    String? fromDate,
    String? toDate,
  }) async {
    try {
      final params = <String, dynamic>{'limit': limit};
      if (cursor != null && cursor.isNotEmpty) params['cursor'] = cursor;
      final q = search?.trim();
      if (q != null && q.isNotEmpty) params['search'] = q;
      if (fromDate != null && fromDate.isNotEmpty) {
        params['from_date'] = fromDate;
      }
      if (toDate != null && toDate.isNotEmpty) params['to_date'] = toDate;

      final res = await _api.dio.get(
        '/conversations/$conversationId/messages',
        queryParameters: params,
      );
      final data = res.data;
      if (data is! Map) throw ApiException('استجابة غير متوقعة');

      final list = data['data'];
      final messages = <ChatMessage>[];
      if (list is List) {
        for (final row in list) {
          if (row is Map) {
            messages.add(ChatMessage.fromJson(Map<String, dynamic>.from(row)));
          }
        }
      }

      final conv = data['conversation'];
      String? name;
      String? phone;
      var isArchived = false;
      if (conv is Map) {
        name = '${conv['name'] ?? ''}'.trim();
        phone = '${conv['phone'] ?? ''}'.trim();
        if (name.isEmpty) name = null;
        if (phone.isEmpty) phone = null;
        isArchived = conv['is_archived'] == true ||
            conv['is_archived'] == 1 ||
            conv['is_archived'] == '1' ||
            '${conv['whatsapp_archived_at'] ?? ''}'.trim().isNotEmpty;
      }

      return MessagesPage(
        messages: messages,
        hasMore: data['has_more'] == true,
        nextCursor: data['next_cursor']?.toString(),
        conversationName: name,
        conversationPhone: phone,
        isArchived: isArchived,
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<ChatMessage?> sendMessage({
    required String customerPhone,
    required String message,
  }) async {
    try {
      final res = await _api.dio.post(
        '/whatsapp/send',
        data: {
          'customer_phone': customerPhone,
          'message': message,
        },
      );
      final data = res.data;
      if (data is! Map || data['success'] != true) {
        final err = data is Map ? (data['error'] ?? data['message']) : null;
        throw ApiException('${err ?? 'فشل إرسال الرسالة'}');
      }
      final row = data['data'];
      if (row is Map) {
        final map = Map<String, dynamic>.from(row);
        return ChatMessage(
          id: int.tryParse('${map['id']}') ?? 0,
          message: '${map['content'] ?? map['message'] ?? message}',
          type: '${map['type'] ?? 'text'}',
          direction: 'sent',
          status: map['status']?.toString(),
          senderName: map['sender'] is Map
              ? '${map['sender']['name'] ?? ''}'.trim()
              : null,
          createdAt: ChatMessage.fromJson({
            ...map,
            'message': map['content'] ?? map['message'] ?? message,
            'direction': 'sent',
          }).createdAt,
        );
      }
      return null;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<int> archiveAllCustomers({
    String? fromDate,
    String? toDate,
  }) async {
    try {
      final data = <String, dynamic>{};
      if (fromDate != null && fromDate.isNotEmpty) {
        data['from_date'] = fromDate;
      }
      if (toDate != null && toDate.isNotEmpty) {
        data['to_date'] = toDate;
      }
      final res = await _api.dio.post(
        '/whatsapp/customers/archive-all',
        data: data,
      );
      final body = res.data;
      if (body is! Map || body['success'] != true) {
        final err = body is Map ? (body['error'] ?? body['message']) : null;
        throw ApiException('${err ?? 'تعذر أرشفة المحادثات'}');
      }
      final row = body['data'];
      if (row is Map) {
        return int.tryParse('${row['archived_count'] ?? 0}') ?? 0;
      }
      return 0;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<bool> setCustomerArchive({
    required int customerId,
    required bool archived,
  }) async {
    try {
      final res = await _api.dio.patch(
        '/whatsapp/customers/$customerId/archive',
        data: {'archived': archived},
      );
      final data = res.data;
      if (data is! Map || data['success'] != true) {
        final err = data is Map ? (data['error'] ?? data['message']) : null;
        throw ApiException('${err ?? 'تعذر تحديث الأرشيف'}');
      }
      return data['data'] is Map
          ? data['data']['is_archived'] == true
          : archived;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<Uint8List> fetchMediaBytes(int messageId) async {
    try {
      final res = await _api.dio.get<List<int>>(
        '/media/$messageId',
        options: Options(responseType: ResponseType.bytes),
      );
      final bytes = res.data;
      if (bytes == null || bytes.isEmpty) {
        throw ApiException('تعذر تحميل الوسائط');
      }
      return Uint8List.fromList(bytes);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }
}

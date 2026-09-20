class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.message,
    required this.type,
    required this.direction,
    this.status,
    this.mediaUrl,
    this.mediaMimeType,
    this.mediaFilename,
    this.mediaCaption,
    this.senderName,
    this.createdAt,
  });

  final int id;
  final String message;
  final String type;
  final String direction; // sent | received
  final String? status;
  final String? mediaUrl;
  final String? mediaMimeType;
  final String? mediaFilename;
  final String? mediaCaption;
  final String? senderName;
  final DateTime? createdAt;

  bool get isSent => direction == 'sent';
  bool get isImage => type == 'image' || type == 'sticker';
  bool get isVideo => type == 'video';
  bool get isAudio => type == 'audio';
  bool get isDocument => type == 'document';
  bool get hasMedia => isImage || isVideo || isAudio || isDocument;

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'];
    String? senderName;
    if (sender is Map) {
      senderName = '${sender['name'] ?? ''}'.trim();
      if (senderName.isEmpty) senderName = null;
    }

    return ChatMessage(
      id: int.tryParse('${json['id']}') ?? 0,
      message: '${json['message'] ?? json['content'] ?? ''}',
      type: '${json['type'] ?? 'text'}',
      direction: '${json['direction'] ?? 'received'}',
      status: json['status']?.toString(),
      mediaUrl: json['media_url']?.toString(),
      mediaMimeType: json['media_mime_type']?.toString(),
      mediaFilename: json['media_filename']?.toString(),
      mediaCaption: json['media_caption']?.toString(),
      senderName: senderName,
      createdAt: _parseDate(json['created_at']),
    );
  }

  static DateTime? _parseDate(dynamic raw) {
    if (raw == null) return null;
    final s = '$raw'.trim();
    if (s.isEmpty) return null;
    return DateTime.tryParse(s.replaceFirst(' ', 'T'));
  }
}

class MessagesPage {
  const MessagesPage({
    required this.messages,
    required this.hasMore,
    this.nextCursor,
    this.conversationName,
    this.conversationPhone,
    this.isArchived = false,
  });

  final List<ChatMessage> messages;
  final bool hasMore;
  final String? nextCursor;
  final String? conversationName;
  final String? conversationPhone;
  final bool isArchived;
}

import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../models/chat_message.dart';
import '../../services/whatsapp_api.dart';
import '../../theme/app_colors.dart';

/// WhatsApp-Web style thread — mirrors Angular `chat-page` bubbles / colors.
class WhatsAppChatScreen extends StatefulWidget {
  const WhatsAppChatScreen({
    super.key,
    required this.customerId,
    required this.customerName,
    this.customerPhone,
    this.initiallyArchived = false,
  });

  final String customerId;
  final String customerName;
  final String? customerPhone;
  final bool initiallyArchived;

  @override
  State<WhatsAppChatScreen> createState() => _WhatsAppChatScreenState();
}

class _WhatsAppChatScreenState extends State<WhatsAppChatScreen> {
  static const _bg = Color(0xFFE8E4DC);
  static const _out = Color(0xFF056162);
  static const _outEnd = Color(0xFF075E54);

  final _scrollCtrl = ScrollController();
  final _inputCtrl = TextEditingController();
  final _expanded = <int>{};
  final _mediaCache = <int, Uint8List>{};
  final _mediaLoading = <int>{};

  List<ChatMessage> _messages = [];
  String? _cursor;
  bool _hasMore = false;
  bool _loading = true;
  bool _loadingMore = false;
  bool _sending = false;
  String? _error;
  String? _phone;
  String? _name;
  bool _archived = false;
  bool _archiveBusy = false;

  @override
  void initState() {
    super.initState();
    _name = widget.customerName;
    _phone = widget.customerPhone;
    _archived = widget.initiallyArchived;
    _load();
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _inputCtrl.dispose();
    super.dispose();
  }

  int? get _conversationId => int.tryParse(widget.customerId);

  bool get _awaitingReply {
    if (_archived || _messages.isEmpty) return false;
    return !_messages.last.isSent;
  }

  bool get _canArchive => _archived || !_awaitingReply;

  Future<void> _load() async {
    final id = _conversationId;
    if (id == null) {
      setState(() {
        _error = 'معرّف المحادثة غير صالح';
        _loading = false;
      });
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final page = await WhatsAppApi.instance.getMessages(conversationId: id);
      if (!mounted) return;
      setState(() {
        _messages = page.messages;
        _hasMore = page.hasMore;
        _cursor = page.nextCursor;
        _name = page.conversationName ?? _name;
        _phone = page.conversationPhone ?? _phone;
        _archived = page.isArchived;
        _loading = false;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());
      _prefetchVisibleMedia();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل الرسائل';
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    final id = _conversationId;
    if (id == null || !_hasMore || _loadingMore || _cursor == null) return;

    setState(() => _loadingMore = true);
    try {
      final page = await WhatsAppApi.instance.getMessages(
        conversationId: id,
        cursor: _cursor,
      );
      if (!mounted) return;
      setState(() {
        _messages = [...page.messages, ..._messages];
        _hasMore = page.hasMore;
        _cursor = page.nextCursor;
        _loadingMore = false;
      });
      _prefetchVisibleMedia();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _loadingMore = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingMore = false);
    }
  }

  void _scrollToBottom() {
    if (!_scrollCtrl.hasClients) return;
    _scrollCtrl.jumpTo(_scrollCtrl.position.maxScrollExtent);
  }

  void _prefetchVisibleMedia() {
    for (final m in _messages) {
      if (m.isImage && !_mediaCache.containsKey(m.id)) {
        _ensureMedia(m);
      }
    }
  }

  Future<void> _ensureMedia(ChatMessage m) async {
    if (!m.hasMedia || _mediaCache.containsKey(m.id) || _mediaLoading.contains(m.id)) {
      return;
    }
    setState(() => _mediaLoading.add(m.id));
    try {
      final bytes = await WhatsAppApi.instance.fetchMediaBytes(m.id);
      if (!mounted) return;
      setState(() {
        _mediaCache[m.id] = bytes;
        _mediaLoading.remove(m.id);
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _mediaLoading.remove(m.id));
    }
  }

  Future<void> _toggleArchive() async {
    final id = _conversationId;
    if (id == null || _archiveBusy) return;
    if (!_canArchive) return;
    setState(() => _archiveBusy = true);
    try {
      final archived = await WhatsAppApi.instance.setCustomerArchive(
        customerId: id,
        archived: !_archived,
      );
      if (!mounted) return;
      setState(() {
        _archived = archived;
        _archiveBusy = false;
      });
      if (_archived) {
        Navigator.of(context).pop();
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('عادت المحادثة للصندوق'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _archiveBusy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _archiveBusy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر تحديث الأرشيف'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _send() async {
    final phone = (_phone ?? '').trim();
    final text = _inputCtrl.text.trim();
    if (phone.isEmpty || text.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      final sent = await WhatsAppApi.instance.sendMessage(
        customerPhone: phone,
        message: text,
      );
      if (!mounted) return;
      _inputCtrl.clear();
      setState(() {
        _sending = false;
        _archived = true;
        if (sent != null) {
          _messages = [..._messages, sent];
        } else {
          _messages = [
            ..._messages,
            ChatMessage(
              id: DateTime.now().millisecondsSinceEpoch,
              message: text,
              type: 'text',
              direction: 'sent',
              status: 'sent',
              createdAt: DateTime.now(),
            ),
          ];
        }
      });
      WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('فشل إرسال الرسالة'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  String _formatBubbleContent(String? content) {
    if (content == null || content.isEmpty) return '';
    final t = content.trim();
    if (t == '[Button Message]' || t == '[Interactive Message]') {
      return '🔘 رد تفاعلي (زر واتساب)';
    }
    return content;
  }

  String _formatDate(DateTime? d) {
    if (d == null) return '';
    final local = d.toLocal();
    final now = DateTime.now();
    final startToday = DateTime(now.year, now.month, now.day);
    final startMsg = DateTime(local.year, local.month, local.day);
    final days = startToday.difference(startMsg).inDays;
    final time = DateFormat('h:mm a', 'ar').format(local);
    if (days == 0) return time;
    if (days == 1) return 'أمس $time';
    if (days < 7) {
      return '${DateFormat('E', 'ar').format(local)} $time';
    }
    return '${DateFormat('d MMM', 'ar').format(local)} $time';
  }

  Widget _statusIcon(ChatMessage m) {
    if (!m.isSent) return const SizedBox.shrink();
    final status = (m.status ?? '').toLowerCase();
    if (status == 'read') {
      return const Icon(Icons.done_all, size: 14, color: Color(0xFF53BDEB));
    }
    if (status == 'delivered') {
      return Icon(Icons.done_all, size: 14, color: Colors.white.withValues(alpha: 0.85));
    }
    return Icon(Icons.done, size: 14, color: Colors.white.withValues(alpha: 0.85));
  }

  @override
  Widget build(BuildContext context) {
    final title = (_name?.isNotEmpty == true) ? _name! : 'محادثة';
    final phone = _phone ?? '';

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontSize: 16)),
            if (phone.isNotEmpty)
              Text(
                phone,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.white.withValues(alpha: 0.85),
                  fontWeight: FontWeight.w500,
                ),
              ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: _archived
                ? 'إرجاع للصندوق'
                : _awaitingReply
                    ? 'لا يمكن الأرشفة قبل الرد على رسالة العميل'
                    : 'أرشفة المحادثة',
            onPressed: _loading || _archiveBusy || !_canArchive ? null : _toggleArchive,
            icon: Icon(_archived ? Icons.unarchive_outlined : Icons.archive_outlined),
          ),
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(child: _buildBody()),
          _buildComposer(),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.danger,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 12),
              ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (_messages.isEmpty) {
      return const Center(
        child: Text(
          'لا توجد رسائل',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    return ListView.builder(
      controller: _scrollCtrl,
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
      itemCount: _messages.length + (_hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (_hasMore && index == 0) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Center(
              child: TextButton(
                onPressed: _loadingMore ? null : _loadMore,
                child: Text(_loadingMore ? 'جاري التحميل…' : 'تحميل رسائل أقدم'),
              ),
            ),
          );
        }
        final msgIndex = _hasMore ? index - 1 : index;
        return _MessageBubble(
          message: _messages[msgIndex],
          text: _formatBubbleContent(_messages[msgIndex].message),
          timeLabel: _formatDate(_messages[msgIndex].createdAt),
          expanded: _expanded.contains(_messages[msgIndex].id),
          mediaBytes: _mediaCache[_messages[msgIndex].id],
          mediaLoading: _mediaLoading.contains(_messages[msgIndex].id),
          onToggleExpand: () {
            setState(() {
              final id = _messages[msgIndex].id;
              if (_expanded.contains(id)) {
                _expanded.remove(id);
              } else {
                _expanded.add(id);
              }
            });
          },
          onLoadMedia: () => _ensureMedia(_messages[msgIndex]),
          statusIcon: _statusIcon(_messages[msgIndex]),
          outColor: _out,
          outEnd: _outEnd,
        );
      },
    );
  }

  Widget _buildComposer() {
    final canSend =
        (_phone ?? '').trim().isNotEmpty && _inputCtrl.text.trim().isNotEmpty && !_sending;

    return Material(
      color: Colors.white,
      elevation: 6,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _inputCtrl,
                  minLines: 1,
                  maxLines: 4,
                  textInputAction: TextInputAction.send,
                  onChanged: (_) => setState(() {}),
                  onSubmitted: (_) {
                    if (canSend) _send();
                  },
                  decoration: const InputDecoration(
                    hintText: 'اكتب رسالة…',
                    filled: true,
                    fillColor: Color(0xFFF3F4F6),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.all(Radius.circular(22)),
                      borderSide: BorderSide.none,
                    ),
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 10,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(
                onPressed: canSend ? _send : null,
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(22),
                  ),
                ),
                child: Text(_sending ? '...' : 'إرسال'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({
    required this.message,
    required this.text,
    required this.timeLabel,
    required this.expanded,
    required this.onToggleExpand,
    required this.onLoadMedia,
    required this.statusIcon,
    required this.outColor,
    required this.outEnd,
    this.mediaBytes,
    this.mediaLoading = false,
  });

  final ChatMessage message;
  final String text;
  final String timeLabel;
  final bool expanded;
  final VoidCallback onToggleExpand;
  final VoidCallback onLoadMedia;
  final Widget statusIcon;
  final Color outColor;
  final Color outEnd;
  final Uint8List? mediaBytes;
  final bool mediaLoading;

  @override
  Widget build(BuildContext context) {
    final sent = message.isSent;
    final showReadMore = text.length > 220;
    final displayText =
        showReadMore && !expanded ? '${text.substring(0, 220)}…' : text;

    return Align(
      alignment: sent ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
        ),
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
          decoration: BoxDecoration(
            gradient: sent
                ? LinearGradient(colors: [outColor, outEnd])
                : null,
            color: sent ? null : Colors.white,
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(10),
              topRight: const Radius.circular(10),
              bottomLeft: Radius.circular(sent ? 10 : 4),
              bottomRight: Radius.circular(sent ? 4 : 10),
            ),
            border: sent
                ? null
                : Border.all(color: Colors.black.withValues(alpha: 0.06)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 1,
                offset: const Offset(0, 1),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (message.isImage) _buildImage(),
              if (message.isVideo) _buildMediaHint(Icons.play_circle, 'فيديو'),
              if (message.isAudio) _buildMediaHint(Icons.graphic_eq, 'رسالة صوتية'),
              if (message.isDocument)
                _buildMediaHint(
                  Icons.description,
                  message.mediaFilename?.isNotEmpty == true
                      ? message.mediaFilename!
                      : 'ملف مرفق',
                ),
              if (displayText.isNotEmpty)
                Text(
                  displayText,
                  style: TextStyle(
                    color: sent ? Colors.white : const Color(0xFF111111),
                    fontSize: 15,
                    height: 1.45,
                  ),
                ),
              if (showReadMore)
                TextButton(
                  onPressed: onToggleExpand,
                  style: TextButton.styleFrom(
                    padding: EdgeInsets.zero,
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    foregroundColor: sent
                        ? Colors.white.withValues(alpha: 0.95)
                        : const Color(0xFF0277BD),
                  ),
                  child: Text(
                    expanded ? 'إظهار أقل' : 'اقرأ المزيد',
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ),
              const SizedBox(height: 4),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    timeLabel,
                    style: TextStyle(
                      fontSize: 11,
                      color: sent
                          ? Colors.white.withValues(alpha: 0.88)
                          : const Color(0xFF667781),
                    ),
                  ),
                  if (message.senderName != null &&
                      message.senderName!.isNotEmpty) ...[
                    Text(
                      ' — ${message.senderName}',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: sent
                            ? Colors.white.withValues(alpha: 0.88)
                            : const Color(0xFF667781),
                      ),
                    ),
                  ],
                  if (sent) ...[
                    const SizedBox(width: 4),
                    statusIcon,
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildImage() {
    if (mediaBytes != null) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Image.memory(
            mediaBytes!,
            fit: BoxFit.cover,
            width: double.infinity,
          ),
        ),
      );
    }
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: InkWell(
        onTap: onLoadMedia,
        child: Container(
          height: 120,
          width: double.infinity,
          decoration: BoxDecoration(
            color: Colors.black.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Center(
            child: mediaLoading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.image, size: 18),
                      SizedBox(width: 6),
                      Text('تحميل الصورة', style: TextStyle(fontWeight: FontWeight.w700)),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _buildMediaHint(IconData icon, String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: InkWell(
        onTap: onLoadMedia,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 18,
              color: message.isSent ? Colors.white : AppColors.navy,
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  color: message.isSent ? Colors.white : AppColors.navy,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

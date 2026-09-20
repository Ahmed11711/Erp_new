import 'package:intl/intl.dart';

class Formatters {
  Formatters._();

  static final _money = NumberFormat('#,##0', 'ar');
  static final _date = DateFormat('d MMM yyyy', 'ar');
  static final _dateTime = DateFormat('d MMM · h:mm a', 'ar');
  static final _time = DateFormat('h:mm a', 'ar');

  static String money(num value) => '${_money.format(value)} ج.م';

  static String moneyPlain(num value) => _money.format(value);

  static String date(DateTime value) => _date.format(value);

  static String dateTime(DateTime value) => _dateTime.format(value);

  static String time(DateTime value) => _time.format(value);

  static String relative(DateTime value) {
    final diff = DateTime.now().difference(value);
    if (diff.inMinutes < 60) return 'منذ ${diff.inMinutes} د';
    if (diff.inHours < 24) return 'منذ ${diff.inHours} س';
    if (diff.inDays < 7) return 'منذ ${diff.inDays} يوم';
    return date(value);
  }
}

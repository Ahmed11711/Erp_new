import 'package:flutter_test/flutter_test.dart';
import 'package:magalis_mobile/main.dart';

void main() {
  testWidgets('Magalis app boots to splash', (tester) async {
    await tester.pumpWidget(const MagalisApp());
    expect(find.textContaining('نظام إدارة'), findsOneWidget);
    // Advance splash timer so no pending timers remain.
    await tester.pump(const Duration(milliseconds: 1800));
    await tester.pumpAndSettle();
  });
}

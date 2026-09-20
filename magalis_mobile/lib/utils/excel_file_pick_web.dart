import 'dart:async';
import 'dart:html' as html;
import 'dart:typed_data';

class PickedExcelFile {
  const PickedExcelFile({required this.name, required this.bytes});

  final String name;
  final List<int> bytes;
}

Future<PickedExcelFile?> pickExcelFile() async {
  final input = html.FileUploadInputElement()
    ..accept = '.xlsx,.xls,.csv'
    ..multiple = false;
  input.click();
  await input.onChange.first;
  final files = input.files;
  if (files == null || files.isEmpty) return null;
  final file = files.first;
  final reader = html.FileReader();
  final done = Completer<PickedExcelFile?>();
  reader.onLoad.listen((_) {
    final result = reader.result;
    if (result is ByteBuffer) {
      done.complete(
        PickedExcelFile(name: file.name, bytes: result.asUint8List()),
      );
    } else if (result is Uint8List) {
      done.complete(PickedExcelFile(name: file.name, bytes: result));
    } else {
      done.complete(null);
    }
  });
  reader.onError.listen((_) => done.complete(null));
  reader.readAsArrayBuffer(file);
  return done.future;
}

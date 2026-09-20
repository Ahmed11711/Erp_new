class PickedExcelFile {
  const PickedExcelFile({required this.name, required this.bytes});

  final String name;
  final List<int> bytes;
}

Future<PickedExcelFile?> pickExcelFile() async => null;

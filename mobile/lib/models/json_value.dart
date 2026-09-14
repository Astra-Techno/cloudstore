/// Safe conversions at the API boundary.
///
/// PHP/PDO responses can contain native JSON values or database strings
/// (notably DECIMAL values). Models must never use direct casts for those
/// fields, otherwise one imperfect response can crash an entire screen.
abstract final class JsonValue {
  static String string(dynamic value, [String fallback = '']) =>
      value?.toString() ?? fallback;

  static String? nullableString(dynamic value) {
    final result = value?.toString();
    return result == null || result.isEmpty ? null : result;
  }

  static int integer(dynamic value, [int fallback = 0]) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static int? nullableInt(dynamic value) {
    if (value == null || value == '') return null;
    if (value is num) return value.toInt();
    return int.tryParse(value.toString());
  }

  static double? nullableDouble(dynamic value) {
    if (value == null || value == '') return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }

  static bool boolean(dynamic value, [bool fallback = false]) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    final normalized = value?.toString().toLowerCase();
    if (normalized == 'true' || normalized == '1') return true;
    if (normalized == 'false' || normalized == '0') return false;
    return fallback;
  }

  static Map<String, dynamic>? object(dynamic value) => value is Map
      ? Map<String, dynamic>.from(value)
      : null;

  static List<Map<String, dynamic>> objectList(dynamic value) {
    if (value is! List) return const [];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
}

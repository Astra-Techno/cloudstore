/// Defensive helpers for the platform's `{success, data, error}` API envelope.
///
/// An unexpected proxy/server response must become a controlled screen error,
/// never a `NoSuchMethodError` from indexing a non-map value.
abstract final class ApiResponse {
  static Map<String, dynamic>? body(dynamic value) => value is Map
      ? Map<String, dynamic>.from(value)
      : null;

  static bool isSuccess(dynamic value) => body(value)?['success'] == true;

  static Map<String, dynamic>? dataMap(dynamic value) {
    final data = body(value)?['data'];
    return data is Map ? Map<String, dynamic>.from(data) : null;
  }

  static String errorMessage(dynamic value, String fallback) {
    final error = body(value)?['error'];
    return error is Map && error['message'] != null
        ? error['message'].toString()
        : fallback;
  }
}

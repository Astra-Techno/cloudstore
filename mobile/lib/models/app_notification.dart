import 'json_value.dart';

class AppNotification {
  final int id;
  final String uuid;
  final String type;
  final String title;
  final String body;
  final String? data;
  final String? readAt;
  final String createdAt;

  AppNotification({
    required this.id,
    required this.uuid,
    required this.type,
    required this.title,
    required this.body,
    this.data,
    this.readAt,
    required this.createdAt,
  });

  bool get isRead => readAt != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: JsonValue.integer(json['id']),
      uuid: JsonValue.string(json['uuid']),
      type: JsonValue.string(json['type'], 'general'),
      title: JsonValue.string(json['title']),
      body: JsonValue.string(json['body']),
      data: JsonValue.nullableString(json['data']),
      readAt: JsonValue.nullableString(json['read_at']),
      createdAt: JsonValue.string(json['created_at']),
    );
  }
}

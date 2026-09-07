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
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      type: json['type'] as String,
      title: json['title'] as String,
      body: json['body'] as String,
      data: json['data'] as String?,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String,
    );
  }
}

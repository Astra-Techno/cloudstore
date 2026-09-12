import 'dart:async';
import 'package:flutter/material.dart';
import '../../services/api_client.dart';
import '../../services/notification_service.dart';
import '../../models/app_notification.dart';

class NotificationProvider extends ChangeNotifier {
  List<AppNotification> _notifications = [];
  int _unreadCount = 0;
  Timer? _pollTimer;
  int _lastKnownCount = -1;

  List<AppNotification> get notifications => List.unmodifiable(_notifications);
  int get unreadCount => _unreadCount;

  void startPolling() {
    _pollTimer?.cancel();
    fetchNotifications();
    _pollTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      fetchNotifications();
    });
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  Future<void> fetchNotifications() async {
    try {
      final response = await ApiClient().get('/customer/notifications');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final list = data['data']['notifications'] as List<dynamic>? ?? [];
        _notifications = list.map((n) => AppNotification.fromJson(n)).toList();
        final newUnread = data['data']['unread_count'] as int? ?? 0;

        // Show local notification for new items
        if (_lastKnownCount >= 0 && newUnread > _lastKnownCount) {
          final newest = _notifications.where((n) => !n.isRead).firstOrNull;
          if (newest != null) {
            NotificationService().showLocalNotification(
              id: newest.id,
              title: newest.title,
              body: newest.body,
            );
          }
        }

        _lastKnownCount = newUnread;
        _unreadCount = newUnread;
        notifyListeners();
      }
    } catch (_) {
      // Silently fail polling
    }
  }

  Future<void> markRead(int id) async {
    try {
      await ApiClient().patch('/customer/notifications/$id/read');
      final idx = _notifications.indexWhere((n) => n.id == id);
      if (idx >= 0) {
        _notifications[idx] = AppNotification(
          id: _notifications[idx].id,
          uuid: _notifications[idx].uuid,
          type: _notifications[idx].type,
          title: _notifications[idx].title,
          body: _notifications[idx].body,
          data: _notifications[idx].data,
          readAt: DateTime.now().toIso8601String(),
          createdAt: _notifications[idx].createdAt,
        );
        if (_unreadCount > 0) _unreadCount--;
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<void> markAllRead() async {
    try {
      await ApiClient().post('/customer/notifications/read-all');
      for (var i = 0; i < _notifications.length; i++) {
        if (!_notifications[i].isRead) {
          _notifications[i] = AppNotification(
            id: _notifications[i].id,
            uuid: _notifications[i].uuid,
            type: _notifications[i].type,
            title: _notifications[i].title,
            body: _notifications[i].body,
            data: _notifications[i].data,
            readAt: DateTime.now().toIso8601String(),
            createdAt: _notifications[i].createdAt,
          );
        }
      }
      _unreadCount = 0;
      notifyListeners();
    } catch (_) {}
  }

  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }
}

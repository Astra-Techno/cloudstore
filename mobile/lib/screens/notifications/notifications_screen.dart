import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../app/providers/notification_provider.dart';
import '../../widgets/loading_overlay.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    context.read<NotificationProvider>().fetchNotifications();
  }

  String _formatTime(String dateStr) {
    final diff = DateTime.now().difference(DateTime.parse(dateStr));
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return DateFormat('dd MMM, hh:mm a').format(DateTime.parse(dateStr));
  }

  IconData _iconForType(String type) {
    if (type.startsWith('order_')) return Icons.receipt_long;
    if (type == 'new_order') return Icons.shopping_bag;
    if (type.contains('delivery')) return Icons.delivery_dining;
    return Icons.notifications;
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<NotificationProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (provider.unreadCount > 0)
            TextButton(
              onPressed: provider.markAllRead,
              child: const Text('Mark all read'),
            ),
        ],
      ),
      body: provider.notifications.isEmpty
          ? const EmptyState(
              icon: Icons.notifications_off_outlined,
              title: 'No notifications',
              subtitle: 'You\'ll see order updates here',
            )
          : RefreshIndicator(
              onRefresh: provider.fetchNotifications,
              child: ListView.builder(
                itemCount: provider.notifications.length,
                itemBuilder: (context, index) {
                  final n = provider.notifications[index];
                  return Container(
                    color: n.isRead ? null : Theme.of(context).colorScheme.primaryContainer.withAlpha(30),
                    child: ListTile(
                      onTap: n.isRead ? null : () => provider.markRead(n.id),
                      leading: CircleAvatar(
                        backgroundColor: n.isRead
                            ? Colors.grey[200]
                            : Theme.of(context).colorScheme.primaryContainer,
                        child: Icon(
                          _iconForType(n.type),
                          color: n.isRead ? Colors.grey : Theme.of(context).colorScheme.primary,
                          size: 20,
                        ),
                      ),
                      title: Text(
                        n.title,
                        style: TextStyle(
                          fontWeight: n.isRead ? FontWeight.normal : FontWeight.bold,
                          fontSize: 15,
                        ),
                      ),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(n.body, maxLines: 2, overflow: TextOverflow.ellipsis),
                          const SizedBox(height: 4),
                          Text(
                            _formatTime(n.createdAt),
                            style: TextStyle(color: Colors.grey[500], fontSize: 12),
                          ),
                        ],
                      ),
                      isThreeLine: true,
                      trailing: n.isRead
                          ? null
                          : Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}

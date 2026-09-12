import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../models/order.dart';
import '../../services/api_client.dart';
import '../../widgets/loading_overlay.dart';
import '../../widgets/price_text.dart';
import '../../widgets/status_badge.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});
  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  List<Order> _orders = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _loadOrders(); }

  Future<void> _loadOrders() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final response = await ApiClient().get('/customer/orders');
      final data = response.data;
      if (data['success'] == true && data['data'] is List) {
        _orders = (data['data'] as List).map((item) => Order.fromJson(Map<String, dynamic>.from(item as Map))).toList();
      } else {
        _error = data['error']?['message'] ?? 'Unable to load orders.';
      }
    } catch (_) {
      _error = 'Unable to load orders. Check your connection and try again.';
    } finally { if (mounted) setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_orders.isEmpty && _error == null) return EmptyState(icon: Icons.receipt_long_outlined, title: 'No orders yet', subtitle: 'Your orders from this shop will appear here.', actionLabel: 'Explore menu', onAction: () => context.go('/home'));
    final active = _orders.where((order) => order.isActive).toList();
    final previous = _orders.where((order) => !order.isActive).toList();
    return RefreshIndicator(
      onRefresh: _loadOrders,
      child: ListView(padding: const EdgeInsets.fromLTRB(16, 14, 16, 100), children: [
        const Padding(padding: EdgeInsets.only(left: 2, bottom: 5), child: Text('Your orders', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
        Padding(padding: const EdgeInsets.only(left: 2, bottom: 18), child: Text('Track every order from this shop', style: TextStyle(color: Colors.grey.shade600, fontSize: 13))),
        if (_error != null) _ErrorCard(message: _error!, onRetry: _loadOrders),
        if (active.isNotEmpty) ...[
          const _SectionTitle('In progress'),
          ...active.map((order) => _OrderCard(order: order, active: true)),
        ],
        if (previous.isNotEmpty) ...[
          _SectionTitle(active.isEmpty ? 'Past orders' : 'Previously ordered'),
          ...previous.map((order) => _OrderCard(order: order)),
        ],
      ]),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.fromLTRB(2, 0, 2, 10), child: Text(text, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)));
}

class _OrderCard extends StatelessWidget {
  final Order order;
  final bool active;
  const _OrderCard({required this.order, this.active = false});
  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final fulfilment = order.orderType == 'pickup' ? 'Pickup from store' : 'Self delivery';
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(onTap: () => context.push('/order/${order.uuid}'), borderRadius: BorderRadius.circular(20), child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 42, height: 42, decoration: BoxDecoration(color: primary.withAlpha(18), borderRadius: BorderRadius.circular(14)), child: Icon(active ? Icons.delivery_dining_rounded : Icons.receipt_long_rounded, color: primary)),
          const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(order.orderNumber, style: const TextStyle(fontWeight: FontWeight.w800)), const SizedBox(height: 3), Text(fulfilment, style: TextStyle(color: Colors.grey.shade600, fontSize: 12))])),
          StatusBadge(status: order.status),
        ]),
        const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Divider(height: 1)),
        Row(children: [Text(DateFormat('dd MMM · hh:mm a').format(DateTime.parse(order.createdAt)), style: TextStyle(color: Colors.grey.shade600, fontSize: 12)), const Spacer(), Text(PriceText.format(order.total), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))]),
        if (active) Padding(padding: const EdgeInsets.only(top: 11), child: Row(children: [Icon(Icons.track_changes_rounded, size: 16, color: primary), const SizedBox(width: 5), Text('Tap to track your order', style: TextStyle(color: primary, fontSize: 12, fontWeight: FontWeight.w700))])),
      ]))),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;
  const _ErrorCard({required this.message, required this.onRetry});
  @override
  Widget build(BuildContext context) => Container(margin: const EdgeInsets.only(bottom: 16), padding: const EdgeInsets.all(13), decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(14)), child: Row(children: [const Icon(Icons.error_outline, color: Colors.red), const SizedBox(width: 8), Expanded(child: Text(message, style: const TextStyle(fontSize: 12))), TextButton(onPressed: onRetry, child: const Text('Retry'))]));
}
